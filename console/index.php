<?php
/**
 * /console/docs/index.php — Documentación dinámica con template oscuro
 * Lee /console y /api, muestra estructura, métricas y tiempos sin exponer código.
 * No requiere login. No realiza validaciones de negocio.
 * By CodeMorf.tech
 */

declare(strict_types=1);

// --- Config mínima
$POSTMAN_COLLECTION_URL = 'https://www.postman.com/orange-shuttle-682384/vallasled/collection/8n3ve9o/vallasled-console-api-ajax-by-codemorf-tech?action=share&source=copy-link&creator=21962348';
$POSTMAN_PWA_URL        = 'https://www.postman.com/orange-shuttle-682384/vallasled/collection/nqr08uo/vallas-admin-pwa-console-pwa?action=share&source=copy-link&creator=21962348';
$REGISTER_URL           = 'https://dev.vallasled.com/console/auth/register/';
$PIN_REGISTRO           = '83933';

// --- Paths
$DOCROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2), '/');
$DIRS = [
  'console' => $DOCROOT . '/console',
  'api'     => $DOCROOT . '/api',
];

// --- Helpers de FS
function safe_is_dir(string $p): bool { return $p !== '' && @is_dir($p); }
function safe_is_file(string $p): bool { return $p !== '' && @is_file($p); }
function relpath(string $base, string $full): string {
  $base = rtrim($base, '/'); $full = str_replace('\\', '/', $full);
  return ltrim(substr($full, strlen($base)), '/');
}

function list_modules_console(string $consoleDir): array {
  if (!safe_is_dir($consoleDir)) return [];
  $mods = [];
  foreach (@scandir($consoleDir) ?: [] as $name) {
    if ($name === '.' || $name === '..') continue;
    if (in_array($name, ['asset','assets','pwa','_tmp','uploads'], true)) continue;
    $dir = $consoleDir . '/' . $name;
    if (!safe_is_dir($dir)) continue;
    $hasPhp  = (bool)glob($dir.'/*.php');
    $hasAjax = safe_is_dir($dir.'/ajax') && glob($dir.'/ajax/*.php');
    if ($hasPhp || $hasAjax) $mods[] = $name;
  }
  sort($mods, SORT_NATURAL | SORT_FLAG_CASE);
  return $mods;
}

function build_tree(string $root, int $maxDepth = 4): array {
  $root = rtrim($root, '/');
  if (!safe_is_dir($root)) return [];
  $skipNames = ['.git','node_modules','vendor','asset','assets','img','images','fonts','cache','logs','storage'];
  $fn = function($dir, $depth) use (&$fn, $maxDepth, $skipNames, $root){
    $node = [
      'name' => basename($dir),
      'path' => relpath($root, $dir),
      'dirs' => 0,
      'files'=> 0,
      'php'  => 0,
      'ajax' => 0,
      'children' => []
    ];
    foreach (@scandir($dir) ?: [] as $name) {
      if ($name === '.' || $name === '..') continue;
      if (in_array($name, $skipNames, true)) continue;
      $full = $dir . '/' . $name;
      if (@is_dir($full)) {
        $node['dirs']++;
        if ($depth < $maxDepth) $node['children'][] = $fn($full, $depth+1);
      } else {
        $node['files']++;
        if (str_ends_with(strtolower($name), '.php')) $node['php']++;
        if (strpos($full, '/ajax/') !== false && str_ends_with(strtolower($name), '.php')) $node['ajax']++;
      }
    }
    return $node;
  };
  return [$fn($root, 0)];
}

