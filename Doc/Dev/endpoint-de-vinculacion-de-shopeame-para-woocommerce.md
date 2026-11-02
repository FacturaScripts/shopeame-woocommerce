El plugin expone un endpoint de la API REST de WordPress para que shopea.me (el plugin **ShopeameServer**) le entregue
las credenciales de la tienda cuando se conecta desde la pantalla de shopea.me con la URL y las claves de la API. Así
la tienda queda vinculada en WordPress sin que el usuario copie nada de vuelta.

## 1. La petición

```text
POST https://<tienda>/wp-json/shopeame/v1/link
Content-Type: application/json; charset=utf-8
X-Shopeame-Timestamp: <segundos Unix>
X-Shopeame-Signature: <HMAC-SHA256 en hexadecimal>
```

Cuerpo:

```json
{
    "event": "link_shop",
    "shop_uuid": "6a1b2c3d4e5f6",
    "webhook_token": "AbCdEfGhIjKlMnOpQrStUvWxYz012345"
}
```

- `shop_uuid`: 13 caracteres hexadecimales en minúscula.
- `webhook_token`: 32 caracteres alfanuméricos.

## 2. La firma

La firma es el HMAC-SHA256 de la marca de tiempo, un punto y el cuerpo exacto de la petición, con la **consumer
secret** de la API de WooCommerce que generó el plugin (la que el usuario pegó en shopea.me). Es el único secreto que
ya comparten la tienda y shopea.me antes de vincular.

```php
$timestamp = (string) time();
$body = json_encode(['event' => 'link_shop', 'shop_uuid' => $uuid, 'webhook_token' => $token]);
$signature = hash_hmac('sha256', $timestamp . '.' . $body, $consumerSecret);
```

El plugin rechaza la petición con **401** (`shopeame_unauthorized`) si:

- falta la cabecera de la marca de tiempo o no son solo dígitos;
- la firma no tiene 64 caracteres hexadecimales o no coincide (se compara con `hash_equals()`);
- la marca de tiempo se aleja más de 300 segundos de la hora del servidor (constante
  `SHOPEAME_LINK_TIMESTAMP_TOLERANCE`);
- el plugin todavía no tiene consumer secret guardada.

## 3. Las respuestas

| Código | Cuerpo | Cuándo |
|---|---|---|
| 200 | `{"success": true}` | Credenciales guardadas: la tienda queda vinculada |
| 400 | error `shopeame_invalid_json` | El cuerpo no es JSON |
| 401 | error `shopeame_unauthorized` | Firma o marca de tiempo no válidas |
| 422 | error `shopeame_invalid_payload` | `event` no es `link_shop` o el UUID o el token no tienen el formato esperado |

Las credenciales se guardan en las opciones `shopeame_shop_uuid` y `shopeame_webhook_token`; si la tienda ya estaba
vinculada, se sustituyen.

## 4. Quién lo llama

En ShopeameServer, la clase **`ShopLinkNotifier`** (`Lib/Shopeame/ShopLinkNotifier.php`) lo llama al
guardar una tienda WooCommerce desde la pantalla de conectar tienda. Solo lo intenta si la URL de la tienda usa HTTPS,
espera como máximo 4 segundos y no sigue redirecciones. Si falla, la tienda sigue creada en shopea.me y el usuario puede
terminar la vinculación desde WordPress con el UUID y el token del ERP.

⚠️ Cualquier cambio en el cuerpo, las cabeceras o la firma se hace a la vez en este plugin y en `ShopLinkNotifier`.

## Ver también

- [Eventos que envía Shopeame para WooCommerce](eventos-que-envia-shopeame-para-woocommerce.md)
- [Cómo desarrollar y publicar Shopeame para WooCommerce](como-desarrollar-y-publicar-shopeame-para-woocommerce.md)
