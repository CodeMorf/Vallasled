<?php
// /console/facturacion/facturas/ajax/adjuntar_recibo_transferencia.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_exit(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'], 405);
}
if (!csrf_ok_from_header_or_post()) {
  json_exit(['ok'=>false,'error'=>'CSRF'], 419);
}

$factura_id = (int)($_POST['factura_id'] ?? 0);
$cliente_id = (int)($_POST['cliente_id'] ?? 0);
if ($factura_id<=0) json_exit(['ok'=>false,'error'=>'FACTURA_ID_REQUIRED'], 422);

if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
  json_exit(['ok'=>false,'error'=>'FILE_REQUIRED'], 422);
}

$allowed = [
  'application/pdf' => 'pdf',
  'image/jpeg' => 'jpg',
  'image/png'  => 'png',
  'image/webp' => 'webp',
];
$maxBytes = 10 * 1024 * 1024; // 10MB

$f = $_FILES['archivo'];
if ($f['size'] <= 0 || $f['size'] > $maxBytes) json_exit(['ok'=>false,'error'=>'FILE_SIZE'], 422);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($f['tmp_name']);
if (!isset($allowed[$mime])) json_exit(['ok'=>false,'error'=>'FILE_TYPE'], 422);

$ext = $allowed[$mime];
$dir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/uploads/recibos';
if (!is_dir($dir)) @mkdir($dir, 0755, true);

$base = 'recibo_'.$factura_id.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4));
$fname = $base.'.'.$ext;
$path = $dir.'/'.$fname;

if (!move_uploaded_file($f['tmp_name'], $path)) {
  json_exit(['ok'=>false,'error'=>'MOVE_FAILED'], 500);
}

// Guardar registro
$stmt = $conn->prepare("INSERT INTO recibos_transferencia (factura_id, cliente_id, archivo, fecha_subida) VALUES (?,?,?,NOW())");
$stmt->bind_param('iis', $factura_id, $cliente_id, $fname);
$stmt->execute();
$newId = $stmt->insert_id;
$stmt->close();

// URL pública
$public = '/uploads/recibos/'.$fname;

json_exit(['ok'=>true,'id'=>$newId,'factura_id'=>$factura_id,'archivo'=>$public]);
