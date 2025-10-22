<?php
// /console/asset/sidebar.php — Responsive + dominio dinámico + PWA runtime inject
declare(strict_types=1);

start_session_safe();

/* ==== Helpers con guardas para no redeclarar ==== */
if (!function_exists('detect_base_url')) {
  function detect_base_url(): string {
    $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $scheme = is_string($scheme) ? strtolower($scheme) : 'http';
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    if (strpos($host, ':') !== false) { $host = explode(':', $host, 2)[0]; }
    $port = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? $_SERVER['SERVER_PORT'] ?? null;
    $port = $port !== null ? (int)$port : null;
    $default = ($scheme === 'https') ? 443 : 80;
    $portPart = ($port !== null && $port !== $default) ? (':' . $port) : '';
    $prefix = $_SERVER['HTTP_X_FORWARDED_PREFIX'] ?? '';
    $prefix = $prefix ? ('/' . ltrim($prefix, '/')) : '';
    return $scheme . '://' . $host . $portPart . rtrim($prefix, '/');
  }
}
if (!function_exists('abs_url')) {
  function abs_url(string $href, ?string $base = null): string {
    if ($href === '') return detect_base_url() . '/';
    if (preg_match('~^([a-z][a-z0-9+.-]*:)?//~i', $href)) return $href;
    $base = $base ?: detect_base_url();
    return rtrim($base, '/') . '/' . ltrim($href, '/');
  }
}
if (!function_exists('norm_path')) {
  function norm_path(string $href): string {
    $p = parse_url($href, PHP_URL_PATH) ?: '/';
    if ($p === '') $p = '/';
    if ($p[0] !== '/') $p = '/'.$p;
    if (substr($p, -1) !== '/') $p .= '/';
    return $p;
  }
}

$BASE = detect_base_url();

/** PWA versioning */
$docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$mf = $docroot . '/console/pwa/manifest.json';
$sw = $docroot . '/console/pwa/sw.js';
$v1 = @filemtime($mf) ?: 0; $v2 = @filemtime($sw) ?: 0; $PWA_VERSION = max($v1, $v2) ?: time();

/** Branding y assets */
$branding = function_exists('load_branding') ? load_branding($conn) : ['logo_url'=>null,'title'=>'Panel'];
$logo_url_fallback = 'https://auth.vallasled.com/admin/assets/logo.png'; // logo real
$logo = abs_url((string)($branding['logo_url'] ?: $logo_url_fallback), $BASE);

$fav16 = htmlspecialchars(abs_url('/console/pwa/icons/favicon-16.png', $BASE) . '?v=' . $PWA_VERSION, ENT_QUOTES, 'UTF-8');
$fav32 = htmlspecialchars(abs_url('/console/pwa/icons/favicon-32.png', $BASE) . '?v=' . $PWA_VERSION, ENT_QUOTES, 'UTF-8');
$icon192 = htmlspecialchars(abs_url('/console/pwa/icons/icon-192.png', $BASE) . '?v=' . $PWA_VERSION, ENT_QUOTES, 'UTF-8');

/** Sesión y permisos */
$tipo   = $_SESSION['tipo'] ?? 'guest';
$perms  = is_array($_SESSION['permisos'] ?? null) ? $_SESSION['permisos'] : [];
$userIsAdmin = ($tipo === 'admin');

/* ==== Permisos lógicos ==== */
$has = function(string $need) use ($tipo,$perms): bool {
  if ($need === 'admin.only') return $tipo === 'admin';
  if ($need === 'staff.only') return $tipo === 'staff';
  if ($tipo === 'admin') return true;
  if ($need === '*')     return false;
  foreach ($perms as $p) {
    if (!is_string($p)) continue;
    if ($p === $need) return true;
    if (str_ends_with($p,'*')) { $base=rtrim($p,'*'); if (str_starts_with($need,$base)) return true; }
    if (str_ends_with($need,'*')) { $base=rtrim($need,'*'); if (str_starts_with($p,$base)) return true; }
  }
  return false;
};
$can = function($need) use ($has): bool {
  if (is_array($need)) { foreach ($need as $n) if ($has((string)$n)) return true; return false; }
  return $has((string)$need);
};

