<?php
/**
 * Funciones auxiliares compartidas del plugin Shopeame.
 *
 * @package Shopeame
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Opción con el identificador de la tienda en shopea.me. */
const SHOPEAME_OPTION_SHOP_UUID = 'shopeame_shop_uuid';

/** Opción con el token que autentica a la tienda ante shopea.me. */
const SHOPEAME_OPTION_WEBHOOK_TOKEN = 'shopeame_webhook_token';

/** Opción con la clave del cliente (consumer key) de la API REST de WooCommerce. */
const SHOPEAME_OPTION_API_USER = 'shopeame_api_user';

/** Opción con la clave secreta (consumer secret) de la API REST de WooCommerce. */
const SHOPEAME_OPTION_API_KEY = 'shopeame_api_key';

/** Opción que indica si los clientes pueden descargar sus facturas desde Mi cuenta: «yes» (por defecto) o «no». */
const SHOPEAME_OPTION_CUSTOMER_INVOICES = 'shopeame_customer_invoices';

/** Opción con la versión del plugin con la que se migraron por última vez los datos guardados. */
const SHOPEAME_OPTION_DB_VERSION = 'shopeame_db_version';

/** Descripción con la que se guardan en WooCommerce las claves de la API creadas por el plugin. */
const SHOPEAME_API_KEY_DESCRIPTION = 'Shopeame';

/**
 * URL base del servidor de ShopeameServer (shopea.me).
 *
 * Se puede sobreescribir definiendo la constante SHOPEAME_SERVER_URL en wp-config.php
 * (útil para apuntar a una instalación local en desarrollo).
 *
 * @return string URL sin barra final.
 */
function shopeame_server_url() {
    $url = defined( 'SHOPEAME_SERVER_URL' ) ? SHOPEAME_SERVER_URL : 'https://shopea.me';
    return untrailingslashit( $url );
}

/**
 * Migra los datos guardados por versiones anteriores del plugin, una sola vez por versión.
 *
 * Hasta la versión 1.0.0 las credenciales de vinculación se guardaban en las opciones genéricas
 * «shop_uuid» y «webhook_token»; ahora llevan el prefijo del plugin para no chocar con otros plugins.
 *
 * @return void
 */
function shopeame_maybe_upgrade() {
    if ( get_option( SHOPEAME_OPTION_DB_VERSION ) === SHOPEAME_VERSION ) {
        return;
    }

    $old_uuid  = get_option( 'shop_uuid', '' );
    $old_token = get_option( 'webhook_token', '' );
    if ( is_string( $old_uuid ) && $old_uuid !== '' && is_string( $old_token ) && $old_token !== ''
        && get_option( SHOPEAME_OPTION_SHOP_UUID, '' ) === '' ) {
        update_option( SHOPEAME_OPTION_SHOP_UUID, $old_uuid );
        update_option( SHOPEAME_OPTION_WEBHOOK_TOKEN, $old_token );
        delete_option( 'shop_uuid' );
        delete_option( 'webhook_token' );
    }

    update_option( SHOPEAME_OPTION_DB_VERSION, SHOPEAME_VERSION );
}

/**
 * Envía un evento al controlador WebHooks de ShopeameServer.
 *
 * @param array $payload Datos del evento que se envían como JSON.
 * @param int   $timeout Segundos máximos de espera de la respuesta.
 * @return array Array con 'ok' (bool), 'code' (int), 'data' (array|null) y, si falla la petición, 'error' (string).
 */
