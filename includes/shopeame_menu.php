<?php
/**
 * Pantalla administrativa y acciones de vinculación del plugin Shopeame.
 *
 * La pantalla replica la estructura y los textos de la app de Shopify (base visual común a los
 * conectores de Shopify, WooCommerce y PrestaShop).
 */

/** Gestiona el menú y la configuración administrativa de Shopeame. */
class shopeame_menu {
    /** Identificador (slug) de la página de administración del plugin. */
    const PAGE_SLUG = 'shopeame';

    /** Sufijo del hook de la página que devuelve add_menu_page() para un menú de primer nivel. */
    const PAGE_HOOK = 'toplevel_page_shopeame';

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
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'shopeameSettings' ),
            'dashicons-cart',
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

        $base_file = dirname( __DIR__ ) . '/shopeame.php';
        wp_enqueue_style(
            'shopeame-admin',
            plugins_url( 'assets/shopeame-admin.css', $base_file ),
            array(),
            (string) filemtime( dirname( __DIR__ ) . '/assets/shopeame-admin.css' )
        );
        wp_enqueue_script(
            'shopeame-admin',
            plugins_url( 'assets/shopeame-admin.js', $base_file ),
            array(),
            (string) filemtime( dirname( __DIR__ ) . '/assets/shopeame-admin.js' ),
            true
        );
    }

    /**
     * Pinta la pantalla de configuración: vista vinculada o no vinculada según el estado de la tienda.
     *
     * @return void
     */
    function shopeameSettings() {
        // procesamos las acciones del formulario antes de pintar la pantalla
        $this->handleActions();

        echo '<div class="wrap shopeame-page">';
        echo '<h1>Conectar con shopea.me</h1>';
        // los avisos de otros plugins se colocan aquí; el nuestro lleva la clase "inline" para no moverse
        echo '<hr class="wp-header-end">';

        if ( shopeame_is_linked() ) {
            $this->renderLinkedView();
        } else {
            $this->renderNotLinkedView();
        }

        echo '</div>';
    }

    /**
     * Pinta la vista de la tienda pendiente de vincular con shopea.me.
     *
     * @return void
     */
    private function renderNotLinkedView() {
        $api_user = get_option( 'shopeame_api_user', '' );
        $api_key  = get_option( 'shopeame_api_key', '' );
        ?>
        <div class="shopeame-section">
            <h2>Tu tienda ya está lista para conectar 🎉</h2>
            <p>Este plugin actúa de conector entre tu tienda de WooCommerce y
                <a href="https://shopea.me" target="_blank">shopea.me</a>. Para conectar la tienda online con
                shopea.me necesitas tener previamente registrado tu ERP en
                <a href="https://shopea.me" target="_blank">shopea.me</a>. Una vez registrado, tienes dos formas
                de vincular esta tienda.</p>
        </div>

        <?php $this->renderResultBanner(); ?>

        <div class="shopeame-section">
            <h2>Opción 1: copiar los datos en shopea.me</h2>
            <p>Copia los datos de abajo y pégalos en shopea.me al conectar una tienda de tipo WooCommerce.</p>
            <div class="shopeame-stack">
                <?php
                $this->renderCredentialField( 'shopeame-url', 'URL de la tienda', get_site_url() );
                $this->renderCredentialField( 'shopeame-ck', 'Clave del cliente (Consumer key)', $api_user );
                $this->renderCredentialField(
                    'shopeame-cs',
                    'Clave secreta (Consumer secret)',
                    $api_key,
                    'Trata estas claves como una contraseña: cualquiera con ellas puede leer los pedidos y productos de tu tienda a través de la API de WooCommerce.'
                );
                ?>
            </div>
            <form method="post" action="">
                <?php wp_nonce_field( 'shopeame_action' ); ?>
                <input type="hidden" name="shopeame_action" value="generate_keys">
                <button type="submit" class="button">
                    <?php echo esc_html( $api_user !== '' ? 'Regenerar claves API' : 'Generar claves API' ); ?>
                </button>
            </form>
        </div>

        <div class="shopeame-section">
            <h2>Opción 2: vincular indicando tu ERP</h2>
            <p>Introduce el UUID y el token de tu ERP (los obtienes en shopea.me una vez registrado el ERP) y
                vincularemos esta tienda automáticamente.</p>
            <form method="post" action="">
                <?php wp_nonce_field( 'shopeame_action' ); ?>
                <input type="hidden" name="shopeame_action" value="link">
                <div class="shopeame-stack">
                    <div class="shopeame-field">
                        <label class="shopeame-field-label" for="erp_field">UUID del ERP</label>
                        <input name="erp" type="text" id="erp_field" required>
                    </div>
                    <div class="shopeame-field">
                        <label class="shopeame-field-label" for="token_field">Token del ERP</label>
                        <input name="token" type="text" id="token_field" required>
                    </div>
                </div>
                <button type="submit" class="button button-primary">Vincular tienda</button>
            </form>
        </div>
        <?php
    }

    /**
     * Pinta la vista de la tienda ya vinculada con shopea.me.
     *
     * @return void
     */
    private function renderLinkedView() {
        $shop_link_url = shopeame_server_url() . '/shop-link/' . get_option( 'shop_uuid', '' );
        ?>
        <div class="shopeame-section">
            <h2>Tienda vinculada ✅</h2>
            <p>La tienda <strong><?php echo esc_html( get_site_url() ); ?></strong> está vinculada con
                <a href="https://shopea.me" target="_blank">shopea.me</a>. Sus pedidos se sincronizan
                automáticamente.
                <a href="<?php echo esc_url( $shop_link_url ); ?>" target="_blank">Ver la tienda en shopea.me</a></p>
        </div>

        <?php $this->renderResultBanner(); ?>

        <div class="shopeame-section">
            <h2>Acciones</h2>
            <div class="shopeame-actions">
                <form method="post" action="">
                    <?php wp_nonce_field( 'shopeame_action' ); ?>
                    <input type="hidden" name="shopeame_action" value="check">
                    <button type="submit" class="button">Comprobar conexión</button>
                </form>
                <form method="post" action=""
                      data-shopeame-confirm="Esta acción desvinculará la tienda de Shopeame. ¿Quieres continuar?">
                    <?php wp_nonce_field( 'shopeame_action' ); ?>
                    <input type="hidden" name="shopeame_action" value="unlink">
                    <button type="submit" class="button shopeame-button-critical">Desvincular tienda</button>
                </form>
                <form method="post" action=""
                      data-shopeame-confirm="Esta acción solo borrará los datos de conexión guardados en la app y no modificará Shopeame. ¿Quieres continuar?">
                    <?php wp_nonce_field( 'shopeame_action' ); ?>
                    <input type="hidden" name="shopeame_action" value="clear_local">
                    <button type="submit" class="button shopeame-button-critical-secondary">Borrar datos de la app</button>
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
                            data-shopeame-copied="Copiado">Copiar</button>
                <?php } else { ?>
                    <span class="shopeame-field-value shopeame-field-empty">Pulsa «Generar claves API» para crearla</span>
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
     * Procesa las acciones de configuración y conexión enviadas por POST.
     *
     * @return void
     */
    private function handleActions() {
        if ( empty( $_POST['shopeame_action'] ) ) {
            return;
        }
        if ( ! check_admin_referer( 'shopeame_action' ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['shopeame_action'] ) );

        switch ( $action ) {
            case 'generate_keys':
                $this->actionGenerateKeys();
                break;
            case 'link':
                $this->actionLink();
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

    private function actionGenerateKeys() {
        if ( shopeame_generate_api_keys() ) {
            $this->notice( 'success', 'Claves API generadas correctamente.' );
        } else {
            $this->notice( 'error', 'No se pudieron generar las claves API. ¿Está activo WooCommerce?' );
        }
    }

    private function actionLink() {
        $erp   = isset( $_POST['erp'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['erp'] ) ) ) : '';
        $token = isset( $_POST['token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['token'] ) ) ) : '';
        if ( $erp === '' || $token === '' ) {
            $this->notice( 'error', 'Introduce el UUID y el token del ERP.' );
            return;
        }

        // generamos las claves API si aún no existen
        if ( get_option( 'shopeame_api_user', '' ) === '' || get_option( 'shopeame_api_key', '' ) === '' ) {
            if ( ! shopeame_generate_api_keys() ) {
                $this->notice( 'error', 'No se pudieron generar las claves API. ¿Está activo WooCommerce?' );
                return;
            }
        }

        $result = shopeame_post_webhook( array(
            'event'    => 'register_woocommerce',
            'erp'      => $erp,
            'token'    => $token,
            'new_shop' => array(
                'url'      => get_site_url(),
                'api_user' => get_option( 'shopeame_api_user', '' ),
                'api_key'  => get_option( 'shopeame_api_key', '' ),
            ),
        ) );

        if ( $result['ok'] && isset( $result['data']['shop_uuid'], $result['data']['webhook_token'] ) ) {
            update_option( 'shop_uuid', $result['data']['shop_uuid'] );
            update_option( 'webhook_token', $result['data']['webhook_token'] );
            $this->notice( 'success', $this->linkSuccessMessage( $result['data']['message'] ?? '' ) );
        } else {
            $this->notice( 'error', 'No se pudo vincular la tienda. Revisa que el UUID y el token del ERP sean correctos.' );
        }
    }

    private function actionCheck() {
        $result = shopeame_post_webhook( array(
            'event' => 'check_shop',
            'shop'  => get_option( 'shop_uuid', '' ),
            'token' => get_option( 'webhook_token', '' ),
        ) );

        if ( $result['ok'] && ! empty( $result['data']['exists'] ) ) {
            $this->notice( 'success', 'La conexión con shopea.me funciona correctamente.' );
        } else {
            $this->notice( 'error', 'No se pudo confirmar la conexión con shopea.me.' );
        }
    }

    private function actionUnlink() {
        $result = shopeame_post_webhook( array(
            'event' => 'unlink_shop',
            'shop'  => get_option( 'shop_uuid', '' ),
            'token' => get_option( 'webhook_token', '' ),
        ) );

        // borramos las credenciales locales aunque el servidor falle, para no dejar la tienda
        // en un estado intermedio; el usuario siempre podrá volver a vincularla
        update_option( 'shop_uuid', '' );
        update_option( 'webhook_token', '' );

        if ( $result['ok'] ) {
            $this->notice( 'success', 'Tienda desvinculada de shopea.me.' );
        } else {
            $this->notice( 'warning', 'Se borraron las credenciales locales, pero shopea.me no confirmó la desvinculación.' );
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
        update_option( 'shop_uuid', '' );
        update_option( 'webhook_token', '' );

        $this->notice( 'success', 'Se borraron los datos de conexión de la app sin modificar ShopeameServer.' );
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
            return 'La tienda ya estaba vinculada. Se recuperaron sus datos sin crear una tienda nueva.';
        }
        if ( $message === 'shop-reactivated' ) {
            return 'La tienda se reactivó y quedó vinculada con shopea.me correctamente.';
        }
        return 'Tienda vinculada con shopea.me correctamente.';
    }
}
