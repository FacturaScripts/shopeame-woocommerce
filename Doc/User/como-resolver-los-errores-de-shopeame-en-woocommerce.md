Los problemas más habituales al vincular la tienda o al sincronizar los pedidos tienen solución desde la propia página
**Shopeame** o desde el registro de WooCommerce. Un fallo al avisar a shopea.me nunca bloquea el pedido en la tienda:
el cliente termina su compra igual.

## 🔑 No se generan las claves de la API

Aviso: **No se pudieron generar las claves API. ¿Está activo WooCommerce?**

- Comprueba en **Plugins** que **WooCommerce** está activo y vuelve a pulsar **Generar claves API**.
- Comprueba que entras con un usuario administrador.

## 🔗 La tienda no se vincula

- **Introduce el UUID y el token del ERP.**: rellena los dos campos de la **Opción 2**.
- **No se pudo vincular la tienda. Revisa que el UUID y el token del ERP sean correctos.**: copia de nuevo el UUID y el
  token desde la pantalla de conectar WooCommerce de shopea.me, sin espacios al principio ni al final. Si son correctos,
  comprueba que tu servidor puede conectarse a internet.
- Con la **Opción 1**, shopea.me conecta la tienda pero la página **Shopeame** sigue sin vincular: shopea.me no ha
  podido enviar los datos a WordPress. La tienda debe usar HTTPS y no bloquear las peticiones de shopea.me. Termina la
  vinculación con la **Opción 2**, que recupera la tienda ya creada.

## 🔍 Falla la comprobación de la conexión

Aviso: **No se pudo confirmar la conexión con shopea.me.**

- Si la tienda se borró o se desvinculó en shopea.me, pulsa **Borrar datos de la app** y vuelve a vincularla.
- Si el servidor no tiene conexión, espera y repite la comprobación.

## 📦 Los pedidos no llegan al ERP

1. Pulsa **Comprobar conexión** en la página **Shopeame**.
2. Revisa en shopea.me que la tienda está activa y que tiene activada la copia de pedidos al ERP.
3. Ve a **WooCommerce** → **Estado** → **Registros** y abre el registro con la fuente `shopeame`. Cada aviso fallido
   indica el pedido, el código de respuesta y el motivo:
   - Código **0**: no hubo conexión con shopea.me (sin internet, cortafuegos o tiempo de espera agotado).
   - Código **401**: shopea.me no reconoce la tienda. Pulsa **Borrar datos de la app** y vuelve a vincularla.
   - Códigos **500** o superiores: fallo temporal en shopea.me. Los pedidos se recuperan en la siguiente
     sincronización.

⚠️ Si regeneras las claves de la API o las revocas en **WooCommerce** → **Ajustes** → **Avanzado** → **API REST**,
shopea.me deja de poder leer los pedidos hasta que vuelvas a vincular la tienda.

## 🧾 No aparecen las facturas de un pedido

- **Este pedido aún no tiene facturas en el ERP.**: el pedido no está facturado o no llegó al ERP. Revisa en el ERP que
  el pedido de la tienda está facturado.
- **Tu ERP no está disponible ahora mismo. Inténtalo más tarde.**: el ERP está apagado o sin el plugin Shopeame.
- **Tu ERP no permite descargar facturas. Actualiza FacturaScripts y el plugin Shopeame.**: actualiza el ERP.
- **shopea.me todavía no permite descargar facturas.**: shopea.me aún no tiene esta función.

Cada fallo se anota en el registro de WooCommerce con la fuente `shopeame`, como los avisos de pedidos (eventos
`order_invoices` e `invoice_pdf`). Más detalles en
[Cómo descargar las facturas de un pedido](como-descargar-las-facturas-de-un-pedido.md).

## Ver también

- [Cómo comprobar, desvincular o borrar los datos de la tienda](como-comprobar-desvincular-o-borrar-los-datos-de-la-tienda.md)
- [Cómo vincular la tienda con el UUID y el token del ERP](como-vincular-la-tienda-con-el-uuid-y-el-token-del-erp.md)
- [Cómo funciona el plugin Shopeame para WooCommerce](como-funciona-shopeame-para-woocommerce.md)
