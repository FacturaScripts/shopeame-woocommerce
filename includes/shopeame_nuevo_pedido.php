<?php
/**
 * Envío a shopea.me de los pedidos nuevos de WooCommerce.
 *
 * @package Shopeame
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Notifica a ShopeameServer los pedidos creados en la tienda. */
class shopeame_nuevo_pedido {
    /**
     * Envía el evento new_order del pedido indicado.
     *
     * @param int $order_id Identificador del pedido de WooCommerce.
     * @return void
     */
    function shopeame_crear_pedido( $order_id ) {
        shopeame_send_order_event( 'new_order', $order_id );
    }
}
