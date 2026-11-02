# 1. Arquitectura

## 1.1 Archivos

| Archivo | Qué hace |
|---|---|
| `shopeame.php` | Cabecera del plugin, constantes `SHOPEAME_VERSION` y `SHOPEAME_PLUGIN_FILE`, carga de `includes/` y registro de los hooks |
| `includes/shopeame_helpers.php` | Constantes de las opciones, URL del servidor, migración (`shopeame_maybe_upgrade`), envío de eventos (`shopeame_post_webhook`, `shopeame_send_order_event`), guardado y borrado de credenciales y claves de la API |
| `includes/shopeame_link_endpoint.php` | Endpoint REST `POST /wp-json/shopeame/v1/link`, firmado con HMAC |
| `includes/shopeame_menu.php` | Clase `shopeame_menu`: menú **Shopeame**, pantalla de vinculación y sus acciones |
| `includes/shopeame_nuevo_pedido.php`, `includes/shopeame_modificar_pedido.php` | Avisos de pedido nuevo y modificado |
| `uninstall.php` | Borra opciones y claves de la API al eliminar el plugin |
| `assets/shopeame-admin.css`, `assets/shopeame-admin.js` | Estilos de la pantalla y botones «Copiar» y confirmaciones |
| `assets/shopeame-menu-icon.svg` | Logo de Shopeame (la «S» con líneas de velocidad) para el menú lateral. `shopeame_menu::MENU_ICON` guarda este SVG ya codificado en base64 como `data:image/svg+xml;base64,…`, monocromo para que WordPress lo recoloree con el esquema del administrador; se codifica de antemano porque Plugin Check avisa de `base64_encode()` y de `file_get_contents()`. Si se cambia el SVG, hay que regenerar la constante |
| `readme.txt` | Ficha del directorio de WordPress.org (en inglés) |
| `languages/` | Plantilla y traducción al español para translate.wordpress.org; no va en el zip |

La pantalla imita la estructura y los textos de la app de Shopify de Shopeame, que es la base visual común de los
conectores de Shopify, WooCommerce y PrestaShop.

## 1.2 Opciones guardadas

| Opción | Contenido |
|---|---|
| `shopeame_shop_uuid` | Identificador de la tienda en shopea.me (13 caracteres hexadecimales, de `uniqid()`) |
| `shopeame_webhook_token` | Token de la tienda en shopea.me (32 caracteres alfanuméricos) |
| `shopeame_api_user` / `shopeame_api_key` | Consumer key y consumer secret de la API REST de WooCommerce generadas por el plugin |
| `shopeame_db_version` | Versión con la que se ejecutó por última vez `shopeame_maybe_upgrade()` |

La tienda está vinculada cuando hay `shopeame_shop_uuid` y `shopeame_webhook_token` (`shopeame_is_linked()`).

## 1.3 Hooks

| Hook | Función |
|---|---|
| `plugins_loaded` | `shopeame_maybe_upgrade` |
| `before_woocommerce_init` | `shopeame_declare_wc_compatibility` (HPOS y bloques de carrito y pago) |
| `admin_menu`, `admin_enqueue_scripts` | Menú y recursos de la pantalla (solo en `toplevel_page_shopeame`) |
| `rest_api_init` | Registro del endpoint de vinculación |
| `woocommerce_new_order`, `woocommerce_update_order` | Avisos de pedido a shopea.me |

## 1.4 Relación con ShopeameServer

El servidor es el plugin ShopeameServer de FacturaScripts (`../facturascripts/Plugins/ShopeameServer`):

- `Controller/WebHooks.php` recibe en `POST /WebHooks` los eventos del plugin: `register_woocommerce` (autenticado con
  el UUID y el token del ERP), `check_shop`, `unlink_shop`, `new_order` y `update_order` (autenticados con el UUID y el
  token de la tienda).
- `Controller/ConnectShop.php` es la pantalla «conectar tienda» de shopea, donde el usuario pega la URL y las claves.
- `Lib/Shopeame/ShopLinkNotifier.php` entrega las credenciales de la tienda recién creada al endpoint
  `/wp-json/shopeame/v1/link` del plugin.
- ShopeameServer descarga los pedidos y lee la configuración de la tienda (por ejemplo la zona horaria) por la API
  REST de WooCommerce con las claves de solo lectura.

Cualquier cambio de eventos, campos o firma se hace en los dos proyectos en el mismo trabajo.
