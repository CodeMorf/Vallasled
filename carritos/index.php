<?php declare(strict_types=1);

/* ==== Config primero (para is_https(), db(), etc.) ==== */
$cfg = __DIR__ . '/../config/db.php';
$med = __DIR__ . '/../config/media.php';
$trk = __DIR__ . '/../config/tracking.php';
if (file_exists($cfg)) require_once $cfg;
if (file_exists($med)) require_once $med;
if (file_exists($trk)) require_once $trk;

/* ==== Sesión: sin warning, sin caché ==== */
ini_set('session.use_strict_mode','1');
$secureFlag = function_exists('is_https')
  ? is_https()
  : (
      (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO'])==='https')
      || (isset($_SERVER['REQUEST_SCHEME']) && strtolower((string)$_SERVER['REQUEST_SCHEME'])==='https')
    );

/* Debe ejecutarse ANTES de session_start() */
session_cache_limiter('nocache');
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => $secureFlag,
  'httponly' => true,
  'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* Encabezados anti-cache para la vista HTML */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/* ==== Helpers ==== */
if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }
function i($v, int $def=0): int { $n = filter_var($v, FILTER_VALIDATE_INT); return $n===false? $def : $n; }
function only_digits(string $s): string { $r = preg_replace('/\D+/', '', $s); return $r===null? '' : $r; }
function jout(array $p): never {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
  echo json_encode($p, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function cart_count(): int { $c=0; foreach(($_SESSION['cart'] ?? []) as $q){ $c+=(int)$q; } return $c; }

/* ==== Estado carrito ==== */
$_SESSION['cart'] = (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) ? $_SESSION['cart'] : [];

/* ==== Acciones ==== */
$action = (string)($_REQUEST['a'] ?? '');
$id     = i($_REQUEST['id'] ?? 0);
$qty    = i($_REQUEST['qty'] ?? 1);
$qty    = $qty < 1 ? 1 : $qty;

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest';

if ($action === 'add' && $id>0) { $_SESSION['cart'][$id] = (int)(($_SESSION['cart'][$id] ?? 0) + $qty); if ($isAjax) jout(['ok'=>true,'count'=>cart_count()]); header('Location:/carritos/'); exit; }
if ($action === 'set' && $id>0) { $_SESSION['cart'][$id]=$qty; if ($isAjax) jout(['ok'=>true,'count'=>cart_count()]); header('Location:/carritos/'); exit; }
if ($action === 'del' && $id>0) { unset($_SESSION['cart'][$id]); if ($isAjax) jout(['ok'=>true,'count'=>cart_count()]); header('Location:/carritos/'); exit; }
if ($action === 'clear')       { $_SESSION['cart']=[]; if ($isAjax) jout(['ok'=>true,'count'=>0]); header('Location:/carritos/'); exit; }
if ($action === 'count')       { jout(['ok'=>true,'count'=>cart_count()]); }

/* ==== Cargar ítems (MySQL 8 via PDO) ==== */
$items = []; $total = 0.0; $pdo = null;
try {
  $pdo = db(); // desde /config/db.php (PDO MySQL 8)
  if (!empty($_SESSION['cart'])) {
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $in  = implode(',', array_fill(0,count($ids),'?'));
    $sql = "SELECT v.id, v.nombre, v.tipo, v.precio, v.medida,
                   v.mostrar_precio_cliente,
                   v.imagen, v.imagen1, v.imagen2, v.imagen_previa, v.imagen_tercera, v.imagen_cuarta
            FROM vallas v WHERE v.id IN ($in)";
    $st = $pdo->prepare($sql);
    foreach ($ids as $k=>$vid) $st->bindValue($k+1, $vid, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
      $media=null;
      foreach (['imagen_previa','imagen','imagen1','imagen2','imagen_tercera','imagen_cuarta'] as $c){
        $u = function_exists('media_norm') ? media_norm((string)($r[$c]??'')) : (string)($r[$c]??'');
        if ($u) { $media=$u; break; }
      }
      $rid = (int)$r['id'];
      $q   = (int)($_SESSION['cart'][$rid] ?? 1);
      $showPrice = (int)($r['mostrar_precio_cliente'] ?? 1) === 1;
      $pre = $showPrice && isset($r['precio']) ? (float)$r['precio'] : 0.0;
      $sub = $showPrice ? $pre * $q : 0.0;
      if ($showPrice) $total += $sub;

      $items[] = [
        'id'=>$rid,
        'nombre'=>(string)$r['nombre'],
        'tipo'=>(string)$r['tipo'],
        'medida'=>(string)($r['medida'] ?? ''),
        'show_price'=>$showPrice,
        'precio'=>$pre,
        'qty'=>$q,
        'subtotal'=>$sub,
        'img'=>$media ?: 'https://placehold.co/1200x800/0b1220/93c5fd?text=Valla',
      ];
    }
  }
} catch(Throwable $e){
  // Silencioso por UI; log si tienes historial.php
}

/* ==== WhatsApp (sin API) ==== */
$wa = '18090000000';
if ($pdo) {
  $tries = [
    ['t'=>'web_setting','k'=>'clave','v'=>'valor'],
    ['t'=>'web_settings','k'=>'clave','v'=>'valor'],
    ['t'=>'web_setting','k'=>'key','v'=>'value'],
    ['t'=>'web_settings','k'=>'key','v'=>'value'],
    ['t'=>'settings','k'=>'key','v'=>'value'],
    ['t'=>'settings','k'=>'name','v'=>'value'],
  ];
  foreach ($tries as $c) {
    try {
      $st = $pdo->prepare("SELECT {$c['v']} FROM {$c['t']} WHERE {$c['k']}=:k LIMIT 1");
      $st->execute([':k'=>'support_whatsapp']);
      $val = $st->fetchColumn();
      if ($val) { $wa = only_digits((string)$val) ?: $wa; break; }
    } catch(Throwable $e){}
  }
}
$lines = [];
foreach ($items as $it) {
  $lines[] = "- ID {$it['id']} x{$it['qty']} · {$it['nombre']}"
           . ($it['medida']? " ({$it['medida']})" : "")
           . ($it['show_price'] ? "" : " (sin precio)");
}
$body  = "Hola, me interesa cotizar estas vallas:\n".implode("\n",$lines);
if ($total>0) $body .= "\n\nTotal estimado (solo ítems con precio): RD$ ".number_format($total,0,'.',',');
$body .= "\n\n¿Disponibilidad y siguientes pasos? Gracias.";
$wa_link = 'https://api.whatsapp.com/send?phone=' . rawurlencode($wa) . '&text=' . rawurlencode($body);

/* ==== Header / Tracking ==== */
$page_title = 'Carrito | Vallas';
$__header = __DIR__ . '/../partials/header.php';
if (file_exists($__header)) include $__header;
if (function_exists('tracking_body')) tracking_body();
?>
<script src="https://cdn.tailwindcss.com"></script>

<div class="max-w-5xl mx-auto px-4 py-8">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
    <h1 class="text-3xl font-extrabold text-white">Carrito</h1>
    <div class="flex flex-wrap gap-2">
      <a class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-100" href="/">Seguir buscando</a>
      <a class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white js-clear-cart" href="/carritos/?a=clear" aria-label="Vaciar carrito">Vaciar</a>
      <a class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-emerald-950 font-bold" href="<?=h($wa_link)?>" target="_blank" rel="noopener">Enviar por WhatsApp</a>
    </div>
  </div>

  <?php if (!$items): ?>
    <div class="rounded-xl border border-slate-700 bg-slate-800 p-6 text-slate-300">No tienes vallas en el carrito.</div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($items as $it): ?>
        <div class="cart-row grid grid-cols-1 sm:grid-cols-[110px_1fr_auto] gap-4 items-center rounded-xl border border-slate-700 bg-slate-800 p-3" data-id="<?= (int)$it['id'] ?>">
          <img class="w-full sm:w-[110px] h-[180px] sm:h-[74px] object-cover rounded-md" src="<?=h($it['img'])?>" alt="">
          <div>
            <div class="font-bold text-white"><?=h($it['nombre'])?></div>
            <div class="text-slate-400 text-sm"><?= strtoupper(h($it['tipo'])) ?><?= $it['medida'] ? ' · '.h($it['medida']) : '' ?></div>

            <?php if ($it['show_price']): ?>
              <div class="mt-1 text-slate-200 text-sm">RD$ <?= number_format($it['precio'],0,',','.') ?></div>
            <?php else: ?>
              <div class="mt-1 text-amber-400 text-sm">Precio no público</div>
            <?php endif; ?>

            <div class="mt-3 flex flex-wrap items-center gap-3">
              <div class="inline-flex items-center border border-slate-600 rounded-lg overflow-hidden">
                <button class="qminus px-3 py-2 bg-slate-700 text-slate-200" type="button" aria-label="Disminuir cantidad">−</button>
                <input class="qval w-16 text-center bg-slate-900 text-slate-100 border-0 py-2" type="number" min="1" value="<?= (int)$it['qty'] ?>">
                <button class="qplus px-3 py-2 bg-slate-700 text-slate-200" type="button" aria-label="Aumentar cantidad">+</button>
              </div>

              <?php if ($it['show_price']): ?>
                <span class="text-slate-300 text-sm">Subtotal: <b>RD$ <?= number_format($it['subtotal'],0,',','.') ?></b></span>
              <?php else: ?>
                <span class="text-slate-500 text-sm">Subtotal: —</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="text-left sm:text-right">
            <div class="flex flex-row gap-2">
              <a class="px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-100"
                 href="/detalles-<?= $it['tipo']==='led'?'led':'vallas' ?>/?id=<?=$it['id']?>">Detalles</a>

              <a class="px-3 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white js-del-item"
                 href="/carritos/?a=del&id=<?=$it['id']?>" data-id="<?=$it['id']?>">Quitar</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-6 text-right text-xl text-white font-bold">
      Total estimado<?= array_sum(array_map(fn($x)=>$x['show_price']?1:0,$items))!==count($items) ? ' (solo ítems con precio)' : '' ?>:
      <?= $total>0 ? 'RD$ '.number_format($total,0,',','.') : '—' ?>
    </div>
  <?php endif; ?>
</div>

<!-- JS unificado del carrito -->
<script src="/carritos/assets/js/app.js?v=2" defer></script>

<?php
$__footer = __DIR__ . '/../partials/footer.php';
if (file_exists($__footer)) include $__footer;
if (function_exists('track_pageview')) track_pageview($_SERVER['REQUEST_URI'] ?? '/carritos');
