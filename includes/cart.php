<?php
/**
 * includes/cart.php
 * -----------------------------------------------------------------
 * Carrito de compras del sitio. Es puramente client-side: vive en
 * localStorage y lo maneja assets/js/main.js. Este archivo solo
 * imprime el marcado (boton del nav + panel), igual en index.php
 * y en menu/index.php, para no duplicarlo.
 * -----------------------------------------------------------------
 */

/** Boton con contador, para meter dentro de <header class="l2-nav"> */
function render_cart_button(): string
{
    return <<<HTML
    <button class="l2-cart-btn" type="button" data-cart-toggle aria-label="Ver carrito">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      <span class="l2-cart-btn__count" data-cart-count hidden>0</span>
    </button>
    HTML;
}

/**
 * Panel deslizante + fondo oscuro. Se imprime una vez por pagina, antes de </body>.
 * $numeroWhatsapp: numero configurado en el admin (Configuración), solo
 * digitos con codigo de pais. assets/js/main.js lo lee del atributo
 * data-whatsapp-number para armar el link del pedido.
 */
function render_cart_drawer(string $numeroWhatsapp = ''): string
{
    $numero = htmlspecialchars($numeroWhatsapp);
    return <<<HTML
    <div class="l2-cart-backdrop" data-cart-backdrop></div>
    <aside class="l2-cart-drawer" data-cart-drawer aria-hidden="true">
      <div class="l2-cart-drawer__head">
        <h2>Tu pedido</h2>
        <button type="button" class="l2-cart-drawer__close" data-cart-close aria-label="Cerrar carrito">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="l2-cart-drawer__body" data-cart-body></div>
      <div class="l2-cart-drawer__foot">
        <div class="l2-cart-drawer__total">
          <span>Total</span>
          <strong data-cart-total>\$0.00</strong>
        </div>
        <a class="l2-btn l2-btn--accent l2-cart-drawer__send" data-cart-whatsapp data-whatsapp-number="{$numero}" href="#" target="_blank" rel="noopener">
          Enviar pedido por WhatsApp
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>
    </aside>
    HTML;
}
