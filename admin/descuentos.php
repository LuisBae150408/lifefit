<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
admin_requiere_login();

$pdo = get_pdo();

const DIAS_SEMANA = ['lun' => 'Lunes', 'mar' => 'Martes', 'mie' => 'Miércoles', 'jue' => 'Jueves', 'vie' => 'Viernes', 'sab' => 'Sábado', 'dom' => 'Domingo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id      = (int) ($_POST['id'] ?? 0);
        $nombre  = trim($_POST['nombre'] ?? '');
        $tipo    = in_array($_POST['tipo'] ?? '', ['porcentaje', 'monto'], true) ? $_POST['tipo'] : 'porcentaje';
        $valor   = (float) str_replace(',', '.', $_POST['valor'] ?? '0');
        $alcance = $_POST['alcance'] ?? 'global';
        $dias    = implode(',', array_intersect($_POST['dias'] ?? [], array_keys(DIAS_SEMANA)));
        $dias    = $dias === '' ? null : $dias;
        $activo  = isset($_POST['activo']) ? 1 : 0;

        if ($alcance === 'combo') {
            $referenciaId = trim($_POST['combo_constructor_id'] ?? '');
            $comboIds = array_values(array_unique(array_filter(array_map('intval', $_POST['combo_ingredientes'] ?? []))));
            $comboIngredientes = $comboIds ? implode(',', $comboIds) : null;
        } else {
            $referenciaId = $alcance === 'global' ? null : trim($_POST['referencia_id'] ?? '');
            $comboIngredientes = null;
        }

        if ($nombre === '' || $valor <= 0) {
            flash_error('El nombre y un valor mayor a 0 son obligatorios.');
        } elseif ($alcance === 'combo' && ($referenciaId === '' || count($comboIds) < 1)) {
            flash_error('Para este tipo de descuento, elegí el constructor y al menos 1 ingrediente.');
        } elseif ($alcance !== 'global' && $alcance !== 'combo' && $referenciaId === '') {
            flash_error('Elegí a qué aplica el descuento.');
        } elseif ($tipo === 'porcentaje' && $valor > 100) {
            flash_error('Un descuento por porcentaje no puede ser mayor a 100.');
        } elseif ($id > 0) {
            $pdo->prepare('UPDATE descuentos SET nombre=?, tipo=?, valor=?, alcance=?, referencia_id=?, combo_ingredientes=?, dias=?, activo=? WHERE id=?')
                ->execute([$nombre, $tipo, $valor, $alcance, $referenciaId, $comboIngredientes, $dias, $activo, $id]);
            flash_ok('Descuento actualizado.');
        } else {
            $pdo->prepare('INSERT INTO descuentos (nombre, tipo, valor, alcance, referencia_id, combo_ingredientes, dias, activo) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$nombre, $tipo, $valor, $alcance, $referenciaId, $comboIngredientes, $dias, $activo]);
            flash_ok('Descuento creado.');
        }
    } elseif ($accion === 'borrar') {
        $pdo->prepare('DELETE FROM descuentos WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash_ok('Descuento eliminado.');
    }

    redirigir('descuentos.php');
}

$descuentos    = $pdo->query('SELECT * FROM descuentos ORDER BY activo DESC, creado_en DESC')->fetchAll();
$categorias    = $pdo->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();
$productos     = $pdo->query('SELECT id, nombre, categoria_id FROM productos ORDER BY nombre')->fetchAll();
$constructores = $pdo->query('SELECT id, nombre FROM constructores ORDER BY nombre')->fetchAll();
$ingredientes  = $pdo->query(
    'SELECT i.id, i.nombre, g.nombre AS grupo_nombre, c.id AS constructor_id, c.nombre AS constructor_nombre
     FROM ingredientes i
     JOIN grupos_ingredientes g ON g.id = i.grupo_id
     JOIN constructores c ON c.id = g.constructor_id
     ORDER BY c.nombre, g.nombre, i.nombre'
)->fetchAll();

// Mapas id -> nombre legible, para mostrar la tabla sin sub-consultas.
$mapaCategorias = array_column($categorias, 'nombre', 'id');
$mapaProductos = array_column($productos, 'nombre', 'id');
$mapaConstructores = array_column($constructores, 'nombre', 'id');
$mapaIngredientes = [];
foreach ($ingredientes as $i) {
    $mapaIngredientes[$i['id']] = $i['nombre'] . ' (' . $i['constructor_nombre'] . ' → ' . $i['grupo_nombre'] . ')';
}

function etiqueta_alcance(array $d, array $mapas): string
{
    if ($d['alcance'] === 'global') return 'Todo el menú';
    [$cat, $prod, $con, $ing] = $mapas;
    $refId = $d['referencia_id'];
    return match ($d['alcance']) {
        'categoria'   => 'Categoría: ' . ($cat[$refId] ?? "#$refId"),
        'producto'    => 'Producto: ' . ($prod[$refId] ?? "#$refId"),
        'constructor' => 'Constructor: ' . ($con[$refId] ?? "#$refId"),
        'ingrediente' => 'Ingrediente: ' . ($ing[$refId] ?? "#$refId"),
        'combo'       => 'Combo en ' . ($con[$refId] ?? "#$refId") . ': ' . implode(' + ', array_map(
            static fn($idIng) => explode(' (', $ing[trim($idIng)] ?? "#$idIng")[0],
            array_filter(explode(',', (string) ($d['combo_ingredientes'] ?? '')))
        )),
        default       => $d['alcance'],
    };
}

$titulo_pagina = 'Descuentos';
$subtitulo_pagina = 'Aplicá un descuento a todo el menú, una categoría, un producto, un constructor completo, el precio extra de un ingrediente puntual, o al precio final del pedido cuando se elige uno o varios ingredientes específicos juntos — con o sin restricción de día.';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-card">
  <h2>Descuentos existentes</h2>
  <?php if (!$descuentos): ?>
    <p class="admin-empty">Todavía no hay descuentos configurados.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>Nombre</th><th>Aplica a</th><th>Valor</th><th>Días</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($descuentos as $d): ?>
          <tr>
            <td><?= htmlspecialchars($d['nombre']) ?></td>
            <td><?= htmlspecialchars(etiqueta_alcance($d, [$mapaCategorias, $mapaProductos, $mapaConstructores, $mapaIngredientes])) ?></td>
            <td><?= $d['tipo'] === 'porcentaje' ? number_format((float) $d['valor'], 0) . '%' : '$' . number_format((float) $d['valor'], 2) ?></td>
            <td><?= $d['dias'] ? htmlspecialchars(implode(', ', array_map(fn($c) => DIAS_SEMANA[$c] ?? $c, explode(',', $d['dias'])))) : 'Todos' ?></td>
            <td><span class="admin-badge <?= $d['activo'] ? 'admin-badge--on' : 'admin-badge--off' ?>"><?= $d['activo'] ? 'Activo' : 'Pausado' ?></span></td>
            <td><button type="button" class="admin-btn admin-btn--ghost" onclick='editarDescuento(this, <?= json_encode($d, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="admin-card" id="form-descuento">
  <h2>Nuevo descuento</h2>
  <form class="admin-form" method="post" id="descuento-form">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="id" value="0">

    <label for="nombre">Nombre (para vos, no lo ve el cliente)</label>
    <input type="text" id="nombre" name="nombre" required placeholder="ej: Martes de Batidos">

    <div class="row">
      <div>
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
          <option value="porcentaje">Porcentaje (%)</option>
          <option value="monto">Monto fijo ($)</option>
        </select>
      </div>
      <div>
        <label for="valor">Valor</label>
        <input type="number" id="valor" name="valor" step="0.01" min="0" required>
      </div>
    </div>

    <label for="alcance">¿A qué aplica?</label>
    <select id="alcance" name="alcance" onchange="mostrarReferencia(this.closest('form'))">
      <option value="global">Todo el menú</option>
      <option value="categoria">Una categoría</option>
      <option value="producto">Un producto puntual</option>
      <option value="constructor">Un constructor completo (ej. Arma tu Poke)</option>
      <option value="ingrediente">Un ingrediente específico</option>
      <option value="combo">Selección de ingrediente(s) específica</option>
    </select>

    <div id="ref-categoria" data-ref-block style="display:none;">
      <label for="ref_categoria">Categoría</label>
      <select id="ref_categoria" class="ref-input" data-alcance="categoria">
        <?php foreach ($categorias as $c): ?><option value="<?= htmlspecialchars($c['id']) ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
      </select>
    </div>

    <div id="ref-producto" data-ref-block style="display:none;">
      <label for="ref_producto">Producto</label>
      <select id="ref_producto" class="ref-input" data-alcance="producto">
        <?php foreach ($productos as $p): ?><option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option><?php endforeach; ?>
      </select>
    </div>

    <div id="ref-constructor" data-ref-block style="display:none;">
      <label for="ref_constructor">Constructor</label>
      <select id="ref_constructor" class="ref-input" data-alcance="constructor">
        <?php foreach ($constructores as $c): ?><option value="<?= htmlspecialchars($c['id']) ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
      </select>
    </div>

    <div id="ref-ingrediente" data-ref-block style="display:none;">
      <label for="ref_ingrediente">Ingrediente</label>
      <select id="ref_ingrediente" class="ref-input" data-alcance="ingrediente">
        <?php $ultimoConstructor = null; foreach ($ingredientes as $i): ?>
          <?php if ($i['constructor_nombre'] !== $ultimoConstructor): if ($ultimoConstructor !== null) echo '</optgroup>'; ?>
            <optgroup label="<?= htmlspecialchars($i['constructor_nombre']) ?>">
          <?php $ultimoConstructor = $i['constructor_nombre']; endif; ?>
          <option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars($i['grupo_nombre'] . ' → ' . $i['nombre']) ?></option>
        <?php endforeach; if ($ingredientes) echo '</optgroup>'; ?>
      </select>
    </div>

    <div id="ref-combo" data-ref-block data-combo-block style="display:none;">
      <label for="combo_constructor_id">Constructor</label>
      <select id="combo_constructor_id" name="combo_constructor_id" class="ref-input" data-alcance="combo" onchange="renderComboIngredientes(this.value, [], this.closest('[data-ref-block]').querySelector('[data-combo-ing-list]'), 'n')">
        <option value="">Elegí un constructor…</option>
        <?php foreach ($constructores as $c): ?><option value="<?= htmlspecialchars($c['id']) ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
      </select>
      <label>Ingredientes que deben estar todos elegidos</label>
      <div class="combo-ing-list" data-combo-ing-list>
        <p class="admin-hint">Elegí primero el constructor de arriba.</p>
      </div>
      <p class="admin-hint">El descuento se aplica al precio final del pedido cuando el cliente elige (entre otros que quiera agregar) TODOS los ingredientes marcados aquí — puede ser uno solo o varios juntos.</p>
    </div>
    <input type="hidden" name="referencia_id" id="referencia_id">

    <label>Días en que aplica</label>
    <div class="row" style="grid-template-columns:repeat(auto-fit,minmax(100px,1fr)); gap:8px;">
      <?php foreach (DIAS_SEMANA as $codigo => $label): ?>
        <div class="check" style="margin-top:0;">
          <input type="checkbox" id="dia_<?= $codigo ?>" name="dias[]" value="<?= $codigo ?>">
          <label for="dia_<?= $codigo ?>"><?= $label ?></label>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="admin-hint">Si no marcás ningún día, aplica todos los días.</p>

    <div class="check">
      <input type="checkbox" id="activo" name="activo" checked>
      <label for="activo">Descuento activo</label>
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
const CSRF_DESCUENTOS = <?= json_encode(admin_csrf_token()) ?>;
const DIAS_SEMANA_JS = <?= json_encode(DIAS_SEMANA) ?>;
const REF_CATEGORIA_OPTIONS_HTML = document.getElementById('ref_categoria').innerHTML;
const REF_PRODUCTO_OPTIONS_HTML = document.getElementById('ref_producto').innerHTML;
const REF_CONSTRUCTOR_OPTIONS_HTML = document.getElementById('ref_constructor').innerHTML;
const REF_INGREDIENTE_OPTIONS_HTML = document.getElementById('ref_ingrediente').innerHTML;
const COMBO_CONSTRUCTOR_OPTIONS_HTML = document.getElementById('combo_constructor_id').innerHTML
  .replace('<option value="">Elegí un constructor…</option>', ''); // el placeholder ya se pone a mano en cada copia

// Ingredientes de cada constructor, agrupados por su grupo, para armar
// los checkboxes del combo dinámicamente según el constructor elegido.
const INGREDIENTES_POR_CONSTRUCTOR = <?php
    $porConstructor = [];
    foreach ($ingredientes as $i) {
        $porConstructor[$i['constructor_id']][$i['grupo_nombre']][] = ['id' => (int) $i['id'], 'nombre' => $i['nombre']];
    }
    echo json_encode($porConstructor);
?>;

/** Pinta los checkboxes de ingredientes de un constructor para armar un combo. */
function renderComboIngredientes(constructorId, seleccionados, contenedorEl, prefijo) {
  seleccionados = (seleccionados || []).map(Number);
  const grupos = INGREDIENTES_POR_CONSTRUCTOR[constructorId];
  if (!constructorId || !grupos) {
    contenedorEl.innerHTML = '<p class="admin-hint">Elegí primero el constructor de arriba.</p>';
    return;
  }
  contenedorEl.innerHTML = Object.entries(grupos).map(([grupoNombre, ings]) => `
    <p class="admin-hint" style="margin:10px 0 4px; font-weight:700; color:var(--ad-ink);">${adminEscapeHtml(grupoNombre)}</p>
    <div class="row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:6px;">
      ${ings.map(ing => `
        <div class="check" style="margin-top:0;">
          <input type="checkbox" id="ci-${prefijo}-${ing.id}" name="combo_ingredientes[]" value="${ing.id}" ${seleccionados.includes(ing.id) ? 'checked' : ''}>
          <label for="ci-${prefijo}-${ing.id}">${adminEscapeHtml(ing.nombre)}</label>
        </div>`).join('')}
    </div>`).join('');
}

// Muestra/oculta el select de referencia correspondiente al alcance elegido.
// Funciona tanto en el formulario "Nuevo descuento" de abajo como en
// cualquier panel de edición inline (recibe el <form> como contexto).
function mostrarReferencia(form, valorPreseleccionado) {
  const alcance = form.querySelector('[name="alcance"]').value;
  form.querySelectorAll('.ref-input').forEach(sel => {
    const bloque = sel.closest('[data-ref-block]');
    const activo = sel.dataset.alcance === alcance;
    bloque.style.display = activo ? 'block' : 'none';
    if (activo) {
      if (valorPreseleccionado !== undefined) sel.value = valorPreseleccionado;
      form.querySelector('[name="referencia_id"]').value = sel.value;
    }
  });
  if (alcance === 'global') form.querySelector('[name="referencia_id"]').value = '';
}
document.querySelectorAll('#form-descuento .ref-input').forEach(sel => {
  sel.addEventListener('change', () => { document.getElementById('referencia_id').value = sel.value; });
});
document.getElementById('descuento-form').addEventListener('submit', () => {
  mostrarReferencia(document.getElementById('descuento-form'));
});

function diasCheckboxesHtml(prefijo) {
  return Object.entries(DIAS_SEMANA_JS).map(([codigo, label]) => `
    <div class="check" style="margin-top:0;">
      <input type="checkbox" id="${prefijo}_${codigo}" name="dias[]" value="${codigo}">
      <label for="${prefijo}_${codigo}">${label}</label>
    </div>`).join('');
}

function editarDescuento(boton, d) {
  const fila = boton.closest('tr');
  const html = `
    <h3>Editando: ${adminEscapeHtml(d.nombre)}</h3>
    <form class="admin-form" method="post">
      <input type="hidden" name="csrf" value="${CSRF_DESCUENTOS}">
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="id" value="${d.id}">

      <label>Nombre (para vos, no lo ve el cliente)</label>
      <input type="text" name="nombre" required>

      <div class="row">
        <div>
          <label>Tipo</label>
          <select name="tipo">
            <option value="porcentaje">Porcentaje (%)</option>
            <option value="monto">Monto fijo ($)</option>
          </select>
        </div>
        <div>
          <label>Valor</label>
          <input type="number" name="valor" step="0.01" min="0" required>
        </div>
      </div>

      <label>¿A qué aplica?</label>
      <select name="alcance" onchange="mostrarReferencia(this.closest('form'))">
        <option value="global">Todo el menú</option>
        <option value="categoria">Una categoría</option>
        <option value="producto">Un producto puntual</option>
        <option value="constructor">Un constructor completo (ej. Arma tu Poke)</option>
        <option value="ingrediente">Un ingrediente específico</option>
        <option value="combo">Selección de ingrediente(s) específica</option>
      </select>

      <div data-ref-block style="display:none;">
        <label>Categoría</label>
        <select class="ref-input" data-alcance="categoria">${REF_CATEGORIA_OPTIONS_HTML}</select>
      </div>
      <div data-ref-block style="display:none;">
        <label>Producto</label>
        <select class="ref-input" data-alcance="producto">${REF_PRODUCTO_OPTIONS_HTML}</select>
      </div>
      <div data-ref-block style="display:none;">
        <label>Constructor</label>
        <select class="ref-input" data-alcance="constructor">${REF_CONSTRUCTOR_OPTIONS_HTML}</select>
      </div>
      <div data-ref-block style="display:none;">
        <label>Ingrediente</label>
        <select class="ref-input" data-alcance="ingrediente">${REF_INGREDIENTE_OPTIONS_HTML}</select>
      </div>
      <div data-ref-block data-combo-block style="display:none;">
        <label>Constructor</label>
        <select name="combo_constructor_id" class="ref-input" data-alcance="combo" onchange="renderComboIngredientes(this.value, [], this.closest('[data-ref-block]').querySelector('[data-combo-ing-list]'), 'e')">
          <option value="">Elegí un constructor…</option>${COMBO_CONSTRUCTOR_OPTIONS_HTML}
        </select>
        <label>Ingredientes que deben estar todos elegidos</label>
        <div class="combo-ing-list" data-combo-ing-list>
          <p class="admin-hint">Elegí primero el constructor de arriba.</p>
        </div>
        <p class="admin-hint">El descuento se aplica al precio final del pedido cuando el cliente elige (entre otros que quiera agregar) TODOS los ingredientes marcados aquí — puede ser uno solo o varios juntos.</p>
      </div>
      <input type="hidden" name="referencia_id">

      <label>Días en que aplica</label>
      <div class="row" style="grid-template-columns:repeat(auto-fit,minmax(100px,1fr)); gap:8px;">
        ${diasCheckboxesHtml('ed')}
      </div>
      <p class="admin-hint">Si no marcás ningún día, aplica todos los días.</p>

      <div class="check">
        <input type="checkbox" name="activo" id="ed-activo">
        <label for="ed-activo">Descuento activo</label>
      </div>

      <div class="admin-actions">
        <button class="admin-btn admin-btn--accent" type="submit">Guardar</button>
        <button class="admin-btn admin-btn--danger" type="button" onclick="adminConfirmarYBorrar('¿Eliminar este descuento?', 'form-borrar', 'borrar_id', ${d.id})">Eliminar</button>
        <button class="admin-btn admin-btn--ghost" type="button" onclick="AdminEdit.close(true)">Descartar</button>
      </div>
    </form>`;

  const panel = AdminEdit.open(fila, html);
  if (!panel) return;
  const form = panel.querySelector('form');
  panel.querySelector('[name="nombre"]').value = d.nombre;
  panel.querySelector('[name="tipo"]').value = d.tipo;
  panel.querySelector('[name="valor"]').value = d.valor;
  panel.querySelector('[name="activo"]').checked = d.activo == 1;

  if (d.dias) {
    d.dias.split(',').forEach(codigo => {
      const cb = panel.querySelector(`input[name="dias[]"][value="${codigo}"]`);
      if (cb) cb.checked = true;
    });
  }

  panel.querySelector('[name="alcance"]').value = d.alcance;
  panel.querySelectorAll('.ref-input').forEach(sel => {
    sel.addEventListener('change', () => { form.querySelector('[name="referencia_id"]').value = sel.value; });
  });
  form.addEventListener('submit', () => mostrarReferencia(form));
  mostrarReferencia(form, d.referencia_id);

  if (d.alcance === 'combo') {
    const seleccionados = d.combo_ingredientes ? d.combo_ingredientes.split(',').map(Number) : [];
    renderComboIngredientes(d.referencia_id, seleccionados, panel.querySelector('[data-combo-block] [data-combo-ing-list]'), 'e');
  }

  AdminEdit.snapshot();
}
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
