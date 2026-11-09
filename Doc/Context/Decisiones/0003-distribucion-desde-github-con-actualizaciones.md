# 0003. Distribución desde GitHub con actualizaciones en WordPress

- **Fecha**: 09/11/2026
- **Aprobada por**: el usuario («finalmente no se va a hacer», sobre publicar en las tiendas oficiales, y «modifica lo
  que tengas que modificar […] para que el cliente final pueda actualizar su aplicación directamente desde la
  aplicación de la tienda online»).

## Contexto

El plugin se preparó para el directorio de WordPress.org ([decisión 0001](0001-adaptacion-a-wordpress-org.md)), pero
se distribuye solo desde las releases del repositorio público de GitHub. Fuera del directorio, WordPress no sabe que
hay versiones nuevas: el cliente tendría que entrar en GitHub, descargar el zip y subirlo a mano. La versión 1.0.0 no
la había instalado nadie, así que se podía cambiar sin pensar en instalaciones anteriores.

## Decisión

- Cabecera `Update URI` con la URL del repositorio y respuesta a los filtros `update_plugins_github.com` y
  `plugins_api` (`includes/shopeame_updater.php`), que es la forma que documenta WordPress desde la 5.8. WordPress
  muestra la actualización en **Plugins** y **Actualizaciones**, la instala con su actualizador y admite las
  actualizaciones automáticas.
- Los datos salen de un `info.json` que la acción **Crear Release** adjunta a cada release. Se lee desde
  `releases/latest/download/info.json`, que no pasa por la API de GitHub (limitada a 60 peticiones por hora e IP en
  servidores compartidos) y no necesita token.
- El zip anunciado es el de la etiqueta, no el de `latest`, y tiene que estar en las releases del repositorio.
- La pantalla Shopeame muestra el aviso con el botón **Actualizar ahora** del propio WordPress, la versión instalada y
  **Buscar actualizaciones**.
- Plugin Check se sigue pasando, pero su error `plugin_updater_detected` se acepta: es una regla solo del directorio.

## Alternativas descartadas

- Seguir con WordPress.org: el usuario decidió no publicar en las tiendas oficiales. Además, el directorio prohíbe
  cualquier actualizador propio, así que las dos vías no se pueden combinar.
- Consultar `api.github.com/repos/.../releases/latest`: límite de peticiones sin token y más trabajo para sacar los
  requisitos y el changelog.
- Una librería como Plugin Update Checker: añade código de terceros para algo que WordPress ya resuelve con dos filtros.
- Servir el `info.json` desde shopea.me: obligaría a cambiar ShopeameServer en cada versión; GitHub ya publica la
  release.
- Solo un aviso con enlace a GitHub: el cliente tendría que descargar y subir el zip a mano.

## Resultado

Probado en wp.localhost (WordPress 7.1.4) con una versión 1.0.1 de prueba servida en local mediante
`SHOPEAME_UPDATE_INFO_URL`: aparece en **Actualizaciones** y en **Plugins**, la ventana **Ver detalles** muestra el
changelog, **Actualizar ahora** desde la pantalla Shopeame instala la 1.0.1 manteniendo el plugin activo y la tienda
vinculada, y después se puede activar la actualización automática.
