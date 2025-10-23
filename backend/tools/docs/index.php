<?php
// /tools/docs/index.php  — UI autosuficiente para generar manifest + ZIP
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

// Guards compatibles con tu stack
if (function_exists('start_session_safe')) start_session_safe(); else { if (session_status() !== PHP_SESSION_ACTIVE) session_start(); }
$ALLOWED = ['admin','staff','superadmin','owner','root'];
if (function_exists('require_role')) {
  require_role(['admin']);
} else {
  if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], $ALLOWED, true)) {
    header('Location: /console/auth/login/'); exit;
  }
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

$branding = ['title' => 'Console', 'favicon' => ''];
if (function_exists('db') && function_exists('load_branding')) {
  try { $branding = array_merge($branding, (array)load_branding(db())); } catch (Throwable $e) {}
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Generador de Documentación · <?=htmlspecialchars($branding['title'] ?? 'Console', ENT_QUOTES,'UTF-8')?></title>
<link rel="icon" href="<?=htmlspecialchars($branding['favicon'] ?? '', ENT_QUOTES,'UTF-8')?>">
<meta name="csrf" content="<?=htmlspecialchars($csrf, ENT_QUOTES,'UTF-8')?>">
<style>
/* ====== CSS embebido (core mínimo de la página) ====== */
:root{
  --bg:#0b1020; --card:#111827; --fg:#e5e7eb; --muted:#9ca3af; --bd:#1f2937;
  --brand:#22d3ee; --ok:#10b981; --err:#ef4444; --warn:#f59e0b;
}
*{box-sizing:border-box} html,body{height:100%} body{margin:0;background:var(--bg);color:var(--fg);font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Arial}
a{color:inherit;text-decoration:none}
.wrap{max-width:980px;margin:32px auto;padding:0 16px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:20px}
h1{margin:0 0 6px;font-size:20px} p{margin:6px 0 0;color:var(--muted)}
label{display:block;margin:12px 0 6px;color:var(--muted)}
input[type=text],select{width:100%;padding:10px;border-radius:10px;border:1px solid #334155;background:#0b1220;color:#e5e7eb}
.row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media (max-width: 900px){ .row{grid-template-columns:1fr} }
.ck{display:flex;align-items:center;gap:8px;color:#e5e7eb}
.sep{height:1px;background:#1f2937;margin:16px 0}
.btn{display:inline-flex;align-items:center;gap:8px;background:var(--brand);color:#031017;border:0;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer}
.btn.secondary{background:#0e2030;color:#cae7f3;border:1px solid #1d3b52}
.small{font-size:12px;color:var(--muted)}
.badge{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--bd);border-radius:999px;padding:4px 8px;background:#0b1220;color:#cbd5e1}
.alert{padding:10px 12px;border-radius:10px;border:1px solid var(--bd);background:#0b1220}
.alert.ok{border-color:#0e3b2f;color:#d2f5e7;background:#08261f}
.alert.err{border-color:#3b0e0e;color:#ffd6d6;background:#220909}
.toast{position:fixed;right:16px;bottom:16px;display:flex;flex-direction:column;gap:10px;z-index:1000}
.toast .item{background:#0b1220;border:1px solid var(--bd);border-left:4px solid var(--brand);border-radius:10px;padding:10px 12px;min-width:260px;max-width:360px}
.toast .item.ok{border-left-color:var(--ok)} .toast .item.err{border-left-color:var(--err)}
.result{display:grid;gap:10px}
.result a{color:#cfe7ff;text-decoration:underline}
kbd,code{background:#0b1220;border:1px solid var(--bd);border-radius:6px;padding:0 6px}
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Generador de Documentación</h1>
      <p>Escanea <code>/console/</code>, crea <code>manifest.json</code>, Markdown y <code>docs_bundle.zip</code>. El <strong>sidebar</strong> siempre se integra en la vista <code>index.php</code> del módulo (o en tu layout), nunca desde CSS/JS.</p>

      <form id="docform" class="mt-2" novalidate>
        <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf, ENT_QUOTES,'UTF-8')?>">

        <label for="ruta_base">Ruta base a escanear</label>
        <input id="ruta_base" name="ruta_base" type="text" required value="/console/">
        <div class="small">Ejemplos: <code>/console/</code>, <code>/console/portal/</code>, <code>/console/all</code> para todos los módulos.</div>

        <div class="row">
          <div>
            <label for="solo_modulo">Solo un módulo (opcional)</label>
            <input id="solo_modulo" name="solo_modulo" type="text" placeholder="portal, vallas, facturacion, ...">
          </div>
          <div>
            <label for="schema_version">Versión de esquema SQL</label>
            <select id="schema_version" name="schema_version">
              <option value="8.0" selected>MySQL 8.0</option>
              <option value="5.7">MySQL 5.7</option>
            </select>
          </div>
        </div>

        <div class="sep"></div>

        <div class="row">
          <label class="ck"><input type="checkbox" name="incluir_sql" value="1" checked> Incluir dump SQL (usa DB_HOST/DB_NAME/DB_USER/DB_PASS)</label>
          <label class="ck"><input type="checkbox" name="incluir_codigo" value="1" checked> Incluir índice de archivos y hashes</label>
        </div>

        <div class="sep"></div>

        <div style="display:flex;gap:10px;align-items:center">
          <button class="btn" type="submit">Generar</button>
          <button class="btn secondary" type="button" id="btnCheck">Ver última salida</button>
          <span class="small">Salida: <code>/tools/docs/output/</code></span>
        </div>
      </form>

      <div class="sep"></div>

      <div class="alert">
        <strong>Reglas duras del patrón</strong>:
        <ul style="margin:8px 0 0 18px">
          <li>Vista del módulo: <code>/console/{mod}/index.php</code>. Aquí se <strong>incluye</strong> el <code>/console/asset/sidebar.php</code> o el <code>/_layout.php</code> que ya lo incluye.</li>
          <li>AJAX del módulo: <code>/console/{mod}/ajax/*.php</code> con sesión, rol y CSRF.</li>
          <li>Assets del módulo: <code>/console/asset/css/{mod}/</code> y <code>/console/asset/js/{mod}/</code>. La vista <strong>no</strong> linkea assets directos; el layout lo hace por módulo para evitar duplicados.</li>
          <li>El generador solo documenta. No modifica <code>sidebar.php</code> ni tu <code>db.php</code>.</li>
        </ul>
      </div>

      <div class="sep"></div>

      <div id="result" class="result"></div>
    </div>
  </div>

  <div class="toast" id="toast"></div>

<script>
/* ====== JS embebido (UX + fetch a generar.php) ====== */
(() => {
  'use strict';
  const $ = (s, el=document) => el.querySelector(s);
  const $$ = (s, el=document) => Array.from(el.querySelectorAll(s));
  const on = (el, ev, fn, opts) => el && el.addEventListener(ev, fn, opts);
  const toastWrap = $('#toast');

  function toast(msg, type='ok', t=3500){
    const d = document.createElement('div');
    d.className = `item ${type}`; d.textContent = msg;
    toastWrap.appendChild(d);
    setTimeout(()=>d.remove(), t);
  }
  function serializeForm(form){
    const fd = new FormData(form);
    const data = {};
    for (const [k,v] of fd.entries()) {
      if (data[k] !== undefined) continue;
      data[k] = v;
    }
    if (!fd.has('incluir_sql')) data['incluir_sql'] = '';
    if (!fd.has('incluir_codigo')) data['incluir_codigo'] = '';
    return data;
  }
  async function fetchHTML(url, {method='POST', data={}}={}){
    const body = new URLSearchParams();
    for (const k in data) body.append(k, data[k]);
    const res = await fetch(url, {
      method,
      headers: {'Accept':'text/html','Content-Type':'application/x-www-form-urlencoded'},
      body: body.toString(),
      credentials: 'same-origin'
    });
    if (!res.ok) throw new Error(`HTTP ${res.status} ${res.statusText}`);
    return res.text();
  }

  function showResultLinks(){
    const box = $('#result');
    box.innerHTML = `
      <div class="badge">Salida generada</div>
      <div><a href="/tools/docs/output/docs_bundle.zip" target="_blank">Descargar ZIP</a></div>
      <div><a href="/tools/docs/output/manifest.json" target="_blank">Ver manifest.json</a></div>
      <div><a href="/tools/docs/output/FILES.json" target="_blank">Ver FILES.json</a> <span class="small">(si activaste “Incluir índice de archivos”)</span></div>
      <div><a href="/tools/docs/output/README.md" target="_blank">README.md</a> · <a href="/tools/docs/output/MODULES.md" target="_blank">MODULES.md</a> · <a href="/tools/docs/output/ASSETS.md" target="_blank">ASSETS.md</a></div>
      <div class="small">Si no descarga el ZIP, prueba un downloader en <code>/tools/docs/download.php</code> sirviendo el archivo.</div>
    `;
  }

  on($('#docform'), 'submit', async (e) => {
    e.preventDefault();
    const btn = e.submitter || $('.btn[type="submit"]');
    btn && (btn.disabled = true);
    $('#result').innerHTML = '';
    try{
      const data = serializeForm(e.currentTarget);
      const html = await fetchHTML('/tools/docs/generar.php', { method:'POST', data });
      // Render básico de la respuesta o solo links útiles:
      showResultLinks();
      toast('Documentación generada', 'ok');
    }catch(err){
      toast(err.message || 'Error generando', 'err');
    }finally{
      btn && (btn.disabled = false);
    }
  });

  on($('#btnCheck'), 'click', () => {
    showResultLinks();
    toast('Mostrando última salida', 'ok', 2000);
  });
})();
</script>
</body>
</html>
