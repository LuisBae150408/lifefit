<?php
/**
 * index.php
 * -----------------------------------------------------------------
 * Página principal de LIFE FIT. El menú completo vive en /menu/.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/logo.php';
require_once __DIR__ . '/includes/cart.php';

try {
    $catalogo      = get_catalogo();
    $constructores = get_constructores();
    $info          = get_info_negocio();
    $publicaciones = get_publicaciones();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>LIFE FIT</title></head><body style="font-family:sans-serif;padding:60px;text-align:center;">🥜 Un momento, no pudimos cargar la página.</body></html>';
    exit;
}

// Accesos del catálogo: primero los constructores personalizables
// (lo que más engancha), después las categorías estándar.
$accesos = [];
foreach ($constructores as $b) {
    $accesos[] = ['id' => $b['id'], 'nombre' => $b['nombre'], 'icono' => $b['icono'], 'href' => 'menu/#build-' . $b['id']];
}
foreach ($catalogo as $c) {
    $accesos[] = ['id' => $c['id'], 'nombre' => $c['nombre'], 'icono' => $c['icono'], 'href' => 'menu/#cat-' . $c['id']];
}

$total_recetas = array_sum(array_map(fn($c) => count($c['items']), $catalogo)) + count($constructores);

$favicon_paths = implode('', array_map(fn($d) => '<path d="' . $d . '"/>', logo_paths_d()));
$favicon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . logo_viewbox() . '" fill="#c17f45">' . $favicon_paths . '</svg>';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LIFE FIT — Comida real, sin vueltas</title>
<meta name="description" content="LIFE FIT · Bowls, ensaladas, smoothies y snacks saludables en Parque Los Ilustres.">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Space+Grotesk:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode($favicon_svg) ?>">
</head>
<body class="l2">

<header class="l2-nav">
  <a href="#top" class="l2-nav__brand"><?= render_logo('l2-peanut') ?><span>LIFE FIT</span></a>
  <div class="l2-nav__right">
    <nav class="l2-nav__links" id="l2-nav-links">
      <a href="#nosotros">Nosotros</a>
      <a href="#categorias">Categorías</a>
      <a href="#contacto">Contacto</a>
      <a href="menu/" class="l2-btn l2-btn--accent">
        Pedir ahora
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </nav>
    <?= render_cart_button() ?>
    <button class="l2-nav__burger" id="l2-nav-burger" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="l2-nav-links">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<?= render_cart_drawer($info['whatsapp_numero'] ?? '') ?>

<main id="top">

  <!-- ============ HERO ============ -->
  <section class="l2-hero">
    <div class="l2-hero__grid">
      <div>
        <p class="l2-hero__eyebrow">Coffee · Snacks · Shakes · Poke</p>
        <h1>¡Vive bien, Vive <em>saludable</em>, Vive con LifeFit!</h1>
        <p class="l2-hero__sub">Vive saludable sin renunciar al sabor: arma tu bowl, tu poke o tu yogurt a tu gusto, cerca de <?= htmlspecialchars($info['ubicacion']) ?>.</p>

        <div class="l2-hero__ctas">
          <a href="menu/" class="l2-btn l2-btn--solid">
            Ver el menú completo
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
          <a href="#categorias" class="l2-btn l2-btn--ghost">Explorar categorías</a>
        </div>

        <div class="l2-hero__stats">
          <span class="l2-stat"><span class="l2-stat__dot"></span>+<?= $total_recetas ?> opciones en el menú</span>
          <span class="l2-stat"><span class="l2-stat__dot"></span>Ingredientes frescos cada día</span>
        </div>
      </div>

      <div class="l2-hero__art">
        <div class="l2-hero__blob">
          <?= render_logo('l2-peanut-hero-mark') ?>
        </div>
        <span class="l2-hero__badge l2-hero__badge--1">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M2 12h20"/></svg>
          100% natural
        </span>
        <span class="l2-hero__badge l2-hero__badge--2">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/></svg>
          Listo en minutos
        </span>
      </div>
    </div>
  </section>

  <!-- ============ PUBLICACIÓN PERSONALIZADA ============ -->
  <?php foreach ($publicaciones as $publicacion): ?>
    <section class="l2-promo-wrap">
      <div class="l2-promo">
        <div class="l2-promo__media">
          <img src="<?= htmlspecialchars($publicacion['imagen']) ?>" alt="<?= htmlspecialchars($publicacion['titulo'] ?: 'Promoción LIFE FIT') ?>" loading="lazy">
        </div>
        <div class="l2-promo__body">
          <?php if ($publicacion['titulo']): ?><h2><?= htmlspecialchars($publicacion['titulo']) ?></h2><?php endif; ?>
          <?php if ($publicacion['texto']): ?><p><?= nl2br(htmlspecialchars($publicacion['texto'])) ?></p><?php endif; ?>
          <?php if ($publicacion['enlace_url']): ?>
            <a href="<?= htmlspecialchars(normalizar_url_enlace($publicacion['enlace_url'])) ?>" class="l2-btn l2-btn--accent">
              <?= htmlspecialchars($publicacion['enlace_texto'] ?: 'Ver más') ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php endforeach; ?>

  <!-- ============ FEATURES ============ -->
  <section class="l2-features" id="nosotros">
    <div class="l2-section-head">
      <p class="l2-hero__eyebrow">Por qué LifeFit</p>
      <h2>Menos vueltas, más nutrición de verdad.</h2>
    </div>

    <div class="l2-features__grid">
      <article class="l2-feature">
        <div class="l2-feature__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14Z"/><path d="M5 19c1-4 3-7 6-9"/></svg>
        </div>
        <h3>Fresco cada día</h3>
        <p>Compramos y preparamos en el día — nada de bandejas que llevan semanas esperando.</p>
      </article>
      <article class="l2-feature">
        <div class="l2-feature__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h11M4 12h7M4 18h11"/><circle cx="18" cy="6" r="2"/><circle cx="14" cy="18" r="2"/></svg>
        </div>
        <h3>Arma tu pedido</h3>
        <p>Elige base, proteína y toppings en los platos que lo permiten — a tu gusto, sin dos pedidos iguales.</p>
      </article>
      <article class="l2-feature">
        <div class="l2-feature__icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 8.6c0 5.6-8.8 10.6-8.8 10.6S3.2 14.2 3.2 8.6a4.6 4.6 0 0 1 8.8-1.9 4.6 4.6 0 0 1 8.8 1.9Z"/></svg>
        </div>
        <h3>Ingredientes reales</h3>
        <p>Sin rellenos raros: lo que ves en la foto es lo que se prepara en la cocina.</p>
      </article>
    </div>
  </section>

  <!-- ============ CATEGORÍAS ============ -->
  <section class="l2-cats" id="categorias">
    <div class="l2-section-head">
      <p class="l2-hero__eyebrow">Catálogo</p>
      <h2>Elige por dónde quieres empezar.</h2>
    </div>

    <div class="l2-cats__grid">
      <?php foreach ($accesos as $a): ?>
        <a class="l2-cat-card" href="<?= htmlspecialchars($a['href']) ?>">
          <div class="l2-cat-card__icon"><?= render_icon($a['icono']) ?></div>
          <h3><?= htmlspecialchars($a['nombre']) ?></h3>
          <span class="l2-cat-card__go">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ============ CTA BANDA ============ -->
  <section class="l2-cta-band">
    <div>
      <h2>¿List@ para probarlo?</h2>
      <p>El menú completo, con fotos y precios, te espera del otro lado.</p>
    </div>
    <a href="menu/" class="l2-btn l2-btn--accent">
      Ver el menú
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>
  </section>

  <!-- ============ FOOTER ============ -->
  <footer class="l2-footer" id="contacto">
    <div class="l2-footer__brand"><?= render_logo('l2-peanut') ?><span>LIFE FIT</span></div>
    <p><?= htmlspecialchars($info['ubicacion']) ?></p>
    <p><?= htmlspecialchars($info['horario_semana']) ?> · <?= htmlspecialchars($info['horario_finde']) ?></p>

    <div class="l2-footer__socials">
      <?php $igUrl = url_instagram($info['instagram'] ?? ''); ?>
      <?php if ($igUrl): ?><a href="<?= htmlspecialchars($igUrl) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
      <?php $ttUrl = url_tiktok($info['tiktok'] ?? ''); ?>
      <?php if ($ttUrl): ?><a href="<?= htmlspecialchars($ttUrl) ?>" target="_blank" rel="noopener">TikTok</a><?php endif; ?>
      <a href="<?= htmlspecialchars(url_whatsapp($info['whatsapp_numero'] ?? '')) ?>" target="_blank" rel="noopener">WhatsApp</a>
    </div>

    <div class="l2-footer__bottom">
      <span>© <?= date('Y') ?> LIFE FIT. Todos los derechos reservados.</span>
    </div>
  </footer>

</main>

<script src="assets/js/main.js"></script>
</body>
</html>
