<?php
/**
 * publicacion.php
 * -----------------------------------------------------------------
 * Publicaciones personalizadas de la landing: banners con foto (ej.
 * para avisar un descuento o una promo) que el admin puede crear,
 * editar, borrar y activar/desactivar. Máximo 2 al mismo tiempo.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_once __DIR__ . '/includes/helpers.php';
admin_requiere_login();

const PUBLICACIONES_MAX = 2;

$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id           = trim($_POST['id'] ?? '');
        $esNueva      = ($_POST['es_nueva'] ?? '') === '1';
        $titulo       = trim($_POST['titulo'] ?? '');
        $texto        = trim($_POST['texto'] ?? '');
        $enlaceTexto  = trim($_POST['enlace_texto'] ?? '');
        $enlaceUrl    = trim($_POST['enlace_url'] ?? '');
        $imagenActual = trim($_POST['imagen_actual'] ?? '');
        $activo       = isset($_POST['activo']) ? 1 : 0;

        $totalActuales = (int) $pdo->query('SELECT COUNT(*) FROM publicaciones')->fetchColumn();

        if ($esNueva && $totalActuales >= PUBLICACIONES_MAX) {
            flash_error('Ya hay ' . PUBLICACIONES_MAX . ' publicaciones cargadas (el máximo). Borrá una para poder crear otra.');
            redirigir('publicacion.php');
        }

        try {
            $imagenNueva = procesar_imagen_subida('imagen_archivo', 'publicaciones');
            $imagen = $imagenNueva ?? $imagenActual;

            if ($activo && $imagen === '') {
                flash_error('Subí una foto para poder activar la publicación.');
            } elseif ($esNueva) {
                $pdo->prepare('INSERT INTO publicaciones (titulo, texto, imagen, enlace_texto, enlace_url, activo) VALUES (?,?,?,?,?,?)')
                    ->execute([$titulo, $texto, $imagen, $enlaceTexto, $enlaceUrl, $activo]);
                flash_ok('Publicación creada.');
            } else {
                $pdo->prepare('UPDATE publicaciones SET titulo=?, texto=?, imagen=?, enlace_texto=?, enlace_url=?, activo=? WHERE id=?')
                    ->execute([$titulo, $texto, $imagen, $enlaceTexto, $enlaceUrl, $activo, $id]);
                if ($imagenNueva !== null && $imagenActual !== '') {
                    borrar_imagen_subida($imagenActual);
                }
                flash_ok('Publicación actualizada.');
            }
        } catch (RuntimeException $e) {
            flash_error($e->getMessage());
        }
    } elseif ($accion === 'borrar') {
        $idBorrar = (int) ($_POST['id'] ?? 0);
        $fila = $pdo->prepare('SELECT imagen FROM publicaciones WHERE id = ?');
        $fila->execute([$idBorrar]);
        $imagenBorrar = $fila->fetchColumn();
        $pdo->prepare('DELETE FROM publicaciones WHERE id = ?')->execute([$idBorrar]);
        if ($imagenBorrar) {
            borrar_imagen_subida($imagenBorrar);
        }
        flash_ok('Publicación eliminada.');
    }

    redirigir('publicacion.php');
}

$publicaciones = $pdo->query('SELECT * FROM publicaciones ORDER BY id')->fetchAll();
$puedeCrearMas = count($publicaciones) < PUBLICACIONES_MAX;

$titulo_pagina = 'Publicación';
$subtitulo_pagina = 'Banners con foto en la landing -- para avisar un descuento, una promo o lo que quieras. Hasta ' . PUBLICACIONES_MAX . ' al mismo tiempo.';
require __DIR__ . '/includes/layout_top.php';

/** Imprime el formulario (crear o editar) de una publicación. */
function imprimir_form_publicacion(array $p): void
{
    $esNueva = empty($p['id']);
    ?>
    <form class="admin-form" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="es_nueva" value="<?= $esNueva ? '1' : '0' ?>">
      <?php if (!$esNueva): ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><?php endif; ?>
      <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($p['imagen'] ?? '') ?>">

      <label>Título</label>
      <input type="text" name="titulo" placeholder="ej: 20% off en Malteadas esta semana" value="<?= htmlspecialchars($p['titulo'] ?? '') ?>">

      <label>Texto</label>
      <textarea name="texto" placeholder="ej: Todos los sabores, todos los días hasta el domingo."><?= htmlspecialchars($p['texto'] ?? '') ?></textarea>

      <label>Foto</label>
      <?php if (!empty($p['imagen'])): ?>
        <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="" style="width:140px;height:140px;object-fit:cover;border-radius:12px;margin-bottom:8px;display:block;">
      <?php endif; ?>
      <input type="file" name="imagen_archivo" accept="image/*">
      <p class="admin-hint">Dejalo vacío para conservar la foto actual. Se comprime y guarda en WEBP.</p>

      <div class="row">
        <div>
          <label>Texto del botón (opcional)</label>
          <input type="text" name="enlace_texto" placeholder="ej: Ver el menú" value="<?= htmlspecialchars($p['enlace_texto'] ?? '') ?>">
        </div>
        <div>
          <label>Enlace del botón (opcional)</label>
          <input type="text" name="enlace_url" placeholder="ej: menu/ o wa.me/584121234567" value="<?= htmlspecialchars($p['enlace_url'] ?? '') ?>">
          <p class="admin-hint">Podés poner una página del sitio (ej: <code>menu/</code>) o una dirección externa (con o sin <code>https://</code>).</p>
        </div>
      </div>

      <div class="check">
        <input type="checkbox" id="activo-<?= (int) ($p['id'] ?? 0) ?>" name="activo" <?= !empty($p['activo']) ? 'checked' : '' ?>>
        <label for="activo-<?= (int) ($p['id'] ?? 0) ?>">Mostrar en la landing</label>
      </div>

      <div class="admin-actions">
        <button class="admin-btn admin-btn--accent" type="submit"><?= $esNueva ? 'Crear publicación' : 'Guardar' ?></button>
      </div>
    </form>
    <?php
}
?>

<?php foreach ($publicaciones as $p): ?>
  <div class="admin-card">
    <h2 style="margin-top:0;"><?= htmlspecialchars($p['titulo'] ?: 'Publicación #' . $p['id']) ?></h2>
    <?php imprimir_form_publicacion($p); ?>
    <div class="admin-actions" style="margin-top:8px;">
      <button class="admin-btn admin-btn--danger" type="button" onclick="adminConfirmarYBorrar('¿Eliminar esta publicación? Esto no se puede deshacer.', 'form-borrar', 'borrar_id', '<?= (int) $p['id'] ?>')">Eliminar publicación</button>
    </div>
  </div>
<?php endforeach; ?>

<?php if ($puedeCrearMas): ?>
  <div class="admin-card">
    <h2 style="margin-top:0;">Nueva publicación</h2>
    <?php imprimir_form_publicacion(['id' => null, 'activo' => 0]); ?>
  </div>
<?php else: ?>
  <p class="admin-hint">Ya tenés las <?= PUBLICACIONES_MAX ?> publicaciones permitidas. Borrá una para poder cargar otra.</p>
<?php endif; ?>

<form method="post" id="form-borrar" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="accion" value="borrar">
  <input type="hidden" name="id" id="borrar_id">
</form>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
