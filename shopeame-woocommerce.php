<?php
/**
 * Plugin Name: Shopeame WooCommerce
 * Description: Plugin para integrar Shopeame con WooCommerce.
 * Version: 0.1
 * Author: Facturascripts
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acceso directo por URL
}


add_action( 'admin_menu', 'shopeame_add_menu' );
add_action( 'woocommerce_checkout_order_processed', 'shopeame_crear_pedido', 10, 1 );

function shopeame_add_menu() {
    add_menu_page(
        'Shopeame',
        'Shopeame',
        'manage_options',
        'shopeame-woocommerce',
        'shopeameSettings',
        'dashicons-cart',
        56
    );
}


function shopeameSettings() {

    if ($_POST['disconnect_shopeame'] === true) {
        delete_option( 'erp' );
        delete_option( 'token' );
    }
    else if (isset($_POST['erp']) && isset($_POST['token'])) {
        $erp = trim( $_POST['erp'] );
        $token = trim( $_POST['token'] );
        $api_user = trim( $_POST['api_user'] );
        $api_key = trim( $_POST['api_key'] );

        $jsonApi = array(
            'event'    => 'register_woocommerce',
            'erp'      => $erp,
            'token'    => $token,
            'shop'     => array(
                'url'       => get_site_url(),
                'api_user'  => $api_user,
                'api_key'   => $api_key,
            )
        );

        $body = json_encode( $jsonApi );

        $args = array(
            'method'    => 'POST',
            'headers'   => array(
                'Content-Type' => 'application/json; charset=utf-t',
            ),
            'body'      => $body
        );

        $response = wp_remote_post( 'https://shopea.me/WebHooks', $args );
        if ( is_wp_error( $response ) ) {
            echo '<div class="error"><p>Error al conectar con Shopeame: ' . esc_html( $response->get_error_message() ) . '</p></div>';
            return;
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );

            if ( $response_code === 200 ) {
                $data = json_decode( $response_body, true );

                if ( isset( $data['shop_uuid'] ) && isset( $data['webhook_token'] ) ) {
                    update_option( 'shop_uuid', $data['shop_uuid'] );
                    update_option( 'webhook_token', $data['webhook_token'] );
                    echo '<div class="notice notice-success"><p>Conexión con Shopeame establecida correctamente.</p></div>';
                } else {
                    echo '<div class="error"><p>Error: La respuesta del servidor de Shopeame no tiene el formato esperado.</p></div>';
                }
            } else {
                $error_message = wp_remote_retrieve_response_message( $response );
                echo '<div class="error"><p>Error al conectar con Shopeame. El servidor respondió con el código ' . htmlentities( $response_code ) . ': ' . htmlentities( $error_message ) . '</p></div>';
            }
        }
    }
    $erp  = get_option( 'shop_uuid', '' );
    $token = get_option( 'webhook_token', '' );
    

    if($erp!==""&&$token!==""){
        var_dump($erp);
        var_dump($token);
        $connected = true;
    }
    else{
        $connected = false;
    }

    ?>

    <div class="wrap">
        <h1>Shopeame Settings</h1>
        <p>Configura la conexión entre tu tienda WooCommerce y Shopeame.</p>

        <?php
        if ($connected) {
            echo <<< END
            <p style="color: green;">La tienda está conectada a Shopeame.</p>
            <form action="#" method="post">
                <input type="hidden" name="disconnect_shopeame" value="true">
                <input type="submit" value="Desconectar Shopeame">
            </form>
            END;
        }
        else {
            echo <<< 'END'
            <p style="color: red;">La tienda no está conectada a Shopeame.</p>
            <form action="#" method="post">
                <input type="text" name="erp" placeholder="UUID de la tienda" required>
                <input type="text" name="token" placeholder="Token del Webhook" required>
                <input type="text" name="api_user" placeholder="Clave del cliente" required>
                <input type="text" name="api_key" placeholder="Clave secreta de cliente" required>
                <input type="submit" value="Conectar Shopeame">
            </form>
            END;
        }
        ?>

    </div>
    
    <?php
}


function shopeame_crear_pedido( $IDpedido ) {
    if ( ! $IDpedido ) {
        return;
    }

    error_log('Shopeame Plugin: Iniciando el proceso para el pedido ID: ' . $IDpedido);

    // 2. Obtener el objeto del pedido.
    $order = wc_get_order( $IDpedido );

    if ( ! $order ) {
        error_log('Shopeame Plugin ERROR: No se pudo obtener el objeto del pedido para el ID: ' . $IDpedido);
        return;
    }

    // Obtener shop_uuid y webhook_token de las opciones guardadas
    $shop_uuid = get_option( 'shop_uuid', '' );
    $webhook_token = get_option( 'webhook_token', '' );

    // 3. Construir el array con los datos del pedido en el formato especificado.
    $items_data = array();
    foreach ( $order->get_items() as $item_id => $item ) {
        $product = $item->get_product();
        $item_name = $item->get_name(); // Usar el nombre del producto como clave
        $items_data[ $item_name ] = array(
            'product_id'   => $item->get_product_id(),
            'variation_id' => $item->get_variation_id(),
            'sku'          => $product ? $product->get_sku() : null,
            'quantity'     => $item->get_quantity(),
            'price'        => $item->get_total(), // Asumiendo que 'precio' se refiere al total para este artículo
            'subtotal'     => $item->get_subtotal(),
            'total'        => $item->get_total(),
        );
    }

    $payload = array(
        'event' => 'new_order',
        'shop'  => $shop_uuid,
        'token' => $webhook_token,
        'order' => array(
            'id'        => $order->get_id(),
            'reference' => $order->get_order_number(),
            'cliente'   => $order->get_customer_id(),
            'totales'   => $order->get_total(),
            'estado'    => $order->get_status(),
            'items'     => $items_data,
            'fecha'     => $order->get_date_created()->format( 'd-m-y' ),
        )
    );

    // 4. Convertir el array a una cadena JSON.
    $json_body = wp_json_encode( $payload );

    $args = array(
        'body'      => $json_body,
        'headers'   => array(
            'Content-Type' => 'application/json; charset=utf-8',
        ),
        'timeout'   => 45,
        'blocking'  => true,
    );

    $response = wp_remote_post( 'https://shopea.me/WebHooks', $args );

    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        error_log('Shopeame Plugin ERROR al enviar el JSON del pedido ' . $IDpedido . ': ' . $error_message);
    }
}
?>