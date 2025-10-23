<?php
// /tools/docs/generar.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

// === Guards de compatibilidad ===
if (function_exists('start_session_safe')) start_session_safe(); else { if (session_status() !== PHP_SESSION_ACTIVE) session_start(); }
$ALLOWED = ['admin','staff','superadmin','owner','root'];
if (function_exists('require_role')) {
  require_role(['admin']);
} else {
  if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], $ALLOWED, true)) {
    header('Location: /console/auth/login/'); exit;
  }
}
if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(403); exit('CSRF'); }

$REQUEST_START = microtime(true);
$docroot = rtrim(str_replace('\\','/', (string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__,2))), '/');
$nowIso = (new DateTimeImmutable('now', new DateTimeZone('America/Santo_Domingo')))->format('c');

$rutaBase    = trim((string)($_POST['ruta_base'] ?? '/console/'));
$soloModulo  = preg_replace('~[^a-z0-9_-]~i','', (string)($_POST['solo_modulo'] ?? ''));
$withSQL     = isset($_POST['incluir_sql']);
$withCode    = isset($_POST['incluir_codigo']);
$schemaVer   = in_array(($_POST['schema_version'] ?? '8.0'), ['8.0','5.7'], true) ? $_POST['schema_version'] : '8.0';

if ($rutaBase === '/console/all') $rutaBase = '/console/';
$rutaBase = '/'.trim($rutaBase,'/').'/';
$rutaAbs  = realpath($docroot.$rutaBase);
if ($rutaAbs === false || strpos(str_replace('\\','/',$rutaAbs), $docroot) !== 0) { http_response_code(400); exit('Ruta fuera de docroot'); }

$outDir = $docroot.'/tools/docs/output';
@mkdir($outDir, 0775, true);
@mkdir($outDir.'/sql', 0775, true);

function sha256_or_null(string $file): ?string { return is_file($file) ? ('sha256:'.hash_file('sha256', $file)) : null; }
function list_files(string $base, array $excludeDirs): array {
  $out = [];
  $it  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $file) {
    /** @var SplFileInfo $file */
    $path = str_replace('\\','/',$file->getPathname());
    $rel  = str_replace(str_replace('\\','/',$GLOBALS['docroot']),'',$path);
    $skip = false;
    foreach ($excludeDirs as $ex) { if (strpos($rel, $ex) === 0 || strpos('/'.$rel, $ex) !== false) { $skip = true; break; } }
    if ($skip) continue;
    $out[] = '/'.ltrim($rel,'/');
  }
  return $out;
}

$excludes = [
  '/vendor','/node_modules','/.git','/storage','/cache','/logs','/tmp',
  '/tools/docs/output','/tools/docs/sql','/tools/docs/.cache'
];

$modules = [];
if ($soloModulo !== '') {
  $modPath = $docroot.'/console/'.$soloModulo.'/index.php';
  if (!is_file($modPath)) { http_response_code(404); exit("Módulo no encontrado: {$soloModulo}"); }
  $modules[] = $soloModulo;
} else {
  $glob = glob($docroot.'/console/*/index.php', GLOB_NOSORT) ?: [];
  foreach ($glob as $f) {
    $parts = explode('/', str_replace('\\','/', $f));
    $ix = array_search('console', $parts, true);
    if ($ix !== false && isset($parts[$ix+1])) $modules[] = $parts[$ix+1];
  }
}
$modules = array_values(array_unique($modules));

$manifest = [
  'project'       => 'VallasLED Console',
  'generated_at'  => $nowIso,
  'base_path'     => $rutaBase,
  'schema_version'=> $schemaVer,
  'modules'       => [],
  'sql_bundle'    => null,
  'doc_output'    => null,
];

