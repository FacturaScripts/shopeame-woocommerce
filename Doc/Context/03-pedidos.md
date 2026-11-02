# 3. Pedidos

`woocommerce_new_order` y `woocommerce_update_order` llaman a `shopeame_send_order_event()` con `new_order` o
`update_order`. Si la tienda no está vinculada no se envía nada.

El evento lleva solo `shop`, `token` y `order_id`. ShopeameServer no procesa el pedido dentro de la petición: adelanta
a ahora la siguiente sincronización de pedidos de la tienda (`nextordersync`), y su cron descarga los pedidos por la
API REST de WooCommerce y los lleva al ERP. Así la petición responde rápido y un reintento no duplica pedidos. Las
respuestas posibles son `orders-sync-scheduled`, `orders-sync-pending` (ya estaba programada), `orders-sync-deferred`
(el ERP no está en condiciones) y `orders-sync-disabled` (la tienda no copia pedidos o está desconectada).

`woocommerce_update_order` salta en cada guardado del pedido, por eso el envío es ligero y el servidor no repite trabajo
si la sincronización ya estaba pendiente.

Los fallos (sin conexión, timeout, 401 por token inválido...) no interrumpen el pedido: se escriben en el registro de
WooCommerce con la fuente `shopeame` (**WooCommerce → Estado → Registros**), con el evento, el pedido, el código HTTP y
el detalle. Se usa `wc_get_logger()` y no `error_log()`, que Plugin Check marca como código de depuración.

El plugin declara compatibilidad con HPOS (`custom_order_tables`) porque solo trabaja con identificadores de pedido.
