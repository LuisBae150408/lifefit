<?php
/**
 * layout_top.php -- cabecera compartida de todas las páginas del panel.
 * Espera que la página que lo incluye ya haya llamado admin_requiere_login()
 * y definido $titulo_pagina (string) y opcionalmente $subtitulo_pagina.
 */
$paginaActual = basename($_SERVER['SCRIPT_NAME']);
require_once __DIR__ . '/../../includes/logo.php';
$navItems = [
    'index.php'          => 'Inicio',
    'categorias.php'      => 'Categorías',
    'productos.php'       => 'Productos',
    'constructores.php'   => 'Constructores',
    'descuentos.php'      => 'Descuentos',
    'publicacion.php'     => 'Publicación',
    'configuracion.php'   => 'Configuración',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($titulo_pagina ?? 'Panel') ?> — Admin LIFE FIT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css">
<script src="../assets/js/admin-edit.js"></script>
</head>
<body class="admin">
<div class="admin-shell">

  <header class="admin-top">
    <span class="admin-top__brand">
      <?= render_logo('admin-top__logo') ?>
      LIFE FIT · Admin
    </span>
    <span class="admin-top__user">
      <?php if (!empty($_SESSION['admin_usuario'])): ?>
        Hola, <?= htmlspecialchars($_SESSION['admin_usuario']) ?>
      <?php endif; ?>
      <a href="logout.php">Cerrar sesión</a>
    </span>
  </header>

  <nav class="admin-nav">
    <?php foreach ($navItems as $href => $label): ?>
      <a href="<?= $href ?>" class="<?= $paginaActual === $href ? 'is-active' : '' ?>"><?= htmlspecialchars($label) ?></a>
    <?php endforeach; ?>
    <a href="../menu/" target="_blank" rel="noopener">Ver menú público ↗</a>
  </nav>

  <main class="admin-main">
    <div class="admin-head">
      <div>
        <h1><?= htmlspecialchars($titulo_pagina ?? '') ?></h1>
        <?php if (!empty($subtitulo_pagina)): ?><p><?= htmlspecialchars($subtitulo_pagina) ?></p><?php endif; ?>
      </div>
    </div>

    <?php if (!empty($_SESSION['flash_ok'])): ?>
      <div class="admin-alert admin-alert--ok"><?= htmlspecialchars($_SESSION['flash_ok']) ?></div>
      <?php unset($_SESSION['flash_ok']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="admin-alert admin-alert--error"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
      <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
