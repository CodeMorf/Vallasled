/* Carritos · app.js */
(function () {
  'use strict';

  // ===== Utils =====
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  function formData(obj) { const fd = new FormData(); for (const k in obj) fd.set(k, obj[k]); return fd; }

  // Endpoints compatibles
  const API_CART = '/carritos/';               // index.php?a=...
  const API_ALT  = '/api/carritos/api.php';    // api.php?a=...

  async function req(url, data) {
    const opt = data
      ? { method: 'POST', body: formData(data), headers: { 'X-Requested-With': 'XMLHttpRequest' } }
      : { headers: { 'X-Requested-With': 'XMLHttpRequest' } };
    const r = await fetch(url, opt);
    if (!r.ok) throw new Error('net');
    const ct = r.headers.get('content-type') || '';
    return ct.includes('application/json') ? r.json() : {};
  }

  async function api(action, payload) {
    // Intenta primero /carritos/?a=..., si falla usa /api/carritos/api.php?a=...
    try { return await req(`${API_CART}?a=${encodeURIComponent(action)}`, payload); }
    catch (_) {
      try { return await req(`${API_ALT}?a=${encodeURIComponent(action)}`, payload); }
      catch (__){ return {}; }
    }
  }

  // ===== Contador unificado =====
  async function refreshCount() {
    try {
      if (typeof window.cartCount === 'function') { await window.cartCount(); return; }
      const j = await api('count');
      const n = Number(j?.count || 0);
      const el = document.getElementById('cart-count'); // badge legado en header
      if (el) el.textContent = String(Number.isFinite(n) ? n : 0);
    } catch (_) {}
  }

  // ===== Cantidades por fila =====
  function bindRows() {
    qa('.cart-row').forEach(row => {
      const id = row.getAttribute('data-id') || row.querySelector('[href*="id="]')?.href.match(/id=(\d+)/)?.[1];
      if (!id) return;

      const qval  = row.querySelector('.qval');
      const minus = row.querySelector('.qminus');
      const plus  = row.querySelector('.qplus');

      const clamp = n => { n = parseInt(String(n || '1'), 10); return Number.isFinite(n) && n > 0 ? n : 1; };

      async function setQty(n) {
        n = clamp(n);
        if (qval) qval.value = n;
        await api('set', { id, qty: n });
        location.reload();
      }

      minus?.addEventListener('click', () => setQty(clamp((qval?.value || 1)) - 1));
      plus ?.addEventListener('click', () => setQty(clamp((qval?.value || 1)) + 1));
      qval ?.addEventListener('change',   () => setQty(clamp((qval?.value || 1))));
    });
  }

  // ===== Vaciar y Quitar con AJAX + fallback =====
  function bindActions() {
    // Vaciar
    qa('.js-clear-cart, a[href*="/carritos/?a=clear"]').forEach(a => {
      a.addEventListener('click', ev => {
        ev.preventDefault();
        api('clear').then(() => location.reload()).catch(() => { window.location.href = a.href; });
      });
    });

    // Quitar
    qa('.js-del-item, a[href*="/carritos/?a=del"]').forEach(a => {
      a.addEventListener('click', ev => {
        ev.preventDefault();
        const id = a.getAttribute('data-id') || a.href.match(/[?&]id=(\d+)/)?.[1];
        if (!id) { window.location.href = a.href; return; }
        api('del', { id }).then(() => location.reload()).catch(() => { window.location.href = a.href; });
      });
    });
  }

  // ===== Integración con botones genéricos “Agregar/Quitar” fuera del carrito =====
  document.addEventListener('click', e => {
    if (e.target.closest('.btn-add, .add-to-cart, [data-add-to-cart], a[href*="/carritos/?a=add"]')) {
      setTimeout(refreshCount, 120);
    }
    if (e.target.closest('.btn-remove, .remove-from-cart, [data-remove-from-cart]')) {
      setTimeout(refreshCount, 120);
    }
  });

  // Recalcular al volver del historial y al cargar
  window.addEventListener('pageshow', refreshCount);
  document.addEventListener('DOMContentLoaded', () => {
    bindRows();
    bindActions();
    refreshCount();
  });
})();
