<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
admin_requiere_login();

$pdo = get_pdo();

$constructorId = $_GET['id'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM constructores WHERE id = ?');
$stmt->execute([$constructorId]);
$constructor = $stmt->fetch();

if (!$constructor) {
    flash_error('No se encontró ese constructor.');
    redirigir('constructores.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_grupo') {
        $grupoId = (int) ($_POST['grupo_id'] ?? 0);
        $nombre  = trim($_POST['nombre'] ?? '');
        $min     = (int) ($_POST['seleccion_min'] ?? 0);
        $maxRaw  = trim($_POST['seleccion_max'] ?? '');
        $max     = $maxRaw === '' ? null : (int) $maxRaw;
        $orden   = (int) ($_POST['orden'] ?? 0);

        if ($nombre === '') {
            flash_error('El grupo necesita un nombre.');
        } elseif ($grupoId > 0) {
            $pdo->prepare('UPDATE grupos_ingredientes SET nombre=?, seleccion_min=?, seleccion_max=?, orden=? WHERE id=? AND constructor_id=?')
                ->execute([$nombre, $min, $max, $orden, $grupoId, $constructorId]);
            flash_ok('Grupo actualizado.');
        } else {
            $pdo->prepare('INSERT INTO grupos_ingredientes (constructor_id, nombre, seleccion_min, seleccion_max, orden) VALUES (?,?,?,?,?)')
                ->execute([$constructorId, $nombre, $min, $max, $orden]);
            flash_ok('Grupo creado.');
        }
    } elseif ($accion === 'borrar_grupo') {
        $pdo->prepare('DELETE FROM grupos_ingredientes WHERE id = ? AND constructor_id = ?')
            ->execute([(int) ($_POST['grupo_id'] ?? 0), $constructorId]);
        flash_ok('Grupo eliminado (y sus ingredientes).');
    } elseif ($accion === 'guardar_ingrediente') {
        $ingId  = (int) ($_POST['ingrediente_id'] ?? 0);
        $grupoId = (int) ($_POST['grupo_id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $desc   = trim($_POST['descripcion'] ?? '');
        $extra  = (float) str_replace(',', '.', $_POST['precio_extra'] ?? '0');
        $orden  = (int) ($_POST['orden'] ?? 0);
        $disponible = isset($_POST['disponible']) ? 1 : 0;

        if ($nombre === '' || $grupoId <= 0) {
            flash_error('Faltan datos del ingrediente.');
        } elseif ($ingId > 0) {
            $pdo->prepare('UPDATE ingredientes SET nombre=?, descripcion=?, precio_extra=?, orden=?, disponible=? WHERE id=?')
                ->execute([$nombre, $desc, $extra, $orden, $disponible, $ingId]);
            flash_ok('Ingrediente actualizado.');
        } else {
            $pdo->prepare('INSERT INTO ingredientes (grupo_id, nombre, descripcion, precio_extra, orden, disponible) VALUES (?,?,?,?,?,?)')
                ->execute([$grupoId, $nombre, $desc, $extra, $orden, $disponible]);
            flash_ok('Ingrediente agregado.');
        }
    } elseif ($accion === 'borrar_ingrediente') {
        $pdo->prepare('DELETE FROM ingredientes WHERE id = ?')->execute([(int) ($_POST['ingrediente_id'] ?? 0)]);
        flash_ok('Ingrediente eliminado.');
    } elseif ($accion === 'guardar_plantilla') {
        $plantillaId = (int) ($_POST['plantilla_id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['ingredientes'] ?? []))));
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '' || count($ids) < 1) {
            flash_error('La plantilla necesita un nombre y al menos un ingrediente marcado.');
        } elseif ($plantillaId > 0) {
            $pdo->prepare('UPDATE plantillas_constructor SET nombre=?, ingredientes=?, activo=? WHERE id=? AND constructor_id=?')
                ->execute([$nombre, implode(',', $ids), $activo, $plantillaId, $constructorId]);
            flash_ok('Plantilla actualizada.');
        } else {
            $pdo->prepare('INSERT INTO plantillas_constructor (constructor_id, nombre, ingredientes, activo) VALUES (?,?,?,?)')
                ->execute([$constructorId, $nombre, implode(',', $ids), $activo]);
            flash_ok('Plantilla creada.');
        }
    } elseif ($accion === 'borrar_plantilla') {
        $pdo->prepare('DELETE FROM plantillas_constructor WHERE id = ? AND constructor_id = ?')
            ->execute([(int) ($_POST['plantilla_id'] ?? 0), $constructorId]);
        flash_ok('Plantilla eliminada.');
    }

    redirigir('constructor_ingredientes.php?id=' . urlencode($constructorId));
}

$grupos = $pdo->prepare('SELECT * FROM grupos_ingredientes WHERE constructor_id = ? ORDER BY orden, nombre');
$grupos->execute([$constructorId]);
$grupos = $grupos->fetchAll();

$stmtIng = $pdo->prepare('SELECT * FROM ingredientes WHERE grupo_id = ? ORDER BY orden, nombre');
foreach ($grupos as &$g) {
    $stmtIng->execute([$g['id']]);
    $g['ingredientes'] = $stmtIng->fetchAll();
}
unset($g);

// Mapa id ingrediente -> nombre (de TODOS los grupos de este constructor), para
// mostrar los nombres de cada plantilla sin tener que volver a consultar.
$mapaIdNombreIng = [];
foreach ($grupos as $g) {
    foreach ($g['ingredientes'] as $i) {
        $mapaIdNombreIng[(int) $i['id']] = $i['nombre'];
    }
}

$plantillas = $pdo->prepare('SELECT * FROM plantillas_constructor WHERE constructor_id = ? ORDER BY orden, nombre');
$plantillas->execute([$constructorId]);
$plantillas = $plantillas->fetchAll();
foreach ($plantillas as &$pl) {
    $idsPl = array_filter(array_map('trim', explode(',', (string) $pl['ingredientes'])));
    $pl['nombres'] = array_values(array_filter(array_map(
        static fn($id) => $mapaIdNombreIng[(int) $id] ?? null,
        $idsPl
    )));
}
unset($pl);

$titulo_pagina = 'Ingredientes — ' . $constructor['nombre'];
$subtitulo_pagina = 'Grupos y opciones que el cliente ve al personalizar este plato. El precio extra se suma al precio base.';
require __DIR__ . '/includes/layout_top.php';
?>

<p><a href="constructores.php" style="font-size:.85rem; font-weight:700;">← Volver a Constructores</a></p>

<?php foreach ($grupos as $g): ?>
  <div class="admin-card">
    <h2><?= htmlspecialchars($g['nombre']) ?>
      <span style="font-weight:400; font-size:.8rem; color:var(--ad-ink-soft);">
        (min <?= (int) $g['seleccion_min'] ?>, max <?= $g['seleccion_max'] === null ? '∞' : (int) $g['seleccion_max'] ?>)
      </span>
    </h2>

    <table class="admin-table">
      <thead><tr><th>Ingrediente</th><th>Descripción</th><th>Precio extra</th><th>Disponible</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($g['ingredientes'] as $i): ?>
          <tr>
            <td><?= htmlspecialchars($i['nombre']) ?></td>
            <td style="max-width:220px; white-space:normal; color:var(--ad-ink-soft); font-size:.82rem;"><?= htmlspecialchars($i['descripcion']) ?></td>
            <td>$<?= number_format((float) $i['precio_extra'], 2) ?></td>
            <td><span class="admin-badge <?= $i['disponible'] ? 'admin-badge--on' : 'admin-badge--off' ?>"><?= $i['disponible'] ? 'Sí' : 'No' ?></span></td>
            <td><button type="button" class="admin-btn admin-btn--ghost" onclick='editarIngrediente(this, <?= json_encode($i, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$g['ingredientes']): ?><tr><td colspan="5" class="admin-empty">Sin ingredientes todavía.</td></tr><?php endif; ?>
      </tbody>
    </table>

    <div class="admin-actions">
      <button class="admin-btn admin-btn--accent" type="button" onclick='nuevoIngrediente(this, <?= (int) $g['id'] ?>, <?= json_encode($g['nombre'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>+ Agregar ingrediente</button>
      <button class="admin-btn admin-btn--ghost" type="button" onclick='editarGrupo(this, <?= json_encode($g, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar grupo</button>
      <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar el grupo &quot;<?= htmlspecialchars($g['nombre'], ENT_QUOTES) ?>&quot; y todos sus ingredientes?');">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="borrar_grupo">
        <input type="hidden" name="grupo_id" value="<?= (int) $g['id'] ?>">
        <button class="admin-btn admin-btn--danger" type="submit">Eliminar grupo</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$grupos): ?><p class="admin-empty">Este constructor todavía no tiene grupos de ingredientes.</p><?php endif; ?>

<div class="admin-card">
  <h2>Plantillas</h2>
  <p class="admin-hint">Combinaciones ya armadas que el cliente puede elegir con un toque en vez de marcar cada ingrediente a mano (ej. "Poke Clásico"). Las cargás vos desde acá; el cliente solo las ve y las toca.</p>

  <?php if ($plantillas): ?>
    <table class="admin-table">
      <thead><tr><th>Nombre</th><th>Ingredientes</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($plantillas as $pl): ?>
          <tr>
            <td><?= htmlspecialchars($pl['nombre']) ?></td>
            <td style="max-width:280px; white-space:normal; color:var(--ad-ink-soft); font-size:.82rem;"><?= htmlspecialchars(implode(', ', $pl['nombres'])) ?></td>
            <td><span class="admin-badge <?= $pl['activo'] ? 'admin-badge--on' : 'admin-badge--off' ?>"><?= $pl['activo'] ? 'Visible' : 'Oculta' ?></span></td>
            <td><button type="button" class="admin-btn admin-btn--ghost" onclick='editarPlantilla(this, <?= json_encode($pl, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="admin-empty">Sin plantillas todavía.</p>
  <?php endif; ?>

  <div class="admin-actions">
    <button class="admin-btn admin-btn--accent" type="button" onclick="nuevaPlantilla(this)">+ Agregar plantilla</button>
  </div>
</div>

<form method="post" id="form-borrar-plantilla" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="accion" value="borrar_plantilla">
  <input type="hidden" name="plantilla_id" id="borrar_plantilla_id">
</form>

<!-- Formulario de grupo: solo para crear uno nuevo. Editar un grupo existente
     abre un panel inline justo debajo de sus acciones, más arriba. -->
<div class="admin-card" id="form-grupo">
  <h2>Nuevo grupo</h2>
  <form class="admin-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar_grupo">
    <input type="hidden" name="grupo_id" value="0">

    <label for="g_nombre">Nombre del grupo</label>
    <input type="text" id="g_nombre" name="nombre" required placeholder="ej: Toppings">

    <div class="row">
      <div>
        <label for="seleccion_min">Mínimo a elegir</label>
        <input type="number" id="seleccion_min" name="seleccion_min" value="0" min="0">
      </div>
      <div>
        <label for="seleccion_max">Máximo a elegir</label>
        <input type="number" id="seleccion_max" name="seleccion_max" min="1">
        <p class="admin-hint">Dejalo vacío para "sin límite". Poné 1 para que sea de elección única.</p>
      </div>
      <div>
        <label for="g_orden">Orden</label>
        <input type="number" id="g_orden" name="orden" value="0">
      </div>
    </div>

    <div class="admin-actions">
      <button class="admin-btn admin-btn--accent" type="submit">Guardar grupo</button>
    </div>
  </form>
</div>

<form method="post" id="form-borrar-ing" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="accion" value="borrar_ingrediente">
  <input type="hidden" name="ingrediente_id" id="borrar_ing_id">
</form>

<script>
const CSRF_INGREDIENTES = <?= json_encode(admin_csrf_token()) ?>;
// Grupos con sus ingredientes, para armar el checklist de cada plantilla.
const GRUPOS_PARA_PLANTILLA = <?= json_encode(array_map(
    static fn($g) => ['nombre' => $g['nombre'], 'ingredientes' => array_map(
        static fn($i) => ['id' => (int) $i['id'], 'nombre' => $i['nombre']], $g['ingredientes']
    )], $grupos
), JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function checklistPlantillaHtml(idsMarcados) {
  return GRUPOS_PARA_PLANTILLA.map(g => `
    <p style="font-weight:700; font-size:.82rem; margin:10px 0 4px;">${adminEscapeHtml(g.nombre)}</p>
    ${g.ingredientes.map(i => `
      <div class="check">
        <input type="checkbox" name="ingredientes[]" value="${i.id}" id="pl-ing-${i.id}" ${idsMarcados.includes(i.id) ? 'checked' : ''}>
        <label for="pl-ing-${i.id}">${adminEscapeHtml(i.nombre)}</label>
      </div>
    `).join('')}
  `).join('');
}

function nuevaPlantilla(boton) {
  const ancla = boton.closest('.admin-actions');
  const html = `
    <h3>Nueva plantilla</h3>
    <form class="admin-form" method="post">
      <input type="hidden" name="csrf" value="${CSRF_INGREDIENTES}">
      <input type="hidden" name="accion" value="guardar_plantilla">
      <input type="hidden" name="plantilla_id" value="0">

      <label>Nombre</label>
      <input type="text" name="nombre" required placeholder="ej: Poke Clásico">

      <p class="admin-hint">Marcá los ingredientes que se seleccionan solos al tocar esta plantilla:</p>
      ${checklistPlantillaHtml([])}

      <div class="check" style="margin-top:10px;">
        <input type="checkbox" name="activo" id="np-activo" checked>
        <label for="np-activo">Visible para el cliente</label>
      </div>

      <div class="admin-actions">
        <button class="admin-btn admin-btn--accent" type="submit">Guardar plantilla</button>
        <button class="admin-btn admin-btn--ghost" type="button" onclick="AdminEdit.close(true)">Descartar</button>
      </div>
    </form>`;

  const panel = AdminEdit.open(ancla, html);
  if (panel) AdminEdit.snapshot();
}

function editarPlantilla(boton, pl) {
  const fila = boton.closest('tr');
  const idsMarcados = pl.ingredientes.split(',').map(n => parseInt(n.trim(), 10)).filter(Boolean);
  const html = `
    <h3>Editando plantilla: ${adminEscapeHtml(pl.nombre)}</h3>
    <form class="admin-form" method="post">
      <input type="hidden" name="csrf" value="${CSRF_INGREDIENTES}">
      <input type="hidden" name="accion" value="guardar_plantilla">
      <input type="hidden" name="plantilla_id" value="${pl.id}">

      <label>Nombre</label>
      <input type="text" name="nombre" value="${adminEscapeHtml(pl.nombre)}" required>

      <p class="admin-hint">Marcá los ingredientes que se seleccionan solos al tocar esta plantilla:</p>
      ${checklistPlantillaHtml(idsMarcados)}

      <div class="check" style="margin-top:10px;">
        <input type="checkbox" name="activo" id="ep-activo" ${pl.activo == 1 ? 'checked' : ''}>
        <label for="ep-activo">Visible para el cliente</label>
      </div>

      <div class="admin-actions">
        <button class="admin-btn admin-btn--accent" type="submit">Guardar plantilla</button>
        <button class="admin-btn admin-btn--danger" type="button" onclick="adminConfirmarYBorrar('¿Eliminar esta plantilla?', 'form-borrar-plantilla', 'borrar_plantilla_id', ${pl.id})">Eliminar</button>
        <button class="admin-btn admin-btn--ghost" type="button" onclick="AdminEdit.close(true)">Descartar</button>
      </div>
    </form>`;

  const panel = AdminEdit.open(fila, html);
  if (panel) AdminEdit.snapshot();
}

function editarGrupo(boton, g) {
  const ancla = boton.closest('.admin-actions');
  const html = `
    <h3>Editando grupo: ${adminEscapeHtml(g.nombre)}</h3>
    <form class="admin-form" method="post">
      <input type="hidden" name="csrf" value="${CSRF_INGREDIENTES}">
      <input type="hidden" name="accion" value="guardar_grupo">
      <input type="hidden" name="grupo_id" value="${g.id}">

      <label>Nombre del grupo</label>
      <input type="text" name="nombre" required>

      <div class="row">
        <div>
          <label>Mínimo a elegir</label>
          <input type="number" name="seleccion_min" min="0">
        </div>
        <div>
          <label>Máximo a elegir</label>
          <input type="number" name="seleccion_max" min="1">
          <p class="admin-hint">Dejalo vacío para "sin límite". Poné 1 para que sea de elección única.</p>
        </div>
        <div>
          <label>Orden</label>
          <input type="number" name="orden" value="0">
        </div>
      </div>

      <div class="admin-actions">
        <button class="admin-btn admin-btn--accent" type="submit">Guardar grupo</button>
        <button class="admin-btn admin-btn--ghost" type="button" onclick="AdminEdit.close(true)">Descartar</button>
      </div>
    </form>`;

  const panel = AdminEdit.open(ancla, html);
  if (!panel) return;
  panel.querySelector('[name="nombre"]').value = g.nombre;
  panel.querySelector('[name="seleccion_min"]').value = g.seleccion_min;
  panel.querySelector('[name="seleccion_max"]').value = g.seleccion_max ?? '';
  panel.querySelector('[name="orden"]').value = g.orden;
  AdminEdit.snapshot();
}

function nuevoIngrediente(boton, grupoId, grupoNombre) {
  const ancla = boton.closest('.admin-actions');
  const html = `
    <h3>Nuevo ingrediente en "${adminEscapeHtml(grupoNombre)}"</h3>
    <form class="admin-form" method="post">
      <input type="hidden" name="csrf" value="${CSRF_INGREDIENTES}">
      <input type="hidden" name="accion" value="guardar_ingrediente">
      <input type="hidden" name="ingrediente_id" value="0">
      <input type="hidden" name="grupo_id" value="${grupoId}">

      <label>Nombre</label>
      <input type="text" name="nombre" required placeholder="ej: Almendras">

      <label>Descripción (opcional, ej. sabor de un aderezo)</label>
      <input type="text" name="descripcion">

      <div class="row">
        <div>
          <label>Precio extra ($)</label>
          <input type="number" name="precio_extra" step="0.01" min="0" value="0">
          <p class="admin-hint">0 si va incluido sin costo adicional.</p>
        </div>
        <div>
          <label>Orden</label>
          <input type="number" name="orden" value="0">
        </div>
      </div>

      <div class="check">
        <input type="checkbox" name="disponible" id="ni-disponible" checked>
        <label for="ni-disponible">Disponible</label>
      </div>

      <div class="admin-actions">
        <button class="admin-btn admin-btn--accent" type="submit">Guardar ingrediente</button>
        <button class="admin-btn admin-btn--ghost" type="button" onclick="AdminEdit.close(true)">Descartar</button>
      </div>
    </form>`;

  AdminEdit.open(ancla, html);
}

function editarIngrediente(boton, i) {
  const fila = boton.closest('tr');
  const html = `
    <h3>Editando: ${adminEscapeHtml(i.nombre)}</h3>
    <form class="admin-form" method="post">
      <input type="hidden" name="csrf" value="${CSRF_INGREDIENTES}">
      <input type="hidden" name="accion" value="guardar_ingrediente">
      <input type="hidden" name="ingrediente_id" value="${i.id}">
      <input type="hidden" name="grupo_id" value="${i.grupo_id}">

      <label>Nombre</label>
      <input type="text" name="nombre" required>

      <label>Descripción (opcional, ej. sabor de un aderezo)</label>
      <input type="text" name="descripcion">

      <div class="row">
        <div>
          <label>Precio extra ($)</label>
          <input type="number" name="precio_extra" step="0.01" min="0">
          <p class="admin-hint">0 si va incluido sin costo adicional.</p>
        </div>
        <div>
          <label>Orden</label>
          <input type="number" name="orden" value="0">
        </div>
      </div>

      <div class="check">
        <input type="checkbox" name="disponible" id="ei-disponible">
        <label for="ei-disponible">Disponible</label>
      </div>

      <div class="admin-actions">
        <button class="admin-btn admin-btn--accent" type="submit">Guardar ingrediente</button>
        <button class="admin-btn admin-btn--danger" type="button" onclick="adminConfirmarYBorrar('¿Eliminar este ingrediente?', 'form-borrar-ing', 'borrar_ing_id', ${i.id})">Eliminar</button>
        <button class="admin-btn admin-btn--ghost" type="button" onclick="AdminEdit.close(true)">Descartar</button>
      </div>
    </form>`;

  const panel = AdminEdit.open(fila, html);
  if (!panel) return;
  panel.querySelector('[name="nombre"]').value = i.nombre;
  panel.querySelector('[name="descripcion"]').value = i.descripcion;
  panel.querySelector('[name="precio_extra"]').value = i.precio_extra;
  panel.querySelector('[name="orden"]').value = i.orden;
  panel.querySelector('[name="disponible"]').checked = i.disponible == 1;
  AdminEdit.snapshot();
}
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
