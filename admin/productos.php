<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_once __DIR__ . '/includes/helpers.php';
admin_requiere_login();

$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id            = (int) ($_POST['id'] ?? 0);
        $categoria     = $_POST['categoria_id'] ?? '';
        $nombre        = trim($_POST['nombre'] ?? '');
        $descripcion   = trim($_POST['descripcion'] ?? '');
        $precio        = (float) str_replace(',', '.', $_POST['precio'] ?? '0');
        $imagenActual  = trim($_POST['imagen_actual'] ?? '');
        $orden         = (int) ($_POST['orden'] ?? 0);
        $activo        = isset($_POST['activo']) ? 1 : 0;

        if ($categoria === '' || $nombre === '' || $precio < 0) {
            flash_error('Categoría, nombre y precio (≥ 0) son obligatorios.');
        } else {
            try {
                $imagenNueva = procesar_imagen_subida('imagen_archivo', 'productos');
                $imagen = $imagenNueva ?? $imagenActual;

                if ($id > 0) {
                    $pdo->prepare('UPDATE productos SET categoria_id=?, nombre=?, descripcion=?, precio=?, imagen=?, orden=?, activo=? WHERE id=?')
                        ->execute([$categoria, $nombre, $descripcion, $precio, $imagen, $orden, $activo, $id]);
                    if ($imagenNueva !== null && $imagenActual !== '') {
                        borrar_imagen_subida($imagenActual);
                    }
                    $idProducto = $id;
                    flash_ok('Producto actualizado.');
                } else {
                    $pdo->prepare('INSERT INTO productos (categoria_id, nombre, descripcion, precio, imagen, orden, activo) VALUES (?,?,?,?,?,?,?)')
                        ->execute([$categoria, $nombre, $descripcion, $precio, $imagen, $orden, $activo]);
                    $idProducto = (int) $pdo->lastInsertId();
                    flash_ok('Producto creado.');
                }

                // Tamaños (opcional): se borran y se vuelven a cargar los que
                // llegaron con nombre no vacío, en el orden en que están.
                $pdo->prepare('DELETE FROM tamanos_producto WHERE producto_id = ?')->execute([$idProducto]);
                $stmtTam = $pdo->prepare('INSERT INTO tamanos_producto (producto_id, nombre, precio, orden) VALUES (?,?,?,?)');
                $ordenTam = 0;
                foreach (($_POST['tamano_nombre'] ?? []) as $idx => $tNombre) {
                    $tNombre = trim($tNombre);
                    if ($tNombre === '') continue;
                    $tPrecio = (float) str_replace(',', '.', $_POST['tamano_precio'][$idx] ?? '0');
                    $stmtTam->execute([$idProducto, $tNombre, $tPrecio, $ordenTam++]);
                }
            } catch (RuntimeException $e) {
                flash_error($e->getMessage());
            }
        }
    } elseif ($accion === 'borrar') {
        $idBorrar = (int) ($_POST['id'] ?? 0);
        $fila = $pdo->prepare('SELECT imagen FROM productos WHERE id = ?');
        $fila->execute([$idBorrar]);
        $imagenBorrar = $fila->fetchColumn();
        $pdo->prepare('DELETE FROM productos WHERE id = ?')->execute([$idBorrar]);
        if ($imagenBorrar) {
            borrar_imagen_subida($imagenBorrar);
        }
        flash_ok('Producto eliminado.');
    }

    redirigir('productos.php' . (isset($_GET['cat']) ? '?cat=' . urlencode($_GET['cat']) : ''));
}

$categorias = $pdo->query('SELECT id, nombre FROM categorias ORDER BY orden, nombre')->fetchAll();
$catFiltro = $_GET['cat'] ?? '';

$sql = 'SELECT p.*, c.nombre AS categoria_nombre FROM productos p JOIN categorias c ON c.id = p.categoria_id';
if ($catFiltro !== '') {
    $sql .= ' WHERE p.categoria_id = ' . $pdo->quote($catFiltro);
}
$sql .= ' ORDER BY c.orden, p.orden, p.nombre';
$productos = $pdo->query($sql)->fetchAll();

