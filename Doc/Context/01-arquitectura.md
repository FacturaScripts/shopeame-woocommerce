# 1. Arquitectura

## 1.1 Archivos

| Archivo | Qué hace |
|---|---|
| `shopeame.php` | Cabecera del plugin, constantes `SHOPEAME_VERSION` y `SHOPEAME_PLUGIN_FILE`, carga de `includes/` y registro de los hooks |
| `includes/shopeame_helpers.php` | Constantes de las opciones, URL del servidor, migración (`shopeame_maybe_upgrade`), envío de eventos (`shopeame_post_webhook`, `shopeame_send_order_event`), guardado y borrado de credenciales y claves de la API |
| `includes/shopeame_link_endpoint.php` | Endpoint REST `POST /wp-json/shopeame/v1/link`, firmado con HMAC |
| `includes/shopeame_menu.php` | Clase `shopeame_menu`: menú **Shopeame**, pantalla de vinculación y sus acciones |
| `includes/shopeame_nuevo_pedido.php`, `includes/shopeame_modificar_pedido.php` | Avisos de pedido nuevo y modificado |
| `includes/shopeame_updater.php` | Actualizaciones desde GitHub: constantes `SHOPEAME_UPDATE_URI` y `SHOPEAME_PLUGIN_SLUG`, lectura del `info.json` de la última release y filtros `update_plugins_github.com` y `plugins_api` (ver [4. Distribución y actualizaciones](04-distribucion-y-actualizaciones.md)) |
| `includes/shopeame_facturas.php` | Clase `shopeame_facturas`: columna y cuadro **Invoices** de los pedidos, AJAX con la lista de facturas y descarga del PDF (ver [5. Facturas](05-facturas.md)) |
| `uninstall.php` | Borra opciones y claves de la API al eliminar el plugin |
| `assets/shopeame-admin.css`, `assets/shopeame-admin.js` | Estilos de la pantalla y botones «Copiar» y confirmaciones |
| `assets/shopeame-orders.css`, `assets/shopeame-orders.js` | Desplegable de facturas del listado y cuadro de la ficha del pedido |
| `assets/shopeame-menu-icon.svg` | Logo de Shopeame (la «S» con líneas de velocidad) para el menú lateral. `shopeame_menu::MENU_ICON` guarda este SVG ya codificado en base64 como `data:image/svg+xml;base64,…`, monocromo para que WordPress lo recoloree con el esquema del administrador; se codifica de antemano porque Plugin Check avisa de `base64_encode()` y de `file_get_contents()`. Si se cambia el SVG, hay que regenerar la constante |
| `readme.txt` | Descripción en el formato estándar de WordPress (en inglés): requisitos, **External services** y `Changelog`, del que salen las notas de la release y el `info.json` |
| `languages/` | Plantilla, traducción al español y su `.mo`, que el plugin carga (ver [decisión 0004](Decisiones/0004-traducciones-incluidas-en-el-plugin.md)) |

La pantalla imita la estructura y los textos de la app de Shopify de Shopeame, que es la base visual común de los
conectores de Shopify, WooCommerce y PrestaShop.

## 1.2 Opciones guardadas

| Opción | Contenido |
|---|---|
| `shopeame_shop_uuid` | Identificador de la tienda en shopea.me (13 caracteres hexadecimales, de `uniqid()`) |
| `shopeame_webhook_token` | Token de la tienda en shopea.me (32 caracteres alfanuméricos) |
| `shopeame_api_user` / `shopeame_api_key` | Consumer key y consumer secret de la API REST de WooCommerce generadas por el plugin |
| `shopeame_customer_invoices` | `yes` (por defecto, si no existe) o `no`: si los clientes pueden descargar sus facturas desde Mi cuenta. Se cambia en la sección **Invoices** de la página Shopeame |
| `shopeame_db_version` | Versión con la que se ejecutó por última vez `shopeame_maybe_upgrade()` |

La tienda está vinculada cuando hay `shopeame_shop_uuid` y `shopeame_webhook_token` (`shopeame_is_linked()`).

## 1.3 Hooks

| Hook | Función |
|---|---|
| `plugins_loaded` | `shopeame_maybe_upgrade` |
| `init` | `shopeame_load_textdomain` (traducciones de `languages/`) |
| `update_plugins_github.com`, `plugins_api` | `shopeame_filter_update_plugins` y `shopeame_filter_plugins_api` (versión nueva y ventana «Ver detalles») |
| `before_woocommerce_init` | `shopeame_declare_wc_compatibility` (HPOS y bloques de carrito y pago) |
| `admin_menu`, `admin_enqueue_scripts` | Menú y recursos de la pantalla (solo en `toplevel_page_shopeame`); la pantalla pinta además el aviso de versión nueva y la versión instalada |
| `rest_api_init` | Registro del endpoint de vinculación |
| `woocommerce_new_order`, `woocommerce_update_order` | Avisos de pedido a shopea.me |
| `woocommerce_shop_order_list_table_columns`, `woocommerce_shop_order_list_table_custom_column` (HPOS) y `manage_edit-shop_order_columns`, `manage_shop_order_posts_custom_column` (listado clásico) | Columna **Invoices** del listado de pedidos |
| `add_meta_boxes` | Cuadro **Invoices** de la ficha del pedido |
| `woocommerce_my_account_my_orders_actions`, `woocommerce_view_order`, `wp_enqueue_scripts` | Acción y sección **Invoices** de Mi cuenta y sus recursos |
| `wp_ajax_shopeame_order_invoices`, `wp_ajax_shopeame_customer_order_invoices`, `admin_post_shopeame_invoice_pdf` | Lista de facturas (gestor y cliente) y descarga del PDF |

## 1.4 Relación con ShopeameServer

El servidor es el plugin ShopeameServer de FacturaScripts (`../facturascripts/Plugins/ShopeameServer`):

- `Controller/WebHooks.php` recibe en `POST /WebHooks` los eventos del plugin: `register_woocommerce` (autenticado con
  el UUID y el token del ERP), `check_shop`, `unlink_shop`, `new_order`, `update_order`, `order_invoices` e
  `invoice_pdf` (autenticados con el UUID y el token de la tienda).
- `Lib/FacturaScripts/OrderInvoiceFinder.php` busca en el ERP las facturas de un pedido para `order_invoices` e
  `invoice_pdf`.
- `Controller/ConnectShop.php` es la pantalla «conectar tienda» de shopea, donde el usuario pega la URL y las claves.
- `Lib/Shopeame/ShopLinkNotifier.php` entrega las credenciales de la tienda recién creada al endpoint
  `/wp-json/shopeame/v1/link` del plugin.
- ShopeameServer descarga los pedidos y lee la configuración de la tienda (por ejemplo la zona horaria) por la API
  REST de WooCommerce con las claves de solo lectura.

Cualquier cambio de eventos, campos o firma se hace en los dos proyectos en el mismo trabajo.
