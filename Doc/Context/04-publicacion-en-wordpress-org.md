# 4. Publicación en WordPress.org

## 4.1 Datos del directorio

- Nombre `Shopeame`, slug `shopeame` (sale del `Plugin Name`; no puede llevar «WooCommerce» porque es marca ajena) y
  carpeta del plugin `shopeame`.
- Lo sube la cuenta de WordPress.org `facturascripts` (`Contributors` del `readme.txt`).
- Licencia GPLv3 o posterior (`LICENSE` y cabeceras).
- `Requires Plugins: woocommerce`, `Requires at least: 6.5` (mínimo para esa cabecera), `Requires PHP: 7.4`.
- `Tested up to` y `WC tested up to` se revisan con cada versión contra la última estable
  (https://api.wordpress.org/core/version-check/1.7/ y la ficha de WooCommerce).

## 4.2 Directrices que más afectan

- **Servicio externo (directrices 6 y 7)**: el plugin es la interfaz de shopea.me. El `readme.txt` documenta en
  **External services** qué datos se envían, cuándo y a dónde, con los enlaces a términos (https://shopea.me/term),
  privacidad, cookies y precios. No se envía nada hasta que el usuario vincula la tienda. Si cambia lo que se envía,
  se actualiza esa sección en el mismo trabajo.
- **Seguridad**: escapado, saneado, nonce y capacidad (ver [reglas](00-reglas-de-trabajo.md)). La revisión rechaza
  desactivar la verificación TLS.
- **Nombres**: prefijo `shopeame` en todo lo global.
- **Sin código de terceros ni CDN**: los recursos van en `assets/`.
- **Avisos**: solo en la pantalla del plugin.

## 4.3 Comprobación y zip

- Plugin Check (instalado en wp.localhost) con todas las categorías: cero errores y cero advertencias. Plugin Check
  marca como error los zip y los scripts `.sh` dentro del plugin, y avisa de `load_plugin_textdomain()`.
- El zip contiene una sola carpeta `shopeame` sin lo que lista `.distignore` (`Doc/`, `AGENTS.md`, `CLAUDE.md`,
  `README.md`, `languages/` y archivos ocultos). Lo genera `~/Trabajos/FacturaScripts/scripts/build.sh` como
  `zip-plugins/shopeame-woocommerce.zip`; el nombre del archivo no es `shopeame.zip` para no pisar el del plugin
  `Shopeame` de FacturaScripts en un disco que no distingue mayúsculas.
- La acción de GitHub `.github/workflows/release.yml` (**Crear Release**, se lanza a mano) genera el mismo zip con
  `.distignore` y lo publica en una release `v<versión>` con dos nombres: `shopeame-<versión>.zip` y
  `shopeame.zip`. El segundo no cambia de nombre entre versiones para que
  `.../releases/latest/download/shopeame.zip` sea un enlace fijo a la última. Es la plantilla de release de fsmaker
  adaptada: la versión sale de `shopeame.php` y debe coincidir con `SHOPEAME_VERSION` y el `Stable tag`, las notas
  son la entrada del `Changelog` de `readme.txt` y no hay pasos de composer, npm ni traducciones. `.github` está en
  `.distignore` para no ir en el zip.
- El envío se hace en https://wordpress.org/plugins/developers/add/. Después de aprobarlo se publica por SVN:
  código en `trunk/`, versión en `tags/<versión>` e imágenes de la ficha (banner, icono y capturas) en `assets/` del
  SVN, no en el zip.

## 4.4 Traducciones

Ver [decisión 0002](Decisiones/0002-textos-en-ingles-sin-traducciones-incluidas.md). Tras la aprobación, se importa
`languages/shopeame-es_ES.po` en https://translate.wordpress.org/projects/wp-plugins/shopeame/ y se pide el rol de
editor de traducciones del proyecto (PTE) para aprobarlas.
