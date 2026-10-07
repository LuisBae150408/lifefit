<?php
/**
 * config.php
 * -----------------------------------------------------------------
 * Datos de conexión a MySQL, leídos de variables de entorno.
 *
 * Cómo ponerlos:
 *   Opción A (recomendada) -- archivo .env en la raíz del proyecto
 *   (mismo nivel que index.php), formato clave=valor:
 *
 *     DB_HOST=sqlXXX.infinityfree.com
 *     DB_NAME=if0_12345_lifefit
 *     DB_USER=if0_12345
 *     DB_PASS=tu_contraseña
 *
 *   Este archivo lee ese .env automáticamente si existe (no hace
 *   falta ninguna librería). NO lo subas a un repositorio público.
 *
 *   Opción B -- si tu hosting deja definir variables de entorno
 *   reales (paneles tipo Railway, Render, etc.), simplemente
 *   configúralas ahí con esos mismos nombres; este archivo las lee
 *   igual, con getenv().
 *
 * Si no se define nada, se usan los valores por defecto de abajo
 * (que fallarán a propósito) y el sitio cae automáticamente al
 * catálogo de ejemplo — ver includes/data.php.
 * -----------------------------------------------------------------
 */

/**
 * Parser mínimo de .env — sin dependencias. Lee KEY=VALUE por línea,
 * ignora comentarios (#) y líneas vacías, y no pisa una variable que
 * ya exista en el entorno real (para que la Opción B siga mandando).
 */
function cargar_env(string $ruta): void
{
    if (!is_readable($ruta)) {
        return;
    }

    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#')) {
            continue;
        }
        if (!str_contains($linea, '=')) {
            continue;
        }

        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor);

        // Quita comillas envolventes si las hay: KEY="valor" o KEY='valor'
        if (strlen($valor) >= 2 && $valor[0] === $valor[-1] && in_array($valor[0], ['"', "'"], true)) {
            $valor = substr($valor, 1, -1);
        }

        if ($clave !== '' && getenv($clave) === false) {
            putenv("$clave=$valor");
            $_ENV[$clave] = $valor;
        }
    }
}

cargar_env(__DIR__ . '/../.env');

function env_o(string $clave, string $default): string
{
    $valor = getenv($clave);
    return $valor === false ? $default : $valor;
}

define('DB_HOST', getenv('DB_HOST') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');

// En produccion no se muestran errores de PHP en pantalla (podrian filtrar
// rutas del servidor o datos sensibles) -- se registran en el log del
// hosting en su lugar. Para verlos en pantalla durante desarrollo local,
// agrega APP_DEBUG=1 a tu .env.
if (env_o('APP_DEBUG', '0') === '1') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}
