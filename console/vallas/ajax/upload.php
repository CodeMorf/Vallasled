<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}
if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)($_SERVER['HTTP_X_CSRF'] ?? ''))) {
  http_response_code(422); echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error']!==UPLOAD_ERR_OK) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no_file']); exit;
}
$f = $_FILES['file'];
$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
$mime = mime_content_type($f['tmp_name']);
if (!isset($allowed[$mime])) { http_response_code(415); echo json_encode(['ok'=>false,'error'=>'tipo_no_permitido']); exit; }
if ($f['size']>8*1024*1024){ http_response_code(413); echo json_encode(['ok'=>false,'error'=>'muy_pesado']); exit; }

$ext = $allowed[$mime];
$base = preg_replace('/[^A-Za-z0-9._-]+/','_', pathinfo($f['name'], PATHINFO_FILENAME));
$fname = time().'_'.bin2hex(random_bytes(3)).'_'.$base.'.'.$ext;

$dir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/').'/uploads/vallas';
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
$dest = $dir.'/'.$fname;

if (!move_uploaded_file($f['tmp_name'], $dest)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'save_failed']); exit; }

$url = '/uploads/vallas/'.$fname;
echo json_encode(['ok'=>true,'url'=>$url]);
