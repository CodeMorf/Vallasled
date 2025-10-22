<?php
declare(strict_types=1);

/**
 * /console/tools/webauthn_check.php
 * Reporte visual de estado para login con huella (WebAuthn).
 * Sin BOM. No cierra con "?>".
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('html_errors', '0');

$start = microtime(true);

/* ---------- Utilidades ---------- */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
    return false;
}
function scheme_host(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = is_https() ? 'https' : 'http';
    return $scheme . '://' . preg_replace('~:\d+$~','', strtolower($host));
}
function derive_etld1(string $host): string {
    $host = preg_replace('~:\d+$~','', strtolower($host));
    $p = explode('.', $host);
    return count($p) >= 3 ? implode('.', array_slice($p, -2)) : $host;
}
function find_vendor_autoload(string $startDir): ?string {
    $dir = realpath($startDir);
    for ($i = 0; $i < 6 && $dir; $i++, $dir = dirname($dir)) {
        $cand = $dir . '/vendor/autoload.php';
        if (is_file($cand)) return $cand;
    }
    return null;
}
function fetch_url(string $url, int $timeout = 5): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest'],
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$status, $body, $err ?: null];
    }
    $ctx = stream_context_create(['http'=>['timeout'=>$timeout, 'header'=>"X-Requested-With: XMLHttpRequest\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $h) if (preg_match('~HTTP/\d\.\d\s+(\d+)~', $h, $m)) { $status = (int)$m[1]; break; }
    }
    return [$status, $body, null];
}
function has_bom(string $file): bool {
    if (!is_file($file)) return false;
    $fp = fopen($file, 'rb');
    if (!$fp) return false;
    $bytes = fread($fp, 3);
    fclose($fp);
    return $bytes === "\xEF\xBB\xBF";
}

/* ---------- Cargar DB y autoload ---------- */
$projectRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
$autoload    = find_vendor_autoload(__DIR__);
$vendor_ok = false; $classes_ok = false; $lib_errors = [];

if ($autoload) {
    $vendor_ok = @include_once $autoload;
    if (!$vendor_ok) $lib_errors[] = 'autoload.php no se pudo cargar';
} else {
    $lib_errors[] = 'vendor/autoload.php no encontrado';
}

/* DB (opcional): requiere config/db.php que exponga $conn mysqli */
$db_ok = false; $db_err = null; $conn = null;
$cfg_path = $projectRoot . '/config/db.php';
if (is_file($cfg_path)) {
    try {
        require_once $cfg_path;
        if (isset($conn) && ($conn instanceof mysqli)) {
            $db_ok = @$conn->ping();
            if (!$db_ok) $db_err = 'ping() falló';
        } else {
            $db_err = 'config/db.php no expone $conn mysqli';
        }
    } catch (Throwable $e) {
        $db_ok = false; $db_err = $e->getMessage();
    }
} else {
    $db_err = 'config/db.php no encontrado en ' . $cfg_path;
}

/* RP ID */
$rp_from_db = null;
if ($db_ok) {
    try {
        $q = @$conn->query("SELECT `value` FROM config_kv WHERE `key`='webauthn_rp_id' LIMIT 1");
        if ($q && ($r = $q->fetch_assoc())) $rp_from_db = strtolower((string)$r['value']);
    } catch (Throwable $e) {}
}
$rp_effective = $rp_from_db ?: derive_etld1($_SERVER['HTTP_HOST'] ?? 'auth.vallasled.com');

/* Extensiones y funciones */
$req_ext = ['json','mbstring','openssl','curl','sodium'];
$ext_status = [];
foreach ($req_ext as $e) $ext_status[$e] = extension_loaded($e);

$req_fn  = ['putenv','proc_open'];
$fn_status = [];
foreach ($req_fn as $f) $fn_status[$f] = function_exists($f);

/* Clases lib */
if ($vendor_ok) {
    $classes_ok = class_exists(\Nyholm\Psr7\Factory\Psr17Factory::class)
               && class_exists(\Webauthn\PublicKeyCredentialLoader::class)
               && class_exists(\Webauthn\AuthenticatorAssertionResponseValidator::class);
    if (!$classes_ok) $lib_errors[] = 'Clases WebAuthn/PSR7 no disponibles';
}

/* Tablas */
$tbls = ['webauthn_credentials','webauthn_challenges','config_kv'];
$tbl_status = [];
if ($db_ok) {
    foreach ($tbls as $t) {
        try {
            $res = @$conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($t)."'");
            $tbl_status[$t] = ($res && $res->num_rows > 0);
        } catch (Throwable $e) { $tbl_status[$t] = false; }
    }
}