$filesIndex = [];
foreach ($modules as $mod) {
  $view      = "/console/{$mod}/index.php";
  $ajaxDir   = "/console/{$mod}/ajax/";
  $cssGlob   = glob($docroot."/console/asset/css/{$mod}/*.css", GLOB_NOSORT) ?: [];
  $jsGlob    = glob($docroot."/console/asset/js/{$mod}/*.js", GLOB_NOSORT) ?: [];
  $coreCss   = is_file($docroot.'/console/asset/css/core.css') ? ['/console/asset/css/core.css'] : [];
  $coreJs    = is_file($docroot.'/console/asset/js/core.js')   ? ['/console/asset/js/core.js']   : [];

  $assetsCss = [];
  foreach (array_merge($coreCss, array_map(fn($p)=>str_replace($docroot,'',$p), $cssGlob)) as $p) {
    $h = sha256_or_null($docroot.$p);
    if ($h) $assetsCss[] = ['handle'=>basename($p), 'path'=>$p, 'hash'=>$h];
  }
  $assetsJs = [];
  foreach (array_merge($coreJs, array_map(fn($p)=>str_replace($docroot,'',$p), $jsGlob)) as $p) {
    $fp = $docroot.$p; // FIX: no usar $docroot+$p
    $h = sha256_or_null($fp);
    if ($h) $assetsJs[] = ['handle'=>basename($p), 'path'=>$p, 'hash'=>$h];
  }

  $manifest['modules'][] = [
    'name' => $mod,
    'paths' => [
      'view'     => $view,
      'ajax_dir' => $ajaxDir,
      'css_glob' => "/console/asset/css/{$mod}/*.css",
      'js_glob'  => "/console/asset/js/{$mod}/*.js",
    ],
    'sidebar_policy' => [
      'included_by' => '/_layout.php',
      'file'        => '/console/asset/sidebar.php',
      'active_rule' => "startsWith('/console/{$mod}/')",
    ],
    'assets' => ['css' => $assetsCss, 'js' => $assetsJs],
    'security' => [
      'session' => 'required',
      'roles'   => ['admin','staff'],
      'csrf'    => 'required on POST and state-changing GET',
      'ajax_headers' => ['X-Requested-With'],
    ],
  ];

  if ($withCode) {
    $modAbs = realpath($docroot."/console/{$mod}/");
    if ($modAbs !== false) {
      foreach (list_files($modAbs, $excludes) as $rel) {
        $filesIndex[$rel] = ['hash' => sha256_or_null($docroot.$rel)];
      }
    }
  }
}

// Dump SQL opcional
$sqlDumpFile = null;
if ($withSQL) {
  $host = getenv('DB_HOST') ?: '';
  $name = getenv('DB_NAME') ?: '';
  $user = getenv('DB_USER') ?: '';
  $pass = getenv('DB_PASS') ?: '';
  if ($host && $name && $user !== '') {
    $sqlDumpFile = "/tools/docs/output/sql/full.sql";
    $cmd = [
      'mysqldump',
      '--default-character-set=utf8mb4',
      '--skip-add-locks',
      '--set-gtid-purged=OFF',
      '--single-transaction',
      '--routines',
      '--events',
      '--triggers',
      '--hex-blob',
      '--no-tablespaces',
      '--column-statistics=0',
      '-h', $host,
      '-u', $user,
      $name
    ];
    $cmdStr = '';
    foreach ($cmd as $c) { $cmdStr .= escapeshellarg($c).' '; }
    $env = 'MYSQL_PWD='.escapeshellarg($pass).' ';
    $ret = 0;
    @exec($env.$cmdStr.' > '.escapeshellarg($docroot.$sqlDumpFile).' 2>/dev/null', $_o, $ret);
    if ($ret !== 0 || !is_file($docroot.$sqlDumpFile)) $sqlDumpFile = null;
  }
}

$manifest['sql_bundle'] = [
  'dump_file'      => $sqlDumpFile,
  'schema_version' => $schemaVer,
  'notes'          => ['utf8mb4','recomendado sin DEFINER','DDL primero, luego rutinas'],
];

