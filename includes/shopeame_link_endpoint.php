<?php
/**
 * Receptor REST de las credenciales enviadas por ShopeameServer al conectar una tienda.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Ventana máxima, en segundos, de una petición firmada. */
const SHOPEAME_LINK_TIMESTAMP_TOLERANCE = 300;

/**
 * Registra el endpoint usado por ShopeameServer para completar la vinculación local.
 *
 * @return void
 */
function shopeame_register_link_endpoint() {
    register_rest_route(
        'shopeame/v1',
        '/link',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'shopeame_receive_link',
            'permission_callback' => 'shopeame_verify_link_request',
        )
    );
}
add_action( 'rest_api_init', 'shopeame_register_link_endpoint' );

/**
 * Verifica la firma HMAC y la vigencia de la petición.
 *
 * @param WP_REST_Request $request Petición REST recibida.
 * @return true|WP_Error
 */
function shopeame_verify_link_request( WP_REST_Request $request ) {
    $timestamp = $request->get_header( 'x-shopeame-timestamp' );
    $signature = $request->get_header( 'x-shopeame-signature' );
    $secret    = get_option( 'shopeame_api_key', '' );

    if ( ! is_string( $timestamp ) || ! ctype_digit( $timestamp )
        || ! is_string( $signature ) || ! preg_match( '/^[a-f0-9]{64}$/i', $signature )
        || ! is_string( $secret ) || $secret === ''
        || abs( time() - (int) $timestamp ) > SHOPEAME_LINK_TIMESTAMP_TOLERANCE ) {
        return new WP_Error( 'shopeame_unauthorized', 'Firma no autorizada.', array( 'status' => 401 ) );
    }

    $expected = hash_hmac( 'sha256', $timestamp . '.' . $request->get_body(), $secret );
    if ( ! hash_equals( $expected, strtolower( $signature ) ) ) {
        return new WP_Error( 'shopeame_unauthorized', 'Firma no autorizada.', array( 'status' => 401 ) );
    }

    return true;
}

/**
 * Valida y guarda las credenciales de tienda recibidas.
 *
 * @param WP_REST_Request $request Petición REST autenticada.
 * @return WP_REST_Response|WP_Error
 */
function shopeame_receive_link( WP_REST_Request $request ) {
    $payload = $request->get_json_params();
    if ( ! is_array( $payload ) ) {
        return new WP_Error( 'shopeame_invalid_json', 'JSON no válido.', array( 'status' => 400 ) );
    }

    $shop_uuid     = isset( $payload['shop_uuid'] ) ? $payload['shop_uuid'] : '';
    $webhook_token = isset( $payload['webhook_token'] ) ? $payload['webhook_token'] : '';
    if ( ( $payload['event'] ?? '' ) !== 'link_shop'
        || ! is_string( $shop_uuid ) || strlen( $shop_uuid ) !== 13
        || ! is_string( $webhook_token ) || strlen( $webhook_token ) !== 32 ) {
        return new WP_Error( 'shopeame_invalid_payload', 'Datos de vinculación no válidos.', array( 'status' => 422 ) );
    }

    update_option( 'shop_uuid', $shop_uuid );
    update_option( 'webhook_token', $webhook_token );

    return new WP_REST_Response( array( 'success' => true ), 200 );
}