/* Endpoints y JSON */
$docroot = realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__,2));
$endpoints = [
    '/console/ajax/auth/webauthn_begin_login.php',
    '/console/ajax/auth/webauthn_finish_login.php',
    '/console/ajax/auth/webauthn_begin_register.php',
    '/console/ajax/auth/webauthn_finish_register.php',
];
$ep_files = [];
foreach ($endpoints as $ep) $ep_files[$ep] = is_file($docroot . $ep);

$base = scheme_host();
$ep_json = [];
foreach ($endpoints as $ep) {
    $url = $base . $ep;
    [$status, $body, $err] = fetch_url($url, 4);
    $ok = false; $msg = null;
    if ($status) {
        $j = json_decode((string)$body, true);
        if (is_array($j)) { $ok = array_key_exists('ok', $j); $msg = $ok ? 'ok=' . ((string)$j['ok']) : 'JSON sin clave ok'; }
        else $msg = 'No JSON: ' . substr((string)$body, 0, 80);
    } else {
        $msg = $err ?: 'sin respuesta';
    }
    $ep_json[$ep] = [$status, $ok, $msg];
}

/* .user.ini */
$user_ini_path = $docroot . '/.user.ini';
$user_ini = ['exists' => is_file($user_ini_path), 'display_errors'=>null, 'html_errors'=>null];
if ($user_ini['exists']) {
    $ini = @parse_ini_file($user_ini_path, false, INI_SCANNER_RAW) ?: [];
    $user_ini['display_errors'] = $ini['display_errors'] ?? null;
    $user_ini['html_errors'] = $ini['html_errors'] ?? null;
}

/* BOM scan */
$scan_files = array_merge([$cfg_path], array_map(fn($ep)=> $docroot.$ep, $endpoints));
$bom_status = [];
foreach ($scan_files as $f) if ($f && is_file($f)) $bom_status[$f] = has_bom($f);

/* Resumen */
$problems = [];
if (!is_https()) $problems[] = 'HTTPS no detectado';
foreach ($ext_status as $k=>$v) if (!$v) $problems[] = "Extensión faltante: $k";
foreach ($fn_status as $k=>$v) if (!$v) $problems[] = "Función deshabilitada: $k";
if (!$vendor_ok) $problems[] = 'composer autoload no cargado';
if ($vendor_ok && !$classes_ok) $problems[] = 'clases WebAuthn/PSR7 no disponibles';
if (!$db_ok) $problems[] = 'DB no disponible: ' . ($db_err ?? '');
foreach ($tbl_status as $t=>$v) if (!$v) $problems[] = "Tabla faltante: $t";
foreach ($ep_files as $ep=>$v) if (!$v) $problems[] = "Archivo endpoint ausente: $ep";
foreach ($ep_json as $ep=>[$st,$ok,$msg]) if ($st && !$ok) $problems[] = "Endpoint sin JSON válido: $ep → $msg";
foreach ($bom_status as $f=>$b) if ($b) $problems[] = "BOM presente: $f";

