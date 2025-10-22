<?php declare(strict_types=1); ?>
<!-- FAB Carrito -->
<style>
  .cart-fab{position:fixed;right:16px;bottom:16px;z-index:60;background:var(--color-primary,#0ea5e9);color:#fff;border-radius:9999px;width:56px;height:56px;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 20px rgba(0,0,0,.15)}
  .cart-badge{position:absolute;top:-6px;right:-6px;min-width:22px;height:22px;padding:0 6px;background:#ef4444;color:#fff;border-radius:9999px;font:700 12px/22px ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica,Arial}
  .cart-fab svg{width:26px;height:26px}
</style>

<a href="/carritos/index.php" id="cartFab" class="cart-fab" aria-label="Ver carrito">
  <span id="cartBadge" class="cart-badge" hidden>0</span>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
    <path d="M6 6h15l-1.5 9h-13z"></path>
    <circle cx="9" cy="21" r="1.5"></circle>
    <circle cx="18" cy="21" r="1.5"></circle>
    <path d="M6 6l-2-3H2"></path>
  </svg>
</a>

<script>
(function(){
  const $badge = document.getElementById('cartBadge');
  const $legacy = document.getElementById('cart-count'); // posible badge en header viejo

  // Endpoints compatibles
  const API_COUNT_1 = '/api/carritos/api.php?a=count';
  const API_COUNT_2 = '/carritos/?a=count';

  function paint(n){
    const val = String(Math.max(0, parseInt(n||0,10)));
    if ($badge){
      if (val !== '0'){ $badge.textContent = val; $badge.hidden = false; }
      else { $badge.hidden = true; }
    }
    if ($legacy) $legacy.textContent = val;
  }

  async function fetchCount(url){
    const r = await fetch(url, {
      credentials:'include',
      cache:'no-store',
      headers:{'X-Requested-With':'XMLHttpRequest'}
    });
    if(!r.ok) throw new Error('bad');
    const j = await r.json();
    const n = Number(j?.count||0);
    if (Number.isFinite(n)) return n;
    throw new Error('nan');
  }

  async function cartCount(){
    try { paint(await fetchCount(API_COUNT_1)); }
    catch(_){
      try { paint(await fetchCount(API_COUNT_2)); }
      catch(__){ /* no-op */ }
    }
  }

  // Exponer única fuente de la verdad
  window.cartCount = cartCount;

  // Sincronizar después de acciones típicas
  document.addEventListener('click', (e)=>{
    // Botones “Agregar”
    if (e.target.closest('.btn-add, .add-to-cart, [data-add-to-cart], a[href*="/carritos/?a=add"]')){
      setTimeout(cartCount, 120);
      return;
    }
    // Botones “Quitar” o “Vaciar”
    if (e.target.closest('.btn-remove, .remove-from-cart, [data-remove-from-cart], a[href*="/carritos/?a=del"], a[href*="/carritos/?a=clear"]')){
      setTimeout(cartCount, 120);
      return;
    }
    // Formularios que toquen el carrito
    const form = e.target.closest('form');
    if (form && /\/(api\/carritos\/api\.php|carritos\/)/.test(form.action||'')){
      setTimeout(cartCount, 150);
    }
  });

  // Recalcular al volver del historial y al cargar
  window.addEventListener('pageshow', cartCount);
  document.addEventListener('DOMContentLoaded', cartCount);

  // Primer pintado
  cartCount();
})();
</script>
