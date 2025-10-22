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

$valla_id = (int)($_POST['valla_id'] ?? 0);
$url = trim((string)($_POST['url'] ?? ''));
if ($valla_id<=0 || $url===''){ http_response_code(422); echo json_encode(['ok'=>false,'error'=>'faltan_campos']); exit; }

$basename = basename(parse_url($url, PHP_URL_PATH) ?? $url);
try{
  // ajusta el nombre de columna si en tu schema es distinto
  $stmt = $conn->prepare("UPDATE vallas SET imagen_previa=? WHERE id=?");
  $stmt->bind_param('si',$basename,$valla_id);
  $stmt->execute();
  echo json_encode(['ok'=>true]);
}catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_error']); }
