<?php declare(strict_types=1);
/**
 * /config/historial.php
 * Logger híbrido: JSONL + MySQL
 * - Archivo: /config/historial/historial.json (rotación por fecha/tamaño)
 * - Tabla: logs_app (+ logs_app_blob opcional)
 * - Handlers globales: errores PHP, excepciones, shutdown fatales
 * - Respeta web_setting: log_enabled, log_level, log_retention_days
 *
 * Requiere desde /config/db.php: db(), db_setting()
 */

if (!defined('LOGI_REQUEST_INIT')) {
  define('LOGI_REQUEST_INIT', microtime(true));
}
if (!defined('LOGI_REQUEST_ID')) {
  // ULID-lite: timestamp base36 + random 16 chars
  $ts = strtoupper(base_convert((string)time(), 10, 36));
  $rand = substr(strtr(base64_encode(random_bytes(10)), '+/', 'AZ'), 0, 16);
  define('LOGI_REQUEST_ID', str_pad($ts, 10, '0', STR_PAD_LEFT) . $rand);
}

const LOG_DIR        = __DIR__ . '/historial';
const LOG_FILE       = LOG_DIR . '/historial.json';
const LOG_MAX_BYTES  = 5 * 1024 * 1024; // 5 MB
const LOG_LEVELS     = ['DEBUG'=>0,'INFO'=>1,'NOTICE'=>2,'WARNING'=>3,'ERROR'=>4,'CRITICAL'=>5,'ALERT'=>6,'EMERGENCY'=>7];

(function (): void {
  if (!is_dir(LOG_DIR)) { @mkdir(LOG_DIR, 0775, true); }

  set_error_handler(function (int $errno, string $errstr, ?string $errfile = null, ?int $errline = null) {
    if (!(error_reporting() & $errno)) { return false; } // respeta @
    $map = [
      E_ERROR=>'ERROR', E_USER_ERROR=>'ERROR', E_RECOVERABLE_ERROR=>'ERROR',
      E_WARNING=>'WARNING', E_USER_WARNING=>'WARNING',
      E_NOTICE=>'NOTICE', E_USER_NOTICE=>'NOTICE',
      E_PARSE=>'CRITICAL', E_CORE_ERROR=>'CRITICAL', E_COMPILE_ERROR=>'CRITICAL',
      E_CORE_WARNING=>'WARNING', E_COMPILE_WARNING=>'WARNING',
      E_STRICT=>'NOTICE', E_DEPRECATED=>'NOTICE', E_USER_DEPRECATED=>'NOTICE'
    ];
    $lvl = $map[$errno] ?? 'ERROR';
    logi_write($lvl, 'php_error', $errstr, ['errno'=>$errno,'archivo'=>$errfile,'linea'=>$errline]);
    return true;
  });

  set_exception_handler(function (Throwable $e): void {
    logi_write('ERROR', 'exception', get_class($e).': '.$e->getMessage(), [
      'archivo'=>$e->getFile(), 'linea'=>$e->getLine(), 'trace'=>$e->getTrace()
    ]);
  });

  register_shutdown_function(function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'] ?? 0, [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) {
      logi_write('CRITICAL', 'shutdown_fatal', $e['message'] ?? 'fatal', [
        'archivo'=>$e['file'] ?? null, 'linea'=>$e['line'] ?? null
      ]);
    }
    $days = (int) (db_setting('log_retention_days', '30') ?? '30');
    logi_rotate_by_date($days);
  });

  // Opcional: huella de cada request
  // logi_http_footprint('INFO');
})();

/** API pública */
function log_audit(string $mensaje, array $ctx = []): void { logi_write('INFO', 'audit', $mensaje, $ctx); }
function log_info(string $mensaje, array $ctx = []): void   { logi_write('INFO', 'app', $mensaje, $ctx); }
function log_warn(string $mensaje, array $ctx = []): void   { logi_write('WARNING', 'app', $mensaje, $ctx); }
function log_error(string $mensaje, array $ctx = []): void  { logi_write('ERROR', 'app', $mensaje, $ctx); }

