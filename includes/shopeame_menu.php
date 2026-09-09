<?php
/**
 * Pantalla administrativa y acciones de vinculación del plugin Shopeame.
 */

/** Gestiona el menú y la configuración administrativa de Shopeame. */
class shopeame_menu {
    // añade el menú de shopeame en el panel de WordPress
    function shopeame_add_menu() {
        add_menu_page(
            'Shopeame',
            'Shopeame',
            'manage_options',
            'shopeame',
            array( $this, 'shopeameSettings' ),
            'dashicons-cart',
            56
        );
    }

    function shopeameSettings() {
        // procesamos las acciones del formulario antes de pintar la pantalla
        $this->handleActions();

        $linked   = shopeame_is_linked();
        $api_user = get_option( 'shopeame_api_user', '' );
        $api_key  = get_option( 'shopeame_api_key', '' );
        $shop_url = get_site_url();
        ?>
        <div class="wrap">
            <h1>Shopeame</h1>

            <?php if ( $linked ) { ?>
                <div class="card" style="max-width:720px;">
                    <h2>Tienda vinculada ✅</h2>
                    <p>La tienda <strong><?php echo esc_html( $shop_url ); ?></strong> está vinculada con
                       <a href="https://shopea.me" target="_blank">shopea.me</a>. Sus pedidos se sincronizan automáticamente.
                       <a href="<?php echo esc_url( shopeame_server_url() . '/shop-link/' . get_option( 'shop_uuid', '' ) ); ?>" target="_blank">Ver la tienda en shopea.me</a>.</p>
                </div>

                <div class="card" style="max-width:720px;">
                    <h2>Acciones</h2>
                    <div>
                        <form method="post" action="" style="display:inline-block; margin-right:.5rem;">
                            <?php wp_nonce_field( 'shopeame_action' ); ?>
                            <input type="hidden" name="shopeame_action" value="check">
                            <input type="submit" class="button button-secondary" value="Comprobar conexión">
                        </form>
                        <form method="post" action="" style="display:inline-block; margin-right:.5rem;" onsubmit="return confirm('Esta acción desvinculará la tienda de Shopeame. ¿Quieres continuar?');">
                            <?php wp_nonce_field( 'shopeame_action' ); ?>
                            <input type="hidden" name="shopeame_action" value="unlink">
                            <input type="submit" class="button button-primary" value="Desvincular tienda">
                        </form>
                        <form method="post" action="" style="display:inline-block;" onsubmit="return confirm('Esta acción solo borrará los datos de conexión guardados en la app y no modificará Shopeame. ¿Quieres continuar?');">
                            <?php wp_nonce_field( 'shopeame_action' ); ?>
                            <input type="hidden" name="shopeame_action" value="clear_local">
                            <input type="submit" class="button button-secondary" value="Borrar datos de la app">
                        </form>
                    </div>
                </div>
            <?php } else { ?>
                <p>Conecta tu tienda WooCommerce con <a href="https://shopea.me" target="_blank">shopea.me</a>.
                   Para ello necesitas tener previamente registrado tu ERP en shopea.me. Después tienes dos formas de vincularla.</p>

                <div class="card" style="max-width:720px;">
                    <h2>Opción 1: copiar los datos en shopea.me</h2>
                    <p>Copia estos datos y pégalos en shopea.me al conectar una tienda de tipo WooCommerce.</p>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">URL de la tienda</th>
                                <td>
                                    <code id="shopeame-url"><?php echo esc_html( $shop_url ); ?></code>
                                    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('shopeame-url').textContent)">Copiar</button>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Clave del cliente (Consumer key)</th>
                                <td>
                                    <?php if ( $api_user !== '' ) { ?>
                                        <code id="shopeame-ck"><?php echo esc_html( $api_user ); ?></code>
                                        <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('shopeame-ck').textContent)">Copiar</button>
                                    <?php } else { ?>
                                        <em>— genera las claves —</em>
                                    <?php } ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Clave secreta (Consumer secret)</th>
                                <td>
                                    <?php if ( $api_key !== '' ) { ?>
                                        <code id="shopeame-cs"><?php echo esc_html( $api_key ); ?></code>
                                        <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('shopeame-cs').textContent)">Copiar</button>
                                    <?php } else { ?>
                                        <em>— genera las claves —</em>
                                    <?php } ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'shopeame_action' ); ?>
                        <input type="hidden" name="shopeame_action" value="generate_keys">
                        <input type="submit" class="button button-secondary"
                               value="<?php echo $api_user !== '' ? 'Regenerar claves API' : 'Generar claves API'; ?>">
                    </form>
                </div>

                <div class="card" style="max-width:720px;">
                    <h2>Opción 2: vincular indicando tu ERP</h2>
                    <p>Introduce el UUID y el token de tu ERP (los obtienes en shopea.me una vez registrado el ERP).
                       Se generarán las claves API automáticamente si aún no existen.</p>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'shopeame_action' ); ?>
                        <input type="hidden" name="shopeame_action" value="link">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="erp_field">UUID del ERP</label></th>
                                    <td><input name="erp" type="text" id="erp_field" class="regular-text" required placeholder="UUID del ERP"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="token_field">Token del ERP</label></th>
                                    <td><input name="token" type="text" id="token_field" class="regular-text" required placeholder="Token del ERP"></td>
                                </tr>
                            </tbody>
                        </table>
                        <input type="submit" class="button button-primary" value="Vincular tienda">
                    </form>
                </div>
            <?php } ?>
        </div>
        <?php
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

    private function notice( $type, $message ) {
        printf( '<div class="notice notice-%s"><p>%s</p></div>', esc_attr( $type ), esc_html( $message ) );
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
