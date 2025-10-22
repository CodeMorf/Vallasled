<?php
// /console/gestion/web/ajax/branding_apply.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin']); // solo admin

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'], 405);
}
if (!csrf_ok_from_header_or_post()) {
    json_exit(['ok'=>false,'error'=>'BAD_CSRF'], 403);
}

/* ---------- helpers ---------- */
$clamp_int = static function($v, int $min, int $max): int {
  if (!is_numeric($v)) return $min;
  $v = (int)$v; return max($min, min($max, $v));
};
$clean_str = static function($v, int $max=255): string {
  $s = is_string($v) ? $v : (string)$v;
  $s = preg_replace("/\r\n?/", "\n", $s);
  return mb_substr(trim($s), 0, $max, 'UTF-8');
};
$abs_or_rel = static fn(string $s): bool =>
  $s === '' || $s[0] === '/' || (bool)filter_var($s, FILTER_VALIDATE_URL);

/* ---------- lee últimos valores desde web_setting ---------- */
$map_in = ['site_name','logo_url','logo_width_px','logo_height_px'];
$place  = implode(',', array_fill(0, count($map_in), '?'));

$stmt = $conn->prepare(
  "SELECT clave, valor
     FROM web_setting
    WHERE clave IN ($place)
 ORDER BY clave ASC, updated_at DESC, id DESC"
);
$stmt->bind_param(str_repeat('s', count($map_in)), ...$map_in);
$stmt->execute();
$res = $stmt->get_result();

$seen = [];
$cfg  = [];
while ($r = $res->fetch_assoc()) {
  $k = (string)$r['clave'];
  if (!isset($seen[$k])) { $cfg[$k] = (string)$r['valor']; $seen[$k]=true; }
}

/* ---------- overrides por body (JSON o POST) ---------- */
$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_POST;

if (is_array($in)) {
  foreach ($map_in as $k) {
    if (!array_key_exists($k,$in)) continue;
    $v = $in[$k];

    switch ($k) {
      case 'site_name':
        $v = $clean_str($v, 120);
        break;
      case 'logo_url':
        $v = trim((string)$v);
        if ($v !== '' && !$abs_or_rel($v)) {
          json_exit(['ok'=>false,'error'=>'INVALID_URL','field'=>'logo_url'], 422);
        }
        break;
      case 'logo_width_px':
      case 'logo_height_px':
        $v = (string)$clamp_int($v, 0, 2000);
        break;
      default:
        $v = $clean_str($v, 255);
    }
    $cfg[$k] = $v;
  }
}

/* ---------- mapea a config_global ---------- */
$to_global = [
  'site_title' => $clean_str($cfg['site_name']    ?? ''),
  'logo_url'   => trim((string)($cfg['logo_url']  ?? '')),
  'logo_width' => (string)$clamp_int($cfg['logo_width_px']  ?? 120, 0, 2000),
  'logo_height'=> (string)$clamp_int($cfg['logo_height_px'] ?? 40,  0, 2000),
];

/* ---------- persiste (UPDATE->INSERT) ---------- */
try {
  $conn->begin_transaction();

  $upd = $conn->prepare("UPDATE config_global SET valor=?, activo=1 WHERE clave=?");
  $ins = $conn->prepare("INSERT INTO config_global (clave, valor, activo) VALUES (?, ?, 1)");

  foreach ($to_global as $k=>$v) {
    $upd->bind_param('ss', $v, $k);
    $upd->execute();
    if ($upd->affected_rows === 0) {
      $ins->bind_param('ss', $k, $v);
      $ins->execute();
    }
  }

  $conn->commit();
  json_exit(['ok'=>true,'applied'=>$to_global]);
} catch (Throwable $e) {
  @mysqli_rollback($conn);
  $err = ['ok'=>false,'error'=>'APPLY_ERROR','detail'=>$e->getMessage()];
  if ($e instanceof mysqli_sql_exception) $err['errno'] = $e->getCode();
  json_exit($err, 500);
}
