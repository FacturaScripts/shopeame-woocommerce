# Shopeame para WooCommerce

Plugin que conecta una tienda **WooCommerce** (WordPress) con **[shopea.me](https://shopea.me)**
(ShopeameServer), para que shopea.me sincronice el catálogo y los pedidos de la tienda con el ERP
(FacturaScripts / factura.city).

> Este archivo es la documentación de desarrollo del repositorio. La ficha pública del directorio de
> WordPress.org está en `readme.txt` (en inglés, como exige el directorio).

## Qué hace

- Añade una página **Shopeame** en el menú de administración de WordPress.
- **Vinculación con shopea.me**, de dos formas:
  1. **Copiar credenciales**: la página muestra la *URL de la tienda* y las *claves de la API REST*
     de WooCommerce (consumer key/secret, que el plugin **genera automáticamente**) con botones para
     copiarlas y pegarlas en shopea.me al conectar una tienda de tipo WooCommerce. Tras crearla,
     ShopeameServer intenta devolver sus credenciales a `/wp-json/shopeame/v1/link`.
  2. **Vincular indicando el ERP**: el usuario introduce el *UUID* y el *token* de su ERP (los
     obtiene en shopea.me) y el plugin se registra solo en shopea.me (evento `register_woocommerce`,
     generando las claves API si hace falta) y guarda el `shop_uuid` y `webhook_token` devueltos.
     Si ya estaba vinculada, recupera esos datos sin crear un nuevo enlace en ShopeameServer.
- **Estado vinculada**: indica que la tienda está conectada, con enlace directo a su configuración
  en shopea.me, y botones para **comprobar conexión**, **desvincular** o **borrar los datos de la
  app**. Esta última acción elimina solo el `shop_uuid` y el `webhook_token` locales, conserva las
  claves de WooCommerce y no envía ninguna petición a ShopeameServer.
- **Pedidos en tiempo real**: mediante los hooks `woocommerce_new_order` y `woocommerce_update_order`
  envía el `order_id` a ShopeameServer; el servidor descarga el pedido y lo sincroniza con el ERP.
  Los fallos se guardan en el registro de WooCommerce (*WooCommerce > Estado > Registros*, fuente
  `shopeame`).
- Al **eliminar** el plugin (`uninstall.php`) se borran sus opciones y las claves de la API que creó.

La recepción automática requiere HTTPS y una firma HMAC-SHA256 vigente creada con la Consumer
secret guardada por el plugin. Si el endpoint no está disponible, se mantiene el copiado manual.

### Opciones guardadas

| Opción | Contenido |
|---|---|
| `shopeame_shop_uuid` | Identificador de la tienda en shopea.me |
| `shopeame_webhook_token` | Token de la tienda en shopea.me |
| `shopeame_api_user` / `shopeame_api_key` | Consumer key / secret de la API REST de WooCommerce |
| `shopeame_db_version` | Versión con la que se migraron los datos |

Hasta la versión 1.0.0 la vinculación se guardaba en `shop_uuid` y `webhook_token`; se migran solas
al actualizar.

## Instalación

> ⚠️ **La carpeta del plugin debe llamarse `shopeame`** (el slug del plugin), es decir,
> `wp-content/plugins/shopeame/`. El repositorio se llama `shopeame-woocommerce`, pero al
> desplegarlo en WordPress la carpeta tiene que ser `shopeame`.

Requiere **WooCommerce** activo (cabecera `Requires Plugins: woocommerce`).

## Traducciones

Las cadenas del código están en inglés con el text domain `shopeame`. El plugin **no incluye
traducciones**: WordPress.org las genera desde translate.wordpress.org y las instala solo (por eso no
se usa `load_plugin_textdomain()`, que Plugin Check desaconseja). Hasta que exista el paquete de
idioma español, la pantalla se verá en inglés.

La carpeta `languages/` solo está en el repositorio (`.distignore` la deja fuera del zip). Contiene
la plantilla `shopeame.pot` y la traducción completa `shopeame-es_ES.po`, que puedes importar en
https://translate.wordpress.org/projects/wp-plugins/shopeame/ una vez aprobado el plugin (para
aprobar tus propias traducciones necesitas el rol de PTE del proyecto). Para regenerarlas tras
cambiar textos:

```bash
xgettext --language=PHP --from-code=UTF-8 --add-comments=translators: --keyword=__ --keyword=_e \
  --keyword=esc_html__ --keyword=esc_html_e --keyword=esc_attr__ --keyword=esc_attr_e \
  -o languages/shopeame.pot shopeame.php includes/*.php
msgmerge --update languages/shopeame-es_ES.po languages/shopeame.pot
```

## Publicar en WordPress.org

1. Sube la versión en `shopeame.php` (cabecera `Version` y constante `SHOPEAME_VERSION`) y en el
   `Stable tag` de `readme.txt`, y añade la entrada al `Changelog`.
2. Ejecuta `~/Trabajos/FacturaScripts/scripts/build.sh`: además de los plugins de FacturaScripts, crea
   `zip-plugins/shopeame-woocommerce.zip` con una única carpeta `shopeame` y sin los archivos de
   desarrollo listados en `.distignore` (se genera fuera del repositorio, porque Plugin Check rechaza
   zips y scripts `.sh` dentro del plugin).
3. Pasa *Herramientas > Comprobar plugins* (Plugin Check) sobre el plugin instalado desde ese zip.

## Release en GitHub

Con los cambios de versión ya en `master`, lanza la acción **Crear Release** desde la pestaña *Actions* del
repositorio (o `gh workflow run release.yml`). Comprueba que la versión coincide en los tres sitios, crea la
etiqueta `v<versión>` con las notas del `Changelog` de `readme.txt` y adjunta el zip dos veces:
`shopeame-<versión>.zip` y `shopeame.zip`. Si esa versión ya tiene release, no hace nada.

El enlace https://github.com/FacturaScripts/shopeame-woocommerce/releases/latest/download/shopeame.zip descarga
siempre la última release (solo funciona sin iniciar sesión si el repositorio es público).

La documentación de uso y de programación está en `Doc/` (ver `AGENTS.md`).

## Configuración para desarrollo

La URL del servidor de shopea.me se controla con la constante `SHOPEAME_SERVER_URL`. Por defecto se
usa `https://shopea.me` (producción). Para apuntar a una instalación local, defínela en
`wp-config.php`:

```php
define('SHOPEAME_SERVER_URL', 'https://shopea.localhost');
```

Las peticiones usan la API HTTP de WordPress (`wp_remote_post`), que **siempre verifica el
certificado TLS** (WordPress.org no admite plugins que desactiven esa verificación). Si el servidor
local usa un certificado autofirmado, añade su CA (por ejemplo, la de ServBay) al almacén del sistema
o, solo en tu instalación local, crea un mu-plugin con:

```php
add_filter( 'https_ssl_verify', '__return_false' ); // SOLO en desarrollo local
```
