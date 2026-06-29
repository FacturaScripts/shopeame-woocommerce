# Shopeame para WooCommerce

Plugin que conecta una tienda **WooCommerce** (WordPress) con **[shopea.me](https://shopea.me)**
(ShopeameServer), para que shopea.me sincronice el catálogo y los pedidos de la tienda con el ERP
(FacturaScripts / factura.city).

## Qué hace

- Añade una página **Shopeame** en el menú de administración de WordPress.
- **Vinculación con shopea.me**, de dos formas:
  1. **Copiar credenciales**: la página muestra la *URL de la tienda* y las *claves de la API REST*
     de WooCommerce (consumer key/secret, que el plugin **genera automáticamente**) con botones para
     copiarlas y pegarlas en shopea.me al conectar una tienda de tipo WooCommerce.
  2. **Vincular indicando el ERP**: el usuario introduce el *UUID* y el *token* de su ERP (los
     obtiene en shopea.me) y el plugin se registra solo en shopea.me (evento `register_woocommerce`,
     generando las claves API si hace falta) y guarda el `shop_uuid` y `webhook_token` devueltos.
- **Estado vinculada**: indica que la tienda está conectada, con enlace directo a su configuración
  en shopea.me, y botones para **comprobar conexión** y **desvincular**.
- **Pedidos en tiempo real**: mediante los hooks `woocommerce_new_order` y `woocommerce_update_order`
  envía el `order_id` a ShopeameServer; el servidor descarga el pedido y lo sincroniza con el ERP.

## Instalación

> ⚠️ **La carpeta del plugin debe llamarse `shopeame`**, es decir,
> `wp-content/plugins/shopeame/`. (El repositorio se llama `shopeame-woocommerce`, pero al
> desplegarlo en WordPress la carpeta tiene que ser `shopeame`.)

Requiere **WooCommerce** activo (el plugin usa sus funciones para generar las claves de la API REST).

## Configuración para desarrollo

La URL del servidor de shopea.me se controla con la constante `SHOPEAME_SERVER_URL`. Por defecto se
usa `https://shopea.me` (producción). Para apuntar a una instalación local, defínela en
`wp-config.php`:

```php
define('SHOPEAME_SERVER_URL', 'https://localshopea.danielfg.es');

// Modo desarrollo: acepta el certificado TLS local autofirmado (no verifica el SSL).
// Por defecto (sin definir) se asume PRODUCCIÓN. NO usar en producción.
define('SHOPEAME_DEV', true);
```

- **URL**: si `SHOPEAME_SERVER_URL` no se define, se usa `https://shopea.me`.
- **TLS**: las peticiones usan la API HTTP de WordPress (`wp_remote_post`), que por defecto verifica
  el certificado. Con `SHOPEAME_DEV = true` se desactiva esa verificación (`sslverify => false`)
  para poder conectar con un servidor local con certificado autofirmado. Si tu CA local (p. ej. la
  de ServBay) ya está en el almacén del sistema, no necesitas `SHOPEAME_DEV`.
