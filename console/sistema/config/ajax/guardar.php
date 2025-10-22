<?php
// /console/sistema/config/ajax/guardar.php
declare(strict_types=1);
@header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 4) . '/config/db.php';
start_session_safe();
require_console_auth(['admin','staff']);

function str_bool($v): string { return ($v==='1'||$v===1||$v===true)?'1':'0'; }
function clamp_int($v, int $min, int $max): int { $x=(int)$v; return max($min, min($max, $x)); }
function val_hex($c): string { return preg_match('/^#[0-9a-fA-F]{6}$/',$c)?$c:'#4f46e5'; }
function val_email($e): string { return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : ''; }
function val_float($s, int $dec=6): string { if(!is_numeric($s)) return '0'; return number_format((float)$s, $dec, '.', ''); }
function val_currency($s): string { $s=strtolower(trim((string)$s)); return preg_match('/^[a-z]{3}$/',$s)?$s:'usd'; }
function val_pct($s): string { $f=(float)$s; if($f<0)$f=0; if($f>100)$f=100; return number_format($f,2,'.',''); }

try {
  if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF inválido']); exit;
  }

  $section = trim((string)($_POST['section'] ?? ''));
  $payload = (string)($_POST['payload'] ?? '{}');
  $data = json_decode($payload, true);
  if (!is_array($data)) { echo json_encode(['error'=>'Payload inválido']); exit; }

  $conn->query("CREATE TABLE IF NOT EXISTS app_config (
    clave VARCHAR(64) PRIMARY KEY,
    valor TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $uid = (int)($_SESSION['uid'] ?? 0);
  $saved = 0;

  $allow = [
    'general'      => ['site_name','admin_email','site_desc','company_phone','support_whatsapp'],
    'apis'         => ['google_maps_api','openai_api','openai_model','cron_key'],
    'smtp'         => ['smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from_email','smtp_from_name'],
    'payments'     => ['stripe_pk','stripe_sk','stripe_currency','vendor_comision'],
    'appearance'   => ['logo_url','favicon_url','primary_color','secondary_color'],
    'maps'         => ['map_provider','map_style','map_lat','map_lng','map_zoom'],
    'integrations' => ['g_client_id','g_client_secret','g_redirect_uri']
  ];
  if (!isset($allow[$section])) { echo json_encode(['error'=>'Sección inválida']); exit; }

  // Normalización por sección
  if ($section==='smtp') {
    if (isset($data['smtp_port'])) $data['smtp_port'] = (string)clamp_int($data['smtp_port'],1,65535);
    if (isset($data['smtp_from_email'])) $data['smtp_from_email'] = val_email($data['smtp_from_email']);
  }
  if ($section==='payments') {
    if (isset($data['stripe_currency'])) $data['stripe_currency'] = val_currency($data['stripe_currency']);
    if (isset($data['vendor_comision'])) $data['vendor_comision'] = val_pct($data['vendor_comision']);
  }
  if ($section==='appearance') {
    if (isset($data['primary_color']))   $data['primary_color']   = val_hex($data['primary_color']);
    if (isset($data['secondary_color'])) $data['secondary_color'] = val_hex($data['secondary_color']);
  }
  if ($section==='maps') {
    if (isset($data['map_zoom'])) $data['map_zoom'] = (string)clamp_int($data['map_zoom'],1,19);
    if (isset($data['map_lat']))  $data['map_lat']  = val_float($data['map_lat'],7);
    if (isset($data['map_lng']))  $data['map_lng']  = val_float($data['map_lng'],7);
    if (isset($data['map_provider']) && !in_array($data['map_provider'], ['google','osm','carto'], true)) $data['map_provider']='google';
    if (isset($data['map_style']) && !in_array($data['map_style'], ['google.roadmap','google.satellite','google.hybrid','google.terrain'], true)) $data['map_style']='google.roadmap';
  }
  if ($section==='general') {
    if (isset($data['admin_email'])) $data['admin_email'] = val_email($data['admin_email']);
  }

  $stmt = $conn->prepare("INSERT INTO app_config (clave, valor, updated_by) VALUES (?, ?, ?)
                          ON DUPLICATE KEY UPDATE valor=VALUES(valor), updated_by=VALUES(updated_by)");
  foreach ($allow[$section] as $k) {
    if (!array_key_exists($k, $data)) continue;
    $v = (string)$data[$k];
    $stmt->bind_param('ssi', $k, $v, $uid);
    $stmt->execute();
    $saved += max(0, $stmt->affected_rows);
  }
  $stmt->close();

  echo json_encode(['ok'=>true,'saved'=>$saved], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>'Error interno']);
}
