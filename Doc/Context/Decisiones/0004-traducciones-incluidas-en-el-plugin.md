# 0004. Traducciones incluidas en el plugin

- **Fecha**: 09/11/2026
- **Aprobada por**: incluida en el trabajo de la [decisión 0003](0003-distribucion-desde-github-con-actualizaciones.md)
  («modifica lo que tengas que modificar»), como consecuencia de no publicar en WordPress.org. Sustituye a la
  [decisión 0002](0002-textos-en-ingles-sin-traducciones-incluidas.md).

## Contexto

La decisión 0002 dejaba el español en manos de translate.wordpress.org, que solo genera paquetes de idioma para los
plugins del directorio. Fuera de él, la pantalla se vería siempre en inglés aunque el sitio esté en español.

## Decisión

- Las cadenas del código siguen en inglés con el dominio `shopeame`.
- `languages/` va en el zip con `shopeame.pot`, `shopeame-es_ES.po` y `shopeame-es_ES.mo` (el `.mo` se versiona en
  git, porque la acción de release y `build.sh` empaquetan el repositorio tal cual).
- Cabecera `Domain Path: /languages` y `shopeame_load_textdomain()` en `init` con `load_plugin_textdomain()`.
- Al cambiar textos se regenera la plantilla, se traduce el `.po` y se compila el `.mo` (comandos en la página de
  desarrollo).

## Consecuencias

- Plugin Check avisa de que `load_plugin_textdomain()` está desaconsejada: lo está solo para los plugins del
  directorio, que reciben sus traducciones solos. Se acepta.
- Si existe `wp-content/languages/plugins/shopeame-es_ES.mo` (por ejemplo, uno compilado a mano para pruebas con la
  decisión anterior), WordPress lo prefiere al del plugin; si está desfasado, faltarán los textos nuevos.

## Alternativas descartadas

- Mantener la 0002: los clientes en español verían el plugin en inglés.
- Compilar el `.mo` en la acción de release: el zip de `build.sh` no lo tendría y habría dos formas de generar el zip.
