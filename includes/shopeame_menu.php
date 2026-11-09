<?php
/**
 * Pantalla administrativa y acciones de vinculación del plugin Shopeame.
 *
 * La pantalla replica la estructura y los textos de la app de Shopify (base visual común a los
 * conectores de Shopify, WooCommerce y PrestaShop).
 *
 * @package Shopeame
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Gestiona el menú y la configuración administrativa de Shopeame. */
class shopeame_menu {
    /** Identificador (slug) de la página de administración del plugin. */
    const PAGE_SLUG = 'shopeame';

    /** Sufijo del hook de la página que devuelve add_menu_page() para un menú de primer nivel. */
    const PAGE_HOOK = 'toplevel_page_shopeame';

    /** Capacidad necesaria para ver la pantalla y ejecutar sus acciones. */
    const CAPABILITY = 'manage_options';

    /** Acción del nonce de los formularios de la pantalla. */
    const NONCE_ACTION = 'shopeame_action';

    /**
     * Icono del menú lateral: el logo de Shopeame (la «S» con líneas de velocidad) como SVG monocromo.
     *
     * Es el contenido de assets/shopeame-menu-icon.svg codificado en base64. WordPress lo recolorea con el
     * esquema de colores del administrador. Se guarda ya codificado para no usar base64_encode() ni leer el
     * archivo en cada carga. Si se cambia el SVG, se regenera con:
     * printf '%s' "$(cat assets/shopeame-menu-icon.svg)" | base64
     */
    const MENU_ICON = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjEwIDggNzQgNzQiPjxwYXRoIGZpbGw9ImJsYWNrIiBkPSJNNDcgMjdDNDggMjIgNTQgMTkuNSA2MyAxOS41TDcyIDE5LjVDNzggMTkuNyA4MiAyMSA4MS41IDI0TDc5LjUgMzNMNjAgMzNMNTkuNSAzN0w2NiAzNy41Qzc0IDM4IDc5LjUgNDEgNzkgNDdMNzggNTZMNzcgNTlDNzUgNjYgNzEgNjkgNjQgNzAuNUM1OCA3MS41IDUwIDcxLjUgNDQgNzAuNUM0MCA3MCAzNy41IDY4LjUgMzggNjZMNDAgNTdMNjIuNSA1Ni41TDYzIDUzTDU1IDUyLjVDNTAgNTIgNDcuNSA1MSA0NSA0OC41QzQzIDQ2LjUgNDIuNSA0MyA0MyA0MFpNMjIgMjFMNDkgMjAuNUw0NiAyNFpNMTIgMjhMNDMgMjcuNUw0MiAzMS41Wk0xMiAzNUw0MyAzNUw0MCAzOC41Wk0xMyA0M0w0MSA0M0w0MCA0Ni41Wk0xNiA1MC41TDQ2IDUwLjVMNDkgNTMuNVpNMTQgNThMMzggNThMMzYgNjEuNVpNMTMgNjUuNUwzNiA2NS41TDM0IDY4LjVaIi8+PC9zdmc+';

    /**
     * Resultado de la última acción procesada, que se pinta como banner bajo la primera sección.
     *
     * @var array{type: string, message: string}|null
     */
    private $result = null;

    /**
     * Añade el menú de Shopeame en el panel de WordPress.
     *
     * @return void
     */
    function shopeame_add_menu() {
        add_menu_page(
            'Shopeame',
            'Shopeame',
            self::CAPABILITY,
            self::PAGE_SLUG,
            array( $this, 'shopeameSettings' ),
            self::MENU_ICON,
            56
        );
    }

    /**
     * Encola la hoja de estilos y el script de la pantalla, solo en la página del plugin.
     *
     * @param string $hook_suffix Sufijo del hook de la página de administración actual.
     * @return void
     */
    function shopeame_enqueue_assets( $hook_suffix ) {
        if ( $hook_suffix !== self::PAGE_HOOK ) {
            return;
        }

        wp_enqueue_style(
            'shopeame-admin',
            plugins_url( 'assets/shopeame-admin.css', SHOPEAME_PLUGIN_FILE ),
            array(),
            SHOPEAME_VERSION
        );
        wp_enqueue_script(
            'shopeame-admin',
            plugins_url( 'assets/shopeame-admin.js', SHOPEAME_PLUGIN_FILE ),
            array(),
            SHOPEAME_VERSION,
            true
        );
    }

