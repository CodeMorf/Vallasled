<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

/* Solo POST AJAX */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405); echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit;
}

/* CSRF */
$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], (string)$csrf)) {
  http_response_code(403); echo json_encode(['ok'=>false,'error'=>'csrf_invalid']); exit;
}

/* Inputs */
$email = trim((string)($_POST['email'] ?? ''));
$pass  = (string)($_POST['password'] ?? '');
if ($email==='' || $pass==='') { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }

function norm_path(string $p): string {
  $p = parse_url($p, PHP_URL_PATH) ?: '/';
  if ($p==='') $p='/';
  if ($p[0] !== '/') $p = '/'.$p;
  if (substr($p,-1) !== '/') $p .= '/';
  return $p;
}

/* Busca usuario por email (admin o staff) */
$uid = 0; $ok = false; $storedHash = null; $tipo = null; $rol = '';
try {
  $st = $conn->prepare("SELECT id, clave, tipo, IFNULL(rol,'') AS rol FROM usuarios WHERE activo=1 AND LOWER(email)=LOWER(?) LIMIT 1");
  $st->bind_param('s', $email);
  $st->execute();
  $rs = $st->get_result();
  if ($u = $rs->fetch_assoc()) {
    $uid = (int)$u['id'];
    $storedHash = (string)$u['clave'];
    $tipo = (string)$u['tipo'];          // 'admin' | 'staff' | 'cliente'
    $rol  = (string)$u['rol'];
    $ok = password_verify($pass, $storedHash);
  }
} catch (Throwable $e) {}

/* Fallback admin via config_global si no pasó lo anterior */
if (!$ok) {
  $admEmail = null; $admHash = null;
  try {
    $cg = $conn->query("SELECT clave, valor FROM config_global WHERE clave IN ('admin_email','admin_pass_hash') AND activo=1");
    while ($r = $cg->fetch_assoc()) {
      if ($r['clave'] === 'admin_email')     $admEmail = $r['valor'];
      if ($r['clave'] === 'admin_pass_hash') $admHash  = $r['valor'];
    }
  } catch (Throwable $e) {}
  if ($admEmail && $admHash) {
    if (hash_equals(strtolower($admEmail), strtolower($email)) && password_verify($pass, $admHash)) {
      $ok = true; $uid = 0; $tipo = 'admin'; $rol = '';
    }
  }
}

if (!$ok) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'invalid_credentials']); exit; }

/* Construye permisos de sesión */
$perm_set = []; // conjunto para deduplicar
if ($tipo === 'staff' || $tipo === 'admin') {
  // Permisos por rol
  if ($rol !== '') {
    $stmt = $conn->prepare("SELECT permiso FROM roles_permisos WHERE rol=?");
    $stmt->bind_param('s', $rol);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) {
        $p = (string)$row['permiso'];
        if ($p !== '') $perm_set[$p] = true;
      }
    }
  }
  // Permisos directos del usuario
  if ($uid > 0) {
    $stmt = $conn->prepare("SELECT permiso FROM usuarios_permisos WHERE usuario_id=? AND valor=1");
    $stmt->bind_param('i', $uid);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) {
        $p = (string)$row['permiso'];
        if ($p !== '') $perm_set[$p] = true;
      }
    }
  }
  // Asegura acceso al panel de empleado si es staff
  if ($tipo === 'staff') {
    $perm_set['url:'.norm_path('/console/empleados/')] = true;
  }
}
$permisos = array_keys($perm_set);

/* Sesión */
session_regenerate_id(true);
$_SESSION['uid']       = $uid;
$_SESSION['tipo']      = $tipo ?? 'admin';
$_SESSION['email']     = $email;
$_SESSION['rol']       = $rol;
$_SESSION['permisos']  = $permisos;

/* Registrar acceso si existe historial_acceso (maneja esquemas distintos) */
try {
  if ($conn->query("SHOW TABLES LIKE 'historial_acceso'")->num_rows === 1) {
    // Detecta columnas
    $cols = [];
    $q = $conn->query("SHOW COLUMNS FROM historial_acceso");
    while ($c = $q->fetch_assoc()) { $cols[strtolower($c['Field'])] = true; }
    $ip = '0.0.0.0';
    foreach (['HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
      if (!empty($_SERVER[$k])) { $ip = trim(explode(',', (string)$_SERVER[$k])[0]); break; }
    }
    if (isset($cols['user_agent'])) {
      $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
      $stmt = $conn->prepare("INSERT INTO historial_acceso (usuario_id, ip, user_agent, inicio) VALUES (?, ?, ?, NOW())");
      $stmt->bind_param('iss', $uid, $ip, $ua);
      $stmt->execute();
    } else {
      $stmt = $conn->prepare("INSERT INTO historial_acceso (usuario_id, ip, inicio) VALUES (?, ?, NOW())");
      $stmt->bind_param('is', $uid, $ip);
      $stmt->execute();
    }
  }
} catch (Throwable $e) {}

/* Redirect según tipo */
$redirect = ($tipo === 'staff') ? '/console/empleados/index.php' : '/console/portal/';
echo json_encode(['ok'=>true,'redirect'=>$redirect], JSON_UNESCAPED_UNICODE);
