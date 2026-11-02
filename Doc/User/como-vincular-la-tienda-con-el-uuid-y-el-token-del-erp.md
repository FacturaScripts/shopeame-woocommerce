Con esta opción copias de shopea.me el UUID y el token de tu ERP y los pegas en WordPress. El plugin genera solo las
claves de la API de WooCommerce si aún no existen, registra la tienda en shopea.me y guarda los datos de la
vinculación.

## ⚙️ Antes de empezar

- El plugin Shopeame instalado y activo.
- Tu ERP registrado en shopea.me.

## 📋 Copiar el UUID y el token del ERP en shopea.me

1. En shopea.me, abre tu ERP, ve a la pestaña de tiendas y pulsa **Conectar** en **Conectar una tienda online o
   marketplace**. Elige **WooCommerce**.
2. En **Paso 2**, deja marcada **El plugin me pide un UUID y un token del ERP**.
3. Copia el **UUID del ERP** con su botón de copiar.
4. Copia el **Token del ERP** con su botón de copiar.

![Paso 2 de la pantalla Conectar una tienda online WooCommerce de shopea.me, con la opción del UUID y el token marcada y los botones para copiarlos](../Assets/como-vincular-la-tienda-con-el-uuid-y-el-token-del-erp/01-copiar-uuid-y-token-del-erp.png)

Deja esa página abierta: muestra **Esperando a que el plugin vincule la tienda…** y te llevará a la configuración de
la tienda en cuanto la detecte.

⚠️ El token da acceso a tu ERP en shopea.me: no lo compartas.

## 🔗 Vincular la tienda en WordPress

5. En WordPress, ve al menú **Shopeame**.
6. En **Opción 2: vincular indicando tu ERP**, pega el UUID en **UUID del ERP**.
7. Pega el token en **Token del ERP**.
8. Pulsa **Vincular tienda**.

![Sección Opción 2 de la página Shopeame con los campos UUID del ERP y Token del ERP y el botón Vincular tienda marcados](../Assets/como-vincular-la-tienda-con-el-uuid-y-el-token-del-erp/02-vincular-tienda.png)

## ✅ Qué pasa después

La página indica **Tienda vinculada ✅** con uno de estos avisos:

- **Tienda vinculada con shopea.me correctamente.**: se ha creado la tienda en tu ERP.
- **La tienda ya estaba vinculada. Se recuperaron sus datos sin crear una tienda nueva.**: la tienda ya existía en
  shopea.me (por ejemplo, si la conectaste antes con la opción 1).
- **La tienda se reactivó y quedó vinculada con shopea.me correctamente.**: la tienda se había desvinculado y se ha
  vuelto a activar con sus datos y su configuración.

![Página Shopeame con el aviso Tienda vinculada marcado](../Assets/como-vincular-la-tienda-con-el-uuid-y-el-token-del-erp/03-tienda-vinculada.png)

En shopea.me, la página que dejaste abierta muestra **¡Tienda … conectada!** y te lleva a la configuración de la
tienda.

## ⚠️ Si no se vincula

- **Introduce el UUID y el token del ERP.**: falta alguno de los dos campos.
- **No se pudo vincular la tienda. Revisa que el UUID y el token del ERP sean correctos.**: shopea.me no reconoce los
  datos o no se ha podido conectar con él. Vuelve a copiarlos sin espacios y repite.
- **No se pudieron generar las claves API. ¿Está activo WooCommerce?**: activa WooCommerce y repite.

## Ver también

- [Cómo vincular la tienda copiando los datos en shopea.me](como-vincular-la-tienda-copiando-los-datos-en-shopeame.md)
- [Cómo comprobar, desvincular o borrar los datos de la tienda](como-comprobar-desvincular-o-borrar-los-datos-de-la-tienda.md)
- [Cómo resolver los errores de Shopeame en WooCommerce](como-resolver-los-errores-de-shopeame-en-woocommerce.md)