function shopeame_post_webhook( array $payload, $timeout = 20 ) {
    $response = wp_remote_post(
        shopeame_server_url() . '/WebHooks',
        array(
            'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => $timeout,
        )
    );
    if ( is_wp_error( $response ) ) {
        return array(
            'ok'    => false,
            'code'  => 0,
            'data'  => null,
            'error' => $response->get_error_message(),
        );
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    return array(
        'ok'   => $code >= 200 && $code < 300,
        'code' => $code,
        'data' => is_array( $data ) ? $data : null,
    );
}

/**
 * Notifica a ShopeameServer un evento de pedido (creación o modificación).
 *
 * Solo se envía el identificador del pedido: ShopeameServer descarga el pedido por la API REST de
 * WooCommerce. Si la tienda no está vinculada no se envía nada. Los fallos se registran en el
 * registro de WooCommerce (WooCommerce > Estado > Registros, fuente «shopeame»).
 *
 * @param string $event    Evento a notificar: new_order o update_order.
 * @param int    $order_id Identificador del pedido de WooCommerce.
 * @return void
 */
function shopeame_send_order_event( $event, $order_id ) {
    if ( ! $order_id || ! shopeame_is_linked() ) {
        return;
    }

    $result = shopeame_post_webhook(
        array(
            'event'    => $event,
            'shop'     => get_option( SHOPEAME_OPTION_SHOP_UUID, '' ),
            'token'    => get_option( SHOPEAME_OPTION_WEBHOOK_TOKEN, '' ),
            'order_id' => absint( $order_id ),
        )
    );

    if ( $result['ok'] || ! function_exists( 'wc_get_logger' ) ) {
        return;
    }

    // detallamos el motivo: sin el código HTTP no se distingue un token inválido (401)
    // de un fallo de red o un timeout (code 0)
    $detail = isset( $result['error'] ) ? $result['error'] : '';
    if ( $detail === '' && isset( $result['data']['error'] ) && is_string( $result['data']['error'] ) ) {
        $detail = $result['data']['error'];
    }

    wc_get_logger()->error(
        sprintf( 'Error sending %s for order %d (HTTP %d) %s', $event, absint( $order_id ), $result['code'], $detail ),
        array( 'source' => 'shopeame' )
    );
}

/**
 * Indica si la tienda está vinculada con shopea.me (tiene credenciales guardadas).
 *
 * @return bool
 */
function shopeame_is_linked() {
    return get_option( SHOPEAME_OPTION_SHOP_UUID, '' ) !== '' && get_option( SHOPEAME_OPTION_WEBHOOK_TOKEN, '' ) !== '';
}

/**
 * Pregunta a shopea.me (evento check_shop) si la tienda sigue vinculada, para pintar la pantalla.
 *
 * Las credenciales guardadas no bastan: si la tienda se desvincula desde su ficha en shopea.me,
 * el plugin no recibe ningún aviso y las conserva. Solo damos por buena la respuesta cuando trae
 * el campo booleano 'exists' (false si está desvinculada o el token ya no es válido); si
 * shopea.me no responde o devuelve otra cosa, conservamos lo que dicen las credenciales y
 * marcamos el fallo para avisar. Las credenciales no se borran: se sustituyen al volver a
 * vincular. Es el mismo criterio que siguen los conectores de PrestaShop y Shopify.
 *
 * @return array Array con 'linked' (bool, si se muestra como vinculada) y 'check_failed' (bool,
 *               si no se pudo confirmar con shopea.me).
 */
function shopeame_check_link_state() {
    if ( ! shopeame_is_linked() ) {
        return array(
            'linked'       => false,
            'check_failed' => false,
        );
    }

    // espera corta: la comprobación se hace cada vez que se abre la pantalla
    $result = shopeame_post_webhook(
        array(
            'event' => 'check_shop',
            'shop'  => get_option( SHOPEAME_OPTION_SHOP_UUID, '' ),
            'token' => get_option( SHOPEAME_OPTION_WEBHOOK_TOKEN, '' ),
        ),
        10
    );

    if ( isset( $result['data']['exists'] ) && is_bool( $result['data']['exists'] ) ) {
        return array(
            'linked'       => $result['data']['exists'],
            'check_failed' => false,
        );
    }

    return array(
        'linked'       => true,
        'check_failed' => true,
    );
}

/**
 * Guarda las credenciales de vinculación devueltas por ShopeameServer, si tienen un formato válido.
 *
 * El shop_uuid lo genera uniqid() (13 caracteres hexadecimales) y el webhook_token es una cadena
 * alfanumérica de 32 caracteres.
 *
 * @param mixed $shop_uuid     Identificador de la tienda en shopea.me.
 * @param mixed $webhook_token Token de la tienda en shopea.me.
 * @return bool True si las credenciales eran válidas y se guardaron.
 */
function shopeame_save_link_credentials( $shop_uuid, $webhook_token ) {
    if ( ! is_string( $shop_uuid ) || ! preg_match( '/^[a-f0-9]{13}$/', $shop_uuid )
        || ! is_string( $webhook_token ) || ! preg_match( '/^[A-Za-z0-9]{32}$/', $webhook_token ) ) {
        return false;
    }

    update_option( SHOPEAME_OPTION_SHOP_UUID, $shop_uuid );
    update_option( SHOPEAME_OPTION_WEBHOOK_TOKEN, $webhook_token );
    return true;
}

/**
 * Borra las credenciales de vinculación con shopea.me guardadas en WordPress.
 *
 * @return void
 */
function shopeame_clear_link_credentials() {
    delete_option( SHOPEAME_OPTION_SHOP_UUID );
    delete_option( SHOPEAME_OPTION_WEBHOOK_TOKEN );
}

/**
 * Elimina de WooCommerce las claves de la API REST creadas por el plugin.
 *
 * WooCommerce no ofrece una función pública para gestionar sus claves de la API, así que se trabaja
 * directamente con su tabla, igual que hace el propio WooCommerce en WC_AJAX::update_api_key().
 *
 * @return void
 */
function shopeame_delete_api_keys() {
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- tabla propia de WooCommerce sin API pública; no se cachea.
    $wpdb->delete( $wpdb->prefix . 'woocommerce_api_keys', array( 'description' => SHOPEAME_API_KEY_DESCRIPTION ), array( '%s' ) );
}

/**
 * Genera (o regenera) una clave REST de WooCommerce con permisos de lectura y la guarda en options
 * para poder mostrarla y usarla al vincular.
 *
 * El consumer_key se guarda hasheado en la tabla de WooCommerce; conservamos el par en claro en
 * options porque shopea.me lo necesita para conectarse a la API REST de la tienda.
 *
 * @return bool True si se creó correctamente.
 */
function shopeame_generate_api_keys() {
    global $wpdb;

    if ( ! function_exists( 'wc_rand_hash' ) || ! function_exists( 'wc_api_hash' ) ) {
        return false;
    }

    // eliminamos claves Shopeame anteriores para no acumularlas
    shopeame_delete_api_keys();

    $consumer_key    = 'ck_' . wc_rand_hash();
    $consumer_secret = 'cs_' . wc_rand_hash();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- tabla propia de WooCommerce sin API pública, igual que WC_AJAX::update_api_key().
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'woocommerce_api_keys',
        array(
            'user_id'         => get_current_user_id(),
            'description'     => SHOPEAME_API_KEY_DESCRIPTION,
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

    update_option( SHOPEAME_OPTION_API_USER, $consumer_key );
    update_option( SHOPEAME_OPTION_API_KEY, $consumer_secret );
    return true;
}
