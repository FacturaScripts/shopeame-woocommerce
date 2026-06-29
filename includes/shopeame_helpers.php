<?php
/**
 * Funciones auxiliares compartidas del plugin Shopeame.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * URL base del servidor de ShopeameServer (shopea.me).
 * Se puede sobreescribir definiendo la constante SHOPEAME_SERVER_URL en wp-config.php
 * (útil para apuntar a una instalación local en desarrollo).
 */
function shopeame_server_url() {
    $url = defined( 'SHOPEAME_SERVER_URL' ) ? SHOPEAME_SERVER_URL : 'https://shopea.me';
    return rtrim( $url, '/' );
}

/**
 * Indica si estamos en modo desarrollo. Se controla con la constante SHOPEAME_DEV (wp-config.php).
 * En desarrollo se acepta el certificado TLS local autofirmado (no se verifica el SSL).
 */
function shopeame_is_dev() {
    return defined( 'SHOPEAME_DEV' ) && SHOPEAME_DEV;
}

/**
 * Envía un evento al controlador WebHooks de ShopeameServer.
 * Devuelve un array con 'ok' (bool), 'code' (int) y 'data' (array|null).
 */
function shopeame_post_webhook( array $payload ) {
    $args = array(
        'method'    => 'POST',
        'headers'   => array( 'Content-Type' => 'application/json; charset=utf-8' ),
        'body'      => wp_json_encode( $payload ),
        'timeout'   => 20,
        // en desarrollo no verificamos el certificado TLS (servidor local autofirmado)
        'sslverify' => ! shopeame_is_dev(),
    );

    $response = wp_remote_post( shopeame_server_url() . '/WebHooks', $args );
    if ( is_wp_error( $response ) ) {
        return array( 'ok' => false, 'code' => 0, 'data' => null, 'error' => $response->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    return array(
        'ok'   => $code >= 200 && $code < 300,
        'code' => $code,
        'data' => is_array( $data ) ? $data : null,
    );
}

/**
 * Indica si la tienda está vinculada con shopea.me (tiene credenciales guardadas).
 */
function shopeame_is_linked() {
    return get_option( 'shop_uuid', '' ) !== '' && get_option( 'webhook_token', '' ) !== '';
}

/**
 * Genera (o regenera) una clave REST de WooCommerce con permisos de lectura y la guarda en options
 * para poder mostrarla y usarla al vincular. Devuelve true si se creó correctamente.
 * El consumer_key se guarda hasheado en la tabla de WooCommerce; conservamos el par en claro en
 * options porque shopea.me lo necesita para conectarse a la API REST de la tienda.
 */
function shopeame_generate_api_keys() {
    global $wpdb;

    if ( ! function_exists( 'wc_rand_hash' ) || ! function_exists( 'wc_api_hash' ) ) {
        return false;
    }

    // eliminamos claves Shopeame anteriores para no acumularlas
    $wpdb->delete( $wpdb->prefix . 'woocommerce_api_keys', array( 'description' => 'Shopeame' ), array( '%s' ) );

    $consumer_key    = 'ck_' . wc_rand_hash();
    $consumer_secret = 'cs_' . wc_rand_hash();

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'woocommerce_api_keys',
        array(
            'user_id'         => get_current_user_id(),
            'description'     => 'Shopeame',
            'permissions'     => 'read',
            'consumer_key'    => wc_api_hash( $consumer_key ),
            'consumer_secret' => $consumer_secret,
            'truncated_key'   => substr( $consumer_key, -7 ),
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s' )
    );

    if ( false === $inserted ) {
        return false;
    }

    update_option( 'shopeame_api_user', $consumer_key );
    update_option( 'shopeame_api_key', $consumer_secret );
    return true;
}
