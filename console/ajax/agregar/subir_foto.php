<?php
// /console/ajax/agregar/subir_foto.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/_bootstrap.php';
start_session_safe();
only_methods(['POST']);
need_csrf_for_write();

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  json_exit(['ok'=>false,'error'=>'AUTH_REQUIRED'], 401);
}

if (empty($_FILES['foto']) || !is_uploaded_file($_FILES['foto']['tmp_name'])) {
  json_exit(['ok'=>false,'error'=>'NO_FILE'], 422);
}

$f = $_FILES['foto'];
$mime = mime_content_type($f['tmp_name']) ?: '';
if (strpos($mime, 'image/') !== 0) json_exit(['ok'=>false,'error'=>'BAD_MIME'], 415);

// Directorio destino
$ym = date('Y/m');
$webBase = '/uploads/vallas/' . $ym . '/';
$diskBase = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__,3), '/') . $webBase;
if (!is_dir($diskBase) && !mkdir($diskBase, 0755, true)) {
  json_exit(['ok'=>false,'error'=>'MKDIR_FAIL'], 500);
}

// Nombre seguro
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if (!preg_match('/^[a-z0-9]{1,5}$/', $ext)) $ext = 'jpg';
$base = bin2hex(random_bytes(8));
$dest = $diskBase . $base . '.' . $ext;

if (!move_uploaded_file($f['tmp_name'], $dest)) {
  json_exit(['ok'=>false,'error'=>'MOVE_FAIL'], 500);
}

// URL pública
$url = $webBase . $base . '.' . $ext;
json_exit(['ok'=>true,'url'=>$url]);
