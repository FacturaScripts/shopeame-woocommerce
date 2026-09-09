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
            // detallamos el motivo: sin el código HTTP no se distingue un token inválido (401)
            // de un fallo de red o un timeout (code 0)
            $detalle = isset( $result['error'] ) ? $result['error'] : '';
            if ( $detalle === '' && isset( $result['data']['error'] ) ) {
                $detalle = $result['data']['error'];
            }

            error_log( sprintf(
                'Shopeame Plugin: error al enviar update_order del pedido %s (HTTP %s) %s',
                $IDpedido,
                $result['code'],
                $detalle
            ) );
        }
    }
}
