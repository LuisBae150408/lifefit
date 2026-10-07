<?php
/**
 * auth.php
 * -----------------------------------------------------------------
 * Autenticacion del panel de administracion. Sesiones de PHP +
 * contraseñas con password_hash()/password_verify() (bcrypt) +
 * bloqueo temporal tras varios intentos fallidos. Sin librerias
 * externas ni servicios de terceros.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/db.php';

const ADMIN_MAX_INTENTOS = 5;
const ADMIN_BLOQUEO_MINUTOS = 15;

function admin_iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/** ¿Hay una sesión de admin activa? */
function admin_autenticado(): bool
{
    admin_iniciar_sesion_segura();
    return !empty($_SESSION['admin_id']);
}

/** Corta la ejecución y manda al login si no hay sesión válida. */
function admin_requiere_login(): void
{
    admin_iniciar_sesion_segura();
    if (empty($_SESSION['admin_id'])) {
        $destino = basename($_SERVER['SCRIPT_NAME']);
        header('Location: login.php?r=' . urlencode($destino));
        exit;
    }
}

/**
 * Intenta autenticar. Devuelve true si entró, o un string con el
 * motivo del rechazo (para mostrarlo tal cual al usuario).
 */
function admin_intentar_login(string $usuario, string $password): true|string
{
    $pdo = get_pdo();

    $stmt = $pdo->prepare('SELECT * FROM admin_usuarios WHERE usuario = ?');
    $stmt->execute([$usuario]);
    $fila = $stmt->fetch();

    if (!$fila) {
        // Mismo mensaje que "contraseña incorrecta" a propósito:
        // no revelamos si el usuario existe o no.
        return 'Usuario o contraseña incorrectos.';
    }

    if (!empty($fila['bloqueado_hasta']) && strtotime($fila['bloqueado_hasta']) > time()) {
        $minutos = (int) ceil((strtotime($fila['bloqueado_hasta']) - time()) / 60);
        return "Cuenta bloqueada temporalmente por demasiados intentos. Probá de nuevo en $minutos min.";
    }

    if (!password_verify($password, $fila['password_hash'])) {
        $intentos = (int) $fila['intentos_fallidos'] + 1;
        $bloqueo = null;
        if ($intentos >= ADMIN_MAX_INTENTOS) {
            $bloqueo = date('Y-m-d H:i:s', time() + ADMIN_BLOQUEO_MINUTOS * 60);
            $intentos = 0;
        }
        $pdo->prepare('UPDATE admin_usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?')
            ->execute([$intentos, $bloqueo, $fila['id']]);

        return $bloqueo
            ? 'Demasiados intentos fallidos. Cuenta bloqueada ' . ADMIN_BLOQUEO_MINUTOS . ' minutos.'
            : 'Usuario o contraseña incorrectos.';
    }

    // Login correcto: limpiar intentos, marcar acceso, regenerar sesión.
    $pdo->prepare('UPDATE admin_usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id = ?')
        ->execute([$fila['id']]);

    admin_iniciar_sesion_segura();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $fila['id'];
    $_SESSION['admin_usuario'] = $fila['usuario'];

    return true;
}

function admin_cerrar_sesion(): void
{
    admin_iniciar_sesion_segura();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Token CSRF de un solo uso por sesión (se reusa mientras dure la sesión). */
function admin_csrf_token(): string
{
    admin_iniciar_sesion_segura();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function admin_csrf_valido(?string $token): bool
{
    admin_iniciar_sesion_segura();
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}