/* ==== ACL por URL ==== */
$urlAllowed = function(string $href) use ($tipo,$perms): bool {
  if ($tipo === 'admin') return true;
  $target = norm_path($href);
  foreach ($perms as $p) {
    if (!is_string($p) || !str_starts_with($p,'url:')) continue;
    $rule = norm_path(substr($p, 4));
    if (str_ends_with($rule, '*/')) { $pref = rtrim($rule, '*/'); if (str_starts_with($target, $pref)) return true; }
    else { if ($target === $rule || str_starts_with($target, $rule)) return true; }
  }
  return false;
};

$path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');

/* ==== Menú ==== */
/* Orden:
   Dashboard, ADS, Mi Panel, Vallas, Reservas, Facturación,
   Licencias, Zonas, Contabilidad, Gestión, Sistema
*/
$MENU = [
  ['label'=>'Dashboard','icon'=>'fa-gauge','href'=>'/console/portal/','perm'=>'admin.only','key'=>'console/portal'],
  ['label'=>'ADS','icon'=>'fa-rectangle-ad','href'=>'/console/ads/','perm'=>'ads.view','key'=>'console/ads'],
  ['label'=>'Mi Panel','icon'=>'fa-user','href'=>'/console/empleados/','perm'=>'staff.only','key'=>'console/empleados'],

  ['label'=>'Vallas','icon'=>'fa-ad','href'=>'/console/vallas/','perm'=>'vallas.view','key'=>'console/vallas'],
  ['label'=>'Reservas','icon'=>'fa-calendar-check','href'=>'/console/reservas/','perm'=>'reservas.view','key'=>'console/reservas'],
  ['label'=>'Facturación','icon'=>'fa-file-invoice-dollar','href'=>'/console/facturacion/','perm'=>'facturas.view','key'=>'console/facturacion'],

  ['label'=>'Licencias','icon'=>'fa-file-contract','href'=>'/console/licencias/','perm'=>'licencias.view','key'=>'console/licencias'],
  ['label'=>'Zonas','icon'=>'fa-draw-polygon','href'=>'/console/zonas/','perm'=>'zonas.view','key'=>'console/zonas'],
  ['label'=>'Contabilidad','icon'=>'fa-calculator','href'=>'/console/gestion/contabilidad/','perm'=>'conta.view','key'=>'console/gestion/contabilidad'],

  ['label'=>'Gestión','icon'=>'fa-briefcase',
    'perm'=>['clientes.view','proveedores.view','empleados.view','planes.view','pagos.view','web.view','vendor.view','mapa.view'],
    'submenu'=>[
      ['label'=>'Clientes','icon'=>'fa-users','href'=>'/console/gestion/clientes/','perm'=>'clientes.view','key'=>'console/gestion/clientes'],
      ['label'=>'Proveedores','icon'=>'fa-truck-fast','href'=>'/console/gestion/proveedores/','perm'=>'proveedores.view','key'=>'console/gestion/proveedores'],
      ['label'=>'Empleados','icon'=>'fa-user-tie','href'=>'/console/gestion/empleados/','perm'=>'empleados.view','key'=>'console/gestion/empleados'],
      ['label'=>'Planes','icon'=>'fa-cubes','href'=>'/console/gestion/planes/','perm'=>'planes.view','key'=>'console/gestion/planes'],
      ['label'=>'Pagos','icon'=>'fa-credit-card','href'=>'/console/gestion/pagos/','perm'=>'pagos.view','key'=>'console/gestion/pagos'],
      ['label'=>'Web','icon'=>'fa-globe','href'=>'/console/gestion/web/','perm'=>'web.view','key'=>'console/gestion/web'],
      ['label'=>'Vendors','icon'=>'fa-store','href'=>'/console/gestion/vendors/','perm'=>'vendor.view','key'=>'console/gestion/vendors'],
      ['label'=>'Mapa','icon'=>'fa-map-location-dot','href'=>'/console/mapa/','perm'=>'mapa.view','key'=>'console/mapa'],
    ],
    'key'=>'console/gestion'
  ],

  ['label'=>'Sistema','icon'=>'fa-desktop','perm'=>['usuarios.view','config.view'],'submenu'=>[
      ['label'=>'Usuarios','icon'=>'fa-user-shield','href'=>'/console/sistema/usuarios/','perm'=>'usuarios.view','key'=>'console/sistema/usuarios'],
      ['label'=>'Configuración','icon'=>'fa-cogs','href'=>'/console/sistema/config/','perm'=>'config.view','key'=>'console/sistema/config'],
  ],'key'=>'console/sistema'],
];

