# 0001. Adaptación a las directrices de WordPress.org

- **Fecha**: 02/11/2026
- **Aprobada por**: el usuario, al pedir revisar el plugin para publicarlo en el directorio de WordPress.org.
- **Estado**: el plugin ya no se publica en el directorio ([decisión 0003](0003-distribucion-desde-github-con-actualizaciones.md)),
  pero todo lo de esta decisión se mantiene como buena práctica.

## Contexto

Antes de enviarlo había que revisar la FAQ y las directrices del directorio y pasar Plugin Check. La versión anterior
daba 6 errores y 12 advertencias: sin `readme.txt` (el `README.md` en español no sirve), sin licencia en la cabecera,
`Domain Path` a una carpeta inexistente, `error_log()`, datos de `$_POST` leídos fuera de la función que comprueba el
nonce y consultas directas sin justificar. Además, la revisión humana rechaza desactivar TLS y los nombres genéricos.

## Decisión

- `readme.txt` en inglés con la sección **External services** y versión `1.0.0` en cabecera, constante y
  `Stable tag`.
- Se quita `SHOPEAME_DEV` (que ponía `sslverify => false`); la verificación TLS va siempre activa. Se mantiene
  `SHOPEAME_SERVER_URL` para apuntar a otro servidor en desarrollo.
- Las opciones `shop_uuid` y `webhook_token` pasan a `shopeame_shop_uuid` y `shopeame_webhook_token`;
  `shopeame_maybe_upgrade()` migra las antiguas una vez (solo si existen las dos) y guarda `shopeame_db_version`.
- `handleActions()` comprueba el nonce y `manage_options` antes de leer cualquier dato y pasa los valores ya saneados
  a cada acción.
- Las credenciales recibidas del servidor se validan por formato antes de guardarlas.
- Los errores de envío van al registro de WooCommerce (`wc_get_logger()`, fuente `shopeame`).
- Las consultas a `woocommerce_api_keys` se mantienen, con `phpcs:ignore` justificado: WooCommerce no tiene API
  pública para crear claves y él mismo usa `$wpdb`.
- `uninstall.php` borra las opciones y las claves de la API creadas por el plugin.
- Se declara compatibilidad con HPOS y con los bloques de carrito y pago, y `Requires Plugins: woocommerce`.

## Alternativas descartadas

- Mantener `SHOPEAME_DEV` condicionado a una constante: la revisión marca cualquier `sslverify => false`. Para un
  certificado local autofirmado se usa un mu-plugin local con el filtro `https_ssl_verify`, fuera del repositorio
  (con la CA de ServBay ni siquiera hace falta).
- Crear las claves con la API REST o con `WC_Auth`: el flujo de autorización de WooCommerce exige redirigir al usuario
  y no permite generar el par desde la propia tienda sin intervención.

## Resultado

Plugin Check sin errores ni advertencias y prueba de extremo a extremo contra shopea.localhost en los dos sentidos.
