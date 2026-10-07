<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
admin_requiere_login();

$pdo = get_pdo();
$stats = [
    'Categorías'    => (int) $pdo->query('SELECT COUNT(*) FROM categorias')->fetchColumn(),
    'Productos'     => (int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn(),
    'Constructores' => (int) $pdo->query('SELECT COUNT(*) FROM constructores')->fetchColumn(),
    'Ingredientes'  => (int) $pdo->query('SELECT COUNT(*) FROM ingredientes')->fetchColumn(),
    'Descuentos activos' => (int) $pdo->query("SELECT COUNT(*) FROM descuentos WHERE activo = 1")->fetchColumn(),
];

$titulo_pagina = 'Inicio';
$subtitulo_pagina = 'Resumen rápido del contenido del menú.';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-grid">
  <?php foreach ($stats as $label => $n): ?>
    <div class="admin-stat">
      <div class="n"><?= $n ?></div>
      <div class="l"><?= htmlspecialchars($label) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="admin-card" style="margin-top:20px;">
  <h2>¿Por dónde empezar?</h2>
  <p style="color:var(--ad-ink-soft); font-size:.9rem; line-height:1.6;">
    <strong>Categorías</strong> y <strong>Productos</strong> son las secciones fijas del menú (Smoothies, Jugos Vitales, Café, Postres).<br>
    <strong>Constructores</strong> son las secciones personalizables ("Crea tu Yogurt", "Arma tu Poke", etc.) — ahí también editás sus grupos de ingredientes y el precio de cada uno.<br>
    <strong>Descuentos</strong> aplican a todo el menú, una categoría, un producto, un constructor completo o un ingrediente específico, y podés limitarlos a ciertos días de la semana.
  </p>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
