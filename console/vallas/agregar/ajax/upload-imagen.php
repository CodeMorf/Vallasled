<?php
declare(strict_types=1);

// Rutas
require_once __DIR__ . '/../../../../config/db.php';

// Sesión segura si existe helper
if (function_exists('start_session_safe')) { start_session_safe(); }

// Siempre JSON
header('Content-Type: application/json; charset=utf-8');

function jfail(string $msg, int $code = 400, array $extra = []): never {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg] + $extra, JSON_UNESCAPED_SLASHES);
  exit;
}

function up_err_msg(int $code): string {
  return match ($code) {
    UPLOAD_ERR_INI_SIZE   => 'INI_SIZE',
    UPLOAD_ERR_FORM_SIZE  => 'FORM_SIZE',
    UPLOAD_ERR_PARTIAL    => 'PARTIAL',
    UPLOAD_ERR_NO_FILE    => 'NO_FILE',
    UPLOAD_ERR_NO_TMP_DIR => 'NO_TMP_DIR',
    UPLOAD_ERR_CANT_WRITE => 'CANT_WRITE',
    UPLOAD_ERR_EXTENSION  => 'EXTENSION',
    default               => 'UNKNOWN',
  };
}

// Método
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  jfail('METHOD_NOT_ALLOWED', 405);
}

// CSRF si el helper existe
if (function_exists('csrf_ok_from_header_or_post') && !csrf_ok_from_header_or_post()) {
  jfail('CSRF_INVALID', 403);
}

// Archivo
if (empty($_FILES['image-upload']) || !is_array($_FILES['image-upload'])) {
  jfail('NO_FILE', 400);
}
$f = $_FILES['image-upload'];

if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  jfail('UPLOAD_' . up_err_msg((int)$f['error']), 400);
}

$size = (int)($f['size'] ?? 0);
$max  = 10 * 1024 * 1024; // 10 MB
if ($size <= 0 || $size > $max) {
  jfail('FILE_TOO_LARGE', 413, ['limit' => $max]);
}

// Validación de imagen SIN finfo
$tmp = $f['tmp_name'] ?? '';
if (!$tmp || !is_file($tmp)) {
  jfail('TMP_NOT_FOUND', 400);
}

$gi = @getimagesize($tmp);
if ($gi === false) {
  jfail('NOT_IMAGE', 415);
}
$typeConst = (int)$gi[2];
$map = [
  IMAGETYPE_JPEG => 'jpg',
  IMAGETYPE_PNG  => 'png',
  IMAGETYPE_WEBP => 'webp',
  IMAGETYPE_GIF  => 'gif',
];
if (!isset($map[$typeConst])) {
  jfail('TYPE_NOT_ALLOWED', 415, ['allowed' => array_values($map)]);
}
$ext = $map[$typeConst];

// Carpeta destino: /uploads/Y/m
$docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 4), '/');
$subdir  = '/uploads/' . date('Y') . '/' . date('m');
$absdir  = $docroot . $subdir;

if (!is_dir($absdir) && !@mkdir($absdir, 0775, true)) {
  jfail('MKDIR_FAILED', 500);
}

// Nombre único
try {
  $name = bin2hex(random_bytes(8)) . '.' . $ext;
} catch (Throwable) {
  $name = uniqid('img_', true) . '.' . $ext;
}
$dest = $absdir . '/' . $name;

// Mover
if (!@move_uploaded_file($tmp, $dest)) {
  jfail('MOVE_FAILED', 500);
}
@chmod($dest, 0644);

// Respuesta
$rel = $subdir . '/' . $name;        // guarda esto en DB (campo imagen/imagen1/…)
http_response_code(200);
echo json_encode([
  'ok'      => true,
  'url'     => $rel,                  // mismo valor; si necesitas absoluta, construye en frontend
  'relpath' => $rel,
  'filename'=> $name,
], JSON_UNESCAPED_SLASHES);
