<?php
/**
 * Actualizaciones del plugin desde las releases de GitHub.
 *
 * El plugin no está en el directorio de WordPress.org: se descarga del repositorio público de GitHub. Para que
 * WordPress ofrezca las versiones nuevas en **Plugins** y en **Escritorio → Actualizaciones** como con cualquier otro
 * plugin (botón «Actualizar ahora» y actualizaciones automáticas), la cabecera `Update URI` de shopeame.php apunta al
 * repositorio y este archivo responde a los filtros que WordPress ofrece para ese caso:
 *
 * - `update_plugins_{$hostname}` (WordPress 5.8+): versión disponible y zip de la release.
 * - `plugins_api`: ventana «Ver detalles» con el changelog.
 *
 * Los datos salen del `info.json` que la acción de GitHub «Crear Release» adjunta a cada release.
 *
 * https://make.wordpress.org/core/2021/06/29/introducing-update-uri-plugin-header-in-wordpress-5-8/
 * https://developer.wordpress.org/reference/hooks/update_plugins_hostname/
 * https://developer.wordpress.org/reference/hooks/plugins_api/
 *
 * @package Shopeame
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Repositorio del plugin. Tiene que ser idéntico a la cabecera `Update URI` de shopeame.php: WordPress usa el host de
 * esa cabecera para nombrar el filtro y la guarda como identificador de la actualización.
 */
define( 'SHOPEAME_UPDATE_URI', 'https://github.com/FacturaScripts/shopeame-woocommerce' );

/** Slug del plugin (carpeta) en las respuestas de actualización y en la ventana «Ver detalles». */
define( 'SHOPEAME_PLUGIN_SLUG', 'shopeame' );

/**
 * Devuelve la URL del info.json de la última release.
 *
 * Se puede sobrescribir con la constante SHOPEAME_UPDATE_INFO_URL en wp-config.php para probar las actualizaciones
 * con un info.json local.
 *
 * @return string
 */
function shopeame_update_info_url() {
    if ( defined( 'SHOPEAME_UPDATE_INFO_URL' ) ) {
        return (string) SHOPEAME_UPDATE_INFO_URL;
    }

    return SHOPEAME_UPDATE_URI . '/releases/latest/download/info.json';
}

/**
 * Descarga y valida el info.json de la última release.
 *
 * WordPress ya limita cuántas veces busca actualizaciones (cada 12 horas, o cada hora en la pantalla de plugins), así
 * que aquí solo se guarda el resultado durante la petición actual. Si GitHub no responde o el archivo no es válido,
 * devuelve null y WordPress no muestra ninguna actualización hasta la siguiente comprobación.
 *
 * @return array{version: string, download_url: string, details_url: string, homepage: string, released: string, requires: string, tested: string, requires_php: string, changelog: string[]}|null
 */
function shopeame_update_info() {
    static $info = false;
    if ( $info !== false ) {
        return $info;
    }

    $info     = null;
    $response = wp_remote_get(
        shopeame_update_info_url(),
        array(
            'timeout'    => 10,
            // el User-Agent por defecto de WordPress lleva la URL de la tienda; a GitHub no le hace falta
            'user-agent' => 'Shopeame/' . SHOPEAME_VERSION,
            'headers'    => array( 'Accept' => 'application/json' ),
        )
    );
    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return $info;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $data ) || ! shopeame_is_valid_update_info( $data ) ) {
        return $info;
    }

    $info = array(
        'version'      => $data['version'],
        'download_url' => $data['download_url'],
        'details_url'  => shopeame_update_string( $data, 'details_url', SHOPEAME_UPDATE_URI . '/releases' ),
        'homepage'     => shopeame_update_string( $data, 'homepage', SHOPEAME_UPDATE_URI ),
        'released'     => shopeame_update_string( $data, 'released' ),
        'requires'     => shopeame_update_string( $data, 'requires' ),
        'tested'       => shopeame_update_string( $data, 'tested' ),
        'requires_php' => shopeame_update_string( $data, 'requires_php' ),
        'changelog'    => isset( $data['changelog'] ) && is_array( $data['changelog'] )
            ? array_values( array_filter( $data['changelog'], 'is_string' ) )
            : array(),
    );

    return $info;
}

/**
 * Comprueba que el info.json trae una versión y un zip válidos.
 *
 * Fuera de las pruebas (sin SHOPEAME_UPDATE_INFO_URL), el zip tiene que ser una descarga de las releases de nuestro
 * repositorio: así nunca se instala un paquete de otro sitio aunque alguien manipulara el info.json.
 *
 * @param array $data Contenido decodificado del info.json.
 * @return bool
 */
function shopeame_is_valid_update_info( array $data ) {
    if ( ! isset( $data['version'], $data['download_url'] ) || ! is_string( $data['version'] ) || ! is_string( $data['download_url'] ) ) {
        return false;
    }
    if ( preg_match( '/^\d+(\.\d+){1,3}$/', $data['version'] ) !== 1 ) {
        return false;
    }
    if ( defined( 'SHOPEAME_UPDATE_INFO_URL' ) ) {
        return wp_http_validate_url( $data['download_url'] ) !== false;
    }

    return strpos( $data['download_url'], SHOPEAME_UPDATE_URI . '/releases/download/' ) === 0;
}

/**
 * Devuelve un campo de texto del info.json o el valor por defecto si no existe.
 *
 * @param array  $data    Contenido decodificado del info.json.
 * @param string $key     Campo que se quiere leer.
 * @param string $default Valor si el campo falta o no es texto.
 * @return string
 */
