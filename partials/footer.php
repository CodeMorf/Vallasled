<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

/* ===== Config base ===== */
$__db_config = __DIR__ . '/config/db.php';
if (file_exists($__db_config)) { require_once $__db_config; }

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('web_setting')) {
  function web_setting(string $key, ?string $default=null): ?string { return $default; }
}
if (!function_exists('base_url')) {
  function base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $sch = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/') ?: '/', '/');
    return $sch.'://'.$host.($base ? $base : '');
  }
}

/* ===== Vars de marca/tema ===== */
$company_name  = web_setting('company_name', 'Vallasled.com') ?? 'Vallasled.com';
$company_addr  = web_setting('company_address','Santo Domingo, RD') ?? 'Santo Domingo, RD';
$company_phone = web_setting('company_phone','18091234567') ?? '18091234567';
$company_rnc   = web_setting('company_rnc','') ?? '';

$support_email = web_setting('support_email','soporte@vallasled.com') ?? 'soporte@vallasled.com';
$wa_raw        = web_setting('support_whatsapp','18091234567') ?? '18091234567';
$wa_digits     = preg_replace('/\D+/','',(string)$wa_raw);
$wa_href       = $wa_digits ? ('https://wa.me/'.$wa_digits) : '#';

$footer_bg      = web_setting('footer_bg_color','#0b1220') ?? '#0b1220';
$footer_text    = web_setting('footer_text_color','#e5e7eb') ?? '#e5e7eb';
$footer_link    = web_setting('footer_link_color','#60a5fa') ?? '#60a5fa';
$footer_border  = web_setting('footer_border_color','#1f2937') ?? '#1f2937';

$brand_by    = web_setting('footer_brand_by','by Vallasled') ?? 'by Vallasled';
$terms_url   = web_setting('legal_terms_url','/es/condiciones/') ?? '/es/condiciones/';
$privacy_url = web_setting('legal_privacy_url','/es/privacidad/') ?? '/es/privacidad/';

$logo_url  = web_setting('logo_url', web_setting('company_logo_url', 'https://auth.vallasled.com/admin/assets/logo.png')) ?? 'https://auth.vallasled.com/admin/assets/logo.png';
$logo_w_px = (int)max(0,(int)(web_setting('logo_width_px','120') ?? '120'));
$logo_h_px = (int)max(0,(int)(web_setting('logo_height_px','40') ?? '40'));
$logo_br_px= (int)max(0,(int)(web_setting('border_radius_px','2') ?? '2'));
$logo_style= 'border-radius:'.$logo_br_px.'px;'.($logo_h_px>0?'height:'.$logo_h_px.'px;':'').($logo_w_px>0?'width:'.$logo_w_px.'px;':'');

