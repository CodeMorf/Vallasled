<?php
// /console/sistema/usuarios/ajax/guardar.php
declare(strict_types=1);
@header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 4) . '/config/db.php';
start_session_safe();
require_console_auth(['admin','staff']);

try {
  // CSRF
  if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF inválido']); exit;
  }

  // Inputs
  $id             = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $usuario        = trim((string)($_POST['usuario'] ?? ''));
  $responsable    = trim((string)($_POST['responsable'] ?? ''));
  $tipo           = trim((string)($_POST['tipo'] ?? 'cliente'));
  $rol            = trim((string)($_POST['rol'] ?? 'operador'));
  $activo         = (isset($_POST['activo']) && $_POST['activo'] === '1') ? 1 : 0;
  $nombre_empresa = trim((string)($_POST['nombre_empresa'] ?? ''));
  $pass           = (string)($_POST['pass']  ?? '');
  $pass2          = (string)($_POST['pass2'] ?? '');

  // Reglas
  $ALLOWED_TIPOS = ['admin','staff','cliente'];
  $ALLOWED_ROLES = ['operador','staff_basico','staff_operativo'];

  if (!in_array($tipo, $ALLOWED_TIPOS, true)) { echo json_encode(['error'=>'Tipo inválido']); exit; }
  if (!in_array($rol,  $ALLOWED_ROLES, true)) { echo json_encode(['error'=>'Rol inválido']); exit; }
  if ($id <= 0) { // Crear
    if ($usuario === '' || !filter_var($usuario, FILTER_VALIDATE_EMAIL)) { echo json_encode(['error'=>'Email de usuario inválido']); exit; }
    if ($pass === '' || $pass !== $pass2) { echo json_encode(['error'=>'Contraseñas vacías o no coinciden']); exit; }
    if (strlen($pass) < 6) { echo json_encode(['error'=>'Contraseña mínima 6 caracteres']); exit; }

    // Unicidad usuario
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE usuario=? LIMIT 1");
    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) { echo json_encode(['error'=>'El usuario ya existe']); exit; }
    $stmt->close();

    $hash = password_hash($pass, PASSWORD_BCRYPT);

    $sql = "INSERT INTO usuarios (usuario, responsable, tipo, rol, activo, nombre_empresa, clave)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssiss', $usuario, $responsable, $tipo, $rol, $activo, $nombre_empresa, $hash);
    $stmt->execute();
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    echo json_encode(['ok'=>true, 'id'=>$newId], JSON_UNESCAPED_UNICODE); exit;
  } else { // Update
    // No permitir cambiar "usuario" por coherencia con el login
    // Password opcional
    if ($pass !== '' || $pass2 !== '') {
      if ($pass !== $pass2) { echo json_encode(['error'=>'Las contraseñas no coinciden']); exit; }
      if (strlen($pass) < 6) { echo json_encode(['error'=>'Contraseña mínima 6 caracteres']); exit; }
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      $sql = "UPDATE usuarios
              SET responsable=?, tipo=?, rol=?, activo=?, nombre_empresa=?, clave=?
              WHERE id=?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('sssissi', $responsable, $tipo, $rol, $activo, $nombre_empresa, $hash, $id);
    } else {
      $sql = "UPDATE usuarios
              SET responsable=?, tipo=?, rol=?, activo=?, nombre_empresa=?
              WHERE id=?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('sssisi', $responsable, $tipo, $rol, $activo, $nombre_empresa, $id);
    }
    $stmt->execute();
    $aff = $stmt->affected_rows; // puede ser 0 si no cambió nada
    $stmt->close();

    echo json_encode(['ok'=>true, 'id'=>$id, 'changed'=>max(0,$aff)], JSON_UNESCAPED_UNICODE); exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>'Error interno']); // sin detalles
}
