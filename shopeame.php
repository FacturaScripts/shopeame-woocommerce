<?php
/**
 * Plugin Name:          Shopeame
 * Plugin URI:           https://shopea.me/woocommerce
 * Description:          Connects your WooCommerce store with shopea.me to sync orders with your FacturaScripts ERP and download their invoices.
 * Version:              1.0.0
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Update URI:           https://github.com/FacturaScripts/shopeame-woocommerce
 * Author:               Shopeame
 * Author URI:           https://shopea.me
 * License:              GPLv3 or later
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:          shopeame
 * Domain Path:          /languages
 * WC requires at least: 8.0
 * WC tested up to:      11.2
 *
 * Archivo principal del plugin Shopeame: define las constantes, carga los archivos del plugin y
 * registra los hooks de WordPress y WooCommerce.
 *
 * @package Shopeame
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acceso directo por URL
}

/** Versión actual del plugin. Debe coincidir con la cabecera «Version» y con el «Stable tag» del readme.txt. */
define( 'SHOPEAME_VERSION', '1.0.0' );

/** Ruta absoluta del archivo principal del plugin. */
define( 'SHOPEAME_PLUGIN_FILE', __FILE__ );

require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_helpers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_link_endpoint.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_menu.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_nuevo_pedido.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_modificar_pedido.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_facturas.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shopeame_updater.php';

add_action( 'plugins_loaded', 'shopeame_maybe_upgrade' );
add_action( 'init', 'shopeame_load_textdomain' );
// actualizaciones desde las releases de GitHub: el filtro lleva el host de la cabecera «Update URI» (github.com)
add_filter( 'update_plugins_' . wp_parse_url( SHOPEAME_UPDATE_URI, PHP_URL_HOST ), 'shopeame_filter_update_plugins', 10, 3 );
add_filter( 'plugins_api', 'shopeame_filter_plugins_api', 10, 3 );
add_action( 'before_woocommerce_init', 'shopeame_declare_wc_compatibility' );
add_action( 'admin_menu', 'shopeame_add_menu' );
add_action( 'admin_enqueue_scripts', 'shopeame_enqueue_admin_assets' );
add_action( 'woocommerce_new_order', 'shopeame_crear_pedido', 10, 1 );
add_action( 'woocommerce_update_order', 'shopeame_modificar_pedido', 10, 1 );
shopeame_facturas::register();

/**
 * Carga las traducciones incluidas en la carpeta languages/ del plugin.
 *
 * El plugin no está en WordPress.org, así que WordPress no le descarga paquetes de idioma desde
 * translate.wordpress.org: la traducción al español viaja en el zip (ver la decisión 0004 de Doc/Context).
 * Si existe un paquete en wp-content/languages/plugins/, WordPress lo prefiere a este.
 *
 * @return void
 */
function shopeame_load_textdomain() {
    load_plugin_textdomain( 'shopeame', false, dirname( plugin_basename( SHOPEAME_PLUGIN_FILE ) ) . '/languages' );
}

/**
 * Declara la compatibilidad del plugin con las funcionalidades de WooCommerce.
 *
 * El plugin solo trabaja con identificadores de pedido, así que es compatible con el almacenamiento
 * de pedidos de alto rendimiento (HPOS) y con los bloques del carrito y del pago.
 *
 * @return void
 */
function shopeame_declare_wc_compatibility() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SHOPEAME_PLUGIN_FILE, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SHOPEAME_PLUGIN_FILE, true );
    }
}

/**
 * Añade el menú de Shopeame al panel de administración.
 *
 * @return void
 */
function shopeame_add_menu() {
    $shopeame_menu = new shopeame_menu();
    $shopeame_menu->shopeame_add_menu();
}

/**
 * Encola los estilos y el script de la pantalla de administración de Shopeame.
 *
 * @param string $hook_suffix Sufijo del hook de la página de administración actual.
 * @return void
 */
function shopeame_enqueue_admin_assets( $hook_suffix ) {
    $shopeame_menu = new shopeame_menu();
    $shopeame_menu->shopeame_enqueue_assets( $hook_suffix );
}

/**
 * Notifica a shopea.me la creación de un pedido.
 *
 * @param int $order_id Identificador del pedido de WooCommerce.
 * @return void
 */
function shopeame_crear_pedido( $order_id ) {
    $shopeame_nuevo_pedido = new shopeame_nuevo_pedido();
    $shopeame_nuevo_pedido->shopeame_crear_pedido( $order_id );
}

/**
 * Notifica a shopea.me la modificación de un pedido.
 *
 * @param int $order_id Identificador del pedido de WooCommerce.
 * @return void
 */
function shopeame_modificar_pedido( $order_id ) {
    $shopeame_modificar_pedido = new shopeame_modificar_pedido();
    $shopeame_modificar_pedido->shopeame_actualizar_pedido( $order_id );
}
