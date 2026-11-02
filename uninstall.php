<?php
/**
 * Limpieza al desinstalar el plugin Shopeame.
 *
 * Borra las opciones guardadas por el plugin y las claves de la API REST de WooCommerce que creó
 * para shopea.me. WordPress solo ejecuta este archivo al eliminar el plugin desde el panel.
 *
 * @package Shopeame
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$shopeame_options = array(
    'shopeame_shop_uuid',
    'shopeame_webhook_token',
    'shopeame_api_user',
    'shopeame_api_key',
    'shopeame_db_version',
);
foreach ( $shopeame_options as $shopeame_option ) {
    delete_option( $shopeame_option );
}

global $wpdb;
$shopeame_keys_table = $wpdb->prefix . 'woocommerce_api_keys';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- comprobación puntual al desinstalar.
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $shopeame_keys_table ) ) === $shopeame_keys_table ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia de WooCommerce sin API pública.
    $wpdb->delete( $shopeame_keys_table, array( 'description' => 'Shopeame' ), array( '%s' ) );
}
