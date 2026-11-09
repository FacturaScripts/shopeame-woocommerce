Esta página explica cómo probar el plugin contra una instalación propia de ShopeameServer, cómo publicar una versión
en las releases de GitHub y cómo probar que las tiendas reciben la actualización. El plugin no está en el directorio de
WordPress.org.

## 1. Apuntar a otro servidor

Por defecto el plugin habla con `https://shopea.me`. Para probarlo contra otra instalación de ShopeameServer, se define
la constante en el `wp-config.php` de la tienda de pruebas:

```php
define( 'SHOPEAME_SERVER_URL', 'https://shopea.localhost' );
```

La verificación del certificado TLS está siempre activa, como exigen las directrices de WordPress.org que el plugin
mantiene. Si el servidor de pruebas usa un certificado propio que WordPress no reconoce, se añade su autoridad de
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
- La traducción al español va incluida en `languages/` y el plugin la carga con `load_plugin_textdomain()`: fuera del
  directorio de WordPress.org no hay paquetes de idioma.
- Tras cambiar textos, se regenera la plantilla, se traducen en el `.po` las cadenas nuevas (las que quedan con
  `msgstr ""`) y se compila el `.mo`, que también se sube al repositorio:

```bash
xgettext --language=PHP --from-code=UTF-8 --add-comments=translators: --keyword=__ --keyword=_e \
  --keyword=esc_html__ --keyword=esc_html_e --keyword=esc_attr__ --keyword=esc_attr_e \
  -o languages/shopeame.pot shopeame.php includes/*.php
msgmerge --update --backup=none languages/shopeame-es_ES.po languages/shopeame.pot
msgfmt -o languages/shopeame-es_ES.mo languages/shopeame-es_ES.po
```

Si la instalación de pruebas tiene un `wp-content/languages/plugins/shopeame-es_ES.mo` antiguo, WordPress lo usa en
lugar del del plugin y faltarán los textos nuevos: se borra.

## 3. Publicar una versión

1. Se sube el número de versión en la cabecera `Version` y la constante `SHOPEAME_VERSION` de `shopeame.php` y en el
   `Stable tag` de `readme.txt`, y se añade su entrada al `Changelog`. Si hay datos que migrar, se hace en
   `shopeame_maybe_upgrade()`.
2. Se revisan `Tested up to` y `WC tested up to` contra las últimas versiones estables de WordPress y WooCommerce.
3. Si cambian los datos que se envían a shopea.me, se actualiza la sección **External services** de `readme.txt`.
4. Se pasa **Herramientas** → **Comprobar plugins** con todas las categorías. Solo deben salir el error
   `plugin_updater_detected` (la cabecera `Update URI`) y la advertencia de `load_plugin_textdomain()`: son reglas de
   los plugins alojados en WordPress.org.
5. Se genera el zip: una sola carpeta `shopeame` con los archivos del plugin y sin lo que lista `.distignore`
   (documentación, `README.md`, `AGENTS.md`, `CLAUDE.md`, `.github` y archivos ocultos).
6. Con los cambios en `master`, se lanza la acción **Crear Release** de GitHub (pestaña *Actions* o
   `gh workflow run release.yml`). Falla si la versión no coincide en los tres sitios del paso 1 o si falta su
   entrada en el `Changelog`, y no hace nada si esa versión ya tiene release. Si todo está bien, crea la etiqueta
   `v<versión>` con esa entrada del `Changelog` como notas y adjunta:
   - el mismo zip del paso 5 con dos nombres, `shopeame-<versión>.zip` y `shopeame.zip`. Este último permite un enlace
     fijo a la última versión:
     `https://github.com/FacturaScripts/shopeame-woocommerce/releases/latest/download/shopeame.zip`.
   - `info.json`, del que dependen las actualizaciones (ver el apartado siguiente).

Desde ese momento, cada tienda ve la versión nueva en su siguiente comprobación de actualizaciones de WordPress (cada
12 horas, o al abrir **Plugins** o **Escritorio** → **Actualizaciones**).

## 4. Cómo llegan las actualizaciones a las tiendas

La cabecera `Update URI: https://github.com/FacturaScripts/shopeame-woocommerce` de `shopeame.php` hace que WordPress
no busque el plugin en WordPress.org y llame al filtro `update_plugins_github.com`. `includes/shopeame_updater.php`
responde con los datos de
`https://github.com/FacturaScripts/shopeame-woocommerce/releases/latest/download/info.json`:

```json
{
    "name": "Shopeame",
    "slug": "shopeame",
    "version": "1.0.1",
    "download_url": "https://github.com/FacturaScripts/shopeame-woocommerce/releases/download/v1.0.1/shopeame.zip",
    "details_url": "https://github.com/FacturaScripts/shopeame-woocommerce/releases/tag/v1.0.1",
    "homepage": "https://github.com/FacturaScripts/shopeame-woocommerce",
    "released": "2026-11-10",
    "requires": "6.5",
    "tested": "7.1",
    "requires_php": "7.4",
    "changelog": ["Line 1 of the changelog.", "Line 2 of the changelog."]
}
```

- `version` y `download_url` son obligatorios; el zip tiene que estar en las releases del repositorio.
- `changelog` aparece en la ventana **Ver detalles** de la actualización.
- La constante `SHOPEAME_UPDATE_URI` de `includes/shopeame_updater.php` tiene que ser idéntica a la cabecera.

### Probar una actualización sin publicar una release

1. Se genera el zip con una versión mayor que la instalada (por ejemplo, cambiando a `1.0.1` la cabecera `Version` y
   `SHOPEAME_VERSION` solo en la copia que se comprime).
2. Se copian el zip y un `info.json` como el de arriba a una carpeta servida por la web de pruebas (por ejemplo,
   `https://wp.localhost/shopeame-update-test/`), con `download_url` apuntando a ese zip.
3. Se añade en el `wp-config.php` de la tienda de pruebas
   `define( 'SHOPEAME_UPDATE_INFO_URL', 'https://wp.localhost/shopeame-update-test/info.json' );`. Con ella, el
   plugin admite cualquier URL de descarga.
4. Se abre **Escritorio** → **Actualizaciones** → **Comprobar de nuevo** y se actualiza desde ahí, desde **Plugins** o
   desde el aviso de la pantalla **Shopeame**.
5. Al terminar se quita la constante, se borra la carpeta y se vuelve a copiar el plugin del repositorio.

## Ver también

- [Cómo actualizar el plugin Shopeame](../User/como-actualizar-el-plugin-shopeame.md)
- [Endpoint de vinculación de Shopeame para WooCommerce](endpoint-de-vinculacion-de-shopeame-para-woocommerce.md)
- [Eventos que envía Shopeame para WooCommerce](eventos-que-envia-shopeame-para-woocommerce.md)
