# 0003. Facturas de los pedidos bajo demanda a través de shopea.me

- **Fecha**: 05/11/2026
- **Aprobada por**: el usuario («Me gusta la opción A y me parece correcta», al elegir cómo llevar las facturas del ERP
  a WooCommerce; pidió además que las facturas que agrupan varios pedidos aparezcan en todos ellos, que la descarga
  esté también en la ficha del pedido y que el cliente final pueda descargarlas desde Mi cuenta, con el botón alineado
  como **Ver**).

## Contexto

Se quería descargar desde WooCommerce las facturas que el ERP genera para cada pedido. Un pedido puede tener varias
facturas (facturación parcial), facturas rectificativas y facturas que agrupan varios pedidos del mismo cliente.
WordPress no conoce la URL ni la clave API del ERP; ShopeameServer sí (`ErpLink`) y ya autentica a la tienda con su
UUID y su token.

Se probó el ciclo completo en local (pedido de WooCommerce → ShopeameServer → ERP, factura parcial, factura del resto,
rectificativa, y dos pedidos agrupados en un albarán y una factura) y se comprobó que:

- Las facturas y rectificativas generadas a partir del pedido heredan `idshopeameorder`.
- Al agrupar varios pedidos, el documento nuevo solo hereda el `idshopeameorder` del primero, pero el ERP guarda en
  `doctransformations` de qué línea de pedido o albarán sale cada línea de la factura.
- El core expone `GET /api/3/exportarFacturaCliente/{id}?type=PDF`.

## Decisión

- **Opción A, bajo demanda**: WordPress pide las facturas a shopea.me al pulsar el botón (`order_invoices`) y cada PDF
  al descargarlo (`invoice_pdf`). shopea.me las busca en el ERP por la API genérica y no guarda nada; WordPress tampoco.
- ShopeameServer (`OrderInvoiceFinder`) combina las facturas con el `idshopeameorder` del pedido, las que reciben
  líneas de sus pedidos y albaranes según `doctransformations` y las rectificativas de todas ellas.
- `invoice_pdf` solo entrega una factura si está entre las del pedido de esa tienda.
- En WordPress: columna **Invoices** en el listado (se carga al pulsar el botón, nunca al pintar el listado) y cuadro
  **Invoices** en la ficha del pedido (se carga al abrirla).
- En Mi cuenta: acción **Invoices** junto a **Ver** en cada pedido y sección **Invoices** al ver un pedido, solo con
  los pedidos del propio cliente (`view_order`). Se activa por defecto y se puede desactivar en la página Shopeame
  (opción `shopeame_customer_invoices`), porque es el comercio quien decide si sus clientes ven las facturas.
- El plugin Shopeame del ERP no cambia.

## Consecuencias

- Las facturas siempre están al día (rectificativas nuevas, facturas borradas), pero hace falta que el ERP esté
  encendido: si no, se avisa al usuario (`erp-offline`).
- Cada consulta hace varias llamadas a la API del ERP (unas 7 u 8 por pedido, ~100 ms en local); por eso el listado no
  las pide al pintarse.
- Una factura que agrupa varios pedidos, y sus rectificativas, aparecen en todos ellos aunque la rectificativa solo
  afecte a las líneas de uno: es la misma factura del mismo cliente.
- El `readme.txt` declara en **External services** los datos que se envían para ver y descargar facturas.

## Alternativas descartadas

- **Opción B, enviar las facturas a WordPress**: el ERP avisaría de cada factura y shopea.me enviaría el PDF a
  WordPress. Necesitaba un canal nuevo del ERP a shopea.me, un endpoint nuevo en WordPress, guardar PDF con datos
  personales en WordPress y mantenerlos al día al cambiar las facturas; además las claves de WooCommerce son de solo
  lectura y no permiten escribir en el pedido.
- **Que WordPress hable directamente con el ERP**: obligaría a guardar la URL y la clave API del ERP en la tienda.
- **Buscar solo por `idshopeameorder`**: las facturas que agrupan varios pedidos no aparecerían en los demás.
