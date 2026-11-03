# Shopeame para WooCommerce: contexto del equipo

Esta carpeta guarda **por qué el código es como es**: las decisiones que no se ven leyendo una sola clase, para que
todo el equipo y los agentes trabajen con el mismo contexto. Lee este índice y abre solo el tema o la decisión que
necesites.

Se puede dividir en tantos archivos y subcarpetas como haga falta; este índice dice qué hay en cada uno y se actualiza
en el mismo trabajo que cambia el código.

## Temas

| Tema | Qué explica |
|---|---|
| [0. Reglas de trabajo](00-reglas-de-trabajo.md) | Documentación oficial, convenciones de WordPress, traducciones, versiones, entorno local y pruebas contra shopea.localhost |
| [1. Arquitectura](01-arquitectura.md) | Archivos del plugin, opciones guardadas, hooks y relación con ShopeameServer |
| [2. Vinculación con shopea.me](02-vinculacion.md) | Las dos formas de vincular, el endpoint REST firmado con HMAC, desvincular y borrar datos |
| [3. Pedidos](03-pedidos.md) | Qué se envía al crear o modificar un pedido, qué hace el servidor y dónde quedan los errores |
| [4. Publicación en WordPress.org](04-publicacion-en-wordpress-org.md) | Directrices que se cumplen, `readme.txt`, Plugin Check, zip, release de GitHub y traducciones |

## Decisiones

| Registro | Fecha | Resumen |
|---|---|---|
| [0001. Adaptación a las directrices de WordPress.org](Decisiones/0001-adaptacion-a-wordpress-org.md) | 02/11/2026 | Para publicarlo en el directorio: verificación TLS siempre activa, opciones con prefijo y migración, nonce y capacidad antes de leer el formulario, registro de WooCommerce, `uninstall.php`, compatibilidad con HPOS y `readme.txt` en inglés |
| [0002. Textos en inglés y sin traducciones incluidas](Decisiones/0002-textos-en-ingles-sin-traducciones-incluidas.md) | 02/11/2026 | Las cadenas del código están en inglés con el dominio `shopeame` y el español se gestiona en translate.wordpress.org; no se usa `load_plugin_textdomain()` |
