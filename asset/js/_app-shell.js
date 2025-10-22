// /console/asset/js/app-shell.js
(function () {
  'use strict';

  const body = document.body;
  const overlay = document.getElementById('sidebar-overlay');
  const lsKey = 'sidebarCollapsed';
  const tBtn = document.getElementById('theme-toggle');
  const dI = document.getElementById('theme-toggle-dark-icon');
  const lI = document.getElementById('theme-toggle-light-icon');
  const metaTC = document.getElementById('meta-theme-color');

  // ---- Utilidades de viewport móvil (iOS 100vh fix)
  function setVH() {
    const vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--vh', `${vh}px`);
  }
  setVH();
  window.addEventListener('resize', setVH);

  // Evita scroll horizontal accidental
  document.documentElement.style.overflowX = 'hidden';
  body.style.overflowX = 'hidden';
  // Mejora gestos: solo pan vertical
  document.documentElement.style.touchAction = 'pan-y';

  // ---- Sidebar: open/close/toggle unificado
  const open = () => { body.classList.add('sidebar-open'); overlay?.classList.remove('hidden'); };
  const close = () => { body.classList.remove('sidebar-open'); overlay?.classList.add('hidden'); };
  const toggle = () => (body.classList.contains('sidebar-open') ? close() : open());

  try { if (localStorage.getItem(lsKey) === '1') body.classList.add('sidebar-collapsed'); } catch {}

  document.getElementById('mobile-menu-button')?.addEventListener('click', e => {
    e.preventDefault(); e.stopPropagation(); toggle();
  });

  overlay?.addEventListener('click', close);
  overlay?.addEventListener('touchstart', close, { passive: true });

  document.getElementById('sidebar-toggle-desktop')?.addEventListener('click', () => {
    body.classList.toggle('sidebar-collapsed');
    try { localStorage.setItem(lsKey, body.classList.contains('sidebar-collapsed') ? '1' : '0'); } catch {}
    setTimeout(() => window.dispatchEvent(new Event('resize')), 120);
  });

  // Cierra el sidebar al navegar en móvil
  document.querySelector('.sidebar')?.addEventListener('click', e => {
    const link = e.target.closest('a[href]');
    if (!link) return;
    if (window.matchMedia('(max-width: 767.98px)').matches) close();
  });

  // Submenús: no abrir si está colapsado en desktop
  document.querySelectorAll('.submenu-trigger').forEach(btn => {
    btn.addEventListener('click', () => {
      if (body.classList.contains('sidebar-collapsed')) return;
      btn.nextElementSibling?.classList.toggle('hidden');
      btn.classList.toggle('submenu-open');
    });
  });

  // Escape cierra sidebar en móvil
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') close();
  });

  // Reset móvil→desktop
  matchMedia('(min-width:768px)').addEventListener('change', () => {
    overlay?.classList.add('hidden');
    body.classList.remove('sidebar-open');
  });

  // ---- Tema + meta theme-color
  function applyTheme(t) {
    const dark = t === 'dark';
    document.documentElement.classList.toggle('dark', dark);
    dI?.classList.toggle('hidden', !dark);
    lI?.classList.toggle('hidden', dark);
    metaTC?.setAttribute('content', dark ? '#111827' : '#ffffff');
    try { localStorage.setItem('theme', t); } catch {}
  }
  applyTheme(localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
  tBtn?.addEventListener('click', () => {
    applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
  });

  // ---- Modal: bloqueo de scroll del body
  const modal = document.getElementById('account-modal');
  const modalContainer = document.getElementById('account-modal-container');
  const addModalHooks = () => {
    if (!modal || !modalContainer) return;
    const openObs = new MutationObserver(() => {
      const isOpen = !modal.classList.contains('hidden') && !modal.classList.contains('opacity-0');
      body.classList.toggle('modal-open', isOpen);
    });
    openObs.observe(modal, { attributes: true, attributeFilter: ['class'] });
  };
  addModalHooks();
})();
