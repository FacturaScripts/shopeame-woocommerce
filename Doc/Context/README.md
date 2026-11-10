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
| [4. Distribución y actualizaciones](04-distribucion-y-actualizaciones.md) | Descarga desde GitHub, cómo WordPress ofrece las versiones nuevas (`Update URI` e `info.json`), zip, release y lo que marca Plugin Check |
| [5. Facturas de los pedidos](05-facturas.md) | Columna y cuadro **Invoices** del administrador, acción y sección **Invoices** de Mi cuenta, eventos `order_invoices` e `invoice_pdf`, cómo encuentra el servidor las facturas parciales, agrupadas y rectificativas, y seguridad de la descarga |

## Decisiones

| Registro | Fecha | Resumen |
|---|---|---|
| [0001. Adaptación a las directrices de WordPress.org](Decisiones/0001-adaptacion-a-wordpress-org.md) | 02/11/2026 | Para publicarlo en el directorio: verificación TLS siempre activa, opciones con prefijo y migración, nonce y capacidad antes de leer el formulario, registro de WooCommerce, `uninstall.php`, compatibilidad con HPOS y `readme.txt` en inglés |
| [0002. Textos en inglés y sin traducciones incluidas](Decisiones/0002-textos-en-ingles-sin-traducciones-incluidas.md) (sustituida por la 0004) | 02/11/2026 | Las cadenas del código están en inglés con el dominio `shopeame` y el español se gestiona en translate.wordpress.org; no se usa `load_plugin_textdomain()` |
| [0003. Distribución desde GitHub con actualizaciones en WordPress](Decisiones/0003-distribucion-desde-github-con-actualizaciones.md) | 09/11/2026 | No se publica en WordPress.org: cabecera `Update URI` y filtros `update_plugins_github.com` y `plugins_api` con el `info.json` de cada release, para actualizar desde **Plugins** o desde la pantalla Shopeame |
| [0004. Traducciones incluidas en el plugin](Decisiones/0004-traducciones-incluidas-en-el-plugin.md) | 09/11/2026 | Sin translate.wordpress.org, `languages/` (con el `.mo`) va en el zip y se carga con `load_plugin_textdomain()` |
| [0005. Facturas bajo demanda a través de shopea.me](Decisiones/0005-facturas-bajo-demanda-a-traves-de-shopeame.md) | 05/11/2026 | Las facturas de cada pedido se piden al ERP a través de shopea.me al pulsar el botón, sin guardarlas; se incluyen las que agrupan varios pedidos (`doctransformations`) y las rectificativas, y el cliente las descarga desde Mi cuenta |
