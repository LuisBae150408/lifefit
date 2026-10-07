<?php
/**
 * menu/index.php
 * -----------------------------------------------------------------
 * Menú completo de LIFE FIT:
 *   - Secciones PERSONALIZABLES (get_constructores()): Crea tu
 *     Yogurt, Malteadas, Arma tu Poke, Salad Bar, Crea tu Bowl,
 *     Waffles -- cada una con su picker de ingredientes dinámico
 *     (ver assets/js/main.js).
 *   - Secciones ESTÁNDAR (get_catalogo()): Smoothies, Jugos
 *     Vitales, Café, Postres Saludables -- formato de tarjeta.
 *
 * Ambas alimentan un carrito compartido (localStorage, ver
 * includes/cart.php y assets/js/main.js): "Añadir al carrito" en
 * vez de un WhatsApp individual por producto, y un solo pedido de
 * WhatsApp al final con todo lo elegido.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/logo.php';
require_once __DIR__ . '/../includes/cart.php';

try {
    $catalogo      = get_catalogo();
    $constructores = get_constructores();
    $info          = get_info_negocio();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>LIFE FIT</title></head><body style="font-family:sans-serif;padding:60px;text-align:center;">🥜 Un momento, no pudimos cargar el menú.</body></html>';
    exit;
}

$favicon_paths = implode('', array_map(fn($d) => '<path d="' . $d . '"/>', logo_paths_d()));
$favicon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . logo_viewbox() . '" fill="#c17f45">' . $favicon_paths . '</svg>';

/**
 * Texto legible de la regla de selección de un grupo, para el
 * fallback sin JS (data-rule se recalcula en vivo con main.js).
 */
