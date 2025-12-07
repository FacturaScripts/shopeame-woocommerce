<?php

class shopeame_menu{
    // añade el menú de shopeame en el panel de WordPress
    function shopeame_add_menu() {
        add_menu_page(
            'Shopeame',
            'Shopeame',
            'manage_options',
            'shopeame-woocommerce',
            array( $this, 'shopeameSettings' ),
            'dashicons-cart',
            56
        );
    }

    function shopeameSettings() {
        //Se encarga del registro de la tienda y la conexión con Shopeame ademas de mostrar el estado y formulario de conexion.
        if ($_POST['disconnect_shopeame'] === 'true') {
            update_option( 'shop_uuid', '');
            update_option( 'webhook_token', '');
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
                    <input type="submit" value="Desconectar Shopeame" class="button-secondary">
                </form>
                END;
            }
            else {
                echo <<< 'END'
                <p style="color: red;">La tienda no está conectada a Shopeame.</p>
                <form method="post" action="">
                    <input type="hidden" name="shopeame_action" value="connect">
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="erp_field">UUID de la tienda</label></th>
                                <td><input name="erp" type="text" id="erp_field" class="regular-text" required placeholder="Introduce el UUID de tu tienda"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="token_field">Token del Webhook</label></th>
                                <td><input name="token" type="text" id="token_field" class="regular-text" required placeholder="Introduce el token del webhook"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="api_user_field">Clave del cliente</label></th>
                                <td><input name="api_user" type="text" id="api_user_field" class="regular-text" required placeholder="Introduce la clave del cliente de WooCommerce"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="api_key_field">Clave secreta de cliente</label></th>
                                <td><input name="api_key" type="text" id="api_key_field" class="regular-text" required placeholder="Introduce la clave secreta del cliente de WooCommerce"></td>
                            </tr>
                        </tbody>
                    </table>
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Conectar Shopeame">
                </form>
                END;
            }
            ?>

        </div>
        
        <?php
    }

}