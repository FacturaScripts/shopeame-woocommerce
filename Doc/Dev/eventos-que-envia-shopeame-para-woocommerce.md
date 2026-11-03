El plugin se comunica con shopea.me enviando eventos JSON por `POST` a `<servidor>/WebHooks`, donde los recibe el
controlador **`WebHooks`** del plugin ShopeameServer (`Controller/WebHooks.php`). El servidor es `https://shopea.me` salvo
que se defina la constante `SHOPEAME_SERVER_URL`. Todos los envíos pasan por `shopeame_post_webhook()`
(`includes/shopeame_helpers.php`), que usa `wp_remote_post()` con 20 segundos de espera (10 en la comprobación al
abrir la página) y verificación TLS.

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

## 3. Opciones y constantes

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