function shopeame_update_string( array $data, $key, $default = '' ) {
    return isset( $data[ $key ] ) && is_string( $data[ $key ] ) && $data[ $key ] !== '' ? $data[ $key ] : $default;
}

/**
 * Responde a WordPress con la última versión del plugin publicada en GitHub.
 *
 * WordPress llama a este filtro para todos los plugins cuya cabecera `Update URI` sea de github.com, así que se
 * devuelve el valor recibido sin tocarlo cuando el plugin no es este. Se responde aunque la versión sea la misma: así
 * WordPress lo coloca entre los plugins al día y permite activar sus actualizaciones automáticas.
 *
 * @param array|false $update      Datos de la actualización que hayan puesto otros filtros (false por defecto).
 * @param array       $plugin_data Cabeceras del plugin.
 * @param string      $plugin_file Archivo del plugin relativo a la carpeta de plugins.
 * @return array|false
 */
function shopeame_filter_update_plugins( $update, $plugin_data, $plugin_file ) {
    if ( $plugin_file !== plugin_basename( SHOPEAME_PLUGIN_FILE ) ) {
        return $update;
    }

    $info = shopeame_update_info();
    if ( $info === null ) {
        return $update;
    }

    return array(
        'id'           => SHOPEAME_UPDATE_URI,
        'slug'         => SHOPEAME_PLUGIN_SLUG,
        'version'      => $info['version'],
        'url'          => $info['details_url'],
        'package'      => $info['download_url'],
        'tested'       => shopeame_tested_up_to( $info['tested'] ),
        'requires_php' => $info['requires_php'],
        'icons'        => array( 'svg' => plugins_url( 'assets/shopeame-menu-icon.svg', SHOPEAME_PLUGIN_FILE ) ),
    );
}

/**
 * Adapta el «Tested up to» del readme.txt (por ejemplo 7.1) a la versión de WordPress instalada.
 *
 * WordPress compara ese valor con la versión completa (7.1.4) y marcaría «No probada» una versión mayor de la misma
 * rama. WordPress.org hace lo mismo que aquí: si la rama coincide, devuelve la versión instalada.
 *
 * @param string $tested Versión de WordPress con la que se ha probado, tal como viene del info.json.
 * @return string
 */
function shopeame_tested_up_to( $tested ) {
    $wp_version = get_bloginfo( 'version' );
    if ( $tested !== '' && preg_match( '/^\d+\.\d+$/', $tested ) === 1 && strpos( $wp_version, $tested . '.' ) === 0 ) {
        return $wp_version;
    }

    return $tested;
}

/**
 * Rellena la ventana «Ver detalles» del plugin con los datos de la última release.
 *
 * Sin este filtro WordPress buscaría el slug `shopeame` en WordPress.org, donde no está (o podría ser de otro autor).
 *
 * @param false|object|array $result Resultado de otros filtros (false por defecto).
 * @param string             $action Tipo de consulta de la API de plugins.
 * @param object             $args   Argumentos de la consulta; `slug` indica el plugin.
 * @return false|object|array
 */
function shopeame_filter_plugins_api( $result, $action, $args ) {
    if ( $action !== 'plugin_information' || ! is_object( $args ) || ! isset( $args->slug ) || $args->slug !== SHOPEAME_PLUGIN_SLUG ) {
        return $result;
    }

    $info = shopeame_update_info();
    if ( $info === null ) {
        return $result;
    }

    $changelog = '<h4>' . esc_html( $info['version'] ) . '</h4>';
    if ( ! empty( $info['changelog'] ) ) {
        $changelog .= '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $info['changelog'] ) ) . '</li></ul>';
    }
    $changelog .= '<p><a href="' . esc_url( $info['details_url'] ) . '">' . esc_html__( 'View the release on GitHub', 'shopeame' ) . '</a></p>';

    return (object) array(
        'name'          => 'Shopeame',
        'slug'          => SHOPEAME_PLUGIN_SLUG,
        'version'       => $info['version'],
        'author'        => '<a href="https://shopea.me">Shopeame</a>',
        'homepage'      => $info['homepage'],
        'requires'      => $info['requires'],
        'tested'        => shopeame_tested_up_to( $info['tested'] ),
        'requires_php'  => $info['requires_php'],
        'last_updated'  => $info['released'],
        'download_link' => $info['download_url'],
        'sections'      => array(
            'description' => '<p>' . esc_html__( 'Connects your WooCommerce store with shopea.me to sync orders with your FacturaScripts ERP.', 'shopeame' ) . '</p>',
            'changelog'   => $changelog,
        ),
    );
}

/**
 * Devuelve la actualización pendiente del plugin que WordPress tiene guardada, si la hay.
 *
 * Lee la última comprobación de WordPress (no hace ninguna petición), así que la pantalla de Shopeame no se retrasa.
 *
 * @return object|null Objeto de la respuesta de actualización (con `new_version` y `url`) o null si está al día.
 */
function shopeame_pending_update() {
    $updates = get_site_transient( 'update_plugins' );
    $file    = plugin_basename( SHOPEAME_PLUGIN_FILE );
    if ( ! is_object( $updates ) || empty( $updates->response[ $file ] ) ) {
        return null;
    }

    $update = $updates->response[ $file ];
    if ( ! is_object( $update ) || empty( $update->new_version ) || ! version_compare( $update->new_version, SHOPEAME_VERSION, '>' ) ) {
        return null;
    }

    return $update;
}