/** Writer central */
function logi_write(string $level, string $tipo, string $mensaje, array $ctx = []): void {
  try {
    $level  = strtoupper($level);
    $minLvl = strtoupper(db_setting('log_level', 'WARNING') ?? 'WARNING');

    $meta = logi_collect_meta($ctx);
    $row  = [
      'ts'         => logi_now(),
      'level'      => $level,
      'tipo'       => $tipo,
      'mensaje'    => logi_trunc($mensaje, 2000),
      'archivo'    => $meta['archivo'] ?? null,
      'linea'      => $meta['linea'] ?? null,
      'url'        => $meta['url'] ?? null,
      'metodo'     => $meta['metodo'] ?? null,
      'ip_str'     => $meta['ip'] ?? null,
      'ip_bin'     => $meta['ip_bin'] ?? null,
      'user_agent' => $meta['user_agent'] ?? null,
      'session_user'=> $meta['session_user'] ?? null,
      'request_id' => LOGI_REQUEST_ID,
      'contexto'   => $meta['contexto'] ?? null,
      'trace'      => $meta['trace'] ?? null,
    ];

    // JSONL siempre
    logi_write_jsonl($row);

    // SQL según flags y umbral
    $enabled = (int) (db_setting('log_enabled', '1') ?? '1');
    if ($enabled === 1 && (LOG_LEVELS[$level] ?? 4) >= (LOG_LEVELS[$minLvl] ?? 3)) {
      logi_insert_sql($row);
    }
  } catch (Throwable $e) {
    // nunca romper por log
  }
}

