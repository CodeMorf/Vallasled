<?php
// /console/facturacion/datos-bancarios/ajax/guardar.php
declare(strict_types=1);
require_once __DIR__ . '/../../../../config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'METHOD']); exit; }
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrfHeader)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'JSON']); exit; }

$id     = isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null;
$banco  = trim((string)($data['banco'] ?? ''));
$tit    = trim((string)($data['titular'] ?? ''));
$tipo   = trim((string)($data['tipo_cuenta'] ?? ''));
$num    = trim((string)($data['numero_cuenta'] ?? ''));

if ($banco === '' || $tit === '' || $num === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'VALIDATION','msg'=>'Campos requeridos']); exit; }
$tipo = in_array($tipo, ['Corriente','Ahorros'], true) ? $tipo : 'Corriente';
if (strlen($num) < 6 || strlen($num) > 34 || !preg_match('/^[A-Za-z0-9 \-]+$/', $num)) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'VALIDATION','msg'=>'Número inválido']); exit;
}

$canon = strtolower(preg_replace('/[^a-z0-9]/i', '', $num)); // para duplicados

try {
  // duplicados
  if ($id) {
    $stmt = $conn->prepare("SELECT id FROM datos_bancarios WHERE REPLACE(REPLACE(LOWER(numero_cuenta),' ',''),'-','')=? AND id<>?");
    $stmt->bind_param('si', $canon, $id);
  } else {
    $stmt = $conn->prepare("SELECT id FROM datos_bancarios WHERE REPLACE(REPLACE(LOWER(numero_cuenta),' ',''),'-','')=?");
    $stmt->bind_param('s', $canon);
  }
  $stmt->execute();
  $dup = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($dup) { http_response_code(409); echo json_encode(['ok'=>false,'error'=>'DUPLICATE','msg'=>'La cuenta ya existe']); exit; }

  if ($id) {
    $stmt = $conn->prepare("UPDATE datos_bancarios SET banco=?, numero_cuenta=?, tipo_cuenta=?, titular=? WHERE id=?");
    $stmt->bind_param('ssssi', $banco, $num, $tipo, $tit, $id);
    $ok = $stmt->execute(); $stmt->close();
    echo json_encode(['ok'=>$ok?true:false,'id'=>$id]);
  } else {
    $stmt = $conn->prepare("INSERT INTO datos_bancarios (banco,numero_cuenta,tipo_cuenta,titular,activo) VALUES (?,?,?,?,1)");
    $stmt->bind_param('ssss', $banco, $num, $tipo, $tit);
    $ok = $stmt->execute();
    $newId = (int)$conn->insert_id;
    $stmt->close();
    echo json_encode(['ok'=>$ok?true:false,'id'=>$newId]);
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'SERVER','msg'=>'Error al guardar']);
}
