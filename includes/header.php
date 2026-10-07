<?php
/** header.php — nav fija + <head> */
$info = get_info_negocio();

// Favicon: mismo logo vectorizado, en un data URI (acento cálido)
$favicon_paths = implode('', array_map(fn($d) => '<path d="' . $d . '"/>', logo_paths_d()));
$favicon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . logo_viewbox() . '" fill="#c17f45">' . $favicon_paths . '</svg>';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LIFE FIT — <?= htmlspecialchars($info['eslogan']) ?></title>
<meta name="description" content="LIFE FIT · Bowls, ensaladas, smoothies, yogures, postres y café saludable en Parque Los Ilustres.">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="assets/css/style.css">

<!-- Favicon: logo LIFE FIT vectorizado -->
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode($favicon_svg) ?>">
</head>
<body data-theme="dark">

<header class="nav" id="site-nav">
  <a href="#top" class="nav__brand" aria-label="LIFE FIT — inicio">
    <?= render_logo('peanut-mark') ?>
    <span>LIFE FIT</span>
  </a>

  <nav class="nav__links" aria-label="Secciones">
    <a href="#menu">Menú</a>
    <a href="#contacto">Contacto</a>
  </nav>

  <button class="theme-toggle" id="theme-toggle" type="button" aria-label="Cambiar modo claro / oscuro" aria-pressed="false">
    <span class="theme-toggle__track">
      <span class="theme-toggle__thumb">
        <svg class="icon-moon" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z"/>
        </svg>
        <svg class="icon-sun" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="3.6"/>
          <path d="M12 3v2M12 19v2M21 12h-2M5 12H3M18.4 5.6l-1.4 1.4M7 16l-1.4 1.4M18.4 18.4 17 17M7 8 5.6 5.6"/>
        </svg>
      </span>
    </span>
  </button>

  <button class="nav__burger" id="nav-burger" type="button" aria-label="Abrir menú" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
</header>
