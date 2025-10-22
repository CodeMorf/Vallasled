<?php
// /console/gestion/empleados/ajax/listar.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/db.php';
start_session_safe();

/* Solo admin */
if (empty($_SESSION['uid']) || ($_SESSION['tipo']!=='admin')) {
  http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}

/* GET: listar */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
  $q = trim((string)($_GET['q'] ?? ''));
  try {
    if ($q !== '') {
      $like = '%' . $q . '%';
      $st = $conn->prepare(
        "SELECT id, usuario, email, activo, rol
         FROM usuarios
         WHERE tipo='staff' AND (usuario LIKE ? OR email LIKE ?)
         ORDER BY id DESC LIMIT 200"
      );
      $st->bind_param('ss', $like, $like);
    } else {
      $st = $conn->prepare(
        "SELECT id, usuario, email, activo, rol
         FROM usuarios
         WHERE tipo='staff'
         ORDER BY id DESC LIMIT 200"
      );
    }
    $st->execute();
    $rs = $st->get_result();
    $items = [];
    while ($r = $rs->fetch_assoc()) {
      $items[] = [
        'id'      => (int)$r['id'],
        'usuario' => (string)$r['usuario'],
        'email'   => (string)$r['email'],
        'activo'  => (int)$r['activo'],
        'rol'     => (string)($r['rol'] ?? ''),
      ];
    }
    echo json_encode(['ok'=>true,'items'=>$items]);
  } catch (Throwable $e) {
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_error']); 
  }
  exit;
}

/* POST: crear empleado */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  /* CSRF */
  $csrf = $_POST['csrf'] ?? '';
  if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], (string)$csrf)) {
    http_response_code(403); echo json_encode(['ok'=>false,'error'=>'csrf_invalid']); exit;
  }
  $action  = (string)($_POST['action'] ?? '');
  if ($action !== 'create') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_action']); exit; }

  $usuario = trim((string)($_POST['usuario'] ?? ''));
  $email   = trim((string)($_POST['email'] ?? ''));
  $pass    = (string)($_POST['password'] ?? '');
  $rol     = trim((string)($_POST['rol'] ?? ''));

  if ($usuario==='' || $email==='' || $pass==='') {
    http_response_code(422); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit;
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422); echo json_encode(['ok'=>false,'error'=>'invalid_email']); exit;
  }

  try {
    /* duplicados por email */
    $st = $conn->prepare("SELECT id FROM usuarios WHERE LOWER(email)=LOWER(?) LIMIT 1");
    $st->bind_param('s', $email); $st->execute(); $st->store_result();
    if ($st->num_rows > 0) { http_response_code(409); echo json_encode(['ok'=>false,'error'=>'email_exists']); exit; }

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $rolDb = ($rol!=='') ? $rol : 'operador';

    $st = $conn->prepare("INSERT INTO usuarios (usuario, email, clave, tipo, rol, activo) VALUES (?,?,?,?,?,1)");
    $tipo = 'staff';
    $st->bind_param('sssss', $usuario, $email, $hash, $tipo, $rolDb);
    $st->execute();
    $uid = (int)$conn->insert_id;

    /* aplica rol predefinido: solo permisos url: */
    if ($rol !== '') {
      $st = $conn->prepare(
        "INSERT IGNORE INTO usuarios_permisos (usuario_id, permiso, valor)
         SELECT ?, rp.permiso, 1
         FROM roles_permisos rp
         WHERE rp.rol = ? AND rp.permiso LIKE 'url:%'"
      );
      $st->bind_param('is', $uid, $rol);
      $st->execute();
    }

    echo json_encode(['ok'=>true,'id'=>$uid,'redirect'=>'/console/gestion/empleados/']);
  } catch (Throwable $e) {
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_error']);
  }
  exit;
}

http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
