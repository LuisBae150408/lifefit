<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logo.php';
require_once __DIR__ . '/includes/helpers.php';

admin_iniciar_sesion_segura();
if (admin_autenticado()) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valido($_POST['csrf'] ?? null)) {
        $error = 'La sesión del formulario expiró, intentá de nuevo.';
    } else {
        $resultado = admin_intentar_login(trim($_POST['usuario'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($resultado === true) {
            $destino = $_GET['r'] ?? 'index.php';
            $destino = preg_match('/^[a-z0-9_-]+\.php$/i', $destino) ? $destino : 'index.php';
            header('Location: ' . $destino);
            exit;
        }
        $error = $resultado;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Iniciar sesión — Admin LIFE FIT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin">
  <div class="admin-login">
    <div class="admin-login__card">
      <div class="admin-login__brand">
        <?= render_logo('admin-login__logo') ?>
        LIFE FIT · Admin
      </div>

      <?php if ($error): ?>
        <div class="admin-alert admin-alert--error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" autocomplete="off">
        <?= csrf_field() ?>
        <label for="usuario">Usuario</label>
        <input type="text" id="usuario" name="usuario" required autofocus>

        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required>

        <div class="admin-actions">
          <button class="admin-btn admin-btn--accent" type="submit" style="width:100%;justify-content:center;">Entrar</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
