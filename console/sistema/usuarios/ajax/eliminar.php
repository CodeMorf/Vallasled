<?php
// /console/sistema/usuarios/ajax/eliminar.php
declare(strict_types=1);
@header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 4) . '/config/db.php';
start_session_safe();
require_console_auth(['admin','staff']);

try {
  if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF inválido']); exit;
  }

  $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $hard = isset($_POST['hard']) && (string)$_POST['hard'] === '1';
  if ($id <= 0) { echo json_encode(['error'=>'ID inválido']); exit; }
  if ($id === (int)($_SESSION['uid'] ?? 0)) { echo json_encode(['error'=>'No puedes eliminar tu propia cuenta']); exit; }

  // verificar usuario objetivo
  $tipoTarget = null;
  $stmt = $conn->prepare("SELECT tipo FROM usuarios WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $stmt->bind_result($tipoTarget);
  $found = $stmt->fetch();
  $stmt->close();

  if (!$found) { echo json_encode(['error'=>'Usuario no existe']); exit; }
  if ($tipoTarget === 'admin' && ($_SESSION['tipo'] ?? '') !== 'admin') {
    echo json_encode(['error'=>'Solo un admin puede eliminar a otro admin']); exit;
  }

  if ($hard && ($_SESSION['tipo'] ?? '') === 'admin') {
    // borrado físico opcional
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $aff = $stmt->affected_rows;
    $stmt->close();
    if ($aff < 1) { echo json_encode(['error'=>'No se eliminó']); exit; }
    echo json_encode(['ok'=>true,'hard'=>1,'removed'=>$aff], JSON_UNESCAPED_UNICODE); exit;
  } else {
    // borrado lógico
    $stmt = $conn->prepare("UPDATE usuarios SET activo=0 WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $aff = $stmt->affected_rows;
    $stmt->close();
    if ($aff < 1) { echo json_encode(['error'=>'No se actualizó']); exit; }
    echo json_encode(['ok'=>true,'hard'=>0,'updated'=>$aff], JSON_UNESCAPED_UNICODE); exit;
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>'Error interno']);
}
