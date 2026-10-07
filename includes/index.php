<?php
/**
 * index.php
 * -----------------------------------------------------------------
 * Sitio LIFE FIT — catálogo leído desde MySQL (ver includes/data.php
 * e includes/db.php). Si la conexión falla (credenciales sin poner
 * en includes/config.php, tabla sin importar, etc.) se muestra un
 * mensaje amigable en vez de un error de PHP.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/logo.php';

try {
    $catalogo = get_catalogo();
} catch (Throwable $e) {
    http_response_code(500);
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>LIFE FIT</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>body{font-family:sans-serif;background:#0b0a08;color:#f4efe4;display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;padding:24px;margin:0;}
p{color:#9c9384;max-width:420px;} code{background:#18150f;padding:2px 8px;border-radius:6px;}</style>
</head>
<body>
  <div>
    <h1>🥜 Un momento…</h1>
    <p>El sitio todavía no logra conectarse a la base de datos. Verifica los datos de conexión en <code>includes/config.php</code> y que hayas importado <code>database/schema.sql</code>.</p>
  </div>
</body>
</html>
    <?php
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<main id="top">

  <!-- ============ HERO ============ -->
  <section class="hero">
    <div class="hero__glow" aria-hidden="true"></div>

    <div class="hero__content">
      <p class="hero__eyebrow">Sushi · Poke · Snacks saludables</p>

      <h1 class="hero__logo">
        LIFE F<?= render_logo('peanut-mark peanut-mark--hero') ?>T
      </h1>

      <p class="hero__tagline"><?= htmlspecialchars($info['eslogan']) ?></p>

      <a href="#menu" class="hero__cta">
        Ver el menú
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 5v14M6 13l6 6 6-6"/></svg>
      </a>
    </div>

    <span class="hero__scroll" aria-hidden="true"></span>
  </section>

  <!-- ============ MENÚ ============ -->
  <section class="menu" id="menu">
    <div class="menu__head">
      <p class="menu__eyebrow">Catálogo</p>
      <h2>Elige tu categoría</h2>
    </div>

    <div class="menu__tabs" role="tablist" aria-label="Categorías del menú">
      <?php foreach ($catalogo as $i => $cat): ?>
        <button
          class="menu__tab <?= $i === 0 ? 'is-active' : '' ?>"
          role="tab"
          aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
          data-target="cat-<?= htmlspecialchars($cat['id']) ?>"
          type="button">
          <?= render_icon($cat['icono']) ?>
          <span><?= htmlspecialchars($cat['nombre']) ?></span>
        </button>
      <?php endforeach; ?>
    </div>

    <?php foreach ($catalogo as $i => $cat): ?>
      <div class="menu__panel <?= $i === 0 ? 'is-active' : '' ?>" id="cat-<?= htmlspecialchars($cat['id']) ?>" role="tabpanel">
        <div class="menu__grid">
          <?php foreach ($cat['items'] as $item): ?>
            <article class="card">
              <div class="card__media">
                <img src="<?= htmlspecialchars($item['imagen']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" loading="lazy">
                <div class="card__icon"><?= render_icon($cat['icono']) ?></div>
              </div>
              <div class="card__body">
                <div class="card__top">
                  <h3><?= htmlspecialchars($item['nombre']) ?></h3>
                  <span class="card__price">$<?= htmlspecialchars($item['precio']) ?></span>
                </div>
                <p><?= htmlspecialchars($item['desc']) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
