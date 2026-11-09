# 4. Distribución y actualizaciones

El plugin **no se publica en el directorio de WordPress.org**: se descarga de las releases del repositorio público
https://github.com/FacturaScripts/shopeame-woocommerce y se instala subiendo el zip en **Plugins → Añadir plugin →
Subir plugin**. Las versiones nuevas aparecen en WordPress como las de cualquier plugin del directorio (ver
[decisión 0003](Decisiones/0003-distribucion-desde-github-con-actualizaciones.md)).

## 4.1 Datos del plugin

- Nombre `Shopeame`, carpeta y slug `shopeame`, dominio de traducciones `shopeame`.
- Licencia GPLv3 o posterior (`LICENSE` y cabeceras).
- `Requires Plugins: woocommerce`, `Requires at least: 6.5` (mínimo para esa cabecera), `Requires PHP: 7.4`.
- `Update URI: https://github.com/FacturaScripts/shopeame-woocommerce`, igual que la constante `SHOPEAME_UPDATE_URI`.
- `Tested up to` y `WC tested up to` se revisan con cada versión contra la última estable
  (https://api.wordpress.org/core/version-check/1.7/ y la ficha de WooCommerce).
- `readme.txt` sigue el formato estándar de WordPress (en inglés): su `Changelog` da las notas de la release y la
  ventana «Ver detalles», y sus cabeceras, los requisitos del `info.json`.

## 4.2 Cómo se actualiza

WordPress permite que un plugin de fuera del directorio ofrezca sus actualizaciones desde la versión 5.8:

1. La cabecera `Update URI` hace que WordPress no pregunte por el slug `shopeame` en WordPress.org (donde podría haber
   un plugin de otro autor con ese nombre) y que llame al filtro `update_plugins_github.com`.
2. `shopeame_filter_update_plugins()` (`includes/shopeame_updater.php`) descarga
   `.../releases/latest/download/info.json` y devuelve la versión, el zip de esa etiqueta, `tested` y
   `requires_php`. WordPress lo pone en **Plugins** y en **Escritorio → Actualizaciones** con el botón
   **Actualizar ahora** y permite activar las **actualizaciones automáticas**. La actualización la hace el
   actualizador de WordPress (descarga, sustitución de la carpeta y `upgrader_process_complete`); después,
   `shopeame_maybe_upgrade()` aplica las migraciones al cargar el plugin.
3. `shopeame_filter_plugins_api()` rellena la ventana **Ver detalles** con el changelog del `info.json`.
4. La pantalla **Shopeame** muestra el aviso «Hay una versión nueva…» con **Actualizar ahora** (el mismo enlace con
   nonce que la pantalla de plugins) y, al pie, la versión instalada y **Buscar actualizaciones**
   (`update-core.php?force-check=1`).

Detalles:

- WordPress decide cuándo preguntar (cada 12 horas, cada hora en **Plugins**, cada minuto en
  **Actualizaciones**), así que el plugin no guarda caché propia, solo el resultado durante la petición. La pantalla
  de Shopeame lee la última comprobación (`update_plugins`) y no hace ninguna petición.
- Si GitHub no responde o el `info.json` no es válido, no se ofrece nada hasta la siguiente comprobación.
- El zip tiene que empezar por `SHOPEAME_UPDATE_URI/releases/download/`: un `info.json` manipulado no puede hacer
  instalar un paquete de otro sitio. La constante `SHOPEAME_UPDATE_INFO_URL` (en `wp-config.php`) cambia la URL del
  `info.json` para pruebas y, solo entonces, admite cualquier URL de descarga.
- `tested` llega como `7.1`; `shopeame_tested_up_to()` lo convierte en la versión instalada si es de la misma rama,
  como hace WordPress.org, para que no salga «No probada» con WordPress 7.1.4.
- La petición a GitHub lleva el User-Agent `Shopeame/<versión>`: el de WordPress incluye la URL de la tienda.
- Justo después de actualizar, la columna de actualizaciones automáticas puede quedar vacía hasta la siguiente
  comprobación de WordPress (en la prueba local apareció al forzarla).

## 4.3 El zip y la release de GitHub

- El zip contiene una sola carpeta `shopeame` sin lo que lista `.distignore` (`Doc/`, `AGENTS.md`, `CLAUDE.md`,
  `README.md`, `.github` y archivos ocultos). `languages/` sí va dentro (ver
  [decisión 0004](Decisiones/0004-traducciones-incluidas-en-el-plugin.md)). Lo genera
  `~/Trabajos/FacturaScripts/scripts/build.sh` como `zip-plugins/shopeame-woocommerce.zip`; el nombre del archivo no
  es `shopeame.zip` para no pisar el del plugin `Shopeame` de FacturaScripts en un disco que no distingue mayúsculas.
- La acción de GitHub `.github/workflows/release.yml` (**Crear Release**, se lanza a mano) genera el mismo zip y lo
  publica en una release `v<versión>` con tres archivos:
  - `shopeame-<versión>.zip` y `shopeame.zip`. El segundo no cambia de nombre entre versiones para que
    `.../releases/latest/download/shopeame.zip` sea un enlace fijo a la última (es el que usa ShopeameServer).
  - `info.json`: `version`, `download_url` (el `shopeame.zip` de esa etiqueta, no el de `latest`, para que el paquete
    coincida siempre con la versión anunciada), `details_url`, `homepage`, `released`, `requires`, `tested` y
    `requires_php` (de las cabeceras de `readme.txt`) y `changelog` (las líneas de su entrada del `Changelog`). Se
    genera con `jq`.
- La versión sale de `shopeame.php` y debe coincidir con `SHOPEAME_VERSION` y el `Stable tag`; las notas son la entrada
  del `Changelog` de `readme.txt`. No hay pasos de composer ni npm. `.github` está en `.distignore` para no ir en el
  zip.

## 4.4 Plugin Check

Se sigue pasando **Herramientas → Comprobar plugins** con todas las categorías como control de calidad. Dan siempre
dos resultados que son reglas solo de los plugins alojados en WordPress.org y no se corrigen:

| Tipo | Código | Por qué |
|---|---|---|
| Error | `plugin_updater_detected` | La cabecera `Update URI`, que es la que activa las actualizaciones desde GitHub |
| Advertencia | `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` | La carga de la traducción incluida; sin el directorio, WordPress no instala paquetes de idioma |

Cualquier otro error o advertencia se corrige. Las directrices de WordPress.org que ya cumplía el plugin se mantienen:
verificación TLS siempre activa, prefijo `shopeame` en todo lo global, nada de código de terceros ni CDN, avisos solo
en la pantalla del plugin y la sección **External services** de `readme.txt`, que se actualiza si cambia lo que se
envía.
