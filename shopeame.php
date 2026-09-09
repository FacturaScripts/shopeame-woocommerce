<?php
/**
 * Plugin Name: Shopeame
 * Plugin URI: https://shopea.me/woocommerce
 * Description: Plugin para integrar WooCommerce con Shopea.me.
 * Version: 1
 * Author: Shopeame
 * Author URI: https://shopea.me
 * Domain Path: /i18n/languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acceso directo por URL
}


require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_helpers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_link_endpoint.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_menu.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_nuevo_pedido.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_modificar_pedido.php';

add_action( 'admin_menu', 'shopeame_add_menu' );
add_action( 'woocommerce_new_order', 'shopeame_crear_pedido', 10, 1 );
add_action('woocommerce_update_order', 'shopeame_modificar_pedido', 10, 1);

function shopeame_add_menu(){
    $shopeame_menu = new shopeame_menu();
    $shopeame_menu->shopeame_add_menu();
}

function shopeame_crear_pedido( $IDpedido ) {
    $shopeame_nuevo_pedido = new shopeame_nuevo_pedido();
    $shopeame_nuevo_pedido->shopeame_crear_pedido( $IDpedido );
}

function shopeame_modificar_pedido( $IDpedido ) {
    $shopeame_modificar_pedido = new shopeame_modificar_pedido();
    $shopeame_modificar_pedido->shopeame_actualizar_pedido( $IDpedido );
}