$ok_all = empty($problems);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WebAuthn Check</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
 body{margin:0;background:#0f172a;color:#e5e7eb;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto}
 .wrap{max-width:1100px;margin:24px auto;padding:0 16px}
 .card{background:#111827;border:1px solid #1f2937;border-radius:14px;padding:18px;margin:14px 0}
 .title{font-size:28px;font-weight:700;margin:8px 0}
 .k{color:#9ca3af}
 .ok{color:#10b981} .bad{color:#ef4444} .warn{color:#f59e0b}
 .pill{display:inline-block;padding:3px 9px;border-radius:999px;font-size:12px;margin-left:8px}
 .pill.ok{background:#064e3b;color:#a7f3d0} .pill.bad{background:#7f1d1d;color:#fecaca} .pill.warn{background:#78350f;color:#fde68a}
 table{width:100%;border-collapse:collapse;margin-top:10px}
 th,td{padding:8px 10px;border-bottom:1px solid #1f2937;font-size:14px}
 th{color:#9ca3af;font-weight:600;text-align:left}
 code{background:#0b1220;border:1px solid #1f2937;padding:2px 6px;border-radius:6px}
 .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
 @media (max-width:900px){.grid{grid-template-columns:1fr}}
 .footer{font-size:12px;color:#9ca3af;margin:10px 0 24px}
</style>
</head>
<body>
<div class="wrap">
  <div class="title">WebAuthn Check <span class="pill <?= $ok_all?'ok':'bad' ?>"><?= $ok_all?'OK':'Errores' ?></span></div>

  <div class="card">
    <div><span class="k">Host:</span> <?= h($_SERVER['HTTP_HOST'] ?? '-') ?> <span class="pill <?= is_https()?'ok':'bad' ?>"><?= is_https()?'HTTPS':'NO HTTPS' ?></span></div>
    <div><span class="k">Base:</span> <?= h($base) ?></div>
    <div><span class="k">RP detectado:</span> <code><?= h($rp_effective) ?></code><?= $rp_from_db ? ' <span class="pill ok">forzado por DB</span>' : '' ?></div>
  </div>

  <div class="grid">
    <div class="card">
      <div><b>Extensiones</b></div>
      <table>
        <tr><th>Extensión</th><th>Estado</th></tr>
        <?php foreach ($ext_status as $e=>$v): ?>
          <tr><td><?= h($e) ?></td><td class="<?= $v?'ok':'bad' ?>"><?= $v?'OK':'FALTA' ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="card">
      <div><b>Funciones requeridas</b></div>
      <table>
        <tr><th>Función</th><th>Estado</th></tr>
        <?php foreach ($fn_status as $f=>$v): ?>
          <tr><td><?= h($f) ?></td><td class="<?= $v?'ok':'bad' ?>"><?= $v?'OK':'DESHABILITADA' ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <div><b>Composer</b> <span class="pill <?= $vendor_ok?'ok':'bad' ?>"><?= $vendor_ok?'autoload OK':'sin autoload' ?></span></div>
      <div class="<?= $classes_ok?'ok':'bad' ?>">Clases WebAuthn/PSR7: <?= $classes_ok?'OK':'NO' ?></div>
      <?php if ($lib_errors): ?>
        <ul>
          <?php foreach ($lib_errors as $e): ?>
            <li class="bad"><?= h($e) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <div><span class="k">Ruta autoload:</span> <code><?= h($autoload ?? '—') ?></code></div>
    </div>

    <div class="card">
      <div><b>Base de datos</b> <span class="pill <?= $db_ok?'ok':'bad' ?>"><?= $db_ok?'conectada':'error' ?></span></div>
      <?php if (!$db_ok): ?><div class="bad"><?= h((string)$db_err) ?></div><?php endif; ?>
      <table>
        <tr><th>Tabla</th><th>Existe</th></tr>
        <?php foreach ($tbls as $t): $v=$tbl_status[$t]??false; ?>
          <tr><td><?= h($t) ?></td><td class="<?= $v?'ok':'bad' ?>"><?= $v?'OK':'NO' ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <div class="card">
    <div><b>Endpoints</b></div>
    <table>
      <tr><th>Ruta</th><th>Archivo</th><th>HTTP</th><th>JSON</th><th>Detalle</th></tr>
      <?php foreach ($endpoints as $ep):
        $f = $ep_files[$ep] ?? false;
        [$st,$ok,$msg] = $ep_json[$ep] ?? [0,false,''];
      ?>
      <tr>
        <td><code><?= h($ep) ?></code></td>
        <td class="<?= $f?'ok':'bad' ?>"><?= $f?'OK':'NO' ?></td>
        <td><?= $st ?: '—' ?></td>
        <td class="<?= $ok?'ok':'bad' ?>"><?= $ok?'OK':'NO' ?></td>
        <td class="<?= $ok?'k':'warn' ?>"><?= h((string)$msg) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="grid">
    <div class="card">
      <div><b>.user.ini</b> <span class="pill <?= $user_ini['exists']?'ok':'warn' ?>"><?= $user_ini['exists']?'existe':'no existe' ?></span></div>
      <div><span class="k">display_errors:</span> <?= h((string)($user_ini['display_errors'] ?? '')) ?></div>
      <div><span class="k">html_errors:</span> <?= h((string)($user_ini['html_errors'] ?? '')) ?></div>
    </div>

    <div class="card">
      <div><b>BOM</b></div>
      <table>
        <tr><th>Archivo</th><th>BOM</th></tr>
        <?php foreach ($bom_status as $file=>$b): ?>
          <tr><td><code><?= h($file) ?></code></td><td class="<?= $b?'bad':'ok' ?>"><?= $b?'SÍ':'NO' ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <div class="card">
    <div><b>Sugerencias</b></div>
    <ul>
      <?php if (!is_https()): ?>
        <li class="bad">Activa HTTPS real. WebAuthn requiere origen seguro.</li>
      <?php endif; ?>

      <?php foreach ($ext_status as $e=>$v): ?>
        <?php if (!$v): ?><li class="bad">Instala extensión PHP: <?= h($e) ?></li><?php endif; ?>
      <?php endforeach; ?>

      <?php foreach ($fn_status as $f=>$v): ?>
        <?php if (!$v): ?><li class="bad">Habilita función: <?= h($f) ?> en php.ini</li><?php endif; ?>
      <?php endforeach; ?>

      <?php if (!$vendor_ok): ?>
        <li class="bad">Ejecuta Composer en el raíz del proyecto.</li>
      <?php endif; ?>

      <?php if ($vendor_ok && !$classes_ok): ?>
        <li class="bad">Requiere paquetes: <code>web-auth/webauthn-lib</code>, <code>nyholm/psr7</code>, <code>psr/http-message</code>.</li>
      <?php endif; ?>

      <?php if (!$db_ok): ?>
        <li class="bad">Revisa credenciales MySQL y conexión.</li>
      <?php endif; ?>

      <?php foreach ($tbl_status as $t=>$v): ?>
        <?php if (!$v): ?><li class="bad">Crea tabla faltante: <?= h($t) ?></li><?php endif; ?>
      <?php endforeach; ?>

      <?php foreach ($ep_files as $ep=>$v): ?>
        <?php if (!$v): ?><li class="bad">Sube el endpoint: <code><?= h($ep) ?></code></li><?php endif; ?>
      <?php endforeach; ?>

      <?php foreach ($ep_json as $ep=>[$st,$ok,$msg]): ?>
        <?php if ($st && !$ok): ?><li class="warn">Endpoint responde pero no JSON válido: <code><?= h($ep) ?></code> (<?= h($msg) ?>)</li><?php endif; ?>
      <?php endforeach; ?>

      <?php foreach ($bom_status as $f=>$b): ?>
        <?php if ($b): ?><li class="warn">Quita BOM de: <code><?= h($f) ?></code> (sed: <code>sed -i '1s/^\xEF\xBB\xBF//'</code>)</li><?php endif; ?>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="footer">
    Generado en <?= number_format((microtime(true)-$start)*1000,1) ?> ms • PHP <?= h(PHP_VERSION) ?> • Docroot <?= h($docroot ?: '-') ?>
  </div>
</div>
</body>
</html>
