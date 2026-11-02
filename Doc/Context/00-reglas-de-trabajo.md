# 0. Reglas de trabajo

## 0.1 Documentación oficial

Cada tarea se contrasta antes con la documentación oficial, no con la memoria:

- WordPress: https://developer.wordpress.org (manual de plugins, seguridad, internacionalización y directrices del
  directorio en https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).
- WooCommerce: https://developer.woocommerce.com/docs/ (API REST, HPOS, registro de errores).

## 0.2 Convenciones del código

- Todo lo global lleva el prefijo del plugin: funciones `shopeame_*`, clases `shopeame_*`, constantes `SHOPEAME_*`,
  opciones `shopeame_*`, espacio REST `shopeame/v1`. Nada de nombres genéricos (ver
  [decisión 0001](Decisiones/0001-adaptacion-a-wordpress-org.md)).
- Cada archivo PHP empieza con la comprobación de `ABSPATH` (o `WP_UNINSTALL_PLUGIN` en `uninstall.php`).
- Lo que se pinta se escapa (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`) y lo que llega se sanea
  (`sanitize_text_field`, `sanitize_key`, `wp_unslash`). Los formularios llevan nonce y la acción comprueba el nonce y
  la capacidad `manage_options` **antes** de leer ningún otro dato de `$_POST`.
- Las peticiones salen con la API HTTP de WordPress (`wp_remote_post`) y nunca desactivan la verificación TLS.
- Las consultas directas a la base de datos solo se usan con la tabla de claves de la API de WooCommerce, que no tiene
  API pública, y llevan su `phpcs:ignore` justificado.
- Comentarios y Docblocks en español; los textos visibles, en inglés y traducibles (ver 0.3).

## 0.3 Textos y traducciones

- Toda cadena visible va con `__()`, `esc_html__()`, `esc_html_e()`, `esc_attr_e()`... y el dominio `shopeame`, con un
  comentario `/* translators: */` si lleva marcadores.
- Al cambiar textos, regenera `languages/shopeame.pot` y actualiza `languages/shopeame-es_ES.po` (comandos en el
  `README.md`). Esa carpeta no va en el zip: sirve para importar el español en translate.wordpress.org (ver
  [decisión 0002](Decisiones/0002-textos-en-ingles-sin-traducciones-incluidas.md)).

## 0.4 Versiones

Una versión nueva cambia el número en tres sitios a la vez: la cabecera `Version` y la constante `SHOPEAME_VERSION` de
`shopeame.php` y el `Stable tag` de `readme.txt`, más su entrada en el `Changelog`. Al pasar de versión se ejecuta
`shopeame_maybe_upgrade()` una vez: cualquier migración de datos va ahí.

## 0.5 Entorno local

- `~/Trabajos/deploy.sh` copia el repositorio a `/Applications/ServBay/www/wordpress/wp-content/plugins/shopeame`
  (https://wp.localhost) con las mismas exclusiones que el zip (`.distignore` y archivos ocultos), así que la copia
  local tiene exactamente los archivos que se publican. Si se añade al repositorio algo que no debe publicarse, va en
  `.distignore` y deja de copiarse en los dos sitios.
- Para probar contra la instalación local de ShopeameServer (https://shopea.localhost), añade temporalmente en el
  `wp-config.php` de wp.localhost `define( 'SHOPEAME_SERVER_URL', 'https://shopea.localhost' );`. El certificado de
  ServBay se acepta sin desactivar la verificación TLS. Al terminar, desvincula la tienda, quita la constante y borra
  las claves de prueba: si no, la tienda local enviaría sus pedidos a shopea.me con credenciales de pruebas.

## 0.6 Antes de dar un cambio por terminado

1. `php -l` de los archivos tocados.
2. **Herramientas → Comprobar plugins** (Plugin Check) en wp.localhost, con todas las categorías: cero errores y cero
   advertencias. La copia de deploy.sh tiene los mismos archivos que el zip, así que la comprobación vale para lo que
   se publica.
3. Si el cambio toca la vinculación o los pedidos, la prueba de extremo a extremo contra shopea.localhost en los dos
   sentidos (desde WordPress con el UUID y el token del ERP, y desde la pantalla «conectar tienda» de shopea).
4. Actualizar `Doc/Context`, las páginas de `Doc/User` o `Doc/Dev` afectadas y el `readme.txt` si cambia lo que se
   envía a shopea.me (sección **External services**).
