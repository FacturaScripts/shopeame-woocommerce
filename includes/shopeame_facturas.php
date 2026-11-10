<?php
/**
 * Facturas del ERP en los pedidos de WooCommerce.
 *
 * En el administrador añade una columna «Invoices» al listado de pedidos y un cuadro «Invoices» en la
 * ficha del pedido. En Mi cuenta, si el comercio lo permite (opción shopeame_customer_invoices), el
 * cliente ve una acción «Invoices» en sus pedidos y una sección «Invoices» al ver un pedido suyo.
 *
 * Las facturas no se guardan en WordPress: se piden a shopea.me al pulsar el botón (evento
 * order_invoices) y cada PDF se descarga a través de shopea.me (evento invoice_pdf), que los obtiene
 * del ERP del cliente. Así siempre se ven las facturas parciales, las que agrupan varios pedidos y
 * las rectificativas tal como están en el ERP.
 *
 * @package Shopeame
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Muestra y descarga las facturas del ERP de cada pedido. */
class shopeame_facturas {
    /** Identificador de la columna del listado de pedidos del administrador. */
    const COLUMN = 'shopeame_invoices';

    /** Clave de la acción «Invoices» de Mi cuenta → Pedidos; WooCommerce la usa como clase del botón. */
    const ACCOUNT_ACTION = 'shopeame-invoices-button';

    /** Prefijo del ancla de la sección de facturas al ver un pedido; le sigue el id del pedido. */
    const ACCOUNT_ANCHOR = 'shopeame-invoices-';

    /** Capacidad necesaria para ver y descargar las facturas. */
    const CAPABILITY = 'edit_shop_orders';

    /** Acción AJAX que devuelve las facturas de un pedido en el administrador. */
    const AJAX_ACTION = 'shopeame_order_invoices';

    /** Acción AJAX que devuelve las facturas de un pedido al cliente en Mi cuenta. */
    const CUSTOMER_AJAX_ACTION = 'shopeame_customer_order_invoices';

    /** Acción de admin-post.php que descarga el PDF de una factura. */
    const DOWNLOAD_ACTION = 'shopeame_invoice_pdf';

    /** Acción del nonce de la petición AJAX del administrador. */
    const NONCE_LIST = 'shopeame_order_invoices';

    /** Acción del nonce de la petición AJAX del cliente. */
    const NONCE_CUSTOMER = 'shopeame_customer_order_invoices';

    /** Acción del nonce de los enlaces de descarga. */
    const NONCE_DOWNLOAD = 'shopeame_invoice_pdf';

    /** Segundos máximos de espera de shopea.me, que a su vez consulta el ERP. */
    const TIMEOUT = 30;