function regla_legible(int $min, ?int $max): string
{
    if ($min >= 1 && $max === $min) return "Elige $min";
    if ($min >= 1 && $max !== null) return "Elige entre $min y $max";
    if ($min >= 1) return "Elige al menos $min";
    if ($max === 1) return 'Opcional · elige 1';
    if ($max !== null) return "Opcional · hasta $max";
    return 'Opcional';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menú — LIFE FIT</title>
<meta name="description" content="Menú completo de LIFE FIT: arma tu yogurt, tu poke, tu bowl y más, o elige de nuestras recetas fijas.">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Space+Grotesk:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="../assets/css/style.css">
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode($favicon_svg) ?>">
</head>
<body class="l2">

<header class="l2-nav">
  <a href="../" class="l2-nav__brand"><?= render_logo('l2-peanut') ?><span>LIFE FIT</span></a>
  <div class="l2-nav__right">
    <nav class="l2-nav__links" id="l2-nav-links">
      <a href="../#nosotros">Nosotros</a>
      <a href="../#categorias">Categorías</a>
      <a href="../#contacto">Contacto</a>
    </nav>
    <?= render_cart_button() ?>
    <button class="l2-nav__burger" id="l2-nav-burger" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="l2-nav-links">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<?= render_cart_drawer($info['whatsapp_numero'] ?? '') ?>

<main>

  <section class="l2-menu-hero">
    <a class="l2-back" href="../" style="color:rgba(251,250,247,.8); justify-content:center; display:inline-flex;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
      Volver al inicio
    </a>
    <h1>Nuestro menú</h1>
    <p>Arma tu plato a tu manera en las secciones personalizables, o elige directo de nuestras recetas fijas.</p>
  </section>

  <div class="l2-menu-body">

    <div class="l2-filters" role="tablist" aria-label="Secciones del menú">
      <?php foreach ($constructores as $b): ?>
        <a class="l2-filter" href="#build-<?= htmlspecialchars($b['id']) ?>">
          <?= render_icon($b['icono']) ?>
          <?= htmlspecialchars($b['nombre']) ?>
        </a>
      <?php endforeach; ?>
      <?php foreach ($catalogo as $cat): ?>
        <a class="l2-filter" href="#cat-<?= htmlspecialchars($cat['id']) ?>">
          <?= render_icon($cat['icono']) ?>
          <?= htmlspecialchars($cat['nombre']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- ============ SECCIONES PERSONALIZABLES ============ -->
    <?php foreach ($constructores as $b): ?>
      <section class="l2-cat-section" id="build-<?= htmlspecialchars($b['id']) ?>">
        <?php
          // Si ninguno de los grupos de este constructor se llama "elige tu
          // base" (o similar), la base es fija y solo se explica en la
          // descripcion (ej. Yogurt: "Base: Banana, fresa y piña."). Ese
          // texto se manda al JS para que salga siempre en el resumen y,
          // por lo tanto, tambien en el mensaje de WhatsApp.
          $tieneGrupoBaseSeleccionable = false;
          foreach ($b['grupos'] as $g) {
              if (stripos($g['nombre'], 'base') !== false) { $tieneGrupoBaseSeleccionable = true; break; }
          }
          $baseFija = (!$tieneGrupoBaseSeleccionable && $b['desc']) ? $b['desc'] : '';
        ?>
        <div class="l2-builder" data-builder data-builder-id="<?= htmlspecialchars($b['id']) ?>" data-base-price="<?= (float) $b['precio_final'] ?>" data-base-price-mini="<?= $b['precio_mini_final'] !== null ? (float) $b['precio_mini_final'] : '' ?>" data-base-fija="<?= htmlspecialchars($baseFija) ?>" data-combos="<?= htmlspecialchars(json_encode($b['combos']), ENT_QUOTES) ?>" data-descuento="<?= htmlspecialchars($b['descuento'] ?? '') ?>">
          <div class="l2-builder__head">
            <div class="l2-builder__media">
              <img src="<?= htmlspecialchars($b['imagen']) ?>" alt="<?= htmlspecialchars($b['nombre']) ?>" loading="lazy">
            </div>
            <div class="l2-builder__info">
              <span class="l2-tag">Personalizable</span>
              <h3 style="margin-top:8px;"><?= htmlspecialchars($b['nombre']) ?></h3>
              <p><?= htmlspecialchars($b['desc']) ?></p>
              <?php if ($b['nota']): ?><p class="l2-builder__note"><?= htmlspecialchars($b['nota']) ?></p><?php endif; ?>
              <?php if ($b['descuento']): ?>
                <p class="l2-builder__price"><s class="l2-price-old">$<?= htmlspecialchars($b['precio']) ?></s> $<?= htmlspecialchars($b['precio_final']) ?> <span class="l2-tag" style="vertical-align:middle;"><?= htmlspecialchars($b['descuento']) ?></span></p>
              <?php else: ?>
                <p class="l2-builder__price">Desde $<?= htmlspecialchars($b['precio']) ?></p>
              <?php endif; ?>
            </div>
          </div>

          <button class="l2-builder__toggle" type="button" aria-expanded="false">
            Personalizar mi pedido
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
          </button>

          <div class="l2-builder__body">
            <?php if ($b['precio_mini'] !== null): ?>
              <div class="l2-builder__size">
                <span class="l2-plantillas__label">Tamaño:</span>
                <div class="l2-tamanos">
                  <label class="l2-tamano-opt">
                    <input type="radio" name="tamano-build-<?= htmlspecialchars($b['id']) ?>" value="normal" data-builder-size checked>
                    <span>Normal · $<?= htmlspecialchars($b['precio_final']) ?></span>
                  </label>
                  <label class="l2-tamano-opt">
                    <input type="radio" name="tamano-build-<?= htmlspecialchars($b['id']) ?>" value="mini" data-builder-size>
                    <span>Mini · $<?= htmlspecialchars($b['precio_mini_final']) ?></span>
                  </label>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($b['plantillas'] ?? []): ?>
              <div class="l2-plantillas">
                <span class="l2-plantillas__label">Plantillas:</span>
                <?php foreach ($b['plantillas'] as $pl): ?>
                  <button type="button" class="l2-tpl-btn" data-plantilla="<?= htmlspecialchars(json_encode($pl['ingredientes']), ENT_QUOTES) ?>"><?= htmlspecialchars($pl['nombre']) ?></button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <div class="l2-builder__groups">
              <?php foreach ($b['grupos'] as $gi => $grupo): ?>
                <div class="l2-group" data-group data-min="<?= (int) $grupo['min'] ?>" data-max="<?= $grupo['max'] === null ? '' : (int) $grupo['max'] ?>" data-min-mini="<?= $grupo['min_mini'] === null ? '' : (int) $grupo['min_mini'] ?>" data-max-mini="<?= $grupo['max_mini'] === null ? '' : (int) $grupo['max_mini'] ?>" data-nombre="<?= htmlspecialchars($grupo['nombre']) ?>">
                  <div class="l2-group__head">
                    <span class="l2-group__title"><?= htmlspecialchars($grupo['nombre']) ?></span>
                    <span class="l2-group__rule" data-rule><?= htmlspecialchars(regla_legible($grupo['min'], $grupo['max'])) ?></span>
                  </div>
                  <div class="l2-opts">
                    <?php $inputType = $grupo['max'] === 1 ? 'radio' : 'checkbox'; ?>
                    <?php foreach ($grupo['ingredientes'] as $ing): ?>
                      <label class="l2-opt" title="<?= htmlspecialchars($ing['desc']) ?>">
                        <input type="<?= $inputType ?>" name="g-<?= htmlspecialchars($b['id']) ?>-<?= $gi ?>" value="<?= htmlspecialchars($ing['nombre']) ?>" data-extra="<?= (float) $ing['extra'] ?>">
                        <span><?= htmlspecialchars($ing['nombre']) ?></span>
                        <?php if ($ing['extra'] > 0): ?><span class="l2-opt__extra">+$<?= number_format($ing['extra'], 2) ?></span><?php endif; ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="l2-builder__summary">
              <p class="l2-builder__summary-text" data-summary>Elige tus ingredientes arriba para armar tu pedido.</p>
              <p class="l2-builder__total" data-total>Total: $<?= number_format((float) $b['precio_final'], 2) ?></p>
              <div class="l2-builder__comment-wrap">
                <label class="l2-builder__comment-label" for="comentario-<?= htmlspecialchars($b['id']) ?>">Comentario (opcional)</label>
                <textarea id="comentario-<?= htmlspecialchars($b['id']) ?>" class="l2-builder__comment" data-builder-comment maxlength="140" rows="2" placeholder="Ej: poco pistacho, sin hielo..."></textarea>
              </div>
              <div class="l2-builder__cart-row">
                <div class="l2-qty" data-qty>
                  <button type="button" data-qty-minus aria-label="Restar">−</button>
                  <span data-qty-value>1</span>
                  <button type="button" data-qty-plus aria-label="Sumar">+</button>
                </div>
                <button type="button" class="l2-btn l2-btn--accent" data-add-to-cart>
                  Añadir al carrito
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>
    <?php endforeach; ?>

    <!-- ============ SECCIONES ESTÁNDAR ============ -->
    <?php foreach ($catalogo as $cat): ?>
      <section class="l2-cat-section" id="cat-<?= htmlspecialchars($cat['id']) ?>">
        <div class="l2-cat-section__head">
          <div class="l2-cat-section__icon"><?= render_icon($cat['icono']) ?></div>
          <h2><?= htmlspecialchars($cat['nombre']) ?></h2>
        </div>

        <div class="l2-grid">
          <?php foreach ($cat['items'] as $ii => $item): ?>
            <?php $tamanos = $item['tamanos'] ?? []; $t0 = $tamanos[0] ?? null; ?>
            <article class="l2-prod" data-catalog-item data-id="<?= htmlspecialchars($cat['id']) ?>-<?= $ii ?>" data-nombre="<?= htmlspecialchars($item['nombre']) ?>" data-price="<?= (float) ($t0['precio_final'] ?? $item['precio_final']) ?>" data-descuento="<?= htmlspecialchars($item['descuento'] ?? '') ?>" data-tamanos="<?= htmlspecialchars(json_encode($tamanos), ENT_QUOTES) ?>">
              <div class="l2-prod__media">
                <img src="<?= htmlspecialchars($item['imagen']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" loading="lazy">
              </div>
              <div class="l2-prod__body">
                <div class="l2-prod__top">
                  <h3><?= htmlspecialchars($item['nombre']) ?></h3>
                  <span class="l2-prod__price" data-price-display>
                    <?php $precioMostrado = $t0['precio'] ?? $item['precio']; $precioFinalMostrado = $t0['precio_final'] ?? $item['precio_final']; ?>
                    <?php if ($item['descuento']): ?>
                      <s class="l2-price-old">$<?= htmlspecialchars($precioMostrado) ?></s> $<?= htmlspecialchars($precioFinalMostrado) ?>
                    <?php else: ?>
                      $<?= htmlspecialchars($precioMostrado) ?>
                    <?php endif; ?>
                  </span>
                </div>
                <p><?= htmlspecialchars($item['desc']) ?></p>
                <?php if ($tamanos): ?>
                  <div class="l2-tamanos" data-tamanos-group>
                    <?php foreach ($tamanos as $ti => $t): ?>
                      <label class="l2-tamano-opt">
                        <input type="radio" name="tamano-<?= htmlspecialchars($cat['id']) ?>-<?= $ii ?>" value="<?= (int) $ti ?>" <?= $ti === 0 ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($t['nombre']) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <div class="l2-prod__cart-row">
                  <div class="l2-qty" data-qty>
                    <button type="button" data-qty-minus aria-label="Restar">−</button>
                    <span data-qty-value>1</span>
                    <button type="button" data-qty-plus aria-label="Sumar">+</button>
                  </div>
                  <button type="button" class="l2-prod__add" data-add-to-cart aria-label="Añadir <?= htmlspecialchars($item['nombre']) ?> al carrito">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                  </button>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

  </div>

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

<script src="../assets/js/main.js"></script>
</body>
</html>
