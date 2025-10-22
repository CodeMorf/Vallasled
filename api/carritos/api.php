<?php declare(strict_types=1);
/*/api/carritos*/
require_once __DIR__ . '/../../config/db.php';

session_start();
session_cache_limiter('nocache');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/* ---------------- Helpers ---------------- */
function json_ok(array $p, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
  echo json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

/** Lee un valor desde la tabla web_setting (clave → valor). */
function get_web_setting(string $key, ?string $default = null): ?string {
  static $cache = [];
  if (array_key_exists($key, $cache)) return $cache[$key];
  try {
    if (function_exists('db')) {
      $st = db()->prepare('SELECT valor FROM web_setting WHERE clave = ? LIMIT 1');
      $st->execute([$key]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if ($row && isset($row['valor'])) {
        return $cache[$key] = (string)$row['valor'];
      }
    }
  } catch (Throwable $e) {}
  return $cache[$key] = $default;
}

function normalize_msisdn(string $raw): string {
  $raw = trim($raw);
  if (preg_match('/^\+?\d{7,15}$/', str_replace(' ', '', $raw))) return ltrim($raw, '+');
  $digits = preg_replace('/\D+/', '', $raw);
  return $digits ?? '';
}

function wa_number(): string {
  $raw = get_web_setting('support_whatsapp', null);
  if ($raw === null || $raw === '') $raw = get_web_setting('company_phone', '18090000000');
  $n = normalize_msisdn((string)$raw);
  return $n !== '' ? $n : '18090000000';
}

function wa_url(?string $msg = null): string {
  $to = wa_number();
  $base = 'https://wa.me/' . $to;
  return ($msg !== null && $msg !== '') ? $base . '?text=' . rawurlencode($msg) : $base;
}

function cart_init(): void {
  if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
}

function normalize_img(?string $img): string {
  $img = (string)($img ?? '');
  if ($img === '') return '';
  if (preg_match('~^https?://~i', $img)) return $img;
  $img = preg_replace('~(uploads/)(?:uploads/)~', '\\1', $img);
  $base = get_web_setting('uploads_base', 'https://auth.vallasled.com/uploads/');
  return rtrim($base, '/') . '/' . ltrim($img, '/');
}

/* ---------------- Lógica API ---------------- */
cart_init();

$act = $_REQUEST['a'] ?? 'list';

if ($act === 'add') {
  $id = (int)($_REQUEST['id'] ?? 0);
  if ($id <= 0) json_ok(['ok' => false, 'error' => 'MISSING_ID'], 400);
  $_SESSION['cart'][$id] = (int)(($_SESSION['cart'][$id] ?? 0) + 1);
  json_ok(['ok'=>true,'cart'=>$_SESSION['cart'],'count'=>array_sum($_SESSION['cart']),'whatsapp_to'=>wa_number(),'wa_url'=>wa_url()]);
}

if ($act === 'remove' || $act==='del') {
  $id = (int)($_REQUEST['id'] ?? 0);
  if ($id > 0) unset($_SESSION['cart'][$id]);
  json_ok(['ok'=>true,'cart'=>$_SESSION['cart'],'count'=>array_sum($_SESSION['cart']),'whatsapp_to'=>wa_number(),'wa_url'=>wa_url()]);
}

if ($act === 'clear') {
  $_SESSION['cart'] = [];
  json_ok(['ok'=>true,'count'=>0,'whatsapp_to'=>wa_number(),'wa_url'=>wa_url()]);
}

if ($act === 'count') {
  json_ok(['ok'=>true,'count'=>array_sum($_SESSION['cart']),'whatsapp_to'=>wa_number(),'wa_url'=>wa_url()]);
}

if ($act === 'wa' || $act === 'whatsapp') {
  json_ok(['ok'=>true,'to'=>wa_number(),'wa_url'=>wa_url()]);
}

/* ------- list (detalle de carrito) ------- */
$ids = array_keys($_SESSION['cart']);
$items = [];
$total = 0.0;

if (!empty($ids)) {
  $in = implode(',', array_fill(0, count($ids), '?'));
  $st = db()->prepare("SELECT id, nombre, precio, imagen, imagen1 FROM vallas WHERE id IN ($in)");
  $st->execute($ids);

  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $vid  = (int)$r['id'];
    $qty  = (int)($_SESSION['cart'][$vid] ?? 0);
    if ($qty <= 0) continue;

    $precio   = (float)($r['precio'] ?? 0);
    $subtotal = $precio * $qty;
    $total   += $subtotal;

    $img = normalize_img($r['imagen'] ?: ($r['imagen1'] ?? ''));

    $items[] = [
      'id'=>$vid,
      'nombre'=>(string)$r['nombre'],
      'precio'=>$precio,
      'qty'=>$qty,
      'img'=>$img,
      'subtotal'=>$subtotal
    ];
  }
}

json_ok(['ok'=>true,'items'=>$items,'total'=>$total,'count'=>array_sum($_SESSION['cart']),'whatsapp_to'=>wa_number(),'wa_url'=>wa_url()]);
