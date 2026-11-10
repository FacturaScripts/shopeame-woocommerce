El plugin se comunica con shopea.me enviando eventos JSON por `POST` a `<servidor>/WebHooks`, donde los recibe el
controlador **`WebHooks`** del plugin ShopeameServer (`Controller/WebHooks.php`). El servidor es `https://shopea.me` salvo
que se defina la constante `SHOPEAME_SERVER_URL`. Todos los envíos pasan por `shopeame_post_webhook()`
(`includes/shopeame_helpers.php`), que usa `wp_remote_post()` con 20 segundos de espera (10 en la comprobación al
abrir la página y 30 en los eventos de facturas) y verificación TLS. La descarga del PDF (`invoice_pdf`) usa
`wp_remote_post()` directamente porque la respuesta no es JSON.

## 1. Eventos de la vinculación

| Evento | Cuándo | Campos | Autenticación |
|---|---|---|---|
| `register_woocommerce` | Al pulsar **Vincular tienda** | `erp`, `token` y `new_shop` (`url`, `api_user`, `api_key`) | UUID y token del ERP |
| `check_shop` | Al abrir la página **Shopeame** y al pulsar **Comprobar conexión** | `shop`, `token` | UUID y token de la tienda |
| `unlink_shop` | Al pulsar **Desvincular tienda** | `shop`, `token` | UUID y token de la tienda |

Ejemplo de `register_woocommerce`:

```json
{
    "event": "register_woocommerce",
    "erp": "<UUID del ERP>",
    "token": "<token del ERP>",
    "new_shop": {
        "url": "https://mitienda.com",
        "api_user": "ck_…",
        "api_key": "cs_…"
    }
}
```

La respuesta trae `shop_uuid`, `webhook_token` y `message`: `shop-registered` (tienda nueva), `shop-already-exists`
(ya existía y está activa; no se cambia nada) o `shop-reactivated` (existía desvinculada y se reactiva con las claves
nuevas). `check_shop` responde `{"exists": true}` si la tienda está vinculada y `{"exists": false}` si está
desvinculada (`404`, `shop-unlinked`) o el token no es válido (`401`); `unlink_shop` da de baja la tienda en el
servidor.

## 2. Eventos de pedidos

`woocommerce_new_order` y `woocommerce_update_order` llaman a **`shopeame_send_order_event()`**, que envía:

```json
{
    "event": "new_order",
    "shop": "<UUID de la tienda>",
    "token": "<token de la tienda>",
    "order_id": 123
}
```

con `update_order` en las modificaciones. Si la tienda no está vinculada, no se envía nada. El servidor no descarga
el pedido en la petición: adelanta la sincronización de pedidos de la tienda y responde `orders-sync-scheduled`,
`orders-sync-pending`, `orders-sync-deferred` u `orders-sync-disabled`. Después descarga los pedidos por la API REST de
WooCommerce con las claves de solo lectura.

Los fallos se registran con `wc_get_logger()` con la fuente `shopeame`:

```text
Error sending new_order for order 123 (HTTP 401) unauthorized
```

El código 0 indica que no hubo respuesta (sin conexión o tiempo agotado).

## 3. Eventos de facturas

`includes/shopeame_facturas.php` (clase `shopeame_facturas`) añade la columna **Invoices** al listado de pedidos
(filtros `woocommerce_shop_order_list_table_columns` con HPOS y `manage_edit-shop_order_columns` en el listado clásico) y
el cuadro **Invoices** a la ficha del pedido (`add_meta_box` en `wc_get_page_screen_id( 'shop-order' )`). En Mi cuenta,
si la opción `shopeame_customer_invoices` vale `yes` (por defecto), añade la acción **Invoices** a cada pedido
(`woocommerce_my_account_my_orders_actions`, enlace a `#shopeame-invoices-<id>` de la página del pedido, que el script
convierte en desplegable) y la sección **Invoices** al ver un pedido (`woocommerce_view_order`). Las facturas no se
guardan en WordPress: se piden a shopea.me cada vez.

