<?php
/**
 * Utilidades para subir y optimizar el documento de factura adjunto a una entrada.
 *
 * Imágenes (JPG / PNG / GIF / WEBP):
 *   - Se redimensionan si superan MAX_IMG_PX en cualquier lado.
 *   - Se convierten y guardan como WebP (alta compresión, sin pérdida apreciable de calidad).
 *   - Resultado típico: foto de 4-8 MB → 100-300 KB.
 *
 * PDFs:
 *   - Se guardan tal cual; solo se valida el tipo MIME y el tamaño.
 */

define('FAC_MAX_BYTES',     8 * 1024 * 1024);   // 8 MB tope general de subida
define('FAC_MAX_PDF_BYTES', 5 * 1024 * 1024);   // 5 MB para PDF
define('FAC_MAX_IMG_PX',    1600);               // px máximos por lado
define('FAC_WEBP_QUALITY',  78);                 // calidad WebP (0-100)
define('FAC_UPLOAD_DIR',    __DIR__ . '/../uploads/facturas/');
define('FAC_UPLOAD_URL',    'uploads/facturas/');

/**
 * Procesa la carga de un archivo de factura.
 *
 * @param  array       $file           Elemento de $_FILES correspondiente al campo.
 * @param  string|null $archivoAnterior Ruta relativa anterior (para eliminarla al reemplazar).
 * @return string                      Ruta relativa guardada (para almacenar en BD).
 * @throws Exception                   Si hay error de tipo, tamaño o procesamiento.
 */
function procesarArchivoFactura(array $file, ?string $archivoAnterior = null): string
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new Exception('No se recibió ningún archivo válido.');
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        $errores = [
            UPLOAD_ERR_INI_SIZE   => 'El archivo supera el límite del servidor (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite del formulario.',
            UPLOAD_ERR_PARTIAL    => 'El archivo se subió de forma parcial.',
            UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal del servidor.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el servidor.',
            UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP detuvo la carga.',
        ];
        throw new Exception($errores[(int)$file['error']] ?? 'Error desconocido al subir el archivo.');
    }
    if ((int)$file['size'] > FAC_MAX_BYTES) {
        throw new Exception('El archivo supera el tamaño máximo permitido (8 MB).');
    }

    $mime = mime_content_type($file['tmp_name']);
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    if (!in_array($mime, $tiposPermitidos, true)) {
        throw new Exception('Tipo de archivo no permitido. Solo se aceptan imágenes (JPG, PNG, GIF, WEBP) o PDF.');
    }

    if (!is_dir(FAC_UPLOAD_DIR)) {
        if (!mkdir(FAC_UPLOAD_DIR, 0755, true)) {
            throw new Exception('No se pudo crear el directorio de archivos en el servidor.');
        }
    }

    $uniqueId = uniqid('fac_', true);

    if ($mime === 'application/pdf') {
        $ruta = _guardarPdfFactura($file, $uniqueId);
    } else {
        $ruta = _optimizarYGuardarImagenFactura($file, $uniqueId, $mime);
    }

    // Eliminar archivo anterior si existía y se está reemplazando
    if ($archivoAnterior) {
        eliminarArchivoFactura($archivoAnterior);
    }

    return $ruta;
}

/** Guarda el PDF sin modificaciones. */
function _guardarPdfFactura(array $file, string $uid): string
{
    if ((int)$file['size'] > FAC_MAX_PDF_BYTES) {
        throw new Exception('El PDF supera el tamaño máximo permitido (5 MB).');
    }
    $dest = FAC_UPLOAD_DIR . $uid . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new Exception('No se pudo guardar el archivo PDF en el servidor.');
    }
    return FAC_UPLOAD_URL . $uid . '.pdf';
}

/** Redimensiona (si es necesario) y convierte la imagen a WebP. */
function _optimizarYGuardarImagenFactura(array $file, string $uid, string $mime): string
{
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagewebp')) {
        throw new Exception('El servidor no soporta procesamiento de imágenes (extensión GD no disponible).');
    }

    $img = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        'image/gif'  => imagecreatefromgif($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
        default      => false,
    };

    if (!$img) {
        throw new Exception('No se pudo leer la imagen subida.');
    }

    $origW = imagesx($img);
    $origH = imagesy($img);

    // Redimensionar solo si supera el límite en algún lado
    if ($origW > FAC_MAX_IMG_PX || $origH > FAC_MAX_IMG_PX) {
        $ratio  = min(FAC_MAX_IMG_PX / $origW, FAC_MAX_IMG_PX / $origH);
        $newW   = max(1, (int)round($origW * $ratio));
        $newH   = max(1, (int)round($origH * $ratio));
        $canvas = imagecreatetruecolor($newW, $newH);

        // Preservar canal alfa (PNG/WEBP con transparencia)
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparente = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparente);

        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($img);
        $img = $canvas;
    }

    $dest = FAC_UPLOAD_DIR . $uid . '.webp';
    $ok   = imagewebp($img, $dest, FAC_WEBP_QUALITY);
    imagedestroy($img);

    if (!$ok) {
        throw new Exception('No se pudo guardar la imagen optimizada en el servidor.');
    }

    return FAC_UPLOAD_URL . $uid . '.webp';
}

/**
 * Elimina el archivo de factura del disco.
 *
 * @param string|null $rutaRelativa Ruta relativa guardada en BD (p. ej. "uploads/facturas/fac_xxx.webp").
 */
function eliminarArchivoFactura(?string $rutaRelativa): void
{
    if (!$rutaRelativa) return;
    $full = __DIR__ . '/../' . ltrim($rutaRelativa, '/');
    if (is_file($full)) {
        @unlink($full);
    }
}
