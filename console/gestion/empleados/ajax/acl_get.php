<?php
// /console/gestion/empleados/ajax/acl_get.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/db.php';
start_session_safe();

/* Solo admin */
if (empty($_SESSION['uid']) || ($_SESSION['tipo']!=='admin')) {
  http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}

$uid = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;
if ($uid<=0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'missing_user']); exit; }

try {
  /* verifica que sea staff */
  $st = $conn->prepare("SELECT id, usuario, email, rol FROM usuarios WHERE id=? AND tipo='staff' LIMIT 1");
  $st->bind_param('i',$uid); $st->execute(); $u = $st->get_result()->fetch_assoc();
  if (!$u) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

  $st = $conn->prepare("SELECT permiso FROM usuarios_permisos WHERE usuario_id=? AND valor=1 AND permiso LIKE 'url:%'");
  $st->bind_param('i',$uid); $st->execute(); $rs = $st->get_result();
  $urls = [];
  while ($r = $rs->fetch_assoc()) {
    $p = (string)$r['permiso'];
    $urls[] = substr($p, 4); // quita 'url:'
  }

  echo json_encode([
    'ok'=>true,
    'user'=>['id'=>(int)$u['id'],'usuario'=>$u['usuario'],'email'=>$u['email'],'rol'=>$u['rol']],
    'urls'=>$urls
  ]);
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_error']);
}
