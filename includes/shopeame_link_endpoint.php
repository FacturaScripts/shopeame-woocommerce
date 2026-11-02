<?php
/**
 * Receptor REST de las credenciales enviadas por ShopeameServer al conectar una tienda.
 *
 * @package Shopeame
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
 * La firma se calcula con la Consumer secret que el plugin generó y que solo conocen la tienda y
 * shopea.me, por lo que ninguna otra parte puede usar este endpoint.
 *
 * @param WP_REST_Request $request Petición REST recibida.
 * @return true|WP_Error
 */
function shopeame_verify_link_request( WP_REST_Request $request ) {
    $timestamp = $request->get_header( 'x-shopeame-timestamp' );
    $signature = $request->get_header( 'x-shopeame-signature' );
    $secret    = get_option( SHOPEAME_OPTION_API_KEY, '' );

    if ( ! is_string( $timestamp ) || ! ctype_digit( $timestamp )
        || ! is_string( $signature ) || ! preg_match( '/^[a-f0-9]{64}$/i', $signature )
        || ! is_string( $secret ) || $secret === ''
        || abs( time() - (int) $timestamp ) > SHOPEAME_LINK_TIMESTAMP_TOLERANCE ) {
        return new WP_Error( 'shopeame_unauthorized', __( 'Unauthorized signature.', 'shopeame' ), array( 'status' => 401 ) );
    }

    $expected = hash_hmac( 'sha256', $timestamp . '.' . $request->get_body(), $secret );
    if ( ! hash_equals( $expected, strtolower( $signature ) ) ) {
        return new WP_Error( 'shopeame_unauthorized', __( 'Unauthorized signature.', 'shopeame' ), array( 'status' => 401 ) );
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
        return new WP_Error( 'shopeame_invalid_json', __( 'Invalid JSON.', 'shopeame' ), array( 'status' => 400 ) );
    }

    $saved = ( $payload['event'] ?? '' ) === 'link_shop' && shopeame_save_link_credentials(
        $payload['shop_uuid'] ?? '',
        $payload['webhook_token'] ?? ''
    );
    if ( ! $saved ) {
        return new WP_Error( 'shopeame_invalid_payload', __( 'Invalid link data.', 'shopeame' ), array( 'status' => 422 ) );
    }

    return new WP_REST_Response( array( 'success' => true ), 200 );
}