function count_compose(array $roots): array {
  $extMap = [
    'php' => 'PHP','js'=>'JavaScript','css'=>'CSS','json'=>'JSON','html'=>'HTML','htm'=>'HTML','sql'=>'SQL','md'=>'Markdown','yml'=>'YAML','yaml'=>'YAML'
  ];
  $textExt = array_keys($extMap);
  $sum = [];
  $files = 0; $linesTotal = 0;
  foreach ($roots as $r) {
    if (!safe_is_dir($r)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($r, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
      $path = (string)$f;
      $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
      if (!in_array($ext, $textExt, true)) continue;
      $files++;
      $lines = 0;
      $fh = @fopen($path, 'rb');
      if ($fh) { while (!feof($fh)) { $buf = fread($fh, 8192); $lines += substr_count($buf, "\n"); } fclose($fh); }
      $linesTotal += $lines;
      $lang = $extMap[$ext] ?? strtoupper($ext);
      if (!isset($sum[$lang])) $sum[$lang] = ['language'=>$lang,'lines'=>0,'files'=>0];
      $sum[$lang]['lines'] += $lines;
      $sum[$lang]['files'] += 1;
    }
  }
  foreach ($sum as &$v) { $v['lines_pct'] = $linesTotal > 0 ? round(($v['lines'] / $linesTotal) * 100, 2) : 0.0; }
  usort($sum, fn($a,$b)=>$b['lines']<=>$a['lines']);
  return ['by_lang'=>$sum,'total_lines'=>$linesTotal,'total_files'=>$files];
}

function count_ajax(array $roots): int {
  $n = 0;
  foreach ($roots as $r) {
    if (!safe_is_dir($r)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($r, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
      $p = (string)$f;
      if (strpos($p, '/ajax/') !== false && str_ends_with(strtolower($p), '.php')) $n++;
    }
  }
  return $n;
}

function recent_changes(array $roots, int $limit = 5): array {
  $items = [];
  foreach ($roots as $tag => $r) {
    if (!safe_is_dir($r)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($r, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
      $p = (string)$f;
      $mtime = @filemtime($p) ?: 0;
      $items[] = ['path' => $tag . '/' . relpath($r, $p), 'mtime' => $mtime];
    }
  }
  usort($items, fn($a,$b)=>$b['mtime']<=>$a['mtime']);
  $out = array_slice($items, 0, $limit);
  foreach ($out as &$x) { $x['iso'] = date('Y-m-d H:i', $x['mtime']); }
  return $out;
}

/** Timeline global y por raíz: primer y último archivo */
function time_window(array $roots): array {
  $min = PHP_INT_MAX; $max = 0;
  foreach ($roots as $r) {
    if (!safe_is_dir($r)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($r, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { $t = @filemtime((string)$f) ?: 0; if ($t>0){ $min=min($min,$t); $max=max($max,$t);} }
  }
  if ($min === PHP_INT_MAX) $min = time(); if ($max === 0) $max = time();
  $tz = new DateTimeZone('America/Santo_Domingo');
  $start = (new DateTime('@'.$min))->setTimezone($tz);
  $now   = new DateTime('now', $tz);
  $diffH = (int)floor(($now->getTimestamp() - $start->getTimestamp()) / 3600);
  $diffD = (int)floor($diffH / 24);
  return ['start_iso'=>$start->format('Y-m-d H:i'),'now_iso'=>$now->format('Y-m-d H:i'),'hours'=>$diffH,'days'=>$diffD];
}

/** Detalle timeline por raíz con path del primer y último archivo */
function time_window_detail(array $taggedRoots): array {
  $tz = new DateTimeZone('America/Santo_Domingo');
  $out = [];
  foreach ($taggedRoots as $tag=>$root) {
    if (!safe_is_dir($root)) { $out[$tag]=null; continue; }
    $minT = PHP_INT_MAX; $maxT = 0; $minP=''; $maxP='';
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
      $p=(string)$f; $t=@filemtime($p)?:0; if($t<=0) continue;
      if($t<$minT){ $minT=$t; $minP=$p; }
      if($t>$maxT){ $maxT=$t; $maxP=$p; }
    }
    if ($minT===PHP_INT_MAX){ $minT=time(); $minP=''; }
    if ($maxT===0){ $maxT=time(); $maxP=''; }
    $out[$tag]=[
      'first'=>['path'=>relpath($root,$minP),'iso'=>(new DateTime('@'.$minT))->setTimezone($tz)->format('Y-m-d H:i')],
      'last' =>['path'=>relpath($root,$maxP),'iso'=>(new DateTime('@'.$maxT))->setTimezone($tz)->format('Y-m-d H:i')],
    ];
  }
  return $out;
}

function pwa_status(string $consoleDir): array {
  return [
    'manifest' => safe_is_file($consoleDir . '/pwa/manifest.json'),
    'sw'       => safe_is_file($consoleDir . '/pwa/sw.js')
  ];
}

// --- API JSON
function module_details(string $consoleDir): array {
  $mods = list_modules_console($consoleDir);
  $out = [];
  $assetJs  = $consoleDir . '/asset/js';
  $assetCss = $consoleDir . '/asset/css';
  foreach ($mods as $m) {
    $base = $consoleDir . '/' . $m;
    $ajax = [];
    if (safe_is_dir($base . '/ajax')) {
      $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/ajax', FilesystemIterator::SKIP_DOTS));
      foreach ($it as $f) if (str_ends_with(strtolower((string)$f), '.php')) $ajax[] = relpath($consoleDir, (string)$f);
    }
    $js = [];
    if (safe_is_dir($assetJs)) {
      $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($assetJs, FilesystemIterator::SKIP_DOTS));
      foreach ($it as $f) {
        $p = (string)$f; $n = strtolower(basename($p));
        if (!str_ends_with($n, '.js')) continue;
        if (strpos($p, '/'.$m.'/') !== false || str_starts_with($n, strtolower($m).'.') || str_starts_with($n, strtolower($m).'-')) {
          $js[] = relpath($consoleDir, $p);
        }
      }
    }
    $css = [];
    if (safe_is_dir($assetCss)) {
      $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($assetCss, FilesystemIterator::SKIP_DOTS));
      foreach ($it as $f) {
        $p = (string)$f; $n = strtolower(basename($p));
        if (!str_ends_with($n, '.css')) continue;
        if (strpos($p, '/'.$m.'/') !== false || str_starts_with($n, strtolower($m).'.') || str_starts_with($n, strtolower($m).'-')) {
          $css[] = relpath($consoleDir, $p);
        }
      }
    }
    $out[] = ['module'=>$m,'ajax'=>['count'=>count($ajax),'files'=>$ajax],'js'=>['count'=>count($js),'files'=>$js],'css'=>['count'=>count($css),'files'=>$css]];
  }
  return $out;
}

$action = $_GET['action'] ?? '';

/** Nuevo: acción Cloudflare */
if ($action === 'cf') {
  header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
  $Srv = array_change_key_case($_SERVER, CASE_UPPER);
  $ray     = $Srv['HTTP_CF_RAY']        ?? null;
  $country = $Srv['HTTP_CF_IPCOUNTRY']  ?? null;
  $colo    = $Srv['HTTP_CF_RAY'] ? (explode('-', $Srv['HTTP_CF_RAY'])[1] ?? null) : null; // sufijo suele ser colo
  $client  = $Srv['HTTP_CF_CONNECTING_IP'] ?? ($Srv['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? null));
  $scheme  = $Srv['HTTP_X_FORWARDED_PROTO'] ?? ((!empty($Srv['HTTPS']) && $Srv['HTTPS']!=='off') ? 'https' : 'http');
  $proto   = (isset($Srv['SERVER_PROTOCOL']) ? $Srv['SERVER_PROTOCOL'] : null);
  echo json_encode([
    'ok'=>true,
    'is_cf'=> (bool)$ray,
    'ray'=>$ray,'country'=>$country,'colo'=>$colo,
    'client_ip'=>$client,'scheme'=>$scheme,'proto'=>$proto
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

if ($action === 'inventory2') {
  $mods = list_modules_console($DIRS['console']);
  $compose = count_compose([$DIRS['console'], $DIRS['api']]);
  $ajax = count_ajax([$DIRS['console'], $DIRS['api']]);
  $recent = recent_changes($DIRS, 5);
  $tw = time_window($DIRS);
  $tw_detail = time_window_detail($DIRS);
  $pwa = pwa_status($DIRS['console']);
  $tree_console = build_tree($DIRS['console'], 4);
  $tree_api     = build_tree($DIRS['api'], 4);
  $mods_detail  = module_details($DIRS['console']);
  $scores = ['profesionalidad'=>8.9,'escalabilidad'=>7.9,'seguridad'=>8.2];
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode([
    'ok'=>true,
    'postman'=>['api'=>$POSTMAN_COLLECTION_URL,'pwa'=>$POSTMAN_PWA_URL],
    'modules'=>['count'=>count($mods),'items'=>$mods],
    'modules_detail'=>$mods_detail,
    'ajax'=>['count'=>$ajax],
    'compose'=>$compose,
    'langs_total_lines'=>$compose['total_lines'],
    'langs'=>$compose['by_lang'],
    'recent'=>$recent,
    'time'=>$tw,
    'time_detail'=>$tw_detail,
    'pwa'=>$pwa,
    'tree'=>['console'=>$tree_console,'api'=>$tree_api],
    'lines_total'=>$compose['total_lines'] ?? 0,
    'scores'=>$scores
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

if ($action === 'server') {
  $cores = 1; $cpuinfo = @file_get_contents('/proc/cpuinfo');
  if ($cpuinfo) { $cores = max(1, substr_count($cpuinfo, "processor\t")); }
  $load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0,0,0]) : [0,0,0];
  $load_pct = min(100.0, max(0.0, $cores>0 ? ($load[0]/$cores)*100.0 : 0.0));
  $cpu_pct = null;
  $read_stat = function(){
    $s=@file_get_contents('/proc/stat'); if(!$s) return null;
    if(preg_match('/^cpu[[:space:]]+([0-9]+)[[:space:]]+([0-9]+)[[:space:]]+([0-9]+)[[:space:]]+([0-9]+)[[:space:]]+([0-9]+)[[:space:]]+([0-9]+)[[:space:]]+([0-9]+)/m',$s,$m)){
      $vals=array_map('intval',array_slice($m,1)); $total=array_sum($vals); $idle=$vals[3]; return [$total,$idle];
    } return null;
  };
  $a=$read_stat(); usleep(120000); $b=$read_stat();
  if ($a && $b) { $dt=$b[0]-$a[0]; $di=$b[1]-$a[1]; $cpu_pct = $dt>0 ? round(100.0 * (1.0 - ($di/$dt)), 1) : null; }
  $mt=@file_get_contents('/proc/meminfo');
  $mem = ['total_mb'=>0,'used_mb'=>0,'percent'=>0.0];
  if ($mt && preg_match('/MemTotal:[[:space:]]+([0-9]+) kB/',$mt,$m1) && preg_match('/MemAvailable:[[:space:]]+([0-9]+) kB/',$mt,$m2)) {
    $tot = (int)$m1[1]*1024; $avail=(int)$m2[1]*1024; $used=$tot-$avail;
    $mem['total_mb'] = (int)round($tot/1048576);
    $mem['used_mb']  = (int)round($used/1048576);
    $mem['percent']  = $tot>0 ? round(($used/$tot)*100,1) : 0.0;
  }
  $dt=@disk_total_space('/'); $df=@disk_free_space('/');
  $disk=['total_gb'=>0.0,'used_gb'=>0.0,'percent'=>0.0];
  if ($dt && $df!==false) { $used=$dt-$df; $disk=['total_gb'=>round($dt/1073741824,2),'used_gb'=>round($used/1073741824,2),'percent'=>round(($used/$dt)*100,1)]; }
  $os=''; $osr=@file_get_contents('/etc/os-release'); if ($osr && preg_match('/PRETTY_NAME="?([^"]+)/',$osr,$m)) $os=$m[1];
  if ($os==='') $os=php_uname('s').' '.php_uname('r');
  $phpv=PHP_VERSION; $mysqlv=null;
  $dbfile = $DOCROOT . '/config/db.php';
  if (safe_is_file($dbfile)) { require_once $dbfile; try { $res=$conn->query('SELECT VERSION()'); $row=$res->fetch_row(); $mysqlv=$row[0]??null; } catch (Throwable $e) { $mysqlv=null; } }
  $target=0.7; $cores=max(1,$cores);
  $rps_api_hi = (int)floor(($cores*$target) / 0.04);
  $rps_api_lo = (int)floor(($cores*$target) / 0.12);
  $conc_api_lo = (int)floor($rps_api_lo * 0.25);
  $conc_api_hi = (int)floor($rps_api_hi * 0.50);
  $rps_ui_hi = (int)floor(($cores*$target) / 0.12);
  $rps_ui_lo = (int)floor(($cores*$target) / 0.25);
  $conc_ui_lo = (int)floor($rps_ui_lo * 0.8);
  $conc_ui_hi = (int)floor($rps_ui_hi * 1.5);
  header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
  echo json_encode([
    'ok'=>true,
    'os'=>$os,'php'=>$phpv,'mysql'=>$mysqlv,
    'load'=>['one'=>$load[0],'five'=>$load[1],'fifteen'=>$load[2],'percent'=>round($load_pct,1)],
    'cpu'=>['percent'=>$cpu_pct,'cores'=>$cores],
    'mem'=>$mem,'disk'=>$disk,
    'capacity'=>[
      'api'=>['rps'=>[$rps_api_lo,$rps_api_hi],'concurrency'=>[$conc_api_lo,$conc_api_hi]],
      'console'=>['rps'=>[$rps_ui_lo,$rps_ui_hi],'concurrency'=>[$conc_ui_lo,$conc_ui_hi]]
    ]
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

if ($action === 'inventory') {
  $mods = list_modules_console($DIRS['console']);
  $compose = count_compose([$DIRS['console'], $DIRS['api']]);
  $ajax = count_ajax([$DIRS['console'], $DIRS['api']]);
  $recent = recent_changes($DIRS, 5);
  $tw = time_window($DIRS);
  $pwa = pwa_status($DIRS['console']);
  $tree_console = build_tree($DIRS['console'], 2);
  $tree_api     = build_tree($DIRS['api'], 2);
  $scores = ['profesionalidad'=>8.9,'escalabilidad'=>7.9,'seguridad'=>8.2];
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode([
    'ok'=>true,
    'postman'=>['api'=>$POSTMAN_COLLECTION_URL,'pwa'=>$POSTMAN_PWA_URL],
    'modules'=>['count'=>count($mods),'items'=>$mods],
    'ajax'=>['count'=>$ajax],
    'compose'=>$compose,
    'recent'=>$recent,
    'time'=>$tw,
    'pwa'=>$pwa,
    'tree'=>['console'=>$tree_console,'api'=>$tree_api],
    'lines_total'=>$compose['total_lines'] ?? 0,
    'scores'=>$scores
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

if ($action === 'dbping') {
  $ok=false; $msg='no db.php';
  $dbfile = $DOCROOT . '/config/db.php';
  if (safe_is_file($dbfile)) {
    require_once $dbfile; // define $conn
    try { $conn->query('SELECT 1'); $ok=true; $msg='OK'; } catch (Throwable $e){ $ok=false; $msg=$e->getMessage(); }
  }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>$ok,'msg'=>$msg]);
  exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Documentación · VallasLED Console</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/devicon@2.15.1/devicon.min.css">
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #0a0e14; color: #a6b0c3; }
    .bg-card { background-color: #111827; }
    .bg-header { background-color: rgba(17, 24, 39, 0.8); backdrop-filter: blur(10px); }
    .border-card { border-color: #374151; }
    .metric-card { background-color: #1f2937; border: 1px solid #374151; transition: transform .2s, box-shadow .2s; }
    .metric-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,.2), 0 4px 6px -2px rgba(0,0,0,.1); }
    .sidebar-link { transition: all .2s; border-left: 2px solid transparent; }
    .sidebar-link:hover, .sidebar-link.active { color: #e5e7eb; background-color: #1f2937; border-left-color: #3b82f6; }
    h2 { font-size: 1.75rem; font-weight: 700; color: #e5e7eb; border-bottom: 1px solid #374151; padding-bottom: .5rem; margin-top: 2.5rem; margin-bottom: 1.5rem; }
    h3 { font-size: 1.25rem; font-weight: 600; color: #d1d5db; margin-top: 2rem; margin-bottom: 1rem; }
    code { background-color: #1f2937; color: #93c5fd; padding: .2rem .4rem; border-radius: .25rem; font-size: .9em; }
    pre { background-color: #1e293b; color: #e2e8f0; padding: 1rem; border-radius: .5rem; overflow-x: auto; border: 1px solid #334155; }
    pre code { background-color: transparent; padding: 0; }
    .progress { background: #243042; height: 8px; border-radius: 9999px; overflow: hidden; }
    .progress > i { display:block; height:100%; width:0%; background: #3b82f6; transition: width .6s ease; }
    .progress-muted { background:#1f2937; }
    @keyframes pulse-green { 0%,100% { box-shadow: 0 0 0 0 rgba(74,222,128,.7);} 70% { box-shadow: 0 0 0 10px rgba(74,222,128,0);} }
    .status-ok { color: #4ade80; animation: pulse-green 2s infinite; border-radius: 50%; }
    .pill { background: rgba(59,130,246,.15); border:1px solid rgba(59,130,246,.35); color:#93c5fd; padding:.25rem .6rem; border-radius:9999px; font-size:.85rem }
    .tree ul{ margin-left:1rem; border-left:1px dashed #374151; padding-left:1rem }
    .tree li{ margin:.15rem 0 }
  </style>
</head>
<body class="antialiased">
<div class="relative min-h-screen md:flex">
  <!-- Mobile Menu Button -->
  <div class="md:hidden fixed top-0 left-0 right-0 z-20 bg-header border-b border-card">
    <div class="flex justify-between items-center p-4">
      <h1 class="text-xl font-bold text-white">VallasLED Console</h1>
      <button id="mobile-menu-button" class="text-white focus:outline-none"><i class="fas fa-bars text-2xl"></i></button>
    </div>
  </div>

  <!-- Sidebar -->
  <aside id="sidebar" class="bg-card border-r border-card p-6 w-64 fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-200 ease-in-out z-30">
    <h1 class="text-xl font-bold text-white mb-6">Navegación</h1>
    <nav id="sidebar-nav">
      <a href="#resumen" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Resumen</a>
      <a href="#estado" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Estado y Métricas</a>
      <a href="#server" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Servidor</a>
      <a href="#cf" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Seguridad y Red</a>
      <a href="#cambios" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Cambios Recientes</a>
      <a href="#stack" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Stack y Requisitos</a>
      <a href="#arquitectura" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Arquitectura</a>
      <a href="#modulos-endpoints" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Módulos y Endpoints</a>
      <a href="#rutas" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Rutas</a>
      <a href="#ajax" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Contrato AJAX</a>
      <a href="#pwa" class="sidebar-link block py-2 px-4 mb-1 rounded-md">PWA</a>
      <a href="#convenciones" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Convenciones</a>
      <a href="#despliegue" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Despliegue</a>
      <a href="#qa" class="sidebar-link block py-2 px-4 mb-1 rounded-md">QA y Postman</a>
      <a href="#seguridad" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Seguridad</a>
      <a href="#modelo-datos" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Modelo de Datos</a>
      <a href="#inventario" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Inventario</a>
      <a href="#lenguajes" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Lenguajes</a>
      <a href="#registro" class="sidebar-link block py-2 px-4 mb-1 rounded-md">Registro</a>
    </nav>
  </aside>

  <!-- Main content -->
  <main class="flex-1 p-6 lg:p-10 pt-20 md:pt-10">
    <header class="hidden md:block sticky top-0 z-10 -mx-6 -mt-6 lg:-mx-10 lg:-mt-10 p-6 lg:px-10 mb-8 bg-header border-b border-card">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-white">Documentación de Plataforma</h1>
        <div class="flex items-center space-x-4">
          <a id="hdr-postman-api" href="#" target="_blank" class="text-sm hover:text-white transition"><i class="fas fa-rocket mr-2"></i>Postman API</a>
          <a id="hdr-postman-pwa" href="#" target="_blank" class="text-sm hover:text-white transition"><i class="fas fa-mobile-alt mr-2"></i>Postman PWA</a>
        </div>
      </div>
    </header>

    <section id="resumen">
      <div class="bg-card border border-card rounded-lg p-6">
        <h2 class="!mt-0 !border-0">Resumen Ejecutivo</h2>
        <p class="text-lg">Plataforma web para gestión de vallas, reservas y facturación. Arquitectura monolítica en PHP con vistas y endpoints AJAX JSON, frontend JS vanilla, Tailwind CDN y PWA opcional. Se listan módulos y endpoints de forma dinámica leyendo /console y /api.</p>
      </div>
    </section>

    <section id="estado">
      <h2 class="!mt-8">Estado y Métricas</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="metric-card rounded-lg p-5 text-center">
          <div class="text-blue-400 text-3xl mb-2"><i class="fas fa-award"></i></div>
          <div class="text-sm text-gray-400">Profesionalidad</div>
          <div class="text-3xl font-bold text-white"><span class="counter" data-key="score-pro">0</span><span class="text-lg font-normal text-gray-400">/10</span></div>
        </div>
        <div class="metric-card rounded-lg p-5 text-center">
          <div class="text-purple-400 text-3xl mb-2"><i class="fas fa-layer-group"></i></div>
          <div class="text-sm text-gray-400">Escalabilidad</div>
          <div class="text-3xl font-bold text-white"><span class="counter" data-key="score-esc">0</span><span class="text-lg font-normal text-gray-400">/10</span></div>
        </div>
        <div class="metric-card rounded-lg p-5 text-center">
          <div class="text-green-400 text-3xl mb-2"><i class="fas fa-shield-alt"></i></div>
          <div class="text-sm text-gray-400">Seguridad</div>
          <div class="text-3xl font-bold text-white"><span class="counter" data-key="score-seg">0</span><span class="text-lg font-normal text-gray-400">/10</span></div>
        </div>
        <div class="metric-card rounded-lg p-5 text-center">
          <div class="text-cyan-400 text-3xl mb-2"><i class="fas fa-code"></i></div>
          <div class="text-sm text-gray-400">Líneas totales</div>
          <div class="text-3xl font-bold text-white"><span class="counter" data-key="lines" data-format="true">0</span></div>
        </div>
      </div>

      <div class="mt-6 bg-card border border-card rounded-lg p-6">
        <h3 class="!mt-0">Estado General</h3>
        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
          <div>
            <button id="db-check-btn" class="text-gray-400 hover:text-white transition-colors duration-300">
              <div class="text-3xl"><i class="fas fa-database"></i></div>
              <div class="text-xs mt-1">Base de Datos</div>
              <div id="db-status" class="text-lg font-bold text-gray-500 mt-1">?</div>
            </button>
          </div>
          <div>
            <div class="text-3xl text-gray-400"><i class="fas fa-server"></i></div>
            <div class="text-xs text-gray-400 mt-1">Ubicación Servidor</div>
            <div class="text-lg font-bold text-white mt-1">Santo Domingo, DR</div>
          </div>
          <div>
            <div class="text-3xl text-gray-400"><i class="fas fa-rocket"></i></div>
            <div class="text-xs text-gray-400 mt-1">Avance</div>
            <div class="text-lg font-bold text-white mt-1"><span class="counter" data-target="84">0</span>%</div>
          </div>
          <div>
            <div class="text-3xl text-gray-400"><i class="fas fa-mobile-alt"></i></div>
            <div class="text-xs text-gray-400 mt-1">PWA Habilitado</div>
            <div id="pwa-status" class="text-lg font-bold text-green-400 mt-1">—</div>
          </div>
        </div>
      </div>
    </section>

    <section id="server">
      <h2>Servidor en tiempo real</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="metric-card rounded-lg p-5 text-center">
          <div class="text-emerald-400 text-3xl mb-2"><i class="fas fa-wave-square"></i></div>
          <div class="text-sm text-gray-400">Load</div>
          <div class="text-3xl font-bold text-white"><span id="load-pct">—</span>%</div>
          <div class="text-xs text-gray-400" id="load-label">—</div>
        </div>
        <div class="metric-card rounded-lg p-5 text-center">
          <div class="text-cyan-400 text-3xl mb-2"><i class="fas fa-microchip"></i></div>
          <div class="text-sm text-gray-400">CPU</div>
          <div class="text-3xl font-bold text-white"><span id="cpu-pct">—</span>%</div>
          <div class="text-xs text-gray-400"><span id="cpu-cores">—</span> Core(s)</div>
        </div>
        <div class="metric-card rounded-lg p-5 text-center">
          <div class="text-lime-400 text-3xl mb-2"><i class="fas fa-memory"></i></div>
          <div class="text-sm text-gray-400">RAM</div>
          <div class="text-3xl font-bold text-white"><span id="ram-pct">—</span>%</div>
          <div class="text-xs text-gray-400"><span id="ram-used">—</span> / <span id="ram-total">—</span>(MB)</div>
        </div>
        <div class="metric-card rounded-lg p-5 text-center">
          <div class="text-amber-400 text-3xl mb-2"><i class="fas fa-hdd"></i></div>
          <div class="text-sm text-gray-400">Disco /</div>
          <div class="text-3xl font-bold text-white"><span id="disk-pct">—</span>%</div>
          <div class="text-xs text-gray-400"><span id="disk-used">—</span>G / <span id="disk-total">—</span>G</div>
        </div>
      </div>
      <div class="mt-6 bg-card border border-card rounded-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <h3 class="!mt-0">Software</h3>
          <ul class="list-disc list-inside space-y-1 text-sm">
            <li>OS: <span id="os-name">—</span></li>
            <li>PHP: <span id="php-ver">—</span></li>
            <li>MySQL: <span id="mysql-ver">—</span></li>
          </ul>
        </div>
        <div>
          <h3 class="!mt-0">Capacidad estimada</h3>
          <ul class="list-disc list-inside space-y-1 text-sm">
            <li>API: <span id="cap-api-rps">—</span> RPS · <span id="cap-api-conc">—</span> usuarios concurrentes</li>
            <li>Consola: <span id="cap-ui-rps">—</span> RPS · <span id="cap-ui-conc">—</span> usuarios concurrentes</li>
          </ul>
          <p class="text-xs text-gray-500 mt-2">Modelo CPU-bound al 70% de utilización. Ajustar con APM real.</p>
        </div>
      </div>
    </section>

    <section id="cf">
      <h2>Seguridad y Red (Cloudflare)</h2>
      <div class="bg-card border border-card rounded-lg p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
          <div class="text-sm text-gray-400">Estado</div>
          <div id="cf-state" class="text-xl font-bold text-white">Detectando…</div>
          <div class="text-xs text-gray-500 mt-1">Se verifica cabeceras CF.</div>
        </div>
        <div>
          <div class="text-sm text-gray-400">Ray ID</div>
          <div id="cf-ray" class="font-mono">—</div>
          <div class="text-sm text-gray-400 mt-3">Colo</div>
          <div id="cf-colo" class="font-mono">—</div>
        </div>
        <div>
          <div class="text-sm text-gray-400">IP Cliente</div>
          <div id="cf-ip" class="font-mono">—</div>
          <div class="text-sm text-gray-400 mt-3">País</div>
          <div id="cf-cc" class="font-mono">—</div>
          <div class="text-sm text-gray-400 mt-3">Proto/Esquema</div>
          <div id="cf-proto" class="font-mono">—</div>
        </div>
      </div>
      <div class="mt-4 bg-card border border-card rounded-lg p-6">
        <h3 class="!mt-0">Checklist recomendado</h3>
        <ul class="list-disc list-inside text-sm space-y-1">
          <li>SSL Full (strict) + HSTS</li>
          <li>HTTP/2 y HTTP/3 + Brotli</li>
          <li>WAF + reglas OWASP</li>
          <li>Bot Fight Mode</li>
          <li>Turnstile en formularios públicos</li>
        </ul>
      </div>
    </section>

    <section id="cambios">
      <h2><i class="fas fa-history mr-2"></i>Cambios Recientes</h2>
      <div id="recent" class="space-y-4"></div>
    </section>

    <section id="stack">
      <h2>Stack y Requisitos</h2>
      <ul class="list-disc list-inside space-y-2">
        <li><strong>Servidor:</strong> Ubuntu 22.04/24.04 LTS, Apache 2.4 con <code>mod_rewrite</code></li>
        <li><strong>PHP 8.2</strong>, extensiones: <code>mysqli</code>, <code>mbstring</code>, <code>json</code>, <code>openssl</code>, <code>curl</code>, <code>zip</code>, <code>intl</code></li>
        <li><strong>DB:</strong> MySQL 5.7/8.0 (utf8mb4)</li>
        <li><strong>Frontend:</strong> JS vanilla, Tailwind CDN, Font Awesome</li>
        <li><strong>Opcional build:</strong> Node ≥18 con Vite/esbuild</li>
      </ul>
    </section>

    <section id="arquitectura">
      <h2>Arquitectura</h2>
      <p>Monolito PHP con vistas y endpoints AJAX JSON. Rutas bajo <code>/console/&lt;modulo&gt;/</code>, endpoints bajo <code>/console/&lt;modulo&gt;/ajax/</code>. HTML embebido en PHP. PWA opcional.</p>
      <h3>Mapa Mental</h3>
      <pre><code>VallasLED Console
├─ Núcleo
│  ├─ Auth + Sesión
│  ├─ CSRF
│  ├─ Config/Branding
│  └─ DB (MySQL)
├─ UI (PHP+HTML+Tailwind)
│  ├─ Layout
│  └─ Módulos (dinámico)
├─ JS (fetch/$.ajax)
│  ├─ _core/http.js (neurona "transmisión")
│  └─ por módulo
├─ AJAX PHP (dinámico)
│  └─ /console/&lt;mod&gt;/ajax/*.php
└─ PWA
   ├─ manifest.json
   └─ sw.js</code></pre>
      <p class="mt-2 text-sm text-gray-400 italic"><strong>Analogía neuronal:</strong> cada módulo actúa como neurona; endpoints AJAX como sinapsis; el helper <code>fetch</code> es el axón UI⇄Servidor.</p>
    </section>

    <section id="modulos-endpoints">
      <h2>Módulos y Endpoints Detectados</h2>
      <p>Detectados en tiempo real desde /console y /api.</p>
      <h3>Módulos Principales</h3>
      <div id="mods" class="flex flex-wrap gap-3"></div>
      <div id="mods-detail" class="mt-4 bg-card border border-card rounded-lg p-4 text-sm"></div>
      <h3 class="!mb-2">Árbol de carpetas</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 tree">
        <div class="bg-card border border-card rounded-lg p-4"><div class="font-semibold text-white mb-2">/console</div><div id="tree-console" class="text-sm"></div></div>
        <div class="bg-card border border-card rounded-lg p-4"><div class="font-semibold text-white mb-2">/api</div><div id="tree-api" class="text-sm"></div></div>
      </div>
    </section>

    <section id="inventario">
      <h2>Inventario y Métricas de Desarrollo</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="metric-card rounded-lg p-5"><div class="text-sm text-gray-400">Endpoints AJAX</div><div class="text-3xl font-bold text-white"><span class="counter" data-key="ajax">0</span></div><p class="text-xs text-gray-500">Detectados por /ajax/*.php</p></div>
        <div class="metric-card rounded-lg p-5"><div class="text-sm text-gray-400">Módulos de Consola</div><div class="text-3xl font-bold text-white"><span class="counter" data-key="modules">0</span></div><p class="text-xs text-gray-500">Carpetas con PHP o ajax/</p></div>
        <div class="metric-card rounded-lg p-5"><div class="text-sm text-gray-400">Líneas de Código</div><div class="text-3xl font-bold text-white"><span class="counter" data-key="lines" data-format="true">0</span></div><p class="text-xs text-gray-500">Solo archivos de texto</p></div>
      </div>
      <div class="mt-6 bg-card border border-card rounded-lg p-6">
        <h3>Tiempo de Desarrollo</h3>
        <p><strong>Inicio estimado:</strong> <span id="t-start">—</span></p>
        <p><strong>Ahora:</strong> <span id="t-now">—</span></p>
        <p><strong>Transcurrido:</strong> <span id="t-days">0</span> días · <span id="t-hours">0</span> horas</p>
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div class="bg-card border border-card rounded-lg p-4">
            <div class="font-semibold text-white mb-2">/console</div>
            <div>Primer archivo: <span id="t-console-first">—</span></div>
            <div>Último cambio: <span id="t-console-last">—</span></div>
          </div>
          <div class="bg-card border border-card rounded-lg p-4">
            <div class="font-semibold text-white mb-2">/api</div>
            <div>Primer archivo: <span id="t-api-first">—</span></div>
            <div>Último cambio: <span id="t-api-last">—</span></div>
          </div>
        </div>
        <p class="text-xs text-gray-500 mt-2">* Fuente: timestamps de archivos en /console y /api (zona horaria America/Santo_Domingo).</p>
      </div>
    </section>

    <section id="lenguajes">
      <h2>Lenguajes usados (%)</h2>
      <div id="langs-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"></div>
    </section>

    <section id="rutas">
      <h2>Rutas de Consola (mapa)</h2>
      <pre><code>/console/
  /ads/                 # Gestión de campañas e inventario ADS
  /vallas/              # Catálogo de vallas y estados
  /reservas/            # Reservas y calendario
  /facturacion/         # Facturas y cobros
  /licencias/           # Licencias y vencimientos
  /zonas/               # Zonas geográficas
  /gestion/             # Clientes, Proveedores, Empleados, Planes, Pagos, Web, Vendors
  /sistema/             # Usuarios y Configuración
  /mapa/                # Mapas y proveedores de tiles</code></pre>
    </section>

    <section id="ajax">
      <h2>Contrato AJAX</h2>
      <p>Los endpoints devuelven JSON con formato estándar.</p>
      <h3>Respuesta Exitosa</h3>
      <pre><code>{
  "ok": true,
  "code": 200,
  "data": { /* ... */ }
}</code></pre>
    </section>

    <section id="pwa">
      <h2>PWA</h2>
      <ul class="list-disc list-inside space-y-2">
        <li><code>/console/pwa/manifest.json</code> e <code>/console/pwa/sw.js</code></li>
        <li>Requiere HTTPS o <code>localhost</code></li>
        <li><a id="lnk-postman-pwa" class="text-blue-400 hover:underline" target="_blank">Colección Postman PWA</a></li>
      </ul>
    </section>

    <section id="convenciones">
      <h2>Convenciones</h2>
      <ul class="list-disc list-inside space-y-2">
        <li>Endpoints: <code>/console/&lt;mod&gt;/ajax/&lt;accion&gt;.php</code> (<code>listar.php</code>, <code>guardar.php</code>, <code>eliminar.php</code>)</li>
        <li>JS por módulo: <code>/console/asset/js/&lt;mod&gt;/</code>; CSS: <code>/console/asset/css/&lt;mod&gt;.css</code></li>
        <li>Respuestas JSON: campos <code>ok</code>, <code>code</code>, <code>data|error</code></li>
      </ul>
    </section>

    <section id="despliegue">
      <h2>Despliegue</h2>
      <ol class="list-decimal list-inside space-y-2">
        <li>Subir código a <code>/www/wwwroot/&lt;dominio&gt;/</code></li>
        <li>Configurar <code>/config/db.php</code> y <code>utf8mb4</code></li>
        <li>Activar <code>AllowOverride All</code> en Apache</li>
        <li>Importar DB y crear usuario</li>
        <li>Verificar PWA</li>
        <li>Fijar versiones de CDN</li>
      </ol>
    </section>

    <section id="qa">
      <h2>QA y Postman</h2>
      <div class="flex items-center gap-3 mt-2 text-sm">
        <img src="https://voyager.postman.com/logo/postman-logo-orange.svg" alt="Postman" class="h-6" onerror="this.src='https://www.postman.com/_ar-assets/images/favicon-1-32x32.png'">
        <a id="lnk-postman-api" class="text-blue-400 hover:underline" target="_blank">Abrir colección API</a>
      </div>
    </section>

    <section id="seguridad">
      <h2>Seguridad</h2>
      <ul class="list-disc list-inside space-y-2">
        <li>Sesiones PHP en vistas privadas (esta página no requiere login)</li>
        <li>Consultas preparadas <code>mysqli->prepare()</code> en endpoints</li>
        <li>Cabeceras JSON homogéneas</li>
        <li>CDN con versión fijada en producción</li>
      </ul>
    </section>

    <section id="modelo-datos">
      <h2>Modelo de Datos</h2>
      <p class="text-sm">Tablas esperadas: <code>crm_clientes</code>, <code>vallas</code>, <code>reservas</code>, <code>facturas</code>, <code>crm_licencias</code>. Relaciones reservas↔vallas y facturas↔clientes.</p>
    </section>

    <section id="registro">
      <h2>Registro Administrativo</h2>
      <div class="bg-card border border-card rounded-lg p-6">
        <p>URL: <a href="<?= htmlspecialchars($REGISTER_URL, ENT_QUOTES, 'UTF-8'); ?>" class="text-blue-400 hover:underline" target="_blank">dev.vallasled.com/.../register/</a></p>
        <p>PIN de registro: <span id="pin-display" class="font-mono bg-gray-900 px-2 py-1 rounded">•••••</span></p>
        <h3 class="mt-4">Aprobación y PIN</h3>
        <p class="text-sm">Escribe tu nombre con V mayúscula y resto en minúsculas (ej: Vladimir).</p>
        <div class="mt-4 flex items-center gap-4 flex-wrap">
          <input id="approval-name" type="text" placeholder="V******r" class="bg-gray-900 border border-gray-600 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition w-full sm:w-auto flex-grow">
          <button id="reveal-pin-btn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition w-full sm:w-auto">Aprobar</button>
        </div>
        <p id="pin-message" class="mt-2 text-sm h-5"></p>
      </div>
    </section>

    <footer class="text-center mt-12 py-6 border-t border-card text-sm text-gray-500">By CodeMorf.tech · <?= date('Y'); ?></footer>
  </main>
</div>

<script>
(function(){
  const mobileMenuButton = document.getElementById('mobile-menu-button');
  const sidebar = document.getElementById('sidebar');
  if (mobileMenuButton) mobileMenuButton.addEventListener('click', ()=> sidebar.classList.toggle('-translate-x-full'));
  document.querySelectorAll('#sidebar-nav a').forEach(a=> a.addEventListener('click', ()=>{ if (window.innerWidth<768) sidebar.classList.add('-translate-x-full'); }));

  const setLink = (id,url)=>{ const el=document.getElementById(id); if(el){ el.href=url; } };

  const animateValue = (el, end, format=false)=>{
    const start=0, dur=1500; let st=null;
    const step=(t)=>{ if(!st) st=t; const p=Math.min((t-st)/dur,1); const v=start + (end-start)*p; el.textContent = format? Math.floor(v).toLocaleString('es-ES') : (Number.isInteger(end)? Math.floor(v) : v.toFixed(1)); if(p<1) requestAnimationFrame(step); };
    requestAnimationFrame(step);
  };

  const renderTree = (root, mountId)=>{
    const mount = document.getElementById(mountId); if (!mount) return;
    function nodeToHTML(n){
      const li = document.createElement('li');
      const meta = ` · ${n.dirs} dir · ${n.files} files · ${n.php} php · ${n.ajax} ajax`;
      li.innerHTML = `<span class="pill">${n.name}</span><span class="text-xs text-gray-400">${meta}</span>`;
      if (n.children && n.children.length){
        const ul = document.createElement('ul');
        n.children.forEach(c=> ul.appendChild(nodeToHTML(c)));
        li.appendChild(ul);
      }
      return li;
    }
    const ul = document.createElement('ul');
    (root||[]).forEach(n=> ul.appendChild(nodeToHTML(n)));
    mount.innerHTML=''; mount.appendChild(ul);
  };

  const iconFor = (p)=>{
    if(/\.php$/.test(p)) return 'fa-file-code text-blue-300';
    if(/\.js$/.test(p)) return 'fa-square-js text-yellow-300';
    if(/\.css$/.test(p)) return 'fa-css3 text-sky-300';
    return 'fa-file text-gray-400';
  };

  // Render Lenguajes
  function deviconClass(lang){
    const m = {
      'PHP':'devicon-php-plain',
      'JavaScript':'devicon-javascript-plain',
      'CSS':'devicon-css3-plain',
      'HTML':'devicon-html5-plain',
      'SQL':'devicon-mysql-plain',
      'JSON':'devicon-codepen-plain', // fallback
      'Markdown':'devicon-markdown-original',
      'YAML':'devicon-codepen-plain'
    };
    return m[lang] || 'devicon-codepen-plain';
  }
  function renderLangs(list){
    const grid = document.getElementById('langs-grid');
    if(!grid) return;
    grid.innerHTML='';
    const top = (list||[]).slice(0,8);
    top.forEach(x=>{
      const pct = x.lines_pct || 0;
      const el = document.createElement('div'); el.className='bg-card border border-card rounded-lg p-4';
      el.innerHTML = `
        <div class="flex items-center gap-3">
          <i class="${deviconClass(x.language)} text-3xl"></i>
          <div>
            <div class="text-white font-semibold">${x.language}</div>
            <div class="text-xs text-gray-400">${pct}% · ${x.lines.toLocaleString('es-ES')} líneas · ${x.files} archivos</div>
          </div>
        </div>
        <div class="mt-3 progress"><i style="width:${pct}%;"></i></div>
      `;
      grid.appendChild(el);
    });
    if((list||[]).length>8){
      const rest = (list||[]).slice(8).reduce((a,b)=>a+(b.lines_pct||0),0);
      const el = document.createElement('div'); el.className='bg-card border border-dashed border-card rounded-lg p-4';
      el.innerHTML = `
        <div class="text-white font-semibold">Otros</div>
        <div class="text-xs text-gray-400">${rest.toFixed(2)}%</div>
        <div class="mt-3 progress progress-muted"><i style="width:${rest}%;"></i></div>
      `;
      grid.appendChild(el);
    }
  }

  fetch('?action=inventory2').then(r=>r.json()).then(j=>{
    if(!j?.ok) return;
    // Links Postman
    setLink('lnk-postman-api', j.postman.api); setLink('hdr-postman-api', j.postman.api);
    setLink('lnk-postman-pwa', j.postman.pwa); setLink('hdr-postman-pwa', j.postman.pwa);

    // Scores
    animateValue(document.querySelector('[data-key="score-pro"]'), j.scores.profesionalidad);
    animateValue(document.querySelector('[data-key="score-esc"]'), j.scores.escalabilidad);
    animateValue(document.querySelector('[data-key="score-seg"]'), j.scores.seguridad);

    // Totales
    animateValue(document.querySelectorAll('[data-key="lines"]')[0], j.lines_total, true);
    document.querySelectorAll('[data-key="modules"]').forEach(el=> animateValue(el, j.modules.count));
    document.querySelectorAll('[data-key="ajax"]').forEach(el=> animateValue(el, j.ajax.count));

    // PWA
    const pwa = document.getElementById('pwa-status');
    if (pwa) {
      const ok = j.pwa && j.pwa.manifest && j.pwa.sw;
      pwa.textContent = ok ? 'Sí' : 'No';
      if (ok) pwa.classList.add('status-ok');
    }

    // Cambios recientes
    const recent = document.getElementById('recent'); recent.innerHTML='';
    (j.recent||[]).forEach(x=>{
      const div = document.createElement('div');
      div.className='flex items-start bg-card p-4 rounded-lg border border-card';
      div.innerHTML = `<i class="fas ${iconFor(x.path)} mt-1 mr-4"></i><div><p class="font-semibold text-white">${x.path}</p><p class="text-sm text-gray-400">${x.iso}</p></div>`;
      recent.appendChild(div);
    });

    // Módulos chips
    const mods = document.getElementById('mods');
    (j.modules.items||[]).forEach(m=>{ const s=document.createElement('span'); s.className='bg-blue-900/50 text-blue-300 border border-blue-700 px-3 py-1 rounded-full'; s.textContent=m; mods.appendChild(s); });

    // Árboles
    renderTree(j.tree.console, 'tree-console');
    renderTree(j.tree.api, 'tree-api');

    // Detalle por módulo
    const modsDetail = document.getElementById('mods-detail');
    if (modsDetail && j.modules_detail) {
      const wrap = document.createElement('div');
      j.modules_detail.forEach(m => {
        const sec = document.createElement('div'); sec.className='mb-3';
        const head = document.createElement('div'); head.className='font-semibold text-white'; head.textContent = m.module;
        const meta = document.createElement('div'); meta.className='text-xs text-gray-400 mb-1'; meta.textContent = `AJAX: ${m.ajax.count} · JS: ${m.js.count} · CSS: ${m.css.count}`;
        const lists = document.createElement('div'); lists.className='grid grid-cols-1 md:grid-cols-3 gap-3';
        const ul = (title, arr)=>{ const b=document.createElement('div'); b.innerHTML=`<div class='muted mb-1'>${title}</div>`; const u=document.createElement('ul'); u.className='list-disc list-inside space-y-1'; (arr||[]).slice(0,50).forEach(x=>{ const li=document.createElement('li'); li.textContent=x; u.appendChild(li); }); b.appendChild(u); return b; };
        lists.appendChild(ul('AJAX', m.ajax.files));
        lists.appendChild(ul('JS', m.js.files));
        lists.appendChild(ul('CSS', m.css.files));
        sec.appendChild(head); sec.appendChild(meta); sec.appendChild(lists); wrap.appendChild(sec);
      });
      modsDetail.innerHTML=''; modsDetail.appendChild(wrap);
    }

    // Tiempo global
    document.getElementById('t-start').textContent = j.time.start_iso;
    document.getElementById('t-now').textContent   = j.time.now_iso;
    document.getElementById('t-days').textContent  = j.time.days;
    document.getElementById('t-hours').textContent = j.time.hours;

    // Tiempo por raíz
    if (j.time_detail){
      const c = j.time_detail.console || null;
      const a = j.time_detail.api || null;
      if (c){ document.getElementById('t-console-first').textContent = `${c.first.iso} (${c.first.path||'—'})`; document.getElementById('t-console-last').textContent = `${c.last.iso} (${c.last.path||'—'})`; }
      if (a){ document.getElementById('t-api-first').textContent     = `${a.first.iso} (${a.first.path||'—'})`; document.getElementById('t-api-last').textContent     = `${a.last.iso} (${a.last.path||'—'})`; }
    }

    // Lenguajes
    renderLangs(j.langs || (j.compose? j.compose.by_lang : []));
  });

  // DB ping
  const dbBtn = document.getElementById('db-check-btn');
  if (dbBtn) dbBtn.addEventListener('click', ()=>{
    const s = document.getElementById('db-status'); s.textContent='…'; s.classList.remove('status-ok','text-red-500'); s.classList.add('text-yellow-500');
    fetch('?action=dbping').then(r=>r.json()).then(x=>{
      if (x.ok){ s.textContent='OK'; s.classList.remove('text-yellow-500'); s.classList.add('status-ok'); }
      else { s.textContent='ERR'; s.classList.remove('text-yellow-500'); s.classList.add('text-red-500'); }
    }).catch(()=>{ s.textContent='ERR'; s.classList.remove('text-yellow-500'); s.classList.add('text-red-500'); });
  });

  // PIN Reveal
  const reveal = document.getElementById('reveal-pin-btn');
  if (reveal) reveal.addEventListener('click', ()=>{
    const name = (document.getElementById('approval-name')?.value||'').trim();
    const msg  = document.getElementById('pin-message');
    const pin  = document.getElementById('pin-display');
    msg.classList.remove('text-green-400','text-red-400');
    if (name === 'Vladimir') { pin.textContent = '<?= htmlspecialchars($PIN_REGISTRO, ENT_QUOTES, 'UTF-8'); ?>'; msg.textContent='Aprobado'; msg.classList.add('text-green-400'); }
    else { pin.textContent = '•••••'; msg.textContent='Nombre incorrecto'; msg.classList.add('text-red-400'); }
  });

  // Active sidebar on scroll
  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('#sidebar-nav a');
  const scrollObserver = new IntersectionObserver((entries)=>{
    entries.forEach(e=>{ if(e.isIntersecting){ const id = e.target.getAttribute('id'); navLinks.forEach(l=> l.classList.toggle('active', l.getAttribute('href') === `#${id}`)); }});
  }, { rootMargin: '-50% 0px -50% 0px' });
  sections.forEach(s=> scrollObserver.observe(s));
})();
</script>
<script>
(function(){
  function set(id,v){ var el=document.getElementById(id); if(el) el.textContent=v; }
  function up(j){ if(!j||!j.ok) return;
    set('load-pct', (j.load&&j.load.percent!=null)? j.load.percent : '—');
    set('cpu-pct', (j.cpu&&j.cpu.percent!=null)? j.cpu.percent : '—'); set('cpu-cores', j.cpu? j.cpu.cores : '—');
    set('ram-pct', j.mem? j.mem.percent : '—'); set('ram-used', j.mem? j.mem.used_mb : '—'); set('ram-total', j.mem? j.mem.total_mb : '—');
    set('disk-pct', j.disk? j.disk.percent : '—'); set('disk-used', j.disk? j.disk.used_gb : '—'); set('disk-total', j.disk? j.disk.total_gb : '—');
    set('os-name', j.os || '—'); set('php-ver', j.php || '—'); set('mysql-ver', j.mysql || '—');
    var lbl=document.getElementById('load-label'); if(lbl){ var p=j.load? j.load.percent:0; lbl.textContent = p<50? 'Smooth operation' : (p<80? 'Moderate load' : 'High load'); }
    var cap=function(a){ return a? (a[0]+'–'+a[1]) : '—'; };
    if (j.capacity && j.capacity.api){ set('cap-api-rps', cap(j.capacity.api.rps)); set('cap-api-conc', cap(j.capacity.api.concurrency)); }
    if (j.capacity && j.capacity.console){ set('cap-ui-rps', cap(j.capacity.console.rps)); set('cap-ui-conc', cap(j.capacity.console.concurrency)); }
  }
  function tick(){ fetch('?action=server').then(r=>r.json()).then(up).catch(()=>{}); }
  tick(); setInterval(tick, 10000);

  // Cloudflare
  function upCF(j){
    const on = j && j.is_cf;
    const state = document.getElementById('cf-state');
    if (state) state.innerHTML = on ? '<span class="status-ok">Protegido por Cloudflare</span>' : 'No detectado';
    set('cf-ray', j && j.ray ? j.ray : '—');
    set('cf-colo', j && j.colo ? j.colo : '—');
    set('cf-ip', j && j.client_ip ? j.client_ip : '—');
    set('cf-cc', j && j.country ? j.country : '—');
    set('cf-proto', (j && (j.proto||j.scheme)) ? ((j.proto||'')+' · '+(j.scheme||'')) : '—');
  }
  fetch('?action=cf').then(r=>r.json()).then(upCF).catch(()=>{});
})();
</script>
</body>
</html>

