<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/uploads.php';
require_once __DIR__ . '/includes/helpers.php';
admin_requiere_login();

$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id           = trim($_POST['id'] ?? '');
        $nombre       = trim($_POST['nombre'] ?? '');
        $descripcion  = trim($_POST['descripcion'] ?? '');
        $nota         = trim($_POST['nota'] ?? '');
        $precioBase   = (float) str_replace(',', '.', $_POST['precio_base'] ?? '0');
        $imagenActual = trim($_POST['imagen_actual'] ?? '');
        $icono        = $_POST['icono'] ?? 'bowl';
        $orden        = (int) ($_POST['orden'] ?? 0);
        $activo       = isset($_POST['activo']) ? 1 : 0;
        $esNueva      = ($_POST['es_nueva'] ?? '') === '1';

        if ($id === '' || !preg_match('/^[a-z0-9-]+$/', $id) || $nombre === '') {
            flash_error('El identificador (solo minúsculas, números y guiones) y el nombre son obligatorios.');
        } else {
            try {
                $imagenNueva = procesar_imagen_subida('imagen_archivo', 'constructores');
                $imagen = $imagenNueva ?? $imagenActual;

                if ($esNueva) {
                    $existe = $pdo->prepare('SELECT COUNT(*) FROM constructores WHERE id = ?');
                    $existe->execute([$id]);
                    if ($existe->fetchColumn() > 0) {
                        flash_error('Ya existe un constructor con ese identificador.');
                    } else {
                        $pdo->prepare('INSERT INTO constructores (id, nombre, descripcion, nota, precio_base, imagen, icono, orden, activo) VALUES (?,?,?,?,?,?,?,?,?)')
                            ->execute([$id, $nombre, $descripcion, $nota, $precioBase, $imagen, $icono, $orden, $activo]);
                        flash_ok('Constructor creado. Ahora agregale sus grupos de ingredientes.');
                    }
                } else {
                    $pdo->prepare('UPDATE constructores SET nombre=?, descripcion=?, nota=?, precio_base=?, imagen=?, icono=?, orden=?, activo=? WHERE id=?')
                        ->execute([$nombre, $descripcion, $nota, $precioBase, $imagen, $icono, $orden, $activo, $id]);
                    if ($imagenNueva !== null && $imagenActual !== '') {
                        borrar_imagen_subida($imagenActual);
                    }
                    flash_ok('Constructor actualizado.');
                }
            } catch (RuntimeException $e) {
                flash_error($e->getMessage());
            }
        }
    } elseif ($accion === 'borrar') {
        $idBorrar = $_POST['id'] ?? '';
        $fila = $pdo->prepare('SELECT imagen FROM constructores WHERE id = ?');
        $fila->execute([$idBorrar]);
        $imagenBorrar = $fila->fetchColumn();
        $pdo->prepare('DELETE FROM constructores WHERE id = ?')->execute([$idBorrar]);
        if ($imagenBorrar) {
            borrar_imagen_subida($imagenBorrar);
        }
        flash_ok('Constructor eliminado (y sus grupos/ingredientes).');
    }

    redirigir('constructores.php');
}

$constructores = $pdo->query('SELECT * FROM constructores ORDER BY orden, nombre')->fetchAll();
$iconos = render_icon_keys();

