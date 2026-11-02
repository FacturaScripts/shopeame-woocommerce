Esta página explica cómo probar el plugin contra una instalación propia de ShopeameServer y cómo preparar una versión
para el directorio de plugins de WordPress.org.

## 1. Apuntar a otro servidor

Por defecto el plugin habla con `https://shopea.me`. Para probarlo contra otra instalación de ShopeameServer, se define
la constante en el `wp-config.php` de la tienda de pruebas:

```php
define( 'SHOPEAME_SERVER_URL', 'https://shopea.localhost' );
```

La verificación del certificado TLS está siempre activa: el directorio de WordPress.org no admite plugins que la
desactiven. Si el servidor de pruebas usa un certificado propio que WordPress no reconoce, se añade su autoridad de
certificación al sistema o, solo en esa instalación y fuera del plugin, se crea un plugin obligatorio
(`wp-content/mu-plugins/`) con:

```php
add_filter( 'https_ssl_verify', '__return_false' ); // solo en desarrollo local
```

Al terminar, se desvincula la tienda, se quita la constante y se borran las claves de prueba, para que la tienda no
envíe pedidos a shopea.me con credenciales de pruebas.

## 2. Textos y traducciones

- Las cadenas se escriben en inglés con las funciones de traducción de WordPress y el dominio `shopeame`, con un
  comentario `/* translators: */` cuando llevan marcadores.
- El plugin no incluye traducciones: WordPress las instala desde translate.wordpress.org.
- Tras cambiar textos, se regenera la plantilla y se actualiza la traducción al español de la carpeta `languages/`
  (que no va en el zip y sirve para importarla en translate.wordpress.org):

```bash
xgettext --language=PHP --from-code=UTF-8 --add-comments=translators: --keyword=__ --keyword=_e \
  --keyword=esc_html__ --keyword=esc_html_e --keyword=esc_attr__ --keyword=esc_attr_e \
  -o languages/shopeame.pot shopeame.php includes/*.php
msgmerge --update languages/shopeame-es_ES.po languages/shopeame.pot
```

Para ver la traducción en una instalación de pruebas antes de que exista el paquete de idioma, se compila en la
carpeta donde WordPress instala esos paquetes:

```bash
msgfmt -o wp-content/languages/plugins/shopeame-es_ES.mo languages/shopeame-es_ES.po
```

## 3. Publicar una versión

1. Se sube el número de versión en la cabecera `Version` y la constante `SHOPEAME_VERSION` de `shopeame.php` y en el
   `Stable tag` de `readme.txt`, y se añade su entrada al `Changelog`. Si hay datos que migrar, se hace en
   `shopeame_maybe_upgrade()`.
2. Se revisan `Tested up to` y `WC tested up to` contra las últimas versiones estables de WordPress y WooCommerce.
3. Si cambian los datos que se envían a shopea.me, se actualiza la sección **External services** de `readme.txt`.
4. Se pasa **Herramientas** → **Comprobar plugins** con todas las categorías: debe quedar sin errores ni
   advertencias.
5. Se genera el zip: una sola carpeta `shopeame` con los archivos del plugin y sin lo que lista `.distignore`
   (documentación, `README.md`, `AGENTS.md`, `CLAUDE.md`, `languages/` y archivos ocultos).
6. La primera vez se envía el zip desde la página de añadir plugins de WordPress.org; las versiones siguientes se
   publican por SVN (código en `trunk/`, versión en `tags/<versión>` e imágenes de la ficha en `assets/`).

## Ver también

- [Endpoint de vinculación de Shopeame para WooCommerce](endpoint-de-vinculacion-de-shopeame-para-woocommerce.md)
- [Eventos que envía Shopeame para WooCommerce](eventos-que-envia-shopeame-para-woocommerce.md)
