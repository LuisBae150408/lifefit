<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
admin_requiere_login();

$pdo = get_pdo();

// Campos editables: clave => [etiqueta, placeholder, tipo, ayuda]
$campos = [
    'nombre'          => ['Nombre del negocio', 'LIFE FIT', 'text', ''],
    'eslogan'         => ['Eslogan', 'Vive bien. Vive saludable. Vive con LifeFit.', 'text', ''],
    'ubicacion'       => ['Ubicación', 'Parque Los Ilustres', 'text', ''],
    'horario_semana'  => ['Horario de semana', 'Lun – Vie · 12:00 pm – 8:00 pm', 'text', ''],
    'horario_finde'   => ['Horario de fin de semana', 'Sáb – Dom · 2:00 pm – 10:00 pm', 'text', ''],
    'whatsapp_numero' => ['Número de WhatsApp', '584121234567', 'text', 'Con código de país, solo números (sin +, espacios ni guiones). Es el número al que llegan los pedidos del carrito y el enlace del pie de página.'],
    'instagram'       => ['Usuario de Instagram', 'lifefit', 'text', 'Sin la @. Dejalo vacío para no mostrar el enlace.'],
    'tiktok'          => ['Usuario de TikTok', 'lifefit', 'text', 'Sin la @. Dejalo vacío para no mostrar el enlace.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $accion = $_POST['accion'] ?? 'datos';

    if ($accion === 'cuenta') {
        $usuarioNuevo   = trim($_POST['usuario'] ?? '');
        $passwordActual = (string) ($_POST['password_actual'] ?? '');
        $passwordNueva  = (string) ($_POST['password_nueva'] ?? '');
        $passwordNueva2 = (string) ($_POST['password_nueva2'] ?? '');

        $stmtAdmin = $pdo->prepare('SELECT * FROM admin_usuarios WHERE id = ?');
        $stmtAdmin->execute([$_SESSION['admin_id'] ?? 0]);
        $admin = $stmtAdmin->fetch();

        if (!$admin || !password_verify($passwordActual, $admin['password_hash'])) {
            flash_error('La contraseña actual no es correcta.');
        } elseif ($usuarioNuevo === '' || strlen($usuarioNuevo) < 3) {
            flash_error('El usuario debe tener al menos 3 caracteres.');
        } elseif ($passwordNueva !== '' && strlen($passwordNueva) < 8) {
            flash_error('La nueva contraseña debe tener al menos 8 caracteres.');
        } elseif ($passwordNueva !== $passwordNueva2) {
            flash_error('Las contraseñas nuevas no coinciden.');
        } else {
            try {
                if ($passwordNueva !== '') {
                    $pdo->prepare('UPDATE admin_usuarios SET usuario = ?, password_hash = ? WHERE id = ?')
                        ->execute([$usuarioNuevo, password_hash($passwordNueva, PASSWORD_DEFAULT), $admin['id']]);
                } else {
                    $pdo->prepare('UPDATE admin_usuarios SET usuario = ? WHERE id = ?')
                        ->execute([$usuarioNuevo, $admin['id']]);
                }
                $_SESSION['admin_usuario'] = $usuarioNuevo;
                flash_ok('Tu usuario y/o contraseña se actualizaron.');
            } catch (PDOException $e) {
                flash_error($e->getCode() === '23000' ? 'Ese nombre de usuario ya está en uso.' : 'No se pudo actualizar la cuenta.');
            }
        }
        redirigir('configuracion.php');
    }

    $numero = preg_replace('/\D+/', '', $_POST['whatsapp_numero'] ?? '');
    if ($numero === '') {
        flash_error('El número de WhatsApp es obligatorio.');
        redirigir('configuracion.php');
    }

    $stmt = $pdo->prepare('INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
    foreach (array_keys($campos) as $clave) {
        $valor = $clave === 'whatsapp_numero' ? $numero : trim($_POST[$clave] ?? '');
        // Los usuarios de redes se guardan sin @ ni espacios, por si los pegan con ellos.
        if (in_array($clave, ['instagram', 'tiktok'], true)) {
            $valor = ltrim(trim($valor), '@');
        }
        $stmt->execute([$clave, $valor]);
    }

    flash_ok('Configuración actualizada.');
    redirigir('configuracion.php');
}

$rows = $pdo->query('SELECT clave, valor FROM configuracion')->fetchAll();
$valores = array_column($rows, 'valor', 'clave');

$titulo_pagina = 'Configuración';
$subtitulo_pagina = 'Datos generales del negocio: WhatsApp, redes sociales, ubicación y horario. Se usan en la página principal y en el menú.';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-card">
  <form class="admin-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="datos">

    <label for="nombre"><?= htmlspecialchars($campos['nombre'][0]) ?></label>
    <input type="text" id="nombre" name="nombre" placeholder="<?= htmlspecialchars($campos['nombre'][1]) ?>" value="<?= htmlspecialchars($valores['nombre'] ?? '') ?>">

    <label for="eslogan"><?= htmlspecialchars($campos['eslogan'][0]) ?></label>
    <input type="text" id="eslogan" name="eslogan" placeholder="<?= htmlspecialchars($campos['eslogan'][1]) ?>" value="<?= htmlspecialchars($valores['eslogan'] ?? '') ?>">

    <label for="ubicacion"><?= htmlspecialchars($campos['ubicacion'][0]) ?></label>
    <input type="text" id="ubicacion" name="ubicacion" placeholder="<?= htmlspecialchars($campos['ubicacion'][1]) ?>" value="<?= htmlspecialchars($valores['ubicacion'] ?? '') ?>">

    <div class="row">
      <div>
        <label for="horario_semana"><?= htmlspecialchars($campos['horario_semana'][0]) ?></label>
        <input type="text" id="horario_semana" name="horario_semana" placeholder="<?= htmlspecialchars($campos['horario_semana'][1]) ?>" value="<?= htmlspecialchars($valores['horario_semana'] ?? '') ?>">
      </div>
      <div>
        <label for="horario_finde"><?= htmlspecialchars($campos['horario_finde'][0]) ?></label>
        <input type="text" id="horario_finde" name="horario_finde" placeholder="<?= htmlspecialchars($campos['horario_finde'][1]) ?>" value="<?= htmlspecialchars($valores['horario_finde'] ?? '') ?>">
      </div>
    </div>

    <label for="whatsapp_numero"><?= htmlspecialchars($campos['whatsapp_numero'][0]) ?></label>
    <input type="text" id="whatsapp_numero" name="whatsapp_numero" placeholder="<?= htmlspecialchars($campos['whatsapp_numero'][1]) ?>" value="<?= htmlspecialchars($valores['whatsapp_numero'] ?? '') ?>" inputmode="numeric">
    <p class="admin-hint"><?= htmlspecialchars($campos['whatsapp_numero'][3]) ?></p>

    <div class="row">
      <div>
        <label for="instagram"><?= htmlspecialchars($campos['instagram'][0]) ?></label>
        <div style="position:relative;">
          <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--ad-ink-soft); font-weight:700;">@</span>
          <input type="text" id="instagram" name="instagram" placeholder="<?= htmlspecialchars($campos['instagram'][1]) ?>" value="<?= htmlspecialchars($valores['instagram'] ?? '') ?>" style="padding-left:26px;">
        </div>
        <p class="admin-hint"><?= htmlspecialchars($campos['instagram'][3]) ?></p>
      </div>
      <div>
        <label for="tiktok"><?= htmlspecialchars($campos['tiktok'][0]) ?></label>
        <div style="position:relative;">
          <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--ad-ink-soft); font-weight:700;">@</span>
          <input type="text" id="tiktok" name="tiktok" placeholder="<?= htmlspecialchars($campos['tiktok'][1]) ?>" value="<?= htmlspecialchars($valores['tiktok'] ?? '') ?>" style="padding-left:26px;">
        </div>
        <p class="admin-hint"><?= htmlspecialchars($campos['tiktok'][3]) ?></p>
      </div>
    </div>

    <div class="admin-actions">
      <button class="admin-btn admin-btn--accent" type="submit">Guardar</button>
    </div>
  </form>
</div>

<div class="admin-card">
  <h2 style="margin-top:0;">Usuario y contraseña</h2>
  <p class="admin-hint">Cambiá con qué usuario y contraseña entrás al panel. Necesitás tu contraseña actual para confirmar.</p>
  <form class="admin-form" method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="cuenta">

    <label for="cuenta_usuario">Usuario</label>
    <input type="text" id="cuenta_usuario" name="usuario" required minlength="3" value="<?= htmlspecialchars($_SESSION['admin_usuario'] ?? '') ?>">

    <label for="password_actual">Contraseña actual</label>
    <input type="password" id="password_actual" name="password_actual" required>

    <div class="row">
      <div>
        <label for="password_nueva">Contraseña nueva (opcional)</label>
        <input type="password" id="password_nueva" name="password_nueva" minlength="8">
      </div>
      <div>
        <label for="password_nueva2">Repetir contraseña nueva</label>
        <input type="password" id="password_nueva2" name="password_nueva2" minlength="8">
      </div>
    </div>
    <p class="admin-hint">Dejá los dos campos de contraseña nueva vacíos si solo querés cambiar el usuario.</p>

    <div class="admin-actions">
      <button class="admin-btn admin-btn--accent" type="submit">Guardar cuenta</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
