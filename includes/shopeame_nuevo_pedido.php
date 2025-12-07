<?php

class shopeame_nuevo_pedido{
    function shopeame_crear_pedido( $IDpedido ) {
        
        if ( ! $IDpedido ) {
            return;
        }

        error_log('Shopeame Plugin: Iniciando el proceso para el pedido ID: ' . $IDpedido);

        // Obtener el pedido.
        $pedido = wc_get_order( $IDpedido );

        if ( ! $pedido ) {
            error_log('Shopeame Plugin ERROR: No se pudo obtener el objeto del pedido para el ID: ' . $IDpedido);
            return;
        }

        $shop_uuid = get_option( 'shop_uuid', '' );
        $webhook_token = get_option( 'webhook_token', '' );

        // Array de productos en el pedido.
        $items_data = array();
        foreach ( $pedido->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $item_name = $item->get_name();
            $items_data[ $item_name ] = array(
                'product_id'   => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'sku'          => $product ? $product->get_sku() : null,
                'quantity'     => $item->get_quantity(),
                'price'        => $item->get_total(),
                'subtotal'     => $item->get_subtotal(),
                'total'        => $item->get_total(),
            );
        }

        // Crear array con la estructura del JSON.
        $payload = array(
            'event' => 'new_order',
            'shop'  => $shop_uuid,
            'token' => $webhook_token,
            'order' => array(
                'id'        => $pedido->get_id(),
                'reference' => $pedido->get_order_number(),
                'cliente'   => $pedido->get_customer_id(),
                'totales'   => $pedido->get_total(),
                'estado'    => $pedido->get_status(),
                'items'     => $items_data,
                'fecha'     => $pedido->get_date_created()->format( 'd-m-y' ),
            )
        );

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

        $debug_data = array();
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            error_log('Shopeame Plugin ERROR al enviar el JSON del pedido ' . $IDpedido . ': ' . $error_message);
        }
    }

    // Esta función ya no se llama desde shopeame_crear_pedido, pero se mantiene para evitar errores si se usa en otro lugar.
    function test($response){
        update_option( 'respone_test', $response );
        return $response;
    }
}

?>