<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();
header('Content-Type: application/json; charset=utf-8');

function ensure_admin(): void {
  if (empty($_SESSION['tipo']) || $_SESSION['tipo'] !== 'admin') {
    http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
  }
}
function table_exists(mysqli $c, string $t): bool {
  $t = $c->real_escape_string($t);
  $q = $c->query("SHOW TABLES LIKE '{$t}'");
  return $q && $q->num_rows > 0;
}
