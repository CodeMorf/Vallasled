// /console/asset/js/sidebar-mobile.js
(function(){
  'use strict';
  const body = document.body;
  const overlay = document.getElementById('sidebar-overlay');
  const btnMobile = document.getElementById('mobile-menu-button');
  const aside = document.querySelector('aside.sidebar');

  const open  = ()=>{ body.classList.add('sidebar-open'); overlay?.classList.remove('hidden'); };
  const close = ()=>{ body.classList.remove('sidebar-open'); overlay?.classList.add('hidden'); };
  const isMobile = ()=> window.matchMedia('(max-width: 767.98px)').matches;

  btnMobile?.addEventListener('click', e=>{ e.preventDefault(); e.stopPropagation(); open(); });
  overlay?.addEventListener('click', close, {passive:true});
  overlay?.addEventListener('touchstart', close, {passive:true});

  // Cerrar sólo después de pulsar un enlace real; no bloquear navegación
  aside?.addEventListener('click', e=>{
    const a = e.target.closest('a[href]');
    if (!a) return;
    if (a.getAttribute('href') === '#') return;
    if (isMobile()) setTimeout(close, 0);
  });

  // Submenús
  document.querySelectorAll('.submenu-trigger').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      if (body.classList.contains('sidebar-collapsed')) return;
      btn.nextElementSibling?.classList.toggle('hidden');
      btn.classList.toggle('submenu-open');
    });
  });

  // Limpia estado al pasar a desktop
  matchMedia('(min-width:768px)').addEventListener('change', ()=>{ close(); });
})();