$active = function(string $key) use ($path): bool { return str_starts_with($path, $key); };
?>
<aside
  class="sidebar transform fixed top-0 left-0 h-[100dvh] w-[17rem] md:w-64 bg-[var(--sidebar-bg)] text-[var(--sidebar-text)] flex flex-col z-30
         transition-transform duration-200 ease-out md:translate-x-0 -translate-x-full md:shadow-none shadow-xl"
  role="navigation" aria-label="Menú">
  <div class="flex items-center justify-between h-16 px-4 border-b border-[var(--border-color)]">
    <!-- Solo un logo -->
    <img id="dynamic-logo"
         src="<?=htmlspecialchars($logo, ENT_QUOTES, 'UTF-8')?>"
         data-logo-desktop="<?=htmlspecialchars($logo, ENT_QUOTES, 'UTF-8')?>"
         data-logo-mobile="<?=$icon192?>"
         data-logo-collapsed="<?=$fav32?>"
         alt="Logo" class="h-8 md:h-10 select-none"
         referrerpolicy="no-referrer">
    <button id="sidebar-close" class="md:hidden p-2 rounded-lg hover:bg-[var(--sidebar-active-bg)]" aria-label="Cerrar menú">
      <i class="fa fa-times"></i>
    </button>
  </div>

  <nav class="sidebar-nav flex-1 px-3 py-4 space-y-1 overflow-y-auto">
    <?php foreach ($MENU as $item):
      $isSub = isset($item['submenu']);
      $visible = false;
      if (!$isSub) {
        $visible = $userIsAdmin ? $can($item['perm']) : $urlAllowed($item['href'] ?? '#');
      } else {
        $children = array_values(array_filter($item['submenu'], function($sub) use ($userIsAdmin,$can,$urlAllowed){
          return $userIsAdmin ? $can($sub['perm']) : $urlAllowed($sub['href'] ?? '#');
        }));
        if (!empty($children)) { $item['submenu'] = $children; $visible = true; }
      }
      if (!$visible) continue;

      $isOpen = $isSub && $active($item['key']);
      $aCls = !$isSub && $active($item['key'])
        ? 'bg-[var(--sidebar-active-bg)] text-[var(--sidebar-active-text)] font-semibold'
        : 'hover:bg-[var(--sidebar-active-bg)] hover:text-[var(--sidebar-text-hover)]';

      if (!$isSub): ?>
        <a href="<?=htmlspecialchars(abs_url($item['href'], $BASE))?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg <?=$aCls?> transition-colors">
          <i class="fas <?=$item['icon']?> w-5 text-center"></i>
          <span class="sidebar-text-label text-sm"><?=htmlspecialchars($item['label'])?></span>
        </a>
      <?php else: ?>
        <div>
          <button class="submenu-trigger w-full flex justify-between items-center px-3 py-2.5 rounded-lg hover:bg-[var(--sidebar-active-bg)] hover:text-[var(--sidebar-text-hover)] <?= $isOpen?'submenu-open':''?>" type="button" aria-expanded="<?= $isOpen?'true':'false'?>">
            <div class="flex items-center gap-3">
              <i class="fas <?=$item['icon']?> w-5 text-center"></i>
              <span class="sidebar-text-label text-sm"><?=htmlspecialchars($item['label'])?></span>
            </div>
            <i class="fas fa-chevron-right submenu-arrow"></i>
          </button>
          <div class="submenu <?= $isOpen?'':'hidden'?> pl-8 mt-1 space-y-1">
            <?php foreach ($item['submenu'] as $sub):
              $subCls = $active($sub['key'])
                ? 'bg-[var(--sidebar-active-bg)] text-[var(--sidebar-active-text)] font-semibold'
                : 'hover:bg-[var(--sidebar-active-bg)] hover:text-[var(--sidebar-text-hover)]';
            ?>
              <a href="<?=htmlspecialchars(abs_url($sub['href'], $BASE))?>" class="flex items-center gap-2 px-3 py-2 rounded-lg <?=$subCls?> transition-colors">
                <i class="fas <?=$sub['icon']?> w-5"></i><span class="sidebar-text-label text-sm"><?=htmlspecialchars($sub['label'])?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; endforeach; ?>
  </nav>

  <div class="px-3 py-3 border-t border-[var(--border-color)]">
    <div class="brand-signature text-center text-[10px] text-[var(--sidebar-text)] mb-2"><?=htmlspecialchars($branding['title'] ?? 'Panel')?> · By CodeMorf</div>
    <a href="<?=htmlspecialchars(abs_url('/console/auth/logout.php', $BASE))?>" class="flex items-center justify-center md:justify-start gap-2 px-3 py-2.5 rounded-lg hover:bg-[var(--sidebar-active-bg)] hover:text-[var(--sidebar-text-hover)]">
      <i class="fas fa-sign-out-alt w-5 text-center"></i>
      <span class="sidebar-text-label text-sm">Cerrar Sesión</span>
    </a>
  </div>
