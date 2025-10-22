<?php
// /console/gestion/planes/visual.php
declare(strict_types=1);

/**
 * Diagnóstico integral Planes y Comisiones
 * - Valida esquema SQL y datos clave
 * - Healthcheck de AJAX con cookies/CSRF reales (sin escrituras)
 * - No incluye sidebar
 */

header('Content-Type: text/html; charset=utf-8');

require_once dirname(__DIR__, 3) . '/config/db.php';
require_console_auth(['admin','staff']);

// CSRF y branding
$csrf  = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$brand = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel'];
$title = ($brand['title'] ?: 'Panel') . ' - Diagnóstico Planes';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";

// Driver
$isPdo = $conn instanceof PDO;

/* =========================
 * Helpers DB
 * ========================= */
function qAll(PDO|mysqli $conn, string $sql, array $params = []): array {
  $isPdo = $conn instanceof PDO;
  if ($isPdo) {
    $st = $conn->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  } else {
    if ($params) {
      $types = ''; $bind = [];
      foreach ($params as $p) {
        if (is_int($p)) { $types.='i'; }
        elseif (is_float($p)) { $types.='d'; }
        else { $types.='s'; $p = (string)$p; }
        $bind[] = $p;
      }
      $stmt = $conn->prepare($sql);
      $stmt->bind_param($types, ...$bind);
      $stmt->execute();
      $res  = $stmt->get_result();
      $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
      $stmt->close();
      return $rows;
    } else {
      $res = $conn->query($sql);
      if (!$res) return [];
      $rows = [];
      while ($row = $res->fetch_assoc()) $rows[] = $row;
      return $rows;
    }
  }
}
function qOne(PDO|mysqli $conn, string $sql, array $params = []): ?array {
  $rows = qAll($conn, $sql, $params);
  return $rows[0] ?? null;
}
function colList(PDO|mysqli $conn, string $table): array {
  $rows = qAll($conn,
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
    [$table]
  );
  return array_map(fn($r)=>$r['COLUMN_NAME'], $rows);
}
function hasTable(PDO|mysqli $conn, string $table): bool {
  $r = qOne($conn, "SELECT COUNT(*) c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$table]);
  return (int)($r['c'] ?? 0) > 0;
}

/* =========================
 * HTTP self-test con cookies/CSRF reales
 * ========================= */
function httpTest(string $method, string $path, array $opts = []): array {
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

  // Respeta proxy/CDN
  $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'
  );
  $host   = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
  $url    = $scheme . '://' . $host . $path;

  // Cookies: TODAS las que llegan + asegura cookie de sesión activa
  $cookieStr = $_SERVER['HTTP_COOKIE'] ?? '';
  $sid = session_name() . '=' . session_id();
  if ($cookieStr === '') $cookieStr = $sid;
  elseif (!str_contains($cookieStr, session_name().'=')) $cookieStr .= '; ' . $sid;

  // Propaga Authorization si existe
  $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);

  $csrf = $opts['csrf'] ?? '';
  $baseHeaders = [
    'Accept: application/json',
    'X-Requested-With: XMLHttpRequest',
    'X-CSRF: ' . $csrf,          // compat con tus AJAX
    'X-CSRF-TOKEN: ' . $csrf,    // por si tu middleware usa otro nombre
    'Csrf-Token: ' . $csrf,      // idem
    'Referer: ' . $url,
    'User-Agent: visual.php-selftest',
  ];
  if ($auth) { $baseHeaders[] = 'Authorization: '.$auth; }

  $query = $opts['query'] ?? [];
  if ($method === 'GET' && $query) {
    $url .= (str_contains($url,'?') ? '&' : '?') . http_build_query($query);
  }
  $jsonBody = $opts['json'] ?? null;

  $status=0; $rawHeaders=''; $rawBody=null; $json=null;

  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    $headers = $jsonBody ? array_merge($baseHeaders, ['Content-Type: application/json']) : $baseHeaders;
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST  => $method,
      CURLOPT_HTTPHEADER     => $headers,
      CURLOPT_COOKIE         => $cookieStr,
      CURLOPT_HEADER         => true,
      CURLOPT_TIMEOUT        => 8,
      CURLOPT_FOLLOWLOCATION => true,
    ]);
    if ($jsonBody) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody, JSON_UNESCAPED_UNICODE));
    }
    $resp = curl_exec($ch);
    if ($resp === false) {
      $err = curl_error($ch); curl_close($ch);
      return ['ok'=>false,'status'=>0,'error'=>$err,'body'=>null,'json'=>null];
    }
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hsize  = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $rawHeaders = substr($resp, 0, $hsize);
    $rawBody    = substr($resp, $hsize);
    curl_close($ch);
  } else {
    $headers = implode("\r\n", array_merge(
      $baseHeaders,
      ['Cookie: '.$cookieStr],
      $jsonBody ? ['Content-Type: application/json'] : []
    ));
    $ctx = stream_context_create(['http'=>[
      'method'        => $method,
      'header'        => $headers,
      'content'       => $jsonBody ? json_encode($jsonBody, JSON_UNESCAPED_UNICODE) : null,
      'timeout'       => 8,
      'ignore_errors' => true,
    ]]);
    $rawBody = @file_get_contents($url, false, $ctx);
    if (isset($http_response_header) && is_array($http_response_header)) {
      foreach ($http_response_header as $h) {
        if (preg_match('#HTTP/\d\.\d\s+(\d{3})#', $h, $m)) { $status=(int)$m[1]; break; }
      }
      $rawHeaders = implode("\n", $http_response_header);
    }
  }

  if (is_string($rawBody)) {
    $tmp = json_decode($rawBody, true);
    if (is_array($tmp)) $json = $tmp;
  }
  return ['ok'=>($status>=200 && $status<500),'status'=>$status,'headers'=>$rawHeaders,'body'=>$rawBody,'json'=>$json];
}

