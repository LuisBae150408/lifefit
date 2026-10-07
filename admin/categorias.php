<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/includes/helpers.php';
admin_requiere_login();

$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id     = trim($_POST['id'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $icono  = $_POST['icono'] ?? 'bowl';
        $orden  = (int) ($_POST['orden'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;
        $esNueva = ($_POST['es_nueva'] ?? '') === '1';

        if ($id === '' || !preg_match('/^[a-z0-9-]+$/', $id) || $nombre === '') {
            flash_error('El identificador (solo minúsculas, números y guiones) y el nombre son obligatorios.');
        } else {
            if ($esNueva) {
                $existe = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE id = ?');
                $existe->execute([$id]);
                if ($existe->fetchColumn() > 0) {
                    flash_error('Ya existe una categoría con ese identificador.');
                    redirigir('categorias.php');
                }
                $pdo->prepare('INSERT INTO categorias (id, nombre, icono, orden, activo) VALUES (?,?,?,?,?)')
                    ->execute([$id, $nombre, $icono, $orden, $activo]);
                flash_ok('Categoría creada.');
            } else {
                $pdo->prepare('UPDATE categorias SET nombre = ?, icono = ?, orden = ?, activo = ? WHERE id = ?')
                    ->execute([$nombre, $icono, $orden, $activo, $id]);
                flash_ok('Categoría actualizada.');
            }
        }
    } elseif ($accion === 'borrar') {
        $id = $_POST['id'] ?? '';
        $pdo->prepare('DELETE FROM categorias WHERE id = ?')->execute([$id]);
        flash_ok('Categoría eliminada (y sus productos).');
    }

    redirigir('categorias.php');
}

$categorias = $pdo->query('SELECT * FROM categorias ORDER BY orden, nombre')->fetchAll();
$iconos = render_icon_keys();

$titulo_pagina = 'Categorías';
$subtitulo_pagina = 'Las secciones fijas del menú (Smoothies, Café, etc.). Los productos de cada una se editan en "Productos".';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-card">
  <h2>Categorías existentes</h2>
  <?php if (!$categorias): ?>
    <p class="admin-empty">Todavía no hay categorías.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th></th><th>Nombre</th><th>Id</th><th>Orden</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($categorias as $c): ?>
          <tr>
            <td style="width:30px;"><?= render_icon($c['icono']) ?></td>
            <td><?= htmlspecialchars($c['nombre']) ?></td>
            <td><code><?= htmlspecialchars($c['id']) ?></code></td>
            <td><?= (int) $c['orden'] ?></td>
            <td><span class="admin-badge <?= $c['activo'] ? 'admin-badge--on' : 'admin-badge--off' ?>"><?= $c['activo'] ? 'Visible' : 'Oculta' ?></span></td>
            <td>
              <button type="button" class="admin-btn admin-btn--ghost" onclick="editarCategoria(this, <?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">Editar</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="admin-card" id="form-categoria">
  <h2>Nueva categoría</h2>
  <form class="admin-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="es_nueva" value="1">

    <div class="row">
      <div>
        <label for="nombre">Nombre visible</label>
        <input type="text" id="nombre" name="nombre" required placeholder="ej: Postres Saludables">
      </div>
      <div>
        <label for="id">Identificador (id interno)</label>
        <input type="text" id="id" name="id" pattern="[a-z0-9-]+" required placeholder="ej: postres-saludables">
        <p class="admin-hint">Solo minúsculas, números y guiones. No se puede cambiar después de creada.</p>
      </div>
    </div>

    <div class="row">
      <div>
        <label for="icono">Ícono</label>
        <select id="icono" name="icono">
          <?php foreach ($iconos as $key): ?>
            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($key) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="orden">Orden</label>
        <input type="number" id="orden" name="orden" value="0">
      </div>
    </div>

    <div class="check">
      <input type="checkbox" id="activo" name="activo" checked>
      <label for="activo">Visible en el menú público</label>
    </div>

    <div class="admin-actions">
      <button class="admin-btn admin-btn--accent" type="submit">Guardar</button>
    </div>
  </form>
</div>

<form method="post" id="form-borrar" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="accion" value="borrar">
  <input type="hidden" name="id" id="borrar_id">
</form>

<script>
const CSRF_CATEGORIAS = <?= json_encode(admin_csrf_token()) ?>;
const ICONOS_OPTIONS_HTML = document.getElementById('icono').innerHTML;

function editarCategoria(boton, c) {
  const fila = boton.closest('tr');
  const html = `
    <h3>Editando: ${adminEscapeHtml(c.nombre)}</h3>
    <form class="admin-form" method="post">
      <input type="hidden" name="csrf" value="${CSRF_CATEGORIAS}">
      <input type="hidden" name="accion" value="guardar">

      <div class="row">
        <div>
          <label>Nombre visible</label>
          <input type="text" name="nombre" required>
        </div>
        <div>
          <label>Identificador (id interno)</label>
          <input type="text" name="id" readonly style="background:#eee;">
          <p class="admin-hint">No se puede cambiar después de creada.</p>
        </div>
      </div>

      <div class="row">
        <div>
          <label>Ícono</label>
          <select name="icono">${ICONOS_OPTIONS_HTML}</select>
        </div>
        <div>
          <label>Orden</label>
          <input type="number" name="orden" value="0">
        </div>
      </div>

      <div class="check">
        <input type="checkbox" name="activo" id="ec-activo">
        <label for="ec-activo">Visible en el menú público</label>
      </div>

      <div class="admin-actions">
        <button class="admin-btn admin-btn--accent" type="submit">Guardar</button>
        <button class="admin-btn admin-btn--danger" type="button" onclick="adminConfirmarYBorrar('¿Eliminar esta categoría? También se borran sus productos. Esto no se puede deshacer.', 'form-borrar', 'borrar_id', '${c.id}')">Eliminar categoría</button>
        <button class="admin-btn admin-btn--ghost" type="button" onclick="AdminEdit.close(true)">Descartar</button>
      </div>
    </form>`;

  const panel = AdminEdit.open(fila, html);
  if (!panel) return;
  panel.querySelector('[name="id"]').value = c.id;
  panel.querySelector('[name="nombre"]').value = c.nombre;
  panel.querySelector('[name="icono"]').value = c.icono;
  panel.querySelector('[name="orden"]').value = c.orden;
  panel.querySelector('[name="activo"]').checked = c.activo == 1;
  AdminEdit.snapshot();
}
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