| Evento | Cuándo | Campos | Autenticación |
|---|---|---|---|
| `order_invoices` | Al pulsar **Invoices** en el listado o al abrir la ficha del pedido (AJAX `wp_ajax_shopeame_order_invoices`), y en Mi cuenta (AJAX `wp_ajax_shopeame_customer_order_invoices`) | `shop`, `token`, `order_id` | UUID y token de la tienda |
| `invoice_pdf` | Al pulsar el código de una factura (`admin-post.php?action=shopeame_invoice_pdf`, para gestores y clientes) | `shop`, `token`, `order_id`, `invoice_id` | UUID y token de la tienda |

Las acciones de WordPress comprueban primero el nonce y después los permisos: el gestor necesita `edit_shop_orders`; el
cliente, que la opción esté activa y la capacidad `view_order` del pedido, que WooCommerce solo concede al dueño del
pedido. A los clientes se les muestran los errores sin detalles del ERP.

`order_invoices` responde:

```json
{
    "success": true,
    "invoices": [
        {"id": 519, "code": "FAC0001A3", "date": "2026-11-05", "total": 166.98, "currency": "EUR", "paid": true, "is_refund": false, "refund_of": ""},
        {"id": 520, "code": "F0001R2", "date": "2026-11-05", "total": -24.2, "currency": "EUR", "paid": true, "is_refund": true, "refund_of": "FAC0001A3"}
    ]
}
```

El servidor (`OrderInvoiceFinder` de ShopeameServer) busca en el ERP las facturas con el `idshopeameorder` del pedido,
las que reciben líneas de sus pedidos y albaranes según `doctransformations` (así aparecen las facturas que agrupan
varios pedidos) y las rectificativas de todas ellas. Si el pedido no está en el ERP, la lista llega vacía.

`invoice_pdf` responde con el PDF (`application/pdf`, con el código de la factura en `Content-Disposition`). El servidor
solo lo entrega si la factura está entre las del pedido; si no, responde `404 invoice-not-found`. El plugin lo reenvía
al navegador como descarga (`attachment`) sin guardarlo.

Errores de los dos eventos:

| HTTP | `error` | Motivo |
|---|---|---|
| 400 | `unknown-event` | El servidor todavía no admite los eventos de facturas |
| 401 / 410 | `unauthorized` / `shop-unlinked` | Token incorrecto o tienda desvinculada |
| 404 | `invoice-not-found` | `invoice_pdf` con una factura que no es del pedido |
| 422 | `missing-order-id` | Falta `order_id` |
| 501 | `erp-not-supported` | La API del ERP no tiene `exportarFacturaCliente` o `doctransformations` |
| 502 | `erp-error` | El ERP ha respondido con un error |
| 503 | `erp-offline` | El ERP está apagado o sin el plugin Shopeame |

Los fallos se registran con la fuente `shopeame`, igual que los eventos de pedidos.

## 4. Opciones y constantes

| Nombre | Tipo | Contenido |
|---|---|---|
| `shopeame_shop_uuid` | Opción | UUID de la tienda en shopea.me |
| `shopeame_webhook_token` | Opción | Token de la tienda |
| `shopeame_api_user`, `shopeame_api_key` | Opción | Consumer key y secret de la API de WooCommerce generadas por el plugin |
| `shopeame_db_version` | Opción | Versión con la que se migraron los datos |
| `SHOPEAME_SERVER_URL` | Constante de `wp-config.php` | URL del servidor; por defecto `https://shopea.me` |
| `SHOPEAME_VERSION` | Constante del plugin | Versión del plugin |

La tienda se considera vinculada cuando existen `shopeame_shop_uuid` y `shopeame_webhook_token`
(`shopeame_is_linked()`). Al eliminar el plugin, `uninstall.php` borra todas las opciones y las claves de la API con la
descripción `Shopeame`.

## Ver también

- [Endpoint de vinculación de Shopeame para WooCommerce](endpoint-de-vinculacion-de-shopeame-para-woocommerce.md)
- [Cómo desarrollar y publicar Shopeame para WooCommerce](como-desarrollar-y-publicar-shopeame-para-woocommerce.md)