function logi_insert_sql(array $row): void {
  try {
    $pdo = db();
    $st = $pdo->prepare(
      'INSERT INTO logs_app
       (created_at, level, tipo, mensaje, archivo, linea, url, metodo, ip, user_agent, session_user, request_id, contexto, is_handled)
       VALUES (NOW(), :level, :tipo, :mensaje, :archivo, :linea, :url, :metodo, :ip, :ua, :sess, :rid, :ctx, 0)'
    );
    $ctxJson = isset($row['contexto']) ? json_encode($row['contexto'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;
    $st->execute([
      ':level'=>$row['level'],
      ':tipo'=>$row['tipo'],
      ':mensaje'=>$row['mensaje'],
      ':archivo'=>$row['archivo'],
      ':linea'=>$row['linea'],
      ':url'=>$row['url'],
      ':metodo'=>$row['metodo'],
      ':ip'=>$row['ip_bin'],
      ':ua'=>logi_trunc((string)$row['user_agent'], 512),
      ':sess'=>logi_trunc((string)$row['session_user'], 128),
      ':rid'=>$row['request_id'],
      ':ctx'=>$ctxJson,
    ]);

    if (!empty($row['trace'])) {
      $logId = (int)$pdo->lastInsertId();
      $blob  = json_encode(['trace'=>$row['trace']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      $chunkSize = 60000;
      $parts = str_split($blob, $chunkSize);
      $insB = $pdo->prepare('INSERT INTO logs_app_blob (log_id, parte, payload) VALUES (:id,:p,:pl)');
      $p = 1;
      foreach ($parts as $pl) { $insB->execute([':id'=>$logId, ':p'=>$p++, ':pl'=>$pl]); }
    }
  } catch (Throwable $e) { /* no-op */ }
}

function logi_write_jsonl(array $row): void {
  try {
    logi_rotate_by_size();
    $out = [
      'ts'=>$row['ts'],
      'level'=>$row['level'],
      'tipo'=>$row['tipo'],
      'mensaje'=>$row['mensaje'],
      'archivo'=>$row['archivo'],
      'linea'=>$row['linea'],
      'url'=>$row['url'],
      'metodo'=>$row['metodo'],
      'ip'=>$row['ip_str'],
      'user_agent'=>$row['user_agent'],
      'session_user'=>$row['session_user'],
      'request_id'=>$row['request_id'],
      'contexto'=>$row['contexto'] ?? null,
    ];
    $fh = fopen(LOG_FILE, 'ab');
    if ($fh) {
      if (flock($fh, LOCK_EX)) {
        fwrite($fh, json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL);
        fflush($fh);
        flock($fh, LOCK_UN);
      }
      fclose($fh);
    }
  } catch (Throwable $e) { /* no-op */ }
}

function logi_collect_meta(array $ctx): array {
  $ctxClean = logi_sanitize_context($ctx);

  $url = null; $method = null; $ua = null; $ipStr = null; $ipBin = null;
  try {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? null;
    $uri    = $_SERVER['REQUEST_URI'] ?? null;
    $url    = ($host && $uri) ? $scheme.'://'.$host.$uri : null;
    $method = $_SERVER['REQUEST_METHOD'] ?? null;
    $ua     = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $ipStr  = logi_detect_ip();
    $ipBin  = $ipStr ? @inet_pton($ipStr) : null;
  } catch (Throwable $e) {}

  @session_start();
  $sessUser = null;
  foreach (['admin_id','vendor_id','user_id','uid'] as $k) {
    if (!empty($_SESSION[$k])) { $sessUser = $k.':'.(string)$_SESSION[$k]; break; }
  }

  return [
    'archivo'=>$ctx['archivo'] ?? ($ctx['file'] ?? null),
    'linea'=>$ctx['linea'] ?? ($ctx['line'] ?? null),
    'url'=>$url,
    'metodo'=>$method,
    'user_agent'=>$ua,
    'ip'=>$ipStr,
    'ip_bin'=>$ipBin,
    'session_user'=>$sessUser,
    'contexto'=>$ctxClean,
    'trace'=>$ctx['trace'] ?? null,
  ];
}

function logi_sanitize_context(array $ctx): array {
  $hidden = ['pass','password','contrasena','token','secret','authorization','api_key','db_pass','authorization_bearer'];
  $out = [];
  foreach ($ctx as $k=>$v) {
    $kk = strtolower((string)$k);
    if (in_array($kk, $hidden, true)) { $out[$k] = '[redacted]'; continue; }
    if ($kk === 'trace' && is_array($v)) { $out[$k] = '[stacktrace]'; continue; }
    if (is_scalar($v) || $v === null) { $out[$k] = $v; continue; }
    $out[$k] = json_decode(json_encode($v, JSON_PARTIAL_OUTPUT_ON_ERROR), true);
  }
  return $out;
}

function logi_detect_ip(): ?string {
  foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $h) {
    if (!empty($_SERVER[$h])) {
      $list = explode(',', (string)$_SERVER[$h]);
      $ip = trim($list[0]);
      if (filter_var($ip, FILTER_VALIDATE_IP)) { return $ip; }
    }
  }
  return null;
}

function logi_rotate_by_size(): void {
  try {
    if (!file_exists(LOG_FILE)) { return; }
    $size = filesize(LOG_FILE) ?: 0;
    if ($size < LOG_MAX_BYTES) { return; }
    $date = (new DateTimeImmutable('now'))->format('Ymd');
    for ($i=1; $i<=99; $i++) {
      $cand = sprintf('%s/historial-%s.%d.json', LOG_DIR, $date, $i);
      if (!file_exists($cand)) { @rename(LOG_FILE, $cand); break; }
    }
  } catch (Throwable $e) { /* no-op */ }
}

function logi_rotate_by_date(int $retentionDays): void {
  try {
    $files = glob(LOG_DIR.'/historial-*.json') ?: [];
    $limit = (new DateTimeImmutable('now'))->modify(sprintf('-%d days', max(1,$retentionDays)));
    foreach ($files as $f) {
      $mtime = @filemtime($f);
      if ($mtime && (new DateTimeImmutable('@'.$mtime)) < $limit) { @unlink($f); }
    }
  } catch (Throwable $e) { /* no-op */ }
}

function logi_trunc(string $s, int $max): string {
  if (strlen($s) <= $max) return $s;
  return substr($s, 0, $max-3) . '...';
}

function logi_now(): string {
  $tz = new DateTimeZone(date_default_timezone_get() ?: 'UTC');
  return (new DateTimeImmutable('now', $tz))->format('c');
}

/** Registro del request (opcional) */
function logi_http_footprint(string $level = 'INFO'): void {
  $dur = microtime(true) - (float)LOGI_REQUEST_INIT;
  logi_write($level, 'http', 'request', ['duration_ms'=>round($dur*1000,2)]);
}
