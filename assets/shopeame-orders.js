/**
 * Facturas del ERP en el listado y en la ficha de los pedidos de WooCommerce.
 *
 * - En el listado, el botón «Invoices» de cada pedido pide sus facturas por AJAX la primera vez que
 *   se pulsa y las muestra en un desplegable.
 * - En la ficha del pedido y al ver un pedido en Mi cuenta, el cuadro o la sección «Invoices»
 *   (data-autoload) las pide al abrir la página.
 * - En Mi cuenta → Pedidos, la acción «Invoices» (un enlace a la sección de facturas del pedido) se
 *   convierte en un desplegable dentro de la celda de acciones. Sin JavaScript, el enlace sigue
 *   llevando a esa sección.
 *
 * Los textos llegan traducidos desde PHP en shopeameOrders.i18n.
 */
(function () {
    'use strict';

    var config = window.shopeameOrders;
    if (!config) {
        return;
    }

    /**
     * Crea un elemento con una clase y un texto opcionales.
     *
     * @param {string} tag Etiqueta HTML.
     * @param {string} className Clase CSS.
     * @param {string} text Texto del elemento.
     * @returns {HTMLElement}
     */
    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text) {
            node.textContent = text;
        }
        return node;
    }

    /**
     * Pinta un mensaje en el panel, con un botón para volver a cargar si es un error.
     *
     * @param {HTMLElement} widget Contenedor de las facturas del pedido.
     * @param {string} message Mensaje que se muestra.
     * @param {boolean} isError Indica si es un error.
     */
    function renderMessage(widget, message, isError) {
        var panel = widget.querySelector('.shopeame-invoices__panel');
        panel.textContent = '';
        panel.appendChild(el('p', 'shopeame-invoices__message' + (isError ? ' shopeame-invoices__message--error' : ''), message));

        if (isError) {
            var retry = el('button', 'button-link shopeame-invoices__reload', config.i18n.reload);
            retry.type = 'button';
            retry.addEventListener('click', function () {
                load(widget);
            });
            panel.appendChild(retry);
        }
    }

    /**
     * Pinta la lista de facturas con su enlace de descarga.
     *
     * @param {HTMLElement} widget Contenedor de las facturas del pedido.
     * @param {Array} invoices Facturas devueltas por el servidor.
     */
    function renderInvoices(widget, invoices) {
        if (!invoices.length) {
            renderMessage(widget, config.i18n.empty, false);
            return;
        }

        var panel = widget.querySelector('.shopeame-invoices__panel');
        var list = el('ul', 'shopeame-invoices__list');
        invoices.forEach(function (invoice) {
            var item = el('li', 'shopeame-invoices__item' + (invoice.is_refund ? ' shopeame-invoices__item--refund' : ''));

            var link = el('a', 'shopeame-invoices__link', invoice.code);
            link.href = invoice.url;
            link.title = config.i18n.download;
            item.appendChild(link);

            if (invoice.is_refund) {
                var badge = el('span', 'shopeame-invoices__badge', config.i18n.refund);
                if (invoice.refund_of) {
                    badge.title = config.i18n.refundOf.replace('%s', invoice.refund_of);
                }
                item.appendChild(badge);
            }

            var meta = el('span', 'shopeame-invoices__meta');
            meta.appendChild(el('span', 'shopeame-invoices__date', invoice.date));
            meta.appendChild(el('span', 'shopeame-invoices__total', invoice.total));
            item.appendChild(meta);

            list.appendChild(item);
        });

        panel.textContent = '';
        panel.appendChild(list);
    }

    /**
     * Pide al servidor las facturas del pedido y las pinta.
     *
     * @param {HTMLElement} widget Contenedor de las facturas del pedido.
     */
    function load(widget) {
        widget.setAttribute('data-loaded', 'loading');
        renderMessage(widget, config.i18n.loading, false);

        var body = new FormData();
        body.append('action', config.action);
        body.append('nonce', config.nonce);
        body.append('order_id', widget.getAttribute('data-order-id'));

        fetch(config.ajaxUrl, {method: 'POST', body: body, credentials: 'same-origin'})
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (result && result.success && result.data && Array.isArray(result.data.invoices)) {
                    widget.setAttribute('data-loaded', '1');
                    renderInvoices(widget, result.data.invoices);
                    return;
                }

                widget.removeAttribute('data-loaded');
                renderMessage(widget, (result && result.data && result.data.message) || config.i18n.error, true);
            })
            .catch(function () {
                widget.removeAttribute('data-loaded');
                renderMessage(widget, config.i18n.error, true);
            });
    }

    /**
     * Cierra los desplegables abiertos del listado, salvo el indicado.
     *
     * @param {HTMLElement|null} except Contenedor que no se cierra.
     */
    function closeAll(except) {
        document.querySelectorAll('.shopeame-invoices__toggle[aria-expanded="true"]').forEach(function (toggle) {
            var widget = toggle.closest('.shopeame-invoices');
            if (widget === except) {
                return;
            }
            toggle.setAttribute('aria-expanded', 'false');
            widget.querySelector('.shopeame-invoices__panel').hidden = true;
        });
    }

    /**
     * Convierte las acciones «Invoices» de Mi cuenta → Pedidos en desplegables. El id del pedido va en
     * el ancla del enlace (#shopeame-invoices-<id>); la celda de acciones hace de contenedor.
     */
    function enhanceAccountActions() {
        document.querySelectorAll('a.shopeame-invoices-button').forEach(function (link) {
            var match = /#shopeame-invoices-(\d+)$/.exec(link.getAttribute('href') || '');
            var cell = link.parentElement;
            if (!match || !cell || cell.classList.contains('shopeame-invoices')) {
                return;
            }

            cell.classList.add('shopeame-invoices', 'shopeame-invoices--account');
            cell.setAttribute('data-order-id', match[1]);
            link.classList.add('shopeame-invoices__toggle');
            link.setAttribute('aria-expanded', 'false');
            link.setAttribute('role', 'button');

            var panel = el('div', 'shopeame-invoices__panel');
            panel.hidden = true;
            panel.setAttribute('aria-live', 'polite');
            cell.appendChild(panel);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        enhanceAccountActions();
        document.querySelectorAll('.shopeame-invoices[data-autoload]').forEach(load);
    });

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('.shopeame-invoices__toggle');
        if (!toggle) {
            // un clic fuera de los desplegables los cierra
            if (!event.target.closest('.shopeame-invoices')) {
                closeAll(null);
            }
            return;
        }

        // en Mi cuenta el botón es un enlace a la sección de facturas: abrimos el desplegable en su lugar
        event.preventDefault();

        var widget = toggle.closest('.shopeame-invoices');
        var panel = widget.querySelector('.shopeame-invoices__panel');
        var open = toggle.getAttribute('aria-expanded') !== 'true';

        closeAll(widget);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        panel.hidden = !open;

        if (open && !widget.getAttribute('data-loaded')) {
            load(widget);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAll(null);
        }
    });
})();
