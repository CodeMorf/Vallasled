<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../../config/db.php';
start_session_safe();
require_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

$q        = trim($_GET['q'] ?? '');
$limit    = max(1, min(100, (int)($_GET['limit'] ?? 50)));
$valla_id = (int)($_GET['valla_id'] ?? 0);

$proveedor_id = (int)($_SESSION['proveedor_id'] ?? 0);
if ($proveedor_id <= 0 && $valla_id > 0) {
  $stmt = $conn->prepare("SELECT proveedor_id FROM vallas WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $valla_id);
  $stmt->execute(); $stmt->bind_result($proveedor_id); $stmt->fetch(); $stmt->close();
}

$sql = "SELECT id, nombre, email, telefono, empresa FROM crm_clientes";
$params = []; $types = '';

if ($proveedor_id > 0) {
  $sql .= " WHERE proveedor_id=?";
  $types .= 'i'; $params[] = $proveedor_id;
  if ($q !== '') {
    $sql .= " AND (nombre LIKE CONCAT('%',?,'%') OR email LIKE CONCAT('%',?,'%') OR empresa LIKE CONCAT('%',?,'%'))";
    $types .= 'sss'; array_push($params,$q,$q,$q);
  }
} else {
  if ($q !== '') {
    $sql .= " WHERE (nombre LIKE CONCAT('%',?,'%') OR email LIKE CONCAT('%',?,'%') OR empresa LIKE CONCAT('%',?,'%'))";
    $types .= 'sss'; array_push($params,$q,$q,$q);
  }
}
$sql .= " ORDER BY nombre LIMIT ?"; $types .= 'i'; $params[] = $limit;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($row = $res->fetch_assoc()) $items[] = $row;
$stmt->close();

echo json_encode(['ok'=>true,'items'=>$items]);
