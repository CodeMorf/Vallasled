<?php
// /console/vallas/agregar/ajax/handler.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function jexit(array $o, int $code = 200): void {
  http_response_code($code);
  echo json_encode($o, JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jexit(['ok' => false, 'msg' => 'Método no permitido'], 405);
}

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  jexit(['ok' => false, 'msg' => 'No autorizado'], 401);
}

$csrfSession = $_SESSION['csrf'] ?? '';
$csrfHeader  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfPost    = $_POST['csrf'] ?? '';
if (!$csrfSession || !hash_equals($csrfSession, $csrfHeader ?: $csrfPost)) {
  jexit(['ok' => false, 'msg' => 'CSRF inválido'], 400);
}

$action = $_POST['action'] ?? '';
if ($action !== 'create') {
  jexit(['ok' => false, 'msg' => 'Acción inválida'], 400);
}

/** @var mysqli $conn */
if (!isset($conn) || !($conn instanceof mysqli)) {
  jexit(['ok' => false, 'msg' => 'DB no disponible'], 500);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function S(string $k): string { return trim((string)($_POST[$k] ?? '')); }
function N(string $k): float  { return (float)($_POST[$k] ?? 0); }

$nombre   = mb_substr(S('nombre'), 0, 120);
$codigo   = mb_substr(S('codigo'), 0, 40);
$tipo     = mb_substr(S('tipo'), 0, 60);
$estado   = mb_substr(S('estado'), 0, 20);
$direccion= mb_substr(S('direccion'), 0, 200);
$lat      = (float)S('lat');
$lng      = (float)S('lng');
$zona     = mb_substr(S('zona'), 0, 80);
$precio   = (float)S('precio_mensual');
$ancho_m  = (float)S('ancho_m');
$alto_m   = (float)S('alto_m');
$ilum     = mb_substr(S('iluminacion'), 0, 20);
$lic_vence= S('licencia_vence');
$desc     = mb_substr(S('descripcion'), 0, 1000);
$destacado= isset($_POST['destacado']) ? 1 : 0;

// Validaciones servidor (mismas reglas que front)
if ($nombre === '' || $codigo === '' || $tipo === '' || $estado === '' || $direccion === '') {
  jexit(['ok' => false, 'msg' => 'Campos obligatorios faltantes']);
}
if (!preg_match('/^[A-Za-z0-9_.\-]{3,40}$/', $codigo)) {
  jexit(['ok' => false, 'msg' => 'Código inválido']);
}
if (!preg_match('/^-?\d{1,2}\.\d{1,8}$/', (string)$lat) || $lat < -90 || $lat > 90) {
  jexit(['ok' => false, 'msg' => 'Latitud inválida']);
}
if (!preg_match('/^-?\d{1,3}\.\d{1,8}$/', (string)$lng) || $lng < -180 || $lng > 180) {
  jexit(['ok' => false, 'msg' => 'Longitud inválida']);
}
if (!is_numeric($precio) || $precio < 0) {
  jexit(['ok' => false, 'msg' => 'Precio inválido']);
}
if (!is_numeric($ancho_m) || $ancho_m < 0.1) {
  jexit(['ok' => false, 'msg' => 'Ancho inválido']);
}
if (!is_numeric($alto_m) || $alto_m < 0.1) {
  jexit(['ok' => false, 'msg' => 'Alto inválido']);
}
if ($lic_vence !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lic_vence)) {
  jexit(['ok' => false, 'msg' => 'Fecha de licencia inválida']);
}

// Unicidad de código
$stmt = $conn->prepare('SELECT id FROM vallas WHERE codigo = ? LIMIT 1');
$stmt->bind_param('s', $codigo);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
  $stmt->close();
  jexit(['ok' => false, 'msg' => 'Código ya existe']);
}
$stmt->close();

// Procesar imagen si viene
$imagen_url = null;
if (!empty($_FILES['imagen']) && is_uploaded_file($_FILES['imagen']['tmp_name'])) {
  $f = $_FILES['imagen'];
  if ($f['error'] !== UPLOAD_ERR_OK) {
    jexit(['ok' => false, 'msg' => 'Error de subida']);
  }
  if ($f['size'] > 5 * 1024 * 1024) {
    jexit(['ok' => false, 'msg' => 'Imagen supera 5 MB']);
  }
  $fi = new finfo(FILEINFO_MIME_TYPE);
  $mime = $fi->file($f['tmp_name']) ?: '';
  $allow = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
  ];
  if (!isset($allow[$mime])) {
    jexit(['ok' => false, 'msg' => 'Formato de imagen no permitido']);
  }
  $root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 5), '/');
  $sub  = '/media/vallas/' . date('Y/m');
  $dir  = $root . $sub;
  if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    jexit(['ok' => false, 'msg' => 'No se pudo crear directorio']);
  }
  $basename = bin2hex(random_bytes(8)) . '.' . $allow[$mime];
  $destPath = $dir . '/' . $basename;

  // Seguridad: evitar traversal
  $realDir  = realpath($dir);
  if ($realDir === false || strpos($destPath, $realDir) !== 0) {
    jexit(['ok' => false, 'msg' => 'Ruta de destino inválida']);
  }

  if (!move_uploaded_file($f['tmp_name'], $destPath)) {
    jexit(['ok' => false, 'msg' => 'No se pudo guardar la imagen']);
  }
  // URL pública relativa
  $imagen_url = $sub . '/' . $basename;
}

// Insert
try {
  $conn->begin_transaction();

  $sql = "INSERT INTO vallas
    (nombre, codigo, tipo, estado, direccion, lat, lng, zona, precio_mensual, ancho_m, alto_m, iluminacion, licencia_vence, descripcion, destacado, imagen_url, created_at, created_by)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)";

  $stmt = $conn->prepare($sql);
  $uid  = (int)($_SESSION['uid'] ?? 0);

  // Tipos: s=string, d=double, i=int; imagen_url puede ser null
  $stmt->bind_param(
    'sssssd dsss ssssi s',
    /* nombre      */ $nombre,
    /* codigo      */ $codigo,
    /* tipo        */ $tipo,
    /* estado      */ $estado,
    /* direccion   */ $direccion,
    /* lat         */ $lat,
    /* lng         */ $lng,
    /* zona        */ $zona,
    /* precio      */ $precio,
    /* ancho_m     */ $ancho_m,
    /* alto_m      */ $alto_m,
    /* iluminacion */ $ilum,
    /* licencia    */ ($lic_vence !== '' ? $lic_vence : null),
    /* descripcion */ $desc,
    /* destacado   */ $destacado,
    /* imagen_url  */ $imagen_url,
    /* created_by  */ $uid
  );
  // Nota: espacios en la cadena anterior son para legibilidad. PHP ignora.

  $stmt->execute();
  $newId = $conn->insert_id;
  $stmt->close();

  $conn->commit();

  jexit(['ok' => true, 'id' => $newId, 'url' => '/console/vallas/?created=' . $newId]);
} catch (Throwable $e) {
  if ($conn->errno) { $conn->rollback(); }
  // Si subimos imagen, intento limpiar en caso de error
  if (!empty($imagen_url)) {
    $root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 5), '/');
    $p = $root . $imagen_url;
    if (is_file($p)) @unlink($p);
  }
  jexit(['ok' => false, 'msg' => 'Error al guardar'], 500);
}