    /**
     * Pinta la pantalla de configuración: vista vinculada o no vinculada según el estado de la tienda.
     *
     * @return void
     */
    function shopeameSettings() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }

        // procesamos las acciones del formulario antes de pintar la pantalla
        $this->handleActions();

        echo '<div class="wrap shopeame-page">';
        echo '<h1>' . esc_html__( 'Connect with shopea.me', 'shopeame' ) . '</h1>';
        // los avisos de otros plugins se colocan aquí; el nuestro lleva la clase "inline" para no moverse
        echo '<hr class="wp-header-end">';

        $this->renderUpdateNotice();

        // preguntamos a shopea.me si la tienda sigue vinculada: si se desvincula desde allí, el
        // plugin no se entera y conserva las credenciales
        $state = shopeame_check_link_state();
        if ( $state['linked'] ) {
            $this->renderLinkedView( $state['check_failed'] );
        } else {
            $this->renderNotLinkedView();
        }

        $this->renderVersionFooter();

        echo '</div>';
    }

    /**
     * Pinta el aviso de versión nueva, si WordPress ha encontrado una en la última comprobación.
     *
     * El botón «Actualizar ahora» usa la misma acción y el mismo nonce que el enlace de la pantalla de plugins, así que
     * la actualización la hace WordPress con su actualizador, no el plugin. Solo lo ve quien puede actualizar plugins.
     *
     * @return void
     */
    private function renderUpdateNotice() {
        $update = shopeame_pending_update();
        if ( $update === null ) {
            return;
        }

        $file = plugin_basename( SHOPEAME_PLUGIN_FILE );
        echo '<div class="notice notice-warning inline shopeame-update-notice"><p>';
        echo esc_html(
            sprintf(
                /* translators: 1: new version number, 2: installed version number */
                __( 'A new version of Shopeame is available: %1$s. You have version %2$s.', 'shopeame' ),
                $update->new_version,
                SHOPEAME_VERSION
            )
        );
        echo '</p><p>';
        if ( current_user_can( 'update_plugins' ) ) {
            printf(
                '<a class="button button-primary" href="%s">%s</a> ',
                esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $file ) ), 'upgrade-plugin_' . $file ) ),
                esc_html__( 'Update now', 'shopeame' )
            );
        }
        if ( ! empty( $update->url ) ) {
            echo wp_kses_post( $this->externalLink( $update->url, __( 'What’s new', 'shopeame' ) ) );
        }
        echo '</p></div>';
    }

    /**
     * Pinta al pie de la pantalla la versión instalada y el enlace para buscar actualizaciones.
     *
     * El enlace abre **Escritorio → Actualizaciones** con `force-check`, que hace que WordPress vuelva a preguntar por
     * las versiones nuevas en ese momento en lugar de esperar a su comprobación periódica.
     *
     * @return void
     */
    private function renderVersionFooter() {
        echo '<p class="shopeame-version">';
        echo esc_html(
            sprintf(
                /* translators: %s: installed version number */
                __( 'Shopeame version %s', 'shopeame' ),
                SHOPEAME_VERSION
            )
        );
        if ( current_user_can( 'update_plugins' ) ) {
            printf(
                ' · <a href="%s">%s</a>',
                esc_url( self_admin_url( 'update-core.php?force-check=1' ) ),
                esc_html__( 'Check for updates', 'shopeame' )
            );
        }
        echo '</p>';
    }

    /**
     * Pinta la vista de la tienda pendiente de vincular con shopea.me.
     *
     * @return void
     */
    private function renderNotLinkedView() {
        $api_user = (string) get_option( SHOPEAME_OPTION_API_USER, '' );
        $api_key  = (string) get_option( SHOPEAME_OPTION_API_KEY, '' );
        $shopeame = $this->externalLink( 'https://shopea.me', 'shopea.me' );
        ?>
        <div class="shopeame-section">
            <h2><?php esc_html_e( 'Your store is ready to connect 🎉', 'shopeame' ); ?></h2>
            <p>
                <?php
                echo wp_kses_post(
                    sprintf(
                        /* translators: %s: link to shopea.me */
                        __( 'This plugin connects your WooCommerce store with %s. To connect the store you first need to register your ERP on shopea.me. Once registered, there are two ways to link this store.', 'shopeame' ),
                        $shopeame
                    )
                );
                ?>
            </p>
        </div>

        <?php $this->renderResultBanner(); ?>

        <div class="shopeame-section">
            <h2><?php esc_html_e( 'Option 1: copy the data into shopea.me', 'shopeame' ); ?></h2>
            <p><?php esc_html_e( 'Copy the data below and paste it into shopea.me when connecting a WooCommerce store.', 'shopeame' ); ?></p>
            <div class="shopeame-stack">
                <?php
                $this->renderCredentialField( 'shopeame-url', __( 'Store URL', 'shopeame' ), get_site_url() );
                $this->renderCredentialField( 'shopeame-ck', __( 'Consumer key', 'shopeame' ), $api_user );
                $this->renderCredentialField(
                    'shopeame-cs',
                    __( 'Consumer secret', 'shopeame' ),
                    $api_key,
                    __( 'Treat these keys like a password: anyone who has them can read the orders and products of your store through the WooCommerce API.', 'shopeame' )
                );
                ?>
            </div>
            <form method="post" action="">
                <?php wp_nonce_field( self::NONCE_ACTION ); ?>
                <input type="hidden" name="shopeame_action" value="generate_keys">
                <button type="submit" class="button">
                    <?php echo esc_html( $api_user !== '' ? __( 'Regenerate API keys', 'shopeame' ) : __( 'Generate API keys', 'shopeame' ) ); ?>
                </button>
            </form>
        </div>

        <div class="shopeame-section">
            <h2><?php esc_html_e( 'Option 2: link using your ERP details', 'shopeame' ); ?></h2>
            <p><?php esc_html_e( 'Enter the UUID and token of your ERP (you get them on shopea.me once the ERP is registered) and this store will be linked automatically.', 'shopeame' ); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field( self::NONCE_ACTION ); ?>
                <input type="hidden" name="shopeame_action" value="link">
                <div class="shopeame-stack">
                    <div class="shopeame-field">
                        <label class="shopeame-field-label" for="erp_field"><?php esc_html_e( 'ERP UUID', 'shopeame' ); ?></label>
                        <input name="erp" type="text" id="erp_field" required>
                    </div>
                    <div class="shopeame-field">
                        <label class="shopeame-field-label" for="token_field"><?php esc_html_e( 'ERP token', 'shopeame' ); ?></label>
                        <input name="token" type="text" id="token_field" required>
                    </div>
                </div>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Link store', 'shopeame' ); ?></button>
            </form>
        </div>
        <?php
    }

    /**
     * Pinta la vista de la tienda ya vinculada con shopea.me.
     *
     * @param bool $check_failed Si no se pudo confirmar la vinculación con shopea.me al abrir la pantalla.
     * @return void
     */
    private function renderLinkedView( $check_failed = false ) {
        $shop_link_url = shopeame_server_url() . '/shop-link/' . rawurlencode( (string) get_option( SHOPEAME_OPTION_SHOP_UUID, '' ) );
        ?>
        <div class="shopeame-section">
            <h2><?php esc_html_e( 'Store linked ✅', 'shopeame' ); ?></h2>
            <p>
                <?php
                echo wp_kses_post(
                    sprintf(
                        /* translators: 1: store URL, 2: link to shopea.me */
                        __( 'The store %1$s is linked with %2$s. Its orders are synced automatically.', 'shopeame' ),
                        '<strong>' . esc_html( get_site_url() ) . '</strong>',
                        $this->externalLink( 'https://shopea.me', 'shopea.me' )
                    )
                );
                echo ' ';
                echo wp_kses_post( $this->externalLink( $shop_link_url, __( 'View the store on shopea.me', 'shopeame' ) ) );
                ?>
            </p>
        </div>

        <?php
        // sin respuesta de shopea.me mostramos lo guardado, pero avisamos de que no está confirmado
        if ( $check_failed && $this->result === null ) {
            printf(
                '<div class="notice notice-warning inline"><p>%s</p></div>',
                esc_html__( 'Could not connect to shopea.me to confirm that the store is linked.', 'shopeame' )
            );
        }
        $this->renderResultBanner();
        ?>

        <div class="shopeame-section">
            <h2><?php esc_html_e( 'Actions', 'shopeame' ); ?></h2>
            <div class="shopeame-actions">
                <form method="post" action="">
                    <?php wp_nonce_field( self::NONCE_ACTION ); ?>
                    <input type="hidden" name="shopeame_action" value="check">
                    <button type="submit" class="button"><?php esc_html_e( 'Check connection', 'shopeame' ); ?></button>
                </form>
                <form method="post" action=""
                      data-shopeame-confirm="<?php esc_attr_e( 'This will unlink the store from Shopeame. Do you want to continue?', 'shopeame' ); ?>">
                    <?php wp_nonce_field( self::NONCE_ACTION ); ?>
                    <input type="hidden" name="shopeame_action" value="unlink">
                    <button type="submit" class="button shopeame-button-critical"><?php esc_html_e( 'Unlink store', 'shopeame' ); ?></button>
                </form>
                <form method="post" action=""
                      data-shopeame-confirm="<?php esc_attr_e( 'This will only delete the connection data saved in this plugin and will not change Shopeame. Do you want to continue?', 'shopeame' ); ?>">
                    <?php wp_nonce_field( self::NONCE_ACTION ); ?>
                    <input type="hidden" name="shopeame_action" value="clear_local">
                    <button type="submit" class="button shopeame-button-critical-secondary"><?php esc_html_e( 'Delete plugin data', 'shopeame' ); ?></button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Pinta un campo de credencial: etiqueta, valor recortable en monospace y botón "Copiar".
     *
     * Si el valor está vacío (claves aún sin generar) se muestra un texto indicativo y no se ofrece copiar.
     *
     * @param string $id    Identificador HTML del valor, usado por el botón Copiar.
     * @param string $label Etiqueta visible del campo.
     * @param string $value Valor a mostrar y copiar.
     * @param string $hint  Texto de ayuda opcional que se muestra debajo del campo.
     * @return void
     */
    private function renderCredentialField( $id, $label, $value, $hint = '' ) {
        ?>
        <div class="shopeame-field">
            <span class="shopeame-field-label"><?php echo esc_html( $label ); ?></span>
            <div class="shopeame-field-row">
                <?php if ( $value !== '' ) { ?>
                    <code class="shopeame-field-value" id="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $value ); ?></code>
                    <button type="button" class="button" data-shopeame-copy="<?php echo esc_attr( $id ); ?>"
                            data-shopeame-copied="<?php esc_attr_e( 'Copied', 'shopeame' ); ?>"><?php esc_html_e( 'Copy', 'shopeame' ); ?></button>
                <?php } else { ?>
                    <span class="shopeame-field-value shopeame-field-empty"><?php esc_html_e( 'Click “Generate API keys” to create it', 'shopeame' ); ?></span>
                <?php } ?>
            </div>
            <?php if ( $hint !== '' ) { ?>
                <p class="description"><?php echo esc_html( $hint ); ?></p>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * Pinta el banner con el resultado de la última acción, si lo hay.
     *
     * Usa los avisos nativos de WordPress con la clase "inline" para que el script del panel no los
     * mueva bajo el título y queden en la misma posición que en la app de Shopify.
     *
     * @return void
     */
    private function renderResultBanner() {
        if ( $this->result === null ) {
            return;
        }

        printf(
            '<div class="notice notice-%s inline"><p>%s</p></div>',
            esc_attr( $this->result['type'] ),
            esc_html( $this->result['message'] )
        );
    }

    /**
     * Devuelve el HTML de un enlace externo que se abre en una pestaña nueva.
     *
     * @param string $url  URL de destino.
     * @param string $text Texto visible del enlace.
     * @return string HTML ya escapado.
     */
    private function externalLink( $url, $text ) {
        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url( $url ),
            esc_html( $text )
        );
    }

    /**
     * Procesa las acciones de configuración y conexión enviadas por POST.
     *
     * Comprueba el nonce y la capacidad del usuario antes de leer ningún otro dato del formulario.
     *
     * @return void
     */
    private function handleActions() {
        if ( empty( $_POST['shopeame_action'] ) ) {
            return;
        }
        check_admin_referer( self::NONCE_ACTION );
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }

        $action = sanitize_key( wp_unslash( $_POST['shopeame_action'] ) );

        switch ( $action ) {
            case 'generate_keys':
                $this->actionGenerateKeys();
                break;
            case 'link':
                $erp   = isset( $_POST['erp'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['erp'] ) ) ) : '';
                $token = isset( $_POST['token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['token'] ) ) ) : '';
                $this->actionLink( $erp, $token );
                break;
            case 'check':
                $this->actionCheck();
                break;
            case 'unlink':
                $this->actionUnlink();
                break;
            case 'clear_local':
                $this->actionClearLocal();
                break;
        }
    }

    /**
     * Genera (o regenera) las claves de la API REST de WooCommerce que usa shopea.me.
     *
     * @return void
     */
    private function actionGenerateKeys() {
        if ( shopeame_generate_api_keys() ) {
            $this->notice( 'success', __( 'API keys generated successfully.', 'shopeame' ) );
        } else {
            $this->notice( 'error', __( 'The API keys could not be generated. Is WooCommerce active?', 'shopeame' ) );
        }
    }

    /**
     * Registra la tienda en shopea.me con el UUID y el token del ERP, y guarda las credenciales devueltas.
     *
     * @param string $erp   UUID del ERP en shopea.me.
     * @param string $token Token del ERP en shopea.me.
     * @return void
     */
    private function actionLink( $erp, $token ) {
        if ( $erp === '' || $token === '' ) {
            $this->notice( 'error', __( 'Enter the ERP UUID and token.', 'shopeame' ) );
            return;
        }

        // generamos las claves API si aún no existen
        if ( get_option( SHOPEAME_OPTION_API_USER, '' ) === '' || get_option( SHOPEAME_OPTION_API_KEY, '' ) === '' ) {
            if ( ! shopeame_generate_api_keys() ) {
                $this->notice( 'error', __( 'The API keys could not be generated. Is WooCommerce active?', 'shopeame' ) );
                return;
            }
        }

        $result = shopeame_post_webhook(
            array(
                'event'    => 'register_woocommerce',
                'erp'      => $erp,
                'token'    => $token,
                'new_shop' => array(
                    'url'      => get_site_url(),
                    'api_user' => get_option( SHOPEAME_OPTION_API_USER, '' ),
                    'api_key'  => get_option( SHOPEAME_OPTION_API_KEY, '' ),
                ),
            )
        );

        if ( $result['ok']
            && shopeame_save_link_credentials( $result['data']['shop_uuid'] ?? '', $result['data']['webhook_token'] ?? '' ) ) {
            $this->notice( 'success', $this->linkSuccessMessage( $result['data']['message'] ?? '' ) );
        } else {
            $this->notice( 'error', __( 'The store could not be linked. Check that the ERP UUID and token are correct.', 'shopeame' ) );
        }
    }

    /**
     * Comprueba con shopea.me que la tienda sigue vinculada.
     *
     * @return void
     */
    private function actionCheck() {
        $result = shopeame_post_webhook(
            array(
                'event' => 'check_shop',
                'shop'  => get_option( SHOPEAME_OPTION_SHOP_UUID, '' ),
                'token' => get_option( SHOPEAME_OPTION_WEBHOOK_TOKEN, '' ),
            )
        );

        if ( $result['ok'] && ! empty( $result['data']['exists'] ) ) {
            $this->notice( 'success', __( 'The connection with shopea.me is working correctly.', 'shopeame' ) );
        } else {
            $this->notice( 'error', __( 'The connection with shopea.me could not be confirmed.', 'shopeame' ) );
        }
    }

    /**
     * Desvincula la tienda en shopea.me y borra las credenciales locales.
     *
     * @return void
     */
    private function actionUnlink() {
        $result = shopeame_post_webhook(
            array(
                'event' => 'unlink_shop',
                'shop'  => get_option( SHOPEAME_OPTION_SHOP_UUID, '' ),
                'token' => get_option( SHOPEAME_OPTION_WEBHOOK_TOKEN, '' ),
            )
        );

        // borramos las credenciales locales aunque el servidor falle, para no dejar la tienda
        // en un estado intermedio; el usuario siempre podrá volver a vincularla
        shopeame_clear_link_credentials();

        if ( $result['ok'] ) {
            $this->notice( 'success', __( 'Store unlinked from shopea.me.', 'shopeame' ) );
        } else {
            $this->notice( 'warning', __( 'The local credentials were deleted, but shopea.me did not confirm the unlinking.', 'shopeame' ) );
        }
    }

    /**
     * Borra únicamente las credenciales de vinculación guardadas en WordPress.
     *
     * No realiza ninguna petición a ShopeameServer y conserva las claves de la API de WooCommerce.
     *
     * @return void
     */
    private function actionClearLocal() {
        shopeame_clear_link_credentials();

        $this->notice( 'success', __( 'The connection data saved in this plugin was deleted without changing shopea.me.', 'shopeame' ) );
    }

    /**
     * Guarda el resultado de una acción para pintarlo después como banner en su sitio.
     *
     * @param string $type    Tipo de aviso de WordPress: success, error, warning o info.
     * @param string $message Mensaje para el comercio.
     * @return void
     */
    private function notice( $type, $message ) {
        $this->result = array(
            'type'    => $type,
            'message' => $message,
        );
    }

    /**
     * Traduce el código devuelto por ShopeameServer al mensaje para el comercio.
     *
     * @param string $message Código de resultado del servidor.
     * @return string
     */
    private function linkSuccessMessage( $message ) {
        if ( $message === 'shop-already-exists' ) {
            return __( 'The store was already linked. Its data was recovered without creating a new store.', 'shopeame' );
        }
        if ( $message === 'shop-reactivated' ) {
            return __( 'The store was reactivated and linked with shopea.me successfully.', 'shopeame' );
        }
        return __( 'Store linked with shopea.me successfully.', 'shopeame' );
    }
}