$stmtTam = $pdo->prepare('SELECT nombre, precio FROM tamanos_producto WHERE producto_id = ? ORDER BY orden');
foreach ($productos as &$p) {
    $stmtTam->execute([$p['id']]);
    $p['tamanos'] = $stmtTam->fetchAll();
}
unset($p);

$titulo_pagina = 'Productos';
$subtitulo_pagina = 'Los platos de las secciones fijas del menú: nombre, descripción, foto y precio.';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-card">
  <h2>Filtrar por categoría</h2>
  <form method="get" class="admin-form">
    <select name="cat" onchange="this.form.submit()">
      <option value="">Todas las categorías</option>
      <?php foreach ($categorias as $c): ?>
        <option value="<?= htmlspecialchars($c['id']) ?>" <?= $catFiltro === $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div class="admin-card">
  <h2>Productos<?= $catFiltro ? ' — ' . htmlspecialchars($catFiltro) : '' ?></h2>
  <?php if (!$productos): ?>
    <p class="admin-empty">No hay productos<?= $catFiltro ? ' en esta categoría' : '' ?> todavía.</p>
  <?php else: ?>
    <table class="admin-table" id="tabla-productos">
      <thead><tr><th></th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($productos as $p): ?>
          <tr>
            <td><?php if ($p['imagen']): ?><img src="<?= htmlspecialchars($p['imagen']) ?>" alt=""><?php endif; ?></td>
            <td><?= htmlspecialchars($p['nombre']) ?></td>
            <td><?= htmlspecialchars($p['categoria_nombre']) ?></td>
            <td>
              <?php if ($p['tamanos']): ?>
                $<?= number_format(min(array_column($p['tamanos'], 'precio')), 2) ?>–$<?= number_format(max(array_column($p['tamanos'], 'precio')), 2) ?>
                <span class="admin-hint" style="display:block;"><?= count($p['tamanos']) ?> tamaños</span>
              <?php else: ?>
                $<?= number_format((float) $p['precio'], 2) ?>
              <?php endif; ?>
            </td>
            <td><span class="admin-badge <?= $p['activo'] ? 'admin-badge--on' : 'admin-badge--off' ?>"><?= $p['activo'] ? 'Visible' : 'Oculto' ?></span></td>
            <td><button type="button" class="admin-btn admin-btn--ghost" onclick='editarProducto(this, <?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="admin-card" id="form-producto">
  <h2>Nuevo producto</h2>
  <form class="admin-form" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="id" value="0">
    <input type="hidden" name="imagen_actual" value="">

    <label for="categoria_id">Categoría</label>
    <select id="categoria_id" name="categoria_id" required>
      <?php foreach ($categorias as $c): ?>
        <option value="<?= htmlspecialchars($c['id']) ?>"><?= htmlspecialchars($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="nombre">Nombre</label>
    <input type="text" id="nombre" name="nombre" required>

    <label for="descripcion">Descripción</label>
    <textarea id="descripcion" name="descripcion"></textarea>

    <div class="row">
      <div>
        <label for="precio">Precio ($)</label>
        <input type="number" id="precio" name="precio" step="0.01" min="0" required>
      </div>
      <div>
        <label for="orden">Orden</label>
        <input type="number" id="orden" name="orden" value="0">
      </div>
    </div>

    <label for="imagen_archivo">Foto del producto</label>
    <input type="file" id="imagen_archivo" name="imagen_archivo" accept="image/*">
    <p class="admin-hint">Se comprime y guarda automáticamente en formato WEBP.</p>

    <label>Tamaños (opcional)</label>
    <p class="admin-hint">Si cargás tamaños (ej: Chico/Grande, cada uno con su precio), el cliente elige uno en el menú en vez de ver el precio fijo de arriba. Dejalo vacío si el producto tiene un solo precio.</p>
    <div class="tam-filas" id="tam-filas-nuevo"></div>
    <div class="admin-actions" style="margin-top:0; margin-bottom:16px;">
      <button type="button" class="admin-btn admin-btn--ghost" onclick="agregarFilaTamano(document.getElementById('tam-filas-nuevo'))">+ Agregar tamaño</button>
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
const CSRF_PRODUCTOS = <?= json_encode(admin_csrf_token()) ?>;
const CATEGORIAS_OPTIONS_HTML = document.getElementById('categoria_id').innerHTML;

function filaTamanoHtml(nombre, precio) {
  return `<div class="row tam-fila">
    <div><input type="text" name="tamano_nombre[]" placeholder="ej: Grande" value="${adminEscapeHtml(nombre || '')}"></div>
    <div><input type="number" name="tamano_precio[]" step="0.01" min="0" placeholder="Precio" value="${precio ?? ''}"></div>
    <div><button type="button" class="admin-btn admin-btn--ghost" onclick="this.closest('.tam-fila').remove()">Quitar</button></div>
  </div>`;
}
function agregarFilaTamano(contenedor, nombre, precio) {
  contenedor.insertAdjacentHTML('beforeend', filaTamanoHtml(nombre, precio));
}

function editarProducto(boton, p) {
  const fila = boton.closest('tr');
  const html = `
    <h3>Editando: ${adminEscapeHtml(p.nombre)}</h3>
    <form class="admin-form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="${CSRF_PRODUCTOS}">
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="id" value="${p.id}">
      <input type="hidden" name="imagen_actual" value="${adminEscapeHtml(p.imagen || '')}">

      <label>Categoría</label>
      <select name="categoria_id" required>${CATEGORIAS_OPTIONS_HTML}</select>

      <label>Nombre</label>
      <input type="text" name="nombre" required>

      <label>Descripción</label>
      <textarea name="descripcion"></textarea>

      <div class="row">
        <div>
          <label>Precio ($)</label>
          <input type="number" name="precio" step="0.01" min="0" required>
        </div>
        <div>
          <label>Orden</label>
          <input type="number" name="orden" value="0">
        </div>
      </div>

      <label>Foto del producto</label>
      ${p.imagen ? `<img src="${adminEscapeHtml(p.imagen)}" alt="" style="width:90px;height:90px;object-fit:cover;border-radius:10px;margin-bottom:8px;display:block;">` : ''}
      <input type="file" name="imagen_archivo" accept="image/*">
      <p class="admin-hint">Dejalo vacío para conservar la foto actual. Se comprime y guarda en WEBP.</p>

      <label>Tamaños (opcional)</label>
      <p class="admin-hint">Si cargás tamaños, el cliente elige uno en vez de ver el precio fijo de arriba.</p>
      <div class="tam-filas" id="tam-filas-${p.id}"></div>
      <div class="admin-actions" style="margin-top:0; margin-bottom:16px;">
        <button type="button" class="admin-btn admin-btn--ghost" onclick="agregarFilaTamano(document.getElementById('tam-filas-${p.id}'))">+ Agregar tamaño</button>
      </div>

      <div class="check">
        <input type="checkbox" name="activo" id="ep-activo">
        <label for="ep-activo">Visible en el menú público</label>
      </div>

      <div class="admin-actions">
        <button class="admin-btn admin-btn--accent" type="submit">Guardar</button>
        <button class="admin-btn admin-btn--danger" type="button" onclick="adminConfirmarYBorrar('¿Eliminar este producto? Esto no se puede deshacer.', 'form-borrar', 'borrar_id', ${p.id})">Eliminar producto</button>
        <button class="admin-btn admin-btn--ghost" type="button" onclick="AdminEdit.close(true)">Descartar</button>
      </div>
    </form>`;

  const panel = AdminEdit.open(fila, html);
  if (!panel) return;
  panel.querySelector('[name="categoria_id"]').value = p.categoria_id;
  panel.querySelector('[name="nombre"]').value = p.nombre;
  panel.querySelector('[name="descripcion"]').value = p.descripcion;
  panel.querySelector('[name="precio"]').value = p.precio;
  panel.querySelector('[name="orden"]').value = p.orden;
  panel.querySelector('[name="activo"]').checked = p.activo == 1;
  const contenedorTam = panel.querySelector(`#tam-filas-${p.id}`);
  (p.tamanos || []).forEach(t => agregarFilaTamano(contenedorTam, t.nombre, t.precio));
  AdminEdit.snapshot();
}
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