</aside>

<?php require_once __DIR__ . '/loader.php'; ?>

<script>
/* Sidebar universal */
(function(){
  if (window.__sidebar_fix__) return; window.__sidebar_fix__=true;

  const body = document.body;
  const sidebar = document.querySelector('.sidebar');
  if (!sidebar) return;

  let ov = document.getElementById('sidebar-overlay');
  if (!ov) {
    ov = document.createElement('div');
    ov.id = 'sidebar-overlay';
    ov.className = 'fixed inset-0 bg-black/40 z-20 md:hidden hidden';
    document.body.appendChild(ov);
  }

  const dynamicLogo = document.getElementById('dynamic-logo');
  const logoDesktop   = dynamicLogo?.dataset.logoDesktop || dynamicLogo?.src || '';
  const logoMobile    = dynamicLogo?.dataset.logoMobile  || logoDesktop;
  const logoCollapsed = dynamicLogo?.dataset.logoCollapsed || '<?= $fav32 ?>';

  const LS_KEY = 'sidebarCollapsed';
  const mdQuery = window.matchMedia('(min-width: 768px)');
  const isDesktop = () => mdQuery.matches;

  function setLogoForViewport() {
    const collapsed = body.classList.contains('sidebar-collapsed');
    if (!dynamicLogo) return;
    if (collapsed) { dynamicLogo.src = logoCollapsed; dynamicLogo.style.height = isDesktop() ? '2.5rem' : '2rem'; return; }
    if (isDesktop()) { dynamicLogo.src = logoDesktop; dynamicLogo.style.height = '2.5rem'; }
    else { dynamicLogo.src = logoMobile; dynamicLogo.style.height = '2rem'; }
  }

  function applyCollapsed(fromInit=false){
    setLogoForViewport();
    if (!fromInit && body.classList.contains('sidebar-collapsed')) {
      document.querySelectorAll('.submenu-open').forEach(t=>{
        t.classList.remove('submenu-open');
        const sub = t.nextElementSibling; if (sub) sub.classList.add('hidden');
      });
    }
  }

  try { if (localStorage.getItem(LS_KEY) === '1') body.classList.add('sidebar-collapsed'); } catch(e){}
  applyCollapsed(true);

  function setMobile(open){
    if (!sidebar) return;
    if (isDesktop()) { sidebar.style.transform = ''; ov.classList.add('hidden'); body.classList.remove('overflow-hidden','sidebar-open'); return; }
    if (open) { sidebar.style.transform = 'translateX(0)'; ov.classList.remove('hidden'); body.classList.add('overflow-hidden','sidebar-open'); }
    else { sidebar.style.transform = 'translateX(-100%)'; ov.classList.add('hidden'); body.classList.remove('overflow-hidden','sidebar-open'); }
  }

  function syncViewport(){
    if (isDesktop()) { sidebar.style.transform = ''; ov.classList.add('hidden'); body.classList.remove('overflow-hidden','sidebar-open'); }
    else { if (!body.classList.contains('sidebar-open')) sidebar.style.transform = 'translateX(-100%)'; }
    setLogoForViewport();
  }
  mdQuery.addEventListener('change', syncViewport);
  window.addEventListener('orientationchange', syncViewport);
  window.addEventListener('resize', setLogoForViewport, { passive: true });
  syncViewport();

  window.sidebarOpen   = ()=> setMobile(true);
  window.sidebarClose  = ()=> setMobile(false);
  window.sidebarToggle = ()=> setMobile(!body.classList.contains('sidebar-open'));

  document.addEventListener('click', function(e){
    const el = e.target instanceof Element ? e.target : null; if (!el) return;
    if (el.closest('#mobile-menu-button')) { e.preventDefault(); setMobile(true); return; }
    if (el.closest('#sidebar-close') || el.id === 'sidebar-overlay') { e.preventDefault(); setMobile(false); return; }
    if (el.closest('#sidebar-toggle-desktop')) {
      e.preventDefault();
      body.classList.toggle('sidebar-collapsed');
      applyCollapsed();
      try { localStorage.setItem(LS_KEY, body.classList.contains('sidebar-collapsed') ? '1' : '0'); } catch(e){}
      setTimeout(()=>window.dispatchEvent(new Event('resize')), 150);
      return;
    }
    const btn = el.closest('.submenu-trigger');
    if (btn) {
      if (body.classList.contains('sidebar-collapsed')) return;
      const sub = btn.nextElementSibling;
      btn.classList.toggle('submenu-open');
      if (sub) sub.classList.toggle('hidden');
      btn.setAttribute('aria-expanded', btn.classList.contains('submenu-open') ? 'true' : 'false');
      return;
    }
    if (!isDesktop() && el.closest('.sidebar a[href]')) setMobile(false);
  });

  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') setMobile(false); });

  let startX=null;
  sidebar.addEventListener('touchstart',(e)=>{ startX = e.touches[0].clientX; },{passive:true});
  sidebar.addEventListener('touchmove',(e)=>{
    if (startX===null) return;
    const dx = e.touches[0].clientX - startX;
    if (dx < -60) { setMobile(false); startX=null; }
  },{passive:true});
})();
</script>

