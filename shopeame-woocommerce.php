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


require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_menu.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_nuevo_pedido.php';

add_action( 'admin_menu', 'shopeame_add_menu' );
add_action( 'woocommerce_new_order', 'shopeame_crear_pedido', 10, 1 );

function shopeame_add_menu(){
    $shopeame_menu = new shopeame_menu();
    $shopeame_menu->shopeame_add_menu();
}

function shopeame_crear_pedido( $IDpedido ) {
    $shopeame_nuevo_pedido = new shopeame_nuevo_pedido();
    $shopeame_nuevo_pedido->shopeame_crear_pedido( $IDpedido );
}

?>