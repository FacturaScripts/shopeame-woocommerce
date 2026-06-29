<?php

class shopeame_modificar_pedido {
    function shopeame_actualizar_pedido( $IDpedido ) {

        if ( ! $IDpedido ) {
            return;
        }

        // solo enviamos si la tienda está vinculada con shopea.me
        if ( ! shopeame_is_linked() ) {
            return;
        }

        // enviamos solo el id; ShopeameServer descarga el pedido por la API y lo parsea
        $result = shopeame_post_webhook( array(
            'event'    => 'update_order',
            'shop'     => get_option( 'shop_uuid', '' ),
            'token'    => get_option( 'webhook_token', '' ),
            'order_id' => $IDpedido,
        ) );

        if ( ! $result['ok'] ) {
            error_log( 'Shopeame Plugin: error al enviar update_order del pedido ' . $IDpedido );
        }
    }
}