<script>
/* PWA runtime inject: favicon solo en <head> */
(function(){
  if (window.__pwa_injected__) return; window.__pwa_injected__=true;

  const PWA = {
    manifest: "<?=htmlspecialchars(abs_url('/console/pwa/manifest.json', $BASE))?>?v=<?=$PWA_VERSION?>",
    sw: "<?=htmlspecialchars(abs_url('/console/pwa/sw.js', $BASE))?>?v=<?=$PWA_VERSION?>",
    scope: "/console/pwa/",
    icons: {
      x192: "<?=htmlspecialchars(abs_url('/console/pwa/icons/icon-192.png', $BASE))?>?v=<?=$PWA_VERSION?>",
      x512: "<?=htmlspecialchars(abs_url('/console/pwa/icons/icon-512.png', $BASE))?>?v=<?=$PWA_VERSION?>",
      fav16: "<?=htmlspecialchars(abs_url('/console/pwa/icons/favicon-16.png', $BASE))?>?v=<?=$PWA_VERSION?>",
      fav32: "<?=htmlspecialchars(abs_url('/console/pwa/icons/favicon-32.png', $BASE))?>?v=<?=$PWA_VERSION?>"
    },
    themeColor: "#111827"
  };

  function head(){ return document.head || document.getElementsByTagName('head')[0]; }
  function upsert(selector, create){
    const h = head(); if (!h) return null;
    let el = h.querySelector(selector);
    if (!el) { el = create(); h.appendChild(el); }
    return el;
  }
  function link(rel, href, attrs){
    const el = document.createElement('link');
    el.setAttribute('rel', rel); el.setAttribute('href', href);
    if (attrs) for (const k in attrs) el.setAttribute(k, attrs[k]);
    return el;
  }
  function meta(name, content){
    const el = document.createElement('meta');
    el.setAttribute('name', name); el.setAttribute('content', content);
    return el;
  }

  upsert('link[rel="manifest"]', ()=>link('manifest', PWA.manifest)).setAttribute('href', PWA.manifest);
  upsert('meta[name="theme-color"]', ()=>meta('theme-color', PWA.themeColor)).setAttribute('content', PWA.themeColor);
  upsert('link[rel="icon"][sizes="16x16"]', ()=>link('icon', PWA.icons.fav16, {sizes:'16x16', type:'image/png'})).setAttribute('href', PWA.icons.fav16);
  upsert('link[rel="icon"][sizes="32x32"]', ()=>link('icon', PWA.icons.fav32, {sizes:'32x32', type:'image/png'})).setAttribute('href', PWA.icons.fav32);
  upsert('link[rel="apple-touch-icon"]', ()=>link('apple-touch-icon', PWA.icons.x192, {sizes:'192x192'})).setAttribute('href', PWA.icons.x192);

  function isSecure(){ return location.protocol === 'https:' || ['localhost','127.0.0.1'].includes(location.hostname); }
  if ('serviceWorker' in navigator && isSecure()){
    const register = () => {
      navigator.serviceWorker.register(PWA.sw, { scope: PWA.scope }).catch(err => console.warn('[PWA] register error', err));
    };
    if (document.readyState === 'complete') register(); else window.addEventListener('load', register, { once: true });
  }
})();
</script>