/* =========================
 * Inventario esperado
 * ========================= */
$schema = [
  'vendor_planes'         => ['id','nombre','descripcion','limite_vallas','tipo_facturacion','precio','comision','estado','prueba_dias'],
  'vendor_plan_features'  => ['plan_id','access_crm','access_facturacion','access_mapa','access_export','soporte_ncf','comision_model','comision_pct','comision_flat','factura_auto'],
  'vendor_commissions'    => ['id','proveedor_id','valla_id','comision_pct','vigente_desde','vigente_hasta'],
  'proveedores'           => ['id','nombre','estado'],
  'vallas'                => ['id','nombre','proveedor_id','estado'],
  'config_global'         => ['id','clave','valor','activo'],
];

/* =========================
 * Validación de tablas/columnas
 * ========================= */
$schemaReport = [];
$errors = []; $notes = [];
foreach ($schema as $table => $cols) {
  $exists = hasTable($conn, $table);
  $missingCols = [];
  $presentCols = [];
  if ($exists) {
    $presentCols = colList($conn, $table);
    foreach ($cols as $c) if (!in_array($c, $presentCols, true)) $missingCols[] = $c;
  } else {
    $errors[] = "Tabla faltante: $table";
  }
  $schemaReport[] = [
    'table' => $table,
    'exists'=> $exists,
    'missing'=> $missingCols,
    'present_count'=> count($presentCols)
  ];
}

/* =========================
 * Muestras y anomalías
 * ========================= */
$samples = [
  'vendor_planes'        => qAll($conn, "SELECT id,nombre,tipo_facturacion,precio,estado FROM vendor_planes ORDER BY id DESC LIMIT 5"),
  'vendor_plan_features' => qAll($conn, "SELECT plan_id,comision_model,comision_pct,comision_flat FROM vendor_plan_features ORDER BY plan_id DESC LIMIT 5"),
  'vendor_commissions'   => qAll($conn, "SELECT id,proveedor_id,valla_id,comision_pct,vigente_desde,vigente_hasta FROM vendor_commissions ORDER BY id DESC LIMIT 5"),
  'proveedores'          => qAll($conn, "SELECT id,nombre,estado FROM proveedores ORDER BY nombre ASC LIMIT 5"),
  'vallas'               => qAll($conn, "SELECT id,nombre,proveedor_id,estado FROM vallas ORDER BY id DESC LIMIT 5"),
];

