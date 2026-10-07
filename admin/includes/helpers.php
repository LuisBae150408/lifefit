<?php
/** helpers.php -- utilidades chicas compartidas por las páginas del panel. */

function flash_ok(string $msg): void { $_SESSION['flash_ok'] = $msg; }
function flash_error(string $msg): void { $_SESSION['flash_error'] = $msg; }

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(admin_csrf_token()) . '">';
}

function redirigir(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Exige POST + CSRF válido, o corta la ejecución con un error. */
function exigir_post_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_csrf_valido($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida (CSRF). Volvé atrás e intentá de nuevo.');
    }
}
