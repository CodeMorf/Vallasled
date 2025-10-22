<?php
declare(strict_types=1);

// --- FORZAR LOGOUT DE CUALQUIER SESIÓN ACTIVA ---
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['uid'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    session_start();
}
// ------------------------------------------------

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

$branding = load_branding($conn);
$title = $branding['title'] ?: 'Panel';
$logo  = $branding['logo_url'] ?: 'https://placehold.co/150x50/111827/FFFFFF?text=Vallas+Admin';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?> · Registro</title>
<link rel="icon" href="<?=$fav?>"><meta name="theme-color" content="#111827"/>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/console/asset/css/base.css"><script>tailwind.config={darkMode:'class'}</script>
<style>.card{background:var(--card-bg);border:1px solid var(--border-color)}.inp{width:100%;padding:.65rem .8rem;border:1px solid var(--border-color);border-radius:.65rem;background:var(--main-bg)}.btn{padding:.65rem 1rem;border-radius:.65rem}</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
<div class="card rounded-2xl p-6 w-full max-w-xl">
  <div class="flex flex-col items-center gap-3 mb-4">
    <img src="<?=htmlspecialchars($logo)?>" class="h-10 object-contain" alt="Logo">
    <span class="px-3 py-1 text-xs bg-gray-900 text-white rounded"><?=htmlspecialchars($title)?></span>
  </div>
  <h1 class="text-2xl font-bold mb-2">Registro de Admin</h1>
  <p class="text-[var(--text-secondary)] mb-4">Crea un usuario admin. No hay límite.</p>
  <form id="regForm" class="space-y-4" autocomplete="off">
    <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>">
    <div>
      <label class="block text-sm mb-1">Email del Admin</label>
      <input class="inp" type="email" name="email" required maxlength="150" inputmode="email" autocomplete="username">
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm mb-1">Contraseña</label>
        <div class="relative">
          <input class="inp pr-10" id="p1" type="password" name="password" required minlength="8" maxlength="128" autocomplete="new-password">
          <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--text-secondary)]" onclick="tg('p1')"><i class="fa fa-eye"></i></button>
        </div>
      </div>
      <div>
        <label class="block text-sm mb-1">Repetir Contraseña</label>
        <div class="relative">
          <input class="inp pr-10" id="p2" type="password" name="password2" required minlength="8" maxlength="128" autocomplete="new-password">
          <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--text-secondary)]" onclick="tg('p2')"><i class="fa fa-eye"></i></button>
        </div>
      </div>
    </div>
    <div class="flex items-start gap-2 text-xs text-[var(--text-secondary)]"><i class="fa fa-shield-alt mt-1"></i>
      <span>Se guardará en <code>usuarios</code> y podrás iniciar sesión.</span>
    </div>
    <button class="btn w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Registrar</button>
    <p id="msg" class="text-sm mt-2"></p>
  </form>
</div>
<script>
function tg(id){const i=document.getElementById(id); if(i) i.type=i.type==='password'?'text':'password';}
document.getElementById('regForm')?.addEventListener('submit',async e=>{
  e.preventDefault(); const f=e.currentTarget, m=document.getElementById('msg');
  if(f.password.value!==f.password2.value){m.textContent='Las contraseñas no coinciden.';m.className='text-sm text-red-500';return;}
  const body=new URLSearchParams(new FormData(f));
  const r=await fetch('/console/ajax/auth/register.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body});
  let j={}; try{j=await r.json();}catch(_){}
  if(j.ok){ location.href=j.redirect||'/console/portal/index.php'; } else { m.textContent=j.error||'Error'; m.className='text-sm text-red-500'; }
});
</script>
</body>
</html>
