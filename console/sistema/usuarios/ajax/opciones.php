<?php
// /console/sistema/usuarios/ajax/opciones.php
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

  $tipos = ['admin','staff','cliente'];
  $roles = ['operador','staff_basico','staff_operativo'];

  echo json_encode(['ok'=>true,'tipos'=>$tipos,'roles'=>$roles], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>'Error interno']);
}