$anomalies = [];
$badPct1 = qAll($conn, "SELECT id,comision_pct FROM vendor_commissions WHERE comision_pct<0 OR comision_pct>100 LIMIT 5");
foreach ($badPct1 as $r) $anomalies[] = "vendor_commissions.id={$r['id']} comision_pct fuera de 0-100";
$badTipo = qAll($conn, "SELECT id,tipo_facturacion FROM vendor_planes WHERE tipo_facturacion NOT IN ('gratis','mensual','trimestral','anual','comision') LIMIT 5");
foreach ($badTipo as $r) $anomalies[] = "vendor_planes.id={$r['id']} tipo_facturacion inválido";
$orph = qAll($conn, "SELECT f.plan_id FROM vendor_plan_features f LEFT JOIN vendor_planes p ON p.id=f.plan_id WHERE p.id IS NULL LIMIT 5");
foreach ($orph as $r) $anomalies[] = "vendor_plan_features.plan_id={$r['plan_id']} sin plan";
$badV = qAll($conn, "SELECT vc.id,vc.proveedor_id,vc.valla_id FROM vendor_commissions vc
 LEFT JOIN vallas v ON v.id=vc.valla_id
 WHERE vc.valla_id IS NOT NULL AND (v.proveedor_id IS NULL OR v.proveedor_id<>vc.proveedor_id) LIMIT 5");
foreach ($badV as $r) $anomalies[] = "vendor_commissions.id={$r['id']} valla_id={$r['valla_id']} no pertenece a proveedor_id={$r['proveedor_id']}";

$counts = [
  'vendor_planes'        => (int)(qOne($conn, "SELECT COUNT(*) c FROM vendor_planes")['c'] ?? 0),
  'vendor_plan_features' => (int)(qOne($conn, "SELECT COUNT(*) c FROM vendor_plan_features")['c'] ?? 0),
  'vendor_commissions'   => (int)(qOne($conn, "SELECT COUNT(*) c FROM vendor_commissions")['c'] ?? 0),
  'proveedores'          => (int)(qOne($conn, "SELECT COUNT(*) c FROM proveedores")['c'] ?? 0),
  'vallas'               => (int)(qOne($conn, "SELECT COUNT(*) c FROM vallas")['c'] ?? 0),
];

/* =========================
 * Healthcheck AJAX
 * ========================= */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrfRaw = $_SESSION['csrf'] ?? null;

$ajax = [];
$ajax['planes_listar'] = httpTest('GET', '/console/gestion/planes/ajax/planes_listar.php', [
  'csrf'=>$csrfRaw, 'query'=> ['limit'=>1,'page'=>1]
]);
$ajax['comisiones_listar'] = httpTest('GET', '/console/gestion/planes/ajax/comisiones_listar.php', [
  'csrf'=>$csrfRaw, 'query'=> ['limit'=>1,'page'=>1]
]);
$ajax['proveedores'] = httpTest('GET', '/console/gestion/planes/ajax/proveedores.php', [ 'csrf'=>$csrfRaw ]);

$firstProvId = null;
if (isset($ajax['proveedores']['json']['data'][0]['id'])) {
  $firstProvId = (int)$ajax['proveedores']['json']['data'][0]['id'];
}
$ajax['vallas_por_proveedor'] = $firstProvId
  ? httpTest('GET', '/console/gestion/planes/ajax/vallas_por_proveedor.php', [
      'csrf'=>$csrfRaw, 'query'=> ['proveedor_id'=>$firstProvId]
    ])
  : ['ok'=>false,'status'=>0,'error'=>'Sin proveedor para probar','body'=>null,'json'=>null];

$ajax['global_get'] = httpTest('GET', '/console/gestion/planes/ajax/global_get.php', [ 'csrf'=>$csrfRaw ]);

// Validaciones de escritura (espera 400/419) sin tocar DB
$ajax['plan_guardar_validate'] = httpTest('POST', '/console/gestion/planes/ajax/plan_guardar.php', [
  'csrf'=>$csrfRaw, 'json'=> ['nombre'=>'','tipo'=>'zzz']
]);
$ajax['comision_guardar_validate'] = httpTest('POST', '/console/gestion/planes/ajax/comision_guardar.php', [
  'csrf'=>$csrfRaw, 'json'=> ['scope'=>'proveedor','proveedor_id'=>0,'comision_pct'=>120,'desde'=>'']
]);

/* =========================
 * Helpers UI
 * ========================= */
function badge(bool $ok, string $labelOk='OK', string $labelErr='ERROR'): string {
  $cls = $ok ? 'bg-green-200 text-green-900 dark:bg-green-500/20 dark:text-green-300'
             : 'bg-red-200 text-red-900 dark:bg-red-500/20 dark:text-red-300';
  $txt = $ok ? $labelOk : $labelErr;
  return "<span class=\"text-xs font-semibold px-2 py-1 rounded-full $cls\">$txt</span>";
}
function pre($v, int $limit=1200): string {
  $j = is_string($v) ? $v : json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
  if ($j === false) $j = (string)$v;
  if (strlen($j) > $limit) $j = substr($j, 0, $limit) . "…";
  return htmlspecialchars($j, ENT_QUOTES, 'UTF-8');
}

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<title><?=$title?></title>
<link rel="icon" href="<?=$fav?>">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/console/asset/css/base.css">
<style>
  body{font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
  .card{background:var(--card-bg);border:1px solid var(--border-color);border-radius:.75rem;padding:1rem}
  .k{color:var(--text-secondary)}
  .grid-fit{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem}
  pre{white-space:pre-wrap;word-break:break-word;background:var(--main-bg);padding:.75rem;border-radius:.5rem;border:1px solid var(--border-color);font-size:.8rem}
</style>
</head>
<body class="overflow-x-hidden">
<header class="bg-[var(--header-bg)] p-4 border-b border-[var(--border-color)] sticky top-0 z-10">
  <div class="flex items-center justify-between">
    <h1 class="text-lg md:text-2xl font-bold text-[var(--text-primary)]">Diagnóstico | Planes y Comisiones</h1>
    <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
      <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
      <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
    </button>
  </div>
</header>

<main class="p-4 sm:p-6 lg:p-8 space-y-6">
  <!-- Entorno -->
  <section class="card">
    <h2 class="font-bold text-[var(--text-primary)] mb-3">Entorno</h2>
    <div class="grid-fit">
      <div class="card">
        <div class="flex items-center justify-between">
          <div>
            <div class="k text-sm">Driver DB</div>
            <div class="font-semibold"><?= $isPdo ? 'PDO' : 'mysqli' ?></div>
          </div>
          <div><?= badge(true) ?></div>
        </div>
        <div class="k text-sm mt-2">Versión PHP: <?= PHP_VERSION ?></div>
      </div>
      <div class="card">
        <div class="k text-sm">Conteos</div>
        <ul class="text-sm mt-1">
          <?php foreach($counts as $k=>$v): ?>
            <li><span class="k"><?=htmlspecialchars($k)?></span>: <span class="font-semibold"><?=$v?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>

  <!-- Esquema -->
  <section class="card">
    <h2 class="font-bold text-[var(--text-primary)] mb-3">Esquema SQL</h2>
    <div class="grid-fit">
      <?php foreach($schemaReport as $r): ?>
        <div class="card">
          <div class="flex items-center justify-between mb-1">
            <div class="font-semibold"><?=$r['table']?></div>
            <?= badge($r['exists'] && count($r['missing'])===0, $r['exists']?'OK':'FALTA', (count($r['missing'])?'COL FALTAN':'FALTA')) ?>
          </div>
          <div class="k text-sm">Columnas detectadas: <?=$r['present_count']?></div>
          <?php if(!$r['exists']): ?>
            <div class="text-red-600 text-sm mt-1">Tabla no existe</div>
          <?php elseif(count($r['missing'])): ?>
            <div class="text-red-600 text-sm mt-1">Faltan: <?=htmlspecialchars(implode(', ',$r['missing']))?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Muestras -->
  <section class="card">
    <h2 class="font-bold text-[var(--text-primary)] mb-3">Muestras</h2>
    <div class="grid-fit">
      <?php foreach($samples as $name=>$rows): ?>
        <div class="card">
          <div class="flex items-center justify-between">
            <div class="font-semibold"><?=$name?></div>
            <?= badge(true) ?>
          </div>
          <pre><?=pre($rows)?></pre>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Anomalías -->
  <section class="card">
    <h2 class="font-bold text-[var(--text-primary)] mb-3">Anomalías</h2>
    <?php if(!count($anomalies)): ?>
      <div class="text-sm"><?=badge(true,'SIN HALLAZGOS','')?> Sin anomalías críticas.</div>
    <?php else: ?>
      <ul class="list-disc pl-5 text-sm">
        <?php foreach($anomalies as $a): ?>
          <li class="text-red-600"><?=$a?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <!-- Healthcheck AJAX -->
  <section class="card">
    <h2 class="font-bold text-[var(--text-primary)] mb-3">AJAX Healthcheck</h2>
    <div class="grid-fit">
      <?php foreach($ajax as $k=>$res):
        // criterio OK: GET 200; POST de validación 400/419/422
        $isValidate = in_array($k, ['plan_guardar_validate','comision_guardar_validate'], true);
        $status = (int)($res['status'] ?? 0);
        $ok = $isValidate ? in_array($status, [400,401,419,422], true) : ($status===200);
      ?>
        <div class="card">
          <div class="flex items-center justify-between">
            <div class="font-semibold"><?=$k?></div>
            <?= badge($ok) ?>
          </div>
          <div class="k text-xs mt-1">HTTP: <?=$status?></div>
          <pre><?=pre($res['json'] ?? ($res['body'] ?? ($res['error'] ?? ''))) ?></pre>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="k text-xs mt-2">
      Nota: <code>plan_guardar_validate</code> y <code>comision_guardar_validate</code> prueban solo validaciones. No crean ni modifican registros.
    </p>
  </section>
</main>

<script>
(function(){
  const btn  = document.getElementById('theme-toggle');
  const moon = document.getElementById('theme-toggle-dark-icon');
  const sun  = document.getElementById('theme-toggle-light-icon');
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const getTheme = () => localStorage.getItem('theme') || (prefersDark ? 'dark' : 'light');
  const applyTheme = (t) => {
    const dark = t === 'dark';
    document.documentElement.classList.toggle('dark', dark);
    moon.classList.toggle('hidden', !dark);
    sun.classList.toggle('hidden', dark);
  };
  applyTheme(getTheme());
  btn?.addEventListener('click', () => {
    const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
    localStorage.setItem('theme', next);
    applyTheme(next);
  });
})();
</script>
</body>
</html>
