<?php
// /console/auth/login/index.php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

/* Redirección inmediata si ya hay sesión abierta */
if (!empty($_SESSION['tipo'])) {
  if ($_SESSION['tipo'] === 'admin') {
    header('Location: /console/portal/'); exit;
  }
  if ($_SESSION['tipo'] === 'staff') {
    header('Location: /console/empleados/index.php'); exit;
  }
  // otros tipos: cliente, etc. Ajusta si usas otra área
  header('Location: /console/portal/'); exit;
}

/* CSRF */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

/* Branding */
$branding = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel','logo_url'=>null];
$title = $branding['title'] ?: 'Panel';
$logo  = $branding['logo_url'] ?: 'https://placehold.co/200x60/111827/FFFFFF?text=Console';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?> · Login</title>
<link rel="icon" href="<?=$fav?>">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/console/asset/css/base.css">
<script>tailwind.config={darkMode:'class'}</script>
<style>
:root{--tone-ok:#10b981;--tone-err:#ef4444}
.card{background:var(--card-bg);border:1px solid var(--border-color)}
.btn{display:inline-flex;align-items:center;justify-content:center;border-radius:.5rem;padding:.6rem 1rem;font-weight:600}
.btn-indigo{background:#4f46e5;color:#fff}.btn-indigo:hover{background:#4338ca}
.btn-ghost{border:1px solid var(--border-color)}
.muted{color:var(--text-secondary)}
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
  <div class="card rounded-2xl p-6 w-full max-w-md">
    <div class="flex justify-center mb-6"><img src="<?=htmlspecialchars($logo)?>" class="h-12" alt="Logo"></div>
    <h1 class="text-2xl font-bold text-center mb-2">Acceso Admin/Staff</h1>
    <p class="text-center text-sm muted mb-4">Email/contraseña o Passkey.</p>

    <form id="loginForm" class="space-y-4" autocomplete="off">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>">
      <div>
        <label class="block text-sm mb-1">Email</label>
        <input class="w-full px-3 py-2 rounded border border-[var(--border-color)] bg-[var(--main-bg)]" type="email" name="email" required maxlength="150" autocomplete="username" inputmode="email">
      </div>
      <div>
        <label class="block text-sm mb-1">Contraseña</label>
        <input class="w-full px-3 py-2 rounded border border-[var(--border-color)] bg-[var(--main-bg)]" type="password" name="password" required minlength="8" maxlength="128" autocomplete="current-password">
      </div>

      <div class="text-sm flex items-center gap-3">
        <span class="muted">¿Activar huella en este dispositivo?</span>
        <button id="btn-activate-yes" type="button" class="btn btn-indigo">Sí</button>
        <button id="btn-activate-skip" type="button" class="btn btn-ghost">Omitir</button>
      </div>

      <button class="w-full btn btn-indigo">Entrar</button>
      <p id="msg" class="text-sm" style="color:var(--tone-err)"></p>
    </form>

    <div class="mt-6 border-t border-[var(--border-color)] pt-4">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm font-semibold mb-1">Entrar con huella</div>
          <div class="text-xs muted">Requiere passkey registrada.</div>
        </div>
        <button id="btn-passkey" class="btn btn-ghost">Usar huella</button>
      </div>
      <p id="fp-msg" class="text-sm mt-2"></p>
    </div>
  </div>

<script>
const PASSKEY_LOGIN_ENABLED = true;

/* Sonidos mínimos */
function tone(freq=880, ms=120, type='sine'){
  try{
    const ac = new (window.AudioContext||window.webkitAudioContext)();
    const o=ac.createOscillator(), g=ac.createGain();
    o.type=type; o.frequency.value=freq; o.connect(g); g.connect(ac.destination);
    g.gain.setValueAtTime(0.0001, ac.currentTime);
    g.gain.exponentialRampToValueAtTime(0.2, ac.currentTime+0.02);
    o.start(); g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime + ms/1000);
    o.stop(ac.currentTime + ms/1000 + 0.02);
  }catch(e){}
}
const okBeep=()=>tone(880,140,'triangle');
const errBeep=()=>tone(220,200,'square');

/* Guard anti-concurrencia */
const WEBA = {pending:false, ctrl:null};
function guardStart(){ if(WEBA.pending) return false; WEBA.ctrl=new AbortController(); WEBA.pending=true; return true; }
function guardEnd(){ WEBA.ctrl=null; WEBA.pending=false; }

/* Preferencia de alta passkey post-login */
const PREF_KEY = 'webauthn_activate_after_login';
const setActivatePref = v => { try{ localStorage.setItem(PREF_KEY, v?'1':'0'); }catch(e){} };
const getActivatePref = () => { try{ return localStorage.getItem(PREF_KEY)==='1'; }catch(e){ return false; } };
document.getElementById('btn-activate-yes').onclick = ()=>setActivatePref(true);
document.getElementById('btn-activate-skip').onclick = ()=>setActivatePref(false);

/* Helpers */
const b64u = ab => btoa(String.fromCharCode(...new Uint8Array(ab))).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
const toAB = s => Uint8Array.from(atob(s.replace(/-/g,'+').replace(/_/g,'/')), c=>c.charCodeAt(0));
async function jsonFetch(url, opt={}){
  const r = await fetch(url, opt);
  const t = await r.text();
  try{ return JSON.parse(t); }catch{ throw new Error(t.slice(0,200)); }
}
function resolveRedirect(payload){
  // Si el backend envía redirect, úsalo. Si envía tipo, decide.
  if (payload && typeof payload.redirect === 'string' && payload.redirect) return payload.redirect;
  const tipo = (payload && payload.tipo) ? String(payload.tipo) : '';
  if (tipo === 'staff')  return '/console/empleados/index.php';
  if (tipo === 'admin')  return '/console/portal/';
  return '/console/portal/'; // fallback
}

/* Login por passkey */
document.getElementById('btn-passkey').addEventListener('click', async ()=>{
  const out = document.getElementById('fp-msg'); out.textContent='';
  if (!PASSKEY_LOGIN_ENABLED){ out.textContent='Passkey deshabilitado.'; errBeep(); return; }
  if (WEBA.ctrl){ try{ WEBA.ctrl.abort(); }catch(e){} await new Promise(r=>setTimeout(r,50)); }
  if (!guardStart()) return;

  try{
    const j = await jsonFetch('/console/ajax/auth/webauthn_begin_login.php', {credentials:'include'});
    if (!j.ok) throw new Error(j.error||'begin_login');

    const cred = await navigator.credentials.get({
      publicKey: {
        challenge: toAB(j.options.challenge),
        rpId: j.rpId,
        userVerification: 'required'
      },
      signal: WEBA.ctrl.signal
    });
    if (!cred) throw new Error('Cancelado');

    const payload = {
      id: cred.id,
      rawId: b64u(cred.rawId),
      type: cred.type,
      response: {
        clientDataJSON: b64u(cred.response.clientDataJSON),
        authenticatorData: b64u(cred.response.authenticatorData),
        signature: b64u(cred.response.signature),
        userHandle: cred.response.userHandle ? b64u(cred.response.userHandle) : null
      }
    };

    const jj = await jsonFetch('/console/ajax/auth/webauthn_finish_login.php', {
      method:'POST', credentials:'include', headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    if (jj.ok){
      okBeep();
      const url = resolveRedirect(jj);
      location.href = url;
    } else { out.textContent = jj.error || 'Error'; errBeep(); }
  }catch(e){ out.textContent = String(e.message||e); errBeep(); }
  finally{ guardEnd(); }
});

/* Wizard post-login: alta + 2 lecturas */
async function runEnrollWizard(){
  try{
    const bj = await jsonFetch('/console/ajax/auth/webauthn_begin_register.php',{credentials:'include'});
    if (!bj.ok) throw new Error(bj.error||'begin_register');
    const pub = bj.options;

    const cred = await navigator.credentials.create({
      publicKey:{
        rp: pub.rp,
        user:{ id: toAB(pub.user.id), name: pub.user.name, displayName: pub.user.displayName },
        challenge: toAB(pub.challenge),
        pubKeyCredParams: pub.pubKeyCredParams,
        authenticatorSelection: pub.authenticatorSelection,
        attestation: pub.attestation,
        timeout: pub.timeout
      }
    });
    const regPayload = {
      id: cred.id,
      rawId: b64u(cred.rawId),
      type: cred.type,
      response: {
        clientDataJSON: b64u(cred.response.clientDataJSON),
        attestationObject: b64u(cred.response.attestationObject)
      }
    };
    const fj = await jsonFetch('/console/ajax/auth/webauthn_finish_register.php', {
      method:'POST', credentials:'include', headers:{'Content-Type':'application/json'},
      body: JSON.stringify(regPayload)
    });
    if (!fj.ok) throw new Error(fj.error||'finish_register');

    // dos lecturas de verificación
    for (let i=0;i<2;i++){
      const j = await jsonFetch('/console/ajax/auth/webauthn_begin_login.php',{credentials:'include'});
      const asr = await navigator.credentials.get({
        publicKey:{ challenge: toAB(j.options.challenge), rpId:j.rpId, userVerification:'required' }
      });
      const payload = {
        id: asr.id,
        rawId: b64u(asr.rawId),
        type: asr.type,
        response: {
          clientDataJSON: b64u(asr.response.clientDataJSON),
          authenticatorData: b64u(asr.response.authenticatorData),
          signature: b64u(asr.response.signature),
          userHandle: asr.response.userHandle ? b64u(asr.response.userHandle) : null
        }
      };
      await jsonFetch('/console/ajax/auth/webauthn_finish_login.php?probe=1',{
        method:'POST', credentials:'include', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });
    }
    okBeep();
  }catch(e){ errBeep(); console.error(e); }
}

/* Submit contraseña */
document.getElementById('loginForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const msg = document.getElementById('msg'); msg.textContent='';
  try{
    const fd = new FormData(e.currentTarget);
    const r = await fetch('/console/ajax/auth/login.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
      body: new URLSearchParams(fd)
    });
    const j = await r.json();
    if (j.ok) {
      if (getActivatePref()) { await runEnrollWizard(); }
      const url = resolveRedirect(j);
      location.href = url;
    } else { msg.textContent = j.error || 'Error'; errBeep(); }
  }catch(err){ msg.textContent = 'Error de red'; errBeep(); }
});
</script>
</body></html>
