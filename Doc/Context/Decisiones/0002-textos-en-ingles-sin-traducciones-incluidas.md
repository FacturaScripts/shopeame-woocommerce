# 0002. Textos en inglés y sin traducciones incluidas

- **Fecha**: 02/11/2026
- **Aprobada por**: el usuario («Quitarla», al elegir entre mantener la traducción incluida o dejar Plugin Check sin
  advertencias).

## Contexto

Plugin Check exige el `readme.txt` en inglés, y translate.wordpress.org trabaja con el inglés como idioma de origen.
Los textos de la pantalla estaban escritos directamente en español. Al pasarlos a cadenas traducibles se probó a
incluir `languages/shopeame-es_ES.mo` cargado con `load_plugin_textdomain()`, pero Plugin Check avisa de que esa
función está desaconsejada desde WordPress 4.6: los plugins del directorio reciben sus traducciones solas.

## Decisión

- Las cadenas del código están en inglés y usan el dominio `shopeame` (igual que el slug).
- El plugin no incluye traducciones ni llama a `load_plugin_textdomain()`. El español llega como paquete de idioma de
  translate.wordpress.org.
- `languages/shopeame.pot` y `languages/shopeame-es_ES.po` (todas las cadenas traducidas, con los textos que tenía la
  pantalla en español) se conservan en el repositorio, fuera del zip, para importarlos en translate.wordpress.org.

## Consecuencias

Hasta que se apruebe el paquete de idioma español, la pantalla se ve en inglés aunque el sitio esté en español. Para
probar la traducción en local antes de eso, se puede compilar el `.po` en
`wp-content/languages/plugins/shopeame-es_ES.mo`, que es donde WordPress instala los paquetes de idioma.

## Alternativas descartadas

- Incluir la traducción y aceptar la advertencia de Plugin Check: el usuario prefirió dejarlo sin advertencias.
