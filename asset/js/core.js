// Core helpers. No inserta sidebar. Solo UX + AJAX seguro.
(() => {
  'use strict';

  const $ = (s, el=document) => el.querySelector(s);
  const $$ = (s, el=document) => Array.from(el.querySelectorAll(s));
  const on = (el, ev, fn, opts) => el && el.addEventListener(ev, fn, opts);
  const debounce = (fn, ms=200) => { let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; };

  // CSRF
  const CSRF = (document.querySelector('meta[name="csrf"]')?.content) || (window.MOD_CFG?.csrf) || '';
  const MOD  = (window.MOD_CFG?.module) || detectModule();
  function detectModule() {
    const u = location.pathname;
    const m = u.match(/^\/console\/([a-z0-9_-]+)\//i);
    return m ? m[1] : 'portal';
  }

  // Sidebar collapse persistente
  const SIDEBAR_KEY = 'console.sidebar.collapsed';
  function applySidebarState() {
    const collapsed = localStorage.getItem(SIDEBAR_KEY) === '1';
    const layout = $('.layout');
    if (!layout) return;
    layout.dataset.collapsed = collapsed ? '1' : '0';
    const toggleBtn = $('[data-sidebar-toggle]');
    if (toggleBtn) toggleBtn.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
  }
  function toggleSidebar() {
    const current = localStorage.getItem(SIDEBAR_KEY) === '1';
    localStorage.setItem(SIDEBAR_KEY, current ? '0' : '1');
    applySidebarState();
  }

  // Toast
  const toastWrap = (() => {
    let el = $('.toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'toast';
      document.body.appendChild(el);
    }
    return el;
  })();
  function toast(msg, type='ok', timeout=3500) {
    const item = document.createElement('div');
    item.className = `item ${type}`;
    item.textContent = msg;
    toastWrap.appendChild(item);
    setTimeout(() => { item.remove(); }, timeout);
  }

  // Fetch JSON con CSRF y manejo de errores
  async function fetchJSON(url, {method='GET', data=null, headers={}} = {}) {
    const opts = { method, headers: {'Accept':'application/json', ...headers}, credentials:'same-origin' };
    if (method !== 'GET' && method !== 'HEAD') {
      if (data && !(data instanceof FormData)) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify({...data, csrf: CSRF});
      } else if (data instanceof FormData) {
        data.append('csrf', CSRF);
        opts.body = data;
      }
    }
    const res = await fetch(url, opts);
    const ct = res.headers.get('content-type') || '';
    if (!res.ok) {
      let detail = res.statusText;
      if (ct.includes('application/json')) {
        const j = await res.json().catch(()=>null);
        detail = (j && (j.error||j.message)) || detail;
      }
      throw new Error(`HTTP ${res.status}: ${detail}`);
    }
    return ct.includes('application/json') ? res.json() : res.text();
  }

  // Formularios con data-ajax
  function bindAjaxForms() {
    $$('form[data-ajax]').forEach(form => {
      on(form, 'submit', async e => {
        e.preventDefault();
        const action = form.getAttribute('action') || location.href;
        const method = (form.getAttribute('method') || 'POST').toUpperCase();
        const fd = new FormData(form);
        try {
          const r = await fetchJSON(action, {method, data: fd});
          if (r && r.ok) {
            toast(r.message || 'OK', 'ok');
            if (r.redirect) location.href = r.redirect;
            if (form.dataset.reset === '1') form.reset();
          } else {
            toast((r && (r.error||r.message)) || 'Error', 'err');
          }
        } catch (err) {
          toast(err.message || 'Error', 'err');
        }
      });
    });
  }

  // Enlaces con data-click="ajax" + data-url
  function bindAjaxLinks() {
    $$('[data-click="ajax"]').forEach(a => {
      on(a, 'click', async e => {
        e.preventDefault();
        const url = a.dataset.url || a.getAttribute('href');
        if (!url) return;
        try { const r = await fetchJSON(url, {method:'POST', data:{}}); toast(r.message||'OK','ok'); }
        catch (err) { toast(err.message||'Error','err'); }
      });
    });
  }

  // Botón del sidebar
  on(document, 'click', e => {
    const t = e.target.closest('[data-sidebar-toggle]');
    if (t) { e.preventDefault(); toggleSidebar(); }
  });

  // Arranque
  document.addEventListener('DOMContentLoaded', () => {
    applySidebarState();
    bindAjaxForms();
    bindAjaxLinks();
    // Exponer utilidades si hace falta
    window.APP = Object.assign(window.APP || {}, { fetchJSON, toast, MOD, CSRF });
  });
})();