    /**
     * Registra los hooks de la columna, del cuadro de la ficha, de los recursos y de las acciones.
     *
     * @return void
     */
    public static function register() {
        // listado de pedidos con HPOS y listado clásico (pedidos guardados como entradas). El
        // listado clásico define sus columnas con prioridad 10, así que añadimos la nuestra después
        add_filter( 'woocommerce_shop_order_list_table_columns', array( __CLASS__, 'add_column' ), 20 );
        add_action( 'woocommerce_shop_order_list_table_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
        add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_column' ), 20 );
        add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );

        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_list' ) );

        // Mi cuenta: listado de pedidos y detalle de un pedido del cliente
        add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'add_account_action' ), 10, 2 );
        add_action( 'woocommerce_view_order', array( __CLASS__, 'render_view_order' ), 20 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_front_assets' ) );
        add_action( 'wp_ajax_' . self::CUSTOMER_AJAX_ACTION, array( __CLASS__, 'ajax_customer_list' ) );

        // admin-post.php sirve a gestores y clientes: WooCommerce no bloquea esa ruta a los clientes
        add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( __CLASS__, 'download' ) );
    }

    /**
     * Indica si los clientes pueden ver y descargar sus facturas desde Mi cuenta.
     *
     * @return bool
     */
    public static function customers_enabled() {
        return shopeame_is_linked() && get_option( SHOPEAME_OPTION_CUSTOMER_INVOICES, 'yes' ) === 'yes';
    }

    /**
     * Añade la columna de facturas detrás del total del pedido.
     *
     * @param array $columns Columnas del listado.
     * @return array
     */
    public static function add_column( $columns ) {
        if ( ! shopeame_is_linked() || ! current_user_can( self::CAPABILITY ) ) {
            return $columns;
        }

        $result = array();
        foreach ( $columns as $key => $label ) {
            $result[ $key ] = $label;
            if ( $key === 'order_total' ) {
                $result[ self::COLUMN ] = esc_html__( 'Invoices', 'shopeame' );
            }
        }

        // si otro plugin ha quitado la columna del total, la ponemos al final
        if ( ! isset( $result[ self::COLUMN ] ) ) {
            $result[ self::COLUMN ] = esc_html__( 'Invoices', 'shopeame' );
        }

        return $result;
    }

    /**
     * Pinta el botón de facturas de un pedido en el listado.
     *
     * @param string       $column         Columna que se está pintando.
     * @param WC_Order|int $order_or_post  Pedido (listado con HPOS) o id de la entrada (listado clásico).
     * @return void
     */
    public static function render_column( $column, $order_or_post ) {
        if ( $column !== self::COLUMN ) {
            return;
        }

        $order = $order_or_post instanceof WC_Order ? $order_or_post : wc_get_order( $order_or_post );
        if ( ! $order ) {
            return;
        }

        self::render_widget( $order->get_id(), false );
    }

    /**
     * Añade el cuadro de facturas en la barra lateral de la ficha del pedido.
     *
     * @return void
     */
    public static function add_meta_box() {
        if ( ! shopeame_is_linked() || ! current_user_can( self::CAPABILITY ) || ! function_exists( 'wc_get_page_screen_id' ) ) {
            return;
        }

        add_meta_box(
            'shopeame-invoices',
            esc_html__( 'Invoices', 'shopeame' ),
            array( __CLASS__, 'render_meta_box' ),
            wc_get_page_screen_id( 'shop-order' ),
            'side',
            'default'
        );
    }

    /**
     * Pinta el cuadro de facturas de la ficha del pedido, que las carga al abrir la ficha.
     *
     * @param WP_Post|WC_Order $post_or_order Entrada (pedidos clásicos) o pedido (HPOS).
     * @return void
     */
    public static function render_meta_box( $post_or_order ) {
        $order = $post_or_order instanceof WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        self::render_widget( $order->get_id(), true );
    }

    /**
     * Encola el script y los estilos en el listado y en la ficha de los pedidos.
     *
     * @return void
     */
    public static function enqueue_assets() {
        if ( ! shopeame_is_linked() || ! current_user_can( self::CAPABILITY ) ) {
            return;
        }

        $screen = get_current_screen();
        $screens = array( 'woocommerce_page_wc-orders', 'edit-shop_order', 'shop_order' );
        if ( ! $screen || ! in_array( $screen->id, $screens, true ) ) {
            return;
        }

        wp_enqueue_style(
            'shopeame-orders',
            plugins_url( 'assets/shopeame-orders.css', SHOPEAME_PLUGIN_FILE ),
            array(),
            SHOPEAME_VERSION
        );
        wp_enqueue_script(
            'shopeame-orders',
            plugins_url( 'assets/shopeame-orders.js', SHOPEAME_PLUGIN_FILE ),
            array(),
            SHOPEAME_VERSION,
            true
        );
        self::localize_script( self::AJAX_ACTION, wp_create_nonce( self::NONCE_LIST ), false );
    }

    /**
     * Añade la acción «Invoices» a los pedidos de Mi cuenta → Pedidos, junto a «Ver».
     *
     * Así el botón usa exactamente el mismo estilo que el resto de acciones en cualquier tema. El
     * enlace lleva a la sección de facturas del pedido; el script lo convierte en un desplegable.
     *
     * @param array    $actions Acciones del pedido.
     * @param WC_Order $order   Pedido del cliente.
     * @return array
     */
    public static function add_account_action( $actions, $order ) {
        if ( ! $order instanceof WC_Order || ! self::customers_enabled() ) {
            return $actions;
        }

        // WooCommerce también pinta estas acciones al ver un pedido, donde ya está la sección de
        // facturas: solo añadimos el botón en el listado de Mi cuenta → Pedidos
        if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'orders' ) ) {
            return $actions;
        }

        $actions[ self::ACCOUNT_ACTION ] = array(
            'url'        => $order->get_view_order_url() . '#' . self::ACCOUNT_ANCHOR . $order->get_id(),
            'name'       => __( 'Invoices', 'shopeame' ),
            /* translators: %s: order number. */
            'aria-label' => sprintf( __( 'Invoices of order %s', 'shopeame' ), $order->get_order_number() ),
        );

        return $actions;
    }

    /**
     * Pinta la sección de facturas al ver un pedido en Mi cuenta. Se carga al abrir la página.
     *
     * @param int $order_id Identificador del pedido.
     * @return void
     */
    public static function render_view_order( $order_id ) {
        if ( ! self::customers_enabled() || ! current_user_can( 'view_order', $order_id ) ) {
            return;
        }

        printf( '<section class="woocommerce-shopeame-invoices" id="%s">', esc_attr( self::ACCOUNT_ANCHOR . absint( $order_id ) ) );
        echo '<h2 class="woocommerce-column__title">' . esc_html__( 'Invoices', 'shopeame' ) . '</h2>';
        self::render_widget( absint( $order_id ), true );
        echo '</section>';
    }

    /**
     * Encola el script y los estilos en Mi cuenta para los clientes con sesión iniciada.
     *
     * @return void
     */
    public static function enqueue_front_assets() {
        if ( ! is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() || ! self::customers_enabled() ) {
            return;
        }

        wp_enqueue_style(
            'shopeame-orders',
            plugins_url( 'assets/shopeame-orders.css', SHOPEAME_PLUGIN_FILE ),
            array(),
            SHOPEAME_VERSION
        );
        wp_enqueue_script(
            'shopeame-orders',
            plugins_url( 'assets/shopeame-orders.js', SHOPEAME_PLUGIN_FILE ),
            array(),
            SHOPEAME_VERSION,
            true
        );
        self::localize_script( self::CUSTOMER_AJAX_ACTION, wp_create_nonce( self::NONCE_CUSTOMER ), true );
    }

    /**
     * Pasa al script la URL de AJAX, la acción, el nonce y los textos traducidos.
     *
     * @param string $action      Acción AJAX que devuelve las facturas.
     * @param string $nonce       Nonce de la acción.
     * @param bool   $for_customer Si el script se usa en Mi cuenta.
     * @return void
     */
    private static function localize_script( $action, $nonce, $for_customer ) {
        wp_localize_script(
            'shopeame-orders',
            'shopeameOrders',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'action'  => $action,
                'nonce'   => $nonce,
                'i18n'    => array(
                    'loading'  => __( 'Loading invoices…', 'shopeame' ),
                    'empty'    => $for_customer
                        ? __( 'This order has no invoices yet.', 'shopeame' )
                        : __( 'This order has no invoices in the ERP yet.', 'shopeame' ),
                    'error'    => __( 'The invoices could not be loaded.', 'shopeame' ),
                    'refund'   => __( 'Corrective', 'shopeame' ),
                    /* translators: %s: code of the invoice that the corrective invoice rectifies. */
                    'refundOf' => __( 'Corrective invoice of %s', 'shopeame' ),
                    'download' => __( 'Download PDF', 'shopeame' ),
                    'reload'   => __( 'Reload', 'shopeame' ),
                ),
            )
        );
    }

    /**
     * Devuelve por AJAX las facturas de un pedido con sus enlaces de descarga.
     *
     * @return void
     */
    public static function ajax_list() {
        check_ajax_referer( self::NONCE_LIST, 'nonce' );
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to view the invoices.', 'shopeame' ) ), 403 );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
        if ( ! $order_id || ! wc_get_order( $order_id ) ) {
            wp_send_json_error( array( 'message' => __( 'The order does not exist.', 'shopeame' ) ), 404 );
        }

        self::send_invoices_json( $order_id, false );
    }

    /**
     * Devuelve por AJAX al cliente las facturas de uno de sus pedidos (Mi cuenta).
     *
     * WooCommerce concede la capacidad view_order solo al cliente dueño del pedido.
     *
     * @return void
     */
    public static function ajax_customer_list() {
        check_ajax_referer( self::NONCE_CUSTOMER, 'nonce' );
        if ( ! self::customers_enabled() ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to view the invoices.', 'shopeame' ) ), 403 );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
        if ( ! $order_id || ! wc_get_order( $order_id ) || ! current_user_can( 'view_order', $order_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to view the invoices.', 'shopeame' ) ), 403 );
        }

        self::send_invoices_json( $order_id, true );
    }

    /**
     * Pide a shopea.me las facturas de un pedido y las envía como respuesta JSON, con sus enlaces de
     * descarga. Los permisos se comprueban antes de llamarla.
     *
     * @param int  $order_id     Identificador del pedido.
     * @param bool $for_customer Si la pide el cliente: los errores se muestran sin detalles del ERP.
     * @return void
     */
    private static function send_invoices_json( $order_id, $for_customer ) {
        if ( ! shopeame_is_linked() ) {
            wp_send_json_error( array( 'message' => __( 'The store is not linked with shopea.me.', 'shopeame' ) ), 409 );
        }

        $result = shopeame_post_webhook(
            array(
                'event'    => 'order_invoices',
                'shop'     => get_option( SHOPEAME_OPTION_SHOP_UUID, '' ),
                'token'    => get_option( SHOPEAME_OPTION_WEBHOOK_TOKEN, '' ),
                'order_id' => $order_id,
            ),
            self::TIMEOUT
        );

        if ( ! $result['ok'] || ! isset( $result['data']['invoices'] ) || ! is_array( $result['data']['invoices'] ) ) {
            self::log_error( 'order_invoices', $order_id, $result['code'], self::result_detail( $result ) );
            wp_send_json_error( array( 'message' => self::error_message( $result['code'], self::result_detail( $result ), $for_customer ) ), 502 );
        }

        $invoices = array();
        foreach ( $result['data']['invoices'] as $invoice ) {
            if ( ! is_array( $invoice ) || empty( $invoice['id'] ) ) {
                continue;
            }

            $invoices[] = array(
                'code'      => sanitize_text_field( (string) ( $invoice['code'] ?? '' ) ),
                'date'      => self::format_date( (string) ( $invoice['date'] ?? '' ) ),
                'total'     => self::format_total( (float) ( $invoice['total'] ?? 0 ), (string) ( $invoice['currency'] ?? '' ) ),
                'is_refund' => ! empty( $invoice['is_refund'] ),
                'refund_of' => sanitize_text_field( (string) ( $invoice['refund_of'] ?? '' ) ),
                'url'       => self::download_url( $order_id, absint( $invoice['id'] ) ),
            );
        }

        wp_send_json_success( array( 'invoices' => $invoices ) );
    }

    /**
     * Descarga el PDF de una factura a través de shopea.me, sin guardarlo en WordPress.
     *
     * La usan el gestor de la tienda y el cliente dueño del pedido. shopea.me solo entrega facturas que
     * pertenecen al pedido indicado de esta tienda.
     *
     * @return void
     */
    public static function download() {
        check_admin_referer( self::NONCE_DOWNLOAD );

        $order_id   = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
        $invoice_id = isset( $_GET['invoice_id'] ) ? absint( wp_unslash( $_GET['invoice_id'] ) ) : 0;
        if ( ! $order_id || ! $invoice_id || ! wc_get_order( $order_id ) ) {
            wp_die( esc_html__( 'The order does not exist.', 'shopeame' ), 404 );
        }

        // el gestor de la tienda descarga cualquier factura; el cliente, solo las de sus pedidos
        $is_manager  = current_user_can( self::CAPABILITY );
        $is_customer = self::customers_enabled() && current_user_can( 'view_order', $order_id );
        if ( ! $is_manager && ! $is_customer ) {
            wp_die( esc_html__( 'You are not allowed to download the invoices.', 'shopeame' ), 403 );
        }

        if ( ! shopeame_is_linked() ) {
            wp_die( esc_html__( 'The store is not linked with shopea.me.', 'shopeame' ), 409 );
        }

        $response = wp_remote_post(
            shopeame_server_url() . '/WebHooks',
            array(
                'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
                'body'    => wp_json_encode(
                    array(
                        'event'      => 'invoice_pdf',
                        'shop'       => get_option( SHOPEAME_OPTION_SHOP_UUID, '' ),
                        'token'      => get_option( SHOPEAME_OPTION_WEBHOOK_TOKEN, '' ),
                        'order_id'   => $order_id,
                        'invoice_id' => $invoice_id,
                    )
                ),
                'timeout' => self::TIMEOUT,
            )
        );

        $code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
        $body = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );
        if ( $code !== 200 || strpos( $body, '%PDF' ) !== 0 ) {
            $data   = json_decode( $body, true );
            $detail = is_wp_error( $response ) ? $response->get_error_message() : ( is_array( $data ) && isset( $data['error'] ) && is_string( $data['error'] ) ? $data['error'] : '' );
            self::log_error( 'invoice_pdf', $order_id, $code, $detail );
            wp_die(
                esc_html( self::error_message( $code, $detail, ! $is_manager ) ),
                esc_html__( 'Invoice download', 'shopeame' ),
                array(
                    'response'  => 502,
                    'back_link' => true,
                )
            );
        }

        $filename = self::filename_from_header( (string) wp_remote_retrieve_header( $response, 'content-disposition' ), $invoice_id );

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $body ) );
        header( 'X-Content-Type-Options: nosniff' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenido binario del PDF, comprobado arriba que empieza por %PDF.
        echo $body;
        exit;
    }

    /**
     * Pinta el contenedor que el script rellena con las facturas del pedido.
     *
     * Lleva la clase no-link para que el clic no abra el pedido: el listado de WooCommerce abre el
     * pedido al pulsar en cualquier parte de la fila salvo en enlaces, botones y elementos no-link.
     *
     * @param int  $order_id Identificador del pedido.
     * @param bool $autoload Si las facturas se cargan al abrir la página (ficha del pedido y Mi cuenta).
     * @return void
     */
    private static function render_widget( $order_id, $autoload ) {
        printf(
            '<div class="shopeame-invoices no-link%1$s" data-order-id="%2$d"%3$s>',
            $autoload ? ' shopeame-invoices--box' : '',
            absint( $order_id ),
            $autoload ? ' data-autoload="1"' : ''
        );
        if ( ! $autoload ) {
            printf(
                '<button type="button" class="button button-small shopeame-invoices__toggle" aria-expanded="false">%s</button>',
                esc_html__( 'Invoices', 'shopeame' )
            );
        }
        echo '<div class="shopeame-invoices__panel"' . ( $autoload ? '' : ' hidden' ) . ' aria-live="polite"></div>';
        echo '</div>';
    }

    /**
     * Devuelve la URL firmada de descarga de una factura.
     *
     * @param int $order_id   Identificador del pedido.
     * @param int $invoice_id Identificador de la factura en el ERP.
     * @return string
     */
    private static function download_url( $order_id, $invoice_id ) {
        // no usamos wp_nonce_url(): escapa la URL para HTML (&amp;) y el script la asigna tal cual
        $url = add_query_arg(
            array(
                'action'     => self::DOWNLOAD_ACTION,
                'order_id'   => $order_id,
                'invoice_id' => $invoice_id,
                '_wpnonce'   => wp_create_nonce( self::NONCE_DOWNLOAD ),
            ),
            admin_url( 'admin-post.php' )
        );

        return esc_url_raw( $url );
    }

    /**
     * Traduce un error de shopea.me a un mensaje para el usuario.
     *
     * @param int    $code         Código HTTP de la respuesta (0 si no hubo conexión).
     * @param string $detail       Código de error que devuelve shopea.me.
     * @param bool   $for_customer Si el mensaje es para el cliente: sin detalles del ERP ni de shopea.me.
     * @return string
     */
    private static function error_message( $code, $detail, $for_customer = false ) {
        if ( $for_customer ) {
            return __( 'The invoices are not available right now. Try again later.', 'shopeame' );
        } elseif ( $code === 0 ) {
            return __( 'Could not connect with shopea.me. Try again in a few minutes.', 'shopeame' );
        } elseif ( $detail === 'unknown-event' ) {
            return __( 'shopea.me does not support invoice downloads yet.', 'shopeame' );
        } elseif ( $detail === 'erp-offline' ) {
            return __( 'Your ERP is not available right now. Try again later.', 'shopeame' );
        } elseif ( $detail === 'erp-not-supported' ) {
            return __( 'Your ERP does not allow downloading invoices. Update FacturaScripts and the Shopeame plugin.', 'shopeame' );
        } elseif ( $detail === 'invoice-not-found' ) {
            return __( 'The invoice no longer belongs to this order. Reload the invoices.', 'shopeame' );
        } elseif ( $code === 401 || $code === 410 ) {
            return __( 'The store is not linked with shopea.me.', 'shopeame' );
        }

        return __( 'The invoices could not be loaded. Try again in a few minutes.', 'shopeame' );
    }

    /**
     * Devuelve el nombre del archivo que indica shopea.me, o uno genérico si no lo indica.
     *
     * @param string $header     Cabecera Content-Disposition de la respuesta.
     * @param int    $invoice_id Identificador de la factura en el ERP.
     * @return string
     */
    private static function filename_from_header( $header, $invoice_id ) {
        $filename = '';
        if ( preg_match( '/filename="?([^";]+)"?/i', $header, $matches ) ) {
            $filename = sanitize_file_name( $matches[1] );
        }

        if ( $filename === '' || strtolower( substr( $filename, -4 ) ) !== '.pdf' ) {
            $filename = 'invoice-' . absint( $invoice_id ) . '.pdf';
        }

        return $filename;
    }

    /**
     * Da formato a la fecha de una factura con el formato de fechas de WooCommerce.
     *
     * @param string $date Fecha en formato Y-m-d.
     * @return string
     */
    private static function format_date( $date ) {
        $time = strtotime( $date );
        return $time ? date_i18n( wc_date_format(), $time ) : '';
    }

    /**
     * Da formato al total de una factura con su divisa, como texto plano.
     *
     * @param float  $total    Total de la factura.
     * @param string $currency Código de la divisa (vacío para usar la de la tienda).
     * @return string
     */
    private static function format_total( $total, $currency ) {
        $args = preg_match( '/^[A-Z]{3}$/', $currency ) ? array( 'currency' => $currency ) : array();
        return html_entity_decode( wp_strip_all_tags( wc_price( $total, $args ) ), ENT_QUOTES, 'UTF-8' );
    }

    /**
     * Registra un fallo en el registro de WooCommerce (fuente «shopeame»).
     *
     * @param string $event    Evento enviado a shopea.me.
     * @param int    $order_id Identificador del pedido.
     * @param int    $code     Código HTTP de la respuesta.
     * @param string $detail   Detalle del error.
     * @return void
     */
    private static function log_error( $event, $order_id, $code, $detail ) {
        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        wc_get_logger()->error(
            sprintf( 'Error sending %s for order %d (HTTP %d) %s', $event, absint( $order_id ), $code, $detail ),
            array( 'source' => 'shopeame' )
        );
    }

    /**
     * Devuelve el detalle de error de una respuesta de shopeame_post_webhook().
     *
     * @param array $result Resultado de shopeame_post_webhook().
     * @return string
     */
    private static function result_detail( array $result ) {
        if ( isset( $result['error'] ) ) {
            return (string) $result['error'];
        }

        return isset( $result['data']['error'] ) && is_string( $result['data']['error'] ) ? $result['data']['error'] : '';
    }
}