$readme = "# Documentación Console\n\n- Base: `{$rutaBase}`\n- Generado: `{$nowIso}`\n- Política: la vista no incluye JS/CSS; `_layout.php` inyecta por módulo; `sidebar.php` es HTML.\n\n";

$modulesMd = "## Módulos\n\n| Módulo | Vista | AJAX dir | CSS glob | JS glob |\n|---|---|---|---|---|\n";
foreach ($manifest['modules'] as $m) {
  $modulesMd .= "| `{$m['name']}` | `{$m['paths']['view']}` | `{$m['paths']['ajax_dir']}` | `{$m['paths']['css_glob']}` | `{$m['paths']['js_glob']}` |\n";
}
$assetsMd = "## Assets\n\n";
foreach ($manifest['modules'] as $m) {
  $assetsMd .= "### {$m['name']}\n\n**CSS**:\n";
  foreach ($m['assets']['css'] as $a) { $assetsMd .= "- `{$a['path']}` · {$a['hash']}\n"; }
  $assetsMd .= "\n**JS**:\n";
  foreach ($m['assets']['js'] as $a) { $assetsMd .= "- `{$a['path']}` · {$a['hash']}\n"; }
  $assetsMd .= "\n";
}

@file_put_contents($outDir.'/README.md', $readme);
@file_put_contents($outDir.'/MODULES.md', $modulesMd);
@file_put_contents($outDir.'/ASSETS.md', $assetsMd);
@file_put_contents($outDir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
if ($withCode && $filesIndex) @file_put_contents($outDir.'/FILES.json', json_encode($filesIndex, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

// ZIP
$zipPath = $outDir.'/docs_bundle.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE|ZipArchive::OVERWRITE) === true) {
  foreach (['README.md','MODULES.md','ASSETS.md','manifest.json','FILES.json'] as $f) {
    $fp = $outDir.'/'.$f; if (is_file($fp)) $zip->addFile($fp, $f);
  }
  if ($sqlDumpFile && is_file($docroot.$sqlDumpFile)) $zip->addFile($docroot.$sqlDumpFile, 'sql/full.sql');
  $zip->close();
}

$elapsed = round((microtime(true)-$REQUEST_START)*1000);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Docs generadas</title>
<style>
  body{background:#0b1020;color:#e5e7eb;font:14px system-ui; margin:0}
  .wrap{max-width:900px;margin:32px auto;padding:0 16px}
  .card{background:#111827;border:1px solid #1f2937;border-radius:14px;padding:20px}
  a.btn{display:inline-block;background:#22d3ee;color:#031017;padding:10px 14px;border-radius:10px;font-weight:700;text-decoration:none}
  .row{margin:10px 0}
  code{background:#0b1220;padding:2px 6px;border-radius:6px;border:1px solid #1f2937}
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h2>Listo</h2>
      <div class="row">Tiempo: <code><?=$elapsed?> ms</code></div>
      <div class="row"><a class="btn" href="/tools/docs/output/docs_bundle.zip">Descargar ZIP</a></div>
      <div class="row">Manifest: <a href="/tools/docs/output/manifest.json" target="_blank">/tools/docs/output/manifest.json</a></div>
      <?php if ($withSQL): ?>
        <div class="row">SQL: <?= $manifest['sql_bundle']['dump_file'] ? '<a href="'.$manifest['sql_bundle']['dump_file'].'" target="_blank">'.$manifest['sql_bundle']['dump_file'].'</a>' : '<em>Saltado. Configura DB_HOST/DB_NAME/DB_USER/DB_PASS.</em>' ?></div>
      <?php endif; ?>
      <div class="row"><a href="/tools/docs/index.php">Volver</a></div>
      <div class="row"><small>Regla: vistas no incluyen JS/CSS; `_layout.php` inyecta por módulo; `sidebar.php` solo HTML.</small></div>
    </div>
  </div>
</body>
</html>