$asset_version = (string)(web_setting('asset_version','') ?? '');
$year = date('Y');
$base = rtrim((string)base_url(), '/');
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="dark light">
  <title><?= h('Footer · '.$company_name) ?></title>

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous"/>

  <style>
    :root{
      --color-primary: <?= h(web_setting('primary_color', '#007bff') ?? '#007bff') ?>;
      --color-primary-600: <?= h(web_setting('primary_color_600', '#0069d9') ?? '#0069d9') ?>;
      --text: <?= h($footer_text) ?>;
      --muted: <?= h(web_setting('muted_color', '#94a3b8') ?? '#94a3b8') ?>;
      --bg: <?= h(web_setting('bg_color', '#131925') ?? '#131925') ?>;
      --bg-soft: <?= h(web_setting('bg_soft_color', '#1e293b') ?? '#1e293b') ?>;
      --border: <?= h($footer_border) ?>;
      --link: <?= h($footer_link) ?>;
    }
    *{box-sizing:border-box}
    html,body{margin:0;padding:0}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,Helvetica,Arial,sans-serif;color:var(--text);background:var(--bg);line-height:1.5}
    a{color:var(--link);text-decoration:none}
    a:hover,a:focus{text-decoration:underline;outline:none}
    img{max-width:100%;height:auto;display:block}

    #app-footer{ position:relative; z-index:1; background:<?=h($footer_bg)?>; color:var(--text); border-top:1px solid var(--border) }
    .f-wrap{ max-width:72rem; margin:0 auto; padding:2rem 1rem }
    .f-top{ display:flex; align-items:center; gap:.75rem; margin-bottom:1rem }
    .f-logo{ display:block }
    .chip{ display:inline-flex; align-items:center; gap:.4rem; padding:.25rem .55rem; border-radius:9999px; background:rgba(255,255,255,.06); border:1px solid var(--border); font-size:.75rem }
    .f-desc{ margin:.5rem 0 1.25rem 0; max-width:60ch; opacity:.9 }

    .f-acc{ border-top:1px solid var(--border) }
    .f-acc-item{ border-bottom:1px solid var(--border) }
    .f-acc-btn{ width:100%; display:flex; align-items:center; justify-content:space-between; padding:1rem .25rem; font-weight:800; letter-spacing:.02em; text-transform:uppercase; font-size:.95rem; background:none; border:0; color:inherit; cursor:pointer }
    .f-acc-btn:focus-visible{ outline:2px solid var(--link); outline-offset:2px; border-radius:.5rem }
    .f-acc-btn svg{ width:18px; height:18px; flex:none; transition:transform .2s ease }
    .f-acc-panel{ max-height:0; overflow:hidden; transition:max-height .25s ease }
    .f-acc-item.open .f-acc-btn svg{ transform:rotate(180deg) }
    .f-list{ list-style:none; padding:0 0 1rem 0; margin:0; display:grid; gap:.55rem; font-size:1rem }
    .f-meta{ display:grid; gap:.35rem; font-size:.95rem }

    .btn-wa{ display:inline-flex; align-items:center; gap:.55rem; padding:.6rem .9rem; border-radius:.75rem; background:#22c55e; color:#052e16; font-weight:800; border:1px solid rgba(0,0,0,.15) }
    .btn-wa:hover{ text-decoration:none; filter:brightness(1.05) }

    .f-bottom{ display:flex; flex-wrap:wrap; gap:.75rem; align-items:center; justify-content:space-between; border-top:1px solid var(--border); margin-top:1.25rem; padding-top:1rem; font-size:.9rem; opacity:.95 }

    @media (min-width:768px){
      .f-desc{ margin-bottom:2rem }
      .f-acc{ border-top:0; display:grid; grid-template-columns:1.2fr 1fr 1fr 1fr; gap:2rem }
      .f-acc-item{ border:0 }
      .f-acc-btn{ cursor:default; padding:0 0 .5rem 0 }
      .f-acc-btn svg{ display:none }
      .f-acc-panel{ max-height:none; overflow:visible }
      .f-list{ padding:0 }
    }
  </style>
</head>
<body>

<footer id="app-footer">
  <div class="f-wrap" itemscope itemtype="https://schema.org/Organization">
    <div class="f-top">
      <a href="/" aria-label="Inicio">
        <img class="f-logo" src="<?=h($logo_url)?>" alt="<?=h($company_name)?>" style="<?=h($logo_style)?>" loading="lazy" decoding="async" itemprop="logo">
      </a>
      <div class="chip" itemprop="name"><?=h($company_name)?></div>
    </div>

    <p class="f-desc">
      Descubre y alquila vallas <strong>LED</strong> y <strong>estáticas</strong>. También imprenta, publicidad móvil y mochilas.
    </p>

    <div class="f-acc" id="f-acc">
      <section class="f-acc-item">
        <button class="f-acc-btn" type="button" aria-expanded="false">
          <span>Servicios</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div class="f-acc-panel">
          <ul class="f-list">
            <li><a href="/es/alquiler-de-vallas-led/">Alquiler de vallas LED</a></li>
            <li><a href="/es/catalogo/?tipo=impresa">Imprenta y vallas impresas</a></li>
            <li><a href="/es/catalogo/?tipo=movilled">Publicidad móvil (LED)</a></li>
            <li><a href="/es/catalogo/?tipo=mochila">Mochilas publicitarias</a></li>
            <li><a href="/es/marketing/">Marketing y campañas</a></li>
          </ul>
        </div>
      </section>

      <section class="f-acc-item">
        <button class="f-acc-btn" type="button" aria-expanded="false">
          <span>Recursos</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div class="f-acc-panel">
          <ul class="f-list">
            <li><a href="/es/blog/">Blog</a></li>
            <li><a href="/es/blog/">Noticias</a></li>
            <li><a href="/es/analisis/">Análisis</a></li>
            <li><a href="/es/mapas/">Mapa de ubicaciones</a></li>
            <li><a href="/">Inicio</a></li>
          </ul>
        </div>
      </section>

      <section class="f-acc-item">
        <button class="f-acc-btn" type="button" aria-expanded="false">
          <span>Accesos</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div class="f-acc-panel">
          <ul class="f-list">
            <li><a href="https://auth.vallasled.com/login.php" target="_blank" rel="noopener">Login Cliente</a></li>
            <li><a href="https://auth.vallasled.com/vendor/auth/login.php" target="_blank" rel="noopener">Login Proveedor</a></li>
            <li><a href="https://auth.vallasled.com/login.php" target="_blank" rel="noopener">Login Admin</a></li>
            <li><a href="/login/moviles/">Login Prestador Móviles</a></li>
            <li><a href="https://auth.vallasled.com/registro.php" target="_blank" rel="noopener">Registro Cliente</a></li>
            <li><a href="https://auth.vallasled.com/vendor/auth/register.php" target="_blank" rel="noopener">Registro Proveedor</a></li>
          </ul>
        </div>
      </section>

      <section class="f-acc-item">
        <button class="f-acc-btn" type="button" aria-expanded="false">
          <span>Legal y Contacto</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div class="f-acc-panel">
          <ul class="f-list f-meta">
            <li><a href="<?=h($privacy_url)?>">Política de privacidad</a></li>
            <li><a href="<?=h($terms_url)?>">Términos y condiciones</a></li>
            <li><a href="/sitemap.xml">Sitemap</a></li>
            <?php if ($company_rnc): ?><li>RNC: <?=h($company_rnc)?></li><?php endif; ?>
            <?php if ($company_phone): ?><li>Tel.: <?=h($company_phone)?></li><?php endif; ?>
            <?php if ($company_addr): ?><li>Dirección: <?=h($company_addr)?></li><?php endif; ?>
            <li>Email: <a href="mailto:<?=h($support_email)?>" itemprop="email"><?=h($support_email)?></a></li>
            <?php if ($wa_digits): ?>
              <li>
                <a class="btn-wa" href="<?=h($wa_href)?>" target="_blank" rel="noopener" aria-label="WhatsApp soporte">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 3.5A10.5 10.5 0 0 0 4.6 18.3L3 21l2.9-1.5A10.5 10.5 0 1 0 20 3.5Z"/></svg>
                  WhatsApp
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      </section>
    </div>

    <div class="f-bottom">
      <div>© <?=h((string)$year)?> <?=h($company_name)?>. Todos los derechos reservados.</div>
      <div><?= $brand_by!=='' ? h($brand_by) : '' ?></div>
    </div>
  </div>

  <script>
  (function(){
    if (window.__footer_inited) return;
    window.__footer_inited = true;

    const root = document.getElementById('f-acc');
    if(!root) return;

    const isDesktop = () => window.matchMedia('(min-width:768px)').matches;

    function setPanelHeight(item, open){
      const panel = item.querySelector('.f-acc-panel');
      if (!panel) return;
      if (open){
        panel.style.maxHeight = panel.scrollHeight + 'px';
      } else {
        panel.style.maxHeight = '0px';
      }
    }

    function setState(openAll){
      root.querySelectorAll('.f-acc-item').forEach(it=>{
        it.classList.toggle('open', openAll);
        const btn = it.querySelector('.f-acc-btn');
        if(btn) btn.setAttribute('aria-expanded', openAll ? 'true' : 'false');
        setPanelHeight(it, openAll);
      });
    }

    function bindMobile(){
      root.querySelectorAll('.f-acc-btn').forEach(btn=>{
        btn.onclick = () => {
          const item = btn.closest('.f-acc-item');
          const willOpen = !item.classList.contains('open');
          item.classList.toggle('open', willOpen);
          btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
          setPanelHeight(item, willOpen);
        };
      });
    }

    function unbindMobile(){
      root.querySelectorAll('.f-acc-btn').forEach(btn=>{ btn.onclick = null; });
    }

    function init(){
      if(isDesktop()){
        unbindMobile();
        setState(true);
      }else{
        setState(false);
        bindMobile();
      }
    }

    init();
    window.addEventListener('resize', init);
  })();
  </script>
</footer>

<!-- Carga de JS global del sitio -->
<script src="<?= h($base) ?>/assets/js/app.js<?= $asset_version!=='' ? ('?v='.h($asset_version)) : '' ?>" defer></script>
</body>
</html>
