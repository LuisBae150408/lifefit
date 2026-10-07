<?php
/**
 * db.php
 * -----------------------------------------------------------------
 * Conexión única (singleton) a MySQL vía PDO. Todo el resto del
 * sitio (y, más adelante, el panel de administración) debe usar
 * get_pdo() en vez de abrir su propia conexión.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/config.php';

function get_pdo(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // No exponemos el error real de PDO (podría filtrar detalles de
            // la conexión); index.php muestra un mensaje amigable con esto.
            throw new RuntimeException('No se pudo conectar a la base de datos. Revisa includes/config.php.', 0, $e);
        }
    }

    return $pdo;
}
