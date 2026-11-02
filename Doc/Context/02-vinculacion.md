# 2. Vinculación con shopea.me

## 2.1 Claves de la API de WooCommerce

`shopeame_generate_api_keys()` crea una clave de la API REST con permiso **read** y descripción `Shopeame` en la
tabla `{prefijo}woocommerce_api_keys`, igual que hace WooCommerce en `WC_AJAX::update_api_key()`: no hay función
pública para hacerlo. Antes borra las claves `Shopeame` anteriores para no acumularlas. WooCommerce guarda la consumer
key con hash, así que el plugin conserva el par en claro en sus opciones: shopea.me lo necesita y la pantalla lo
muestra para copiarlo. Se crea con el usuario actual (`get_current_user_id()`).

## 2.2 Opción 1: copiar los datos en shopea.me

1. El usuario genera las claves y copia la URL de la tienda, la consumer key y la consumer secret.
2. En shopea, la pantalla «conectar tienda» (`ConnectShop`) comprueba la tienda, crea el enlace y llama a
   `ShopLinkNotifier::notify()`.
3. El notificador envía a `POST /wp-json/shopeame/v1/link` el cuerpo
   `{"event":"link_shop","shop_uuid":"…","webhook_token":"…"}` con las cabeceras `X-Shopeame-Timestamp` (segundos
   Unix) y `X-Shopeame-Signature` = `hash_hmac('sha256', timestamp + '.' + cuerpo, consumer secret)`.
4. `shopeame_verify_link_request()` exige una firma hexadecimal de 64 caracteres, la consumer secret guardada y una
   marca de tiempo con menos de 300 segundos de diferencia (`SHOPEAME_LINK_TIMESTAMP_TOLERANCE`), y compara con
   `hash_equals()`. `shopeame_receive_link()` valida el formato de las credenciales y las guarda.

El envío automático es opcional: el servidor solo lo intenta si la URL es HTTPS y espera 4 segundos sin seguir
redirecciones. Si falla, el usuario sigue teniendo la vinculación manual (opción 2) o puede volver a intentarlo.

La firma usa la consumer secret porque es el único secreto que ya comparten la tienda y shopea.me antes de vincular; así
nadie más puede escribir credenciales en la tienda.

## 2.3 Opción 2: vincular con el UUID y el token del ERP

`actionLink()` genera las claves si faltan y envía a `POST /WebHooks` el evento `register_woocommerce` con el UUID y el
token del ERP y `new_shop` (`url`, `api_user`, `api_key`). El servidor:

- crea la tienda y responde `shop-registered`;
- si ya existe una tienda activa del mismo ERP con la misma URL, devuelve sus credenciales sin cambiar nada
  (`shop-already-exists`);
- si existe pero estaba desvinculada, la reactiva con las claves nuevas (`shop-reactivated`).

En los tres casos el plugin guarda `shop_uuid` y `webhook_token` si tienen el formato esperado
(`shopeame_save_link_credentials()`) y muestra el mensaje correspondiente.

## 2.4 Comprobar, desvincular y borrar datos

- **Comprobar conexión**: evento `check_shop`; el servidor responde `exists: true` si la tienda y su token son válidos.
- **Desvincular**: evento `unlink_shop`, que en el servidor hace `softDelete()` de la tienda (estado `deleted`).
  El plugin borra sus credenciales locales aunque el servidor falle, para no quedarse a medias, y avisa con un aviso
  de advertencia si no hubo confirmación.
- **Borrar datos de la app**: solo borra las credenciales locales, sin llamar al servidor ni tocar las claves de la
  API. Sirve cuando la tienda ya no existe en shopea.me o se ha restaurado una copia de la web.

Las claves de la API de WooCommerce no se borran al desvincular (se reutilizan al volver a vincular); solo
`uninstall.php` las elimina.
