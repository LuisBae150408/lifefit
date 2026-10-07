<?php
/**
 * uploads.php
 * -----------------------------------------------------------------
 * Manejo de imágenes subidas por el administrador (productos,
 * constructores, publicación de la landing): valida el archivo,
 * lo recomprime a WEBP y lo guarda en /uploads/<carpeta>/.
 *
 * Las rutas se guardan en la base de datos SIEMPRE con "/" inicial
 * (relativas a la raíz del sitio), para que funcionen igual desde
 * "/", "/menu/" y "/admin/" sin tener que ajustar el prefijo según
 * la profundidad de la página que las muestra.
 * -----------------------------------------------------------------
 */

/** Carpeta física en disco donde viven las imágenes subidas. */
function ruta_uploads(): string
{
    return __DIR__ . '/../uploads';
}

/**
 * Procesa un archivo subido por <input type="file" name="$campo">, lo
 * convierte a WEBP y lo guarda en uploads/$carpeta/. Devuelve la ruta
 * pública ("/uploads/carpeta/archivo.webp") o null si no se subió
 * ningún archivo nuevo (no es un error: el llamador debe conservar
 * la imagen anterior en ese caso).
 *
 * Lanza RuntimeException con un mensaje legible si el archivo se
 * subió pero es inválido (no es imagen, muy grande, error de subida).
 */
function procesar_imagen_subida(string $campo, string $carpeta): ?string
{
    if (empty($_FILES[$campo]) || ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // no se seleccionó ningún archivo -- no es un error
    }

    $archivo = $_FILES[$campo];

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Hubo un problema al subir la imagen. Probá de nuevo con un archivo más liviano.');
    }

    if ($archivo['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('La imagen pesa demasiado (máximo 8MB).');
    }

    $info = @getimagesize($archivo['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('El archivo subido no es una imagen válida.');
    }

    [$ancho, $alto, $tipo] = $info;

    $origen = match ($tipo) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($archivo['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($archivo['tmp_name']),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($archivo['tmp_name']) : false,
        IMAGETYPE_GIF  => @imagecreatefromgif($archivo['tmp_name']),
        default        => false,
    };

    if (!$origen) {
        throw new RuntimeException('Formato de imagen no soportado. Usá JPG, PNG, WEBP o GIF.');
    }

    // Aplana transparencia sobre blanco (webp con canal alpha se conserva
    // igual, esto es solo para que PNG/GIF con fondo transparente no
    // aparezcan con fondo negro en navegadores que no leen alpha).
    imagepalettetotruecolor($origen);
    imagealphablending($origen, true);
    imagesavealpha($origen, true);

    // Reduce el tamaño si la imagen es más grande de lo necesario para
    // el sitio (evita subir fotos de 12MP tal cual desde el celular).
    $maxAncho = 1600;
    if ($ancho > $maxAncho) {
        $nuevoAlto = (int) round($alto * ($maxAncho / $ancho));
        $redimensionada = imagecreatetruecolor($maxAncho, $nuevoAlto);
        imagealphablending($redimensionada, false);
        imagesavealpha($redimensionada, true);
        imagecopyresampled($redimensionada, $origen, 0, 0, 0, 0, $maxAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($origen);
        $origen = $redimensionada;
    }

    if (!is_dir(ruta_uploads() . '/' . $carpeta)) {
        mkdir(ruta_uploads() . '/' . $carpeta, 0755, true);
    }

    $nombreArchivo = bin2hex(random_bytes(8)) . '.webp';
    $rutaDisco = ruta_uploads() . '/' . $carpeta . '/' . $nombreArchivo;

    $guardadoOk = function_exists('imagewebp') && imagewebp($origen, $rutaDisco, 82);
    imagedestroy($origen);

    if (!$guardadoOk) {
        throw new RuntimeException('No se pudo procesar la imagen en este servidor (falta soporte WEBP en GD).');
    }

    return '/uploads/' . $carpeta . '/' . $nombreArchivo;
}

/**
 * Borra una imagen previamente subida (si la ruta apunta a
 * /uploads/... en este sitio). No hace nada con URLs externas ni
 * con imágenes vacías, para no intentar borrar algo que no nos
 * pertenece.
 */
function borrar_imagen_subida(?string $rutaPublica): void
{
    if (!$rutaPublica || !str_starts_with($rutaPublica, '/uploads/')) {
        return;
    }
    $rutaDisco = __DIR__ . '/..' . $rutaPublica;
    if (is_file($rutaDisco)) {
        @unlink($rutaDisco);
    }
}
