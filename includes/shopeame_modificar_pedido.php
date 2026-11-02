<?php
/**
 * Envío a shopea.me de las modificaciones de pedidos de WooCommerce.
 *
 * @package Shopeame
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Notifica a ShopeameServer los pedidos modificados en la tienda. */
class shopeame_modificar_pedido {
    /**
     * Envía el evento update_order del pedido indicado.
     *
     * @param int $order_id Identificador del pedido de WooCommerce.
     * @return void
     */
    function shopeame_actualizar_pedido( $order_id ) {
        shopeame_send_order_event( 'update_order', $order_id );
    }
}
