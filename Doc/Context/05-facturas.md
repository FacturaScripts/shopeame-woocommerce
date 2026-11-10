# 5. Facturas de los pedidos

El plugin permite descargar las facturas que el ERP ha generado para cada pedido: al comercio, desde el listado y la
ficha de los pedidos, y al cliente, desde Mi cuenta (si la opción `shopeame_customer_invoices` está activa). Por qué
se hace así: [decisión 0005](Decisiones/0005-facturas-bajo-demanda-a-traves-de-shopeame.md).

## 5.1 Recorrido

1. **WordPress** (`includes/shopeame_facturas.php`): el botón **Invoices** del listado, o el cuadro **Invoices** al abrir
   la ficha, llama por AJAX a `wp_ajax_shopeame_order_invoices`, que envía `order_invoices` a shopea.me con el
   `order_id`. Cada enlace de factura apunta a `admin-post.php?action=shopeame_invoice_pdf`, que envía `invoice_pdf` con
   `order_id` e `invoice_id` y reenvía el PDF al navegador sin guardarlo.
2. **ShopeameServer** (`Controller/WebHooks.php`): autentica la tienda, comprueba que el ERP está en línea y que su API
   tiene `exportarFacturaCliente` y `doctransformations`, busca el pedido Shopeame del ERP por `idshop` + `idorder`
   (o por el `idorder` alternativo guardado en `ShopOrder`) y le pasa su `id` a `Lib/FacturaScripts/OrderInvoiceFinder`.
3. **ERP**: solo se usa su API genérica (`facturaclientes`, `pedidoclientes`, `albaranclientes`, `doctransformations` y
   `exportarFacturaCliente`). El plugin Shopeame del ERP no interviene.

## 5.2 Qué facturas se encuentran

| Caso | Cómo se encuentra |
|---|---|
| Factura completa o parcial generada desde el pedido | `facturascli.idshopeameorder` |
| Factura que agrupa este pedido con otros | `doctransformations` desde los pedidos y albaranes con el `idshopeameorder` del pedido (pedido → albarán → factura) |
| Rectificativa | `facturascli.idfacturarect` de cualquiera de las anteriores |

Una consulta hace unas 7 u 8 llamadas a la API del ERP, por eso el listado no pide las facturas al pintarse, solo al
pulsar el botón de un pedido.

## 5.3 Seguridad

- Las acciones de WordPress comprueban el nonce antes que nada y después los permisos: `edit_shop_orders` para el
  gestor, y para el cliente la opción `shopeame_customer_invoices` y la capacidad `view_order` del pedido, que
  WooCommerce (`wc_customer_has_capability`) solo concede al dueño del pedido. El cliente usa su propia acción AJAX
  (`shopeame_customer_order_invoices`) con su propio nonce; la del gestor le responde `403`.
- La descarga usa `admin-post.php` también para los clientes: WooCommerce bloquea `wp-admin` a quien no gestiona la
  tienda, pero deja pasar `admin-post.php` y `admin-ajax.php`.
- A los clientes nunca se les muestran los detalles del error (ERP apagado, ERP antiguo...): ven un aviso genérico.
- El enlace de descarga se construye con `add_query_arg()` y `wp_create_nonce()`: `wp_nonce_url()` devuelve la URL
  escapada para HTML (`&amp;`) y el script la asigna tal cual, así que el nonce no llegaba.
- ShopeameServer solo entrega el PDF de una factura que esté entre las del pedido de esa tienda (si no,
  `404 invoice-not-found`), así que no se pueden pedir facturas ajenas cambiando el id.
- WordPress solo reenvía la respuesta si es `200` y empieza por `%PDF`; el `echo` del binario lleva su `phpcs:ignore`
  justificado.

## 5.4 Interfaz

- En Mi cuenta → Pedidos, **Invoices** es una acción más del pedido (`woocommerce_my_account_my_orders_actions`) y no
  una columna propia: así usa exactamente el estilo de **Ver** en cualquier tema (cada tema da estilos distintos a la
  celda de acciones). El enlace apunta a `#shopeame-invoices-<id>` de la página del pedido, donde está la sección de
  facturas; el script lee el id del ancla, convierte la celda en contenedor y abre el desplegable en su lugar. Sin
  JavaScript, el enlace lleva a esa sección.
- La acción solo se añade en el endpoint `orders` (`is_wc_endpoint_url( 'orders' )`): WooCommerce también pinta las
  acciones del pedido al verlo, y ahí el botón sobra porque la sección de facturas ya está debajo.
- La sección del pedido en Mi cuenta usa `woocommerce_view_order`, que solo se ejecuta en esa página (no en la de
  pedido recibido).
- La columna lleva la clase `no-link`: el listado de WooCommerce abre el pedido al pulsar en cualquier parte de la fila
  salvo en enlaces, botones y elementos `no-link`.
- Los textos del script llegan traducidos desde PHP con `wp_localize_script()`, con la traducción incluida en
  `languages/` (ver [decisión 0004](Decisiones/0004-traducciones-incluidas-en-el-plugin.md)).
- `assets/shopeame-orders.js` y `assets/shopeame-orders.css` solo se encolan en las pantallas
  `woocommerce_page_wc-orders` (HPOS), `edit-shop_order` y `shop_order` (pedidos clásicos), con la tienda vinculada, y
  en Mi cuenta para clientes con sesión iniciada si la opción está activa.

## 5.5 Errores

Los errores de shopea.me (`erp-offline`, `erp-not-supported`, `erp-error`, `invoice-not-found`, `unknown-event` de un
servidor antiguo...) se traducen a mensajes para el usuario en `shopeame_facturas::error_message()` y se anotan en el
registro de WooCommerce con la fuente `shopeame`. La tabla completa está en
[`Doc/Dev/eventos-que-envia-shopeame-para-woocommerce.md`](../Dev/eventos-que-envia-shopeame-para-woocommerce.md).

## 5.6 Limitación conocida del ERP

El plugin Shopeame del ERP calcula el estado del pedido (`ShopeameOrder::checkStatusInvoiced()`) solo con
`idshopeameorder` y con el total de cada factura: con una factura que agrupa varios pedidos, el segundo pedido se queda
«pendiente» y las rectificativas se cuentan en el primero. No afecta a la descarga de facturas, que no usa ese estado.