$titulo_pagina = 'Constructores';
$subtitulo_pagina = 'Las secciones "arma tu X". Los ingredientes de cada uno se editan aparte, en su propia página.';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-card">
  <h2>Constructores existentes</h2>
  <?php if (!$constructores): ?>
    <p class="admin-empty">Todavía no hay constructores.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th></th><th>Nombre</th><th>Id</th><th>Precio base</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($constructores as $c): ?>
          <tr>
            <td style="width:30px;"><?= render_icon($c['icono']) ?></td>
            <td><?= htmlspecialchars($c['nombre']) ?></td>
            <td><code><?= htmlspecialchars($c['id']) ?></code></td>
            <td>$<?= number_format((float) $c['precio_base'], 2) ?></td>
            <td><span class="admin-badge <?= $c['activo'] ? 'admin-badge--on' : 'admin-badge--off' ?>"><?= $c['activo'] ? 'Visible' : 'Oculto' ?></span></td>
            <td style="display:flex; gap:6px; flex-wrap:wrap;">
              <a class="admin-btn admin-btn--accent" href="constructor_ingredientes.php?id=<?= urlencode($c['id']) ?>">Ingredientes</a>
              <button type="button" class="admin-btn admin-btn--ghost" onclick='editarConstructor(this, <?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="admin-card" id="form-constructor">
  <h2>Nuevo constructor</h2>
  <form class="admin-form" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="es_nueva" value="1">
    <input type="hidden" name="imagen_actual" value="">

    <div class="row">
      <div>
        <label for="nombre">Nombre visible</label>
        <input type="text" id="nombre" name="nombre" required placeholder="ej: Crea tu Crepa">
      </div>
      <div>
        <label for="id">Identificador (id interno)</label>
        <input type="text" id="id" name="id" pattern="[a-z0-9-]+" required placeholder="ej: crepas">
      </div>
    </div>

    <label for="descripcion">Descripción (ej. la base fija)</label>
    <textarea id="descripcion" name="descripcion"></textarea>

    <label for="nota">Nota aclaratoria (opcional, ej. versión mini)</label>
    <input type="text" id="nota" name="nota">

    <div class="row">
      <div>
        <label for="precio_base">Precio base ($)</label>
        <input type="number" id="precio_base" name="precio_base" step="0.01" min="0" required>
      </div>
      <div>
        <label for="icono">Ícono</label>
        <select id="icono" name="icono">
          <?php foreach ($iconos as $key): ?><option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($key) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="orden">Orden</label>
        <input type="number" id="orden" name="orden" value="0">
      </div>
    </div>

    <label for="imagen_archivo">Foto del constructor</label>
    <input type="file" id="imagen_archivo" name="imagen_archivo" accept="image/*">
    <p class="admin-hint">Se comprime y guarda automáticamente en formato WEBP.</p>

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
const CSRF_CONSTRUCTORES = <?= json_encode(admin_csrf_token()) ?>;
const ICONOS_CONSTRUCTOR_OPTIONS_HTML = document.getElementById('icono').innerHTML;

function editarConstructor(boton, c) {
  const fila = boton.closest('tr');
  const html = `
    <h3>Editando: ${adminEscapeHtml(c.nombre)}</h3>
    <form class="admin-form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="${CSRF_CONSTRUCTORES}">
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="imagen_actual" value="${adminEscapeHtml(c.imagen || '')}">

      <div class="row">
        <div>
          <label>Nombre visible</label>
          <input type="text" name="nombre" required>
        </div>
        <div>
          <label>Identificador (id interno)</label>
          <input type="text" name="id" readonly style="background:#eee;">
        </div>
      </div>

      <label>Descripción (ej. la base fija)</label>
      <textarea name="descripcion"></textarea>

      <label>Nota aclaratoria (opcional, ej. versión mini)</label>
      <input type="text" name="nota">

      <div class="row">
        <div>
          <label>Precio base ($)</label>
          <input type="number" name="precio_base" step="0.01" min="0" required>
        </div>
        <div>
          <label>Ícono</label>
          <select name="icono">${ICONOS_CONSTRUCTOR_OPTIONS_HTML}</select>
        </div>
        <div>
          <label>Orden</label>
          <input type="number" name="orden" value="0">
        </div>
      </div>

      <label>Foto del constructor</label>
      ${c.imagen ? `<img src="${adminEscapeHtml(c.imagen)}" alt="" style="width:90px;height:90px;object-fit:cover;border-radius:10px;margin-bottom:8px;display:block;">` : ''}
      <input type="file" name="imagen_archivo" accept="image/*">
      <p class="admin-hint">Dejalo vacío para conservar la foto actual. Se comprime y guarda en WEBP.</p>

      <div class="check">
        <input type="checkbox" name="activo" id="ecn-activo">
        <label for="ecn-activo">Visible en el menú público</label>
      </div>

      <div class="admin-actions">
        <button class="admin-btn admin-btn--accent" type="submit">Guardar</button>
        <button class="admin-btn admin-btn--danger" type="button" onclick="adminConfirmarYBorrar('¿Eliminar este constructor? También se borran sus grupos e ingredientes. Esto no se puede deshacer.', 'form-borrar', 'borrar_id', '${c.id}')">Eliminar constructor</button>
        <button class="admin-btn admin-btn--ghost" type="button" onclick="AdminEdit.close(true)">Descartar</button>
      </div>
    </form>`;

  const panel = AdminEdit.open(fila, html);
  if (!panel) return;
  panel.querySelector('[name="id"]').value = c.id;
  panel.querySelector('[name="nombre"]').value = c.nombre;
  panel.querySelector('[name="descripcion"]').value = c.descripcion;
  panel.querySelector('[name="nota"]').value = c.nota;
  panel.querySelector('[name="precio_base"]').value = c.precio_base;
  panel.querySelector('[name="icono"]').value = c.icono;
  panel.querySelector('[name="orden"]').value = c.orden;
  panel.querySelector('[name="activo"]').checked = c.activo == 1;
  AdminEdit.snapshot();
}
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
