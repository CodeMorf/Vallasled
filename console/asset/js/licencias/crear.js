(function () {
  'use strict';

  const CFG = window.LIC_CREATE || { csrf:'', endpoints:{} };
  const CSRF = CFG.csrf || (document.querySelector('meta[name="csrf"]')?.content || '');

  // Tema
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('theme-toggle');
    const moon = document.getElementById('theme-toggle-dark-icon');
    const sun  = document.getElementById('theme-toggle-light-icon');
    const apply = (t) => {
      const dark = t === 'dark';
      document.documentElement.classList.toggle('dark', dark);
      moon?.classList.toggle('hidden', !dark);
      sun?.classList.toggle('hidden', dark);
    };
    const saved = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    apply(saved);
    btn?.addEventListener('click', e => { e.preventDefault(); const next = document.documentElement.classList.contains('dark') ? 'light':'dark'; localStorage.setItem('theme', next); apply(next); });
  });

  // DOM
  const $prov    = document.getElementById('lc-prov');
  const $valla   = document.getElementById('lc-valla');
  const $cliente = document.getElementById('lc-cliente');
  const $period  = document.getElementById('lc-period');
  const $emi     = document.getElementById('lc-emision');
  const $venc    = document.getElementById('lc-venc');
  const $form    = document.getElementById('form-lic');

  // Utils
  async function jget(url) {
    const r = await fetch(url, {
      headers: {'Accept':'application/json','X-CSRF': CSRF, 'X-Requested-With':'XMLHttpRequest'},
      credentials: 'same-origin'
    });
    if (!r.ok) throw new Error('HTTP '+r.status);
    return r.json();
  }
  function fill($el, items, placeholder) {
    if (!$el) return;
    $el.innerHTML = '';
    if (placeholder !== null) {
      const o = document.createElement('option'); o.value = ''; o.textContent = placeholder ?? 'Seleccione…'; $el.appendChild(o);
    }
    for (const it of items || []) {
      const o = document.createElement('option');
      o.value = String(it.id ?? it.value ?? '');
      o.textContent = String(it.nombre ?? it.text ?? '');
      $el.appendChild(o);
    }
  }
  function recomputeVenc() {
    const d = $emi?.valueAsDate;
    if (!d || !$venc) return;
    const per = $period?.value || 'anual';
    const x = new Date(d);
    if (per === 'anual') x.setFullYear(x.getFullYear()+1);
    else if (per === 'mensual') x.setMonth(x.getMonth()+1);
    const y = x.toISOString().slice(0,10);
    if ($venc.value === '' || $venc.value < y) $venc.value = y;
  }

  // Cargar combos
  async function loadInit() {
    try {
      const j = await jget(CFG.endpoints.opciones + '?init=1');
      fill($prov, j.proveedores || [], 'Seleccione…');
      fill($cliente, j.clientes || [], 'Opcional');
    } catch (e) {
      console.warn('opciones init', e);
      fill($prov, [], 'Error al cargar');
      fill($cliente, [], 'Error al cargar');
    }
  }
  $prov?.addEventListener('change', async () => {
    const pid = $prov.value || '';
    fill($valla, [], pid ? 'Cargando…' : 'Seleccione proveedor primero');
    if (!pid) return;
    try {
      const j = await jget(CFG.endpoints.opciones + '?list=vallas&proveedor_id=' + encodeURIComponent(pid));
      fill($valla, j.vallas || [], 'Opcional');
    } catch (e) {
      console.warn('vallas proveedor', e);
      fill($valla, [], 'Error al cargar');
    }
  });

  // Fechas auto
  $emi?.addEventListener('change', recomputeVenc);
  $period?.addEventListener('change', recomputeVenc);

  // Guardar
  $form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData($form);
    try {
      const r = await fetch(CFG.endpoints.guardar, {
        method: 'POST',
        headers: {'X-CSRF': CSRF},
        body: fd,
        credentials: 'same-origin'
      });
      const j = await r.json();
      if (j?.ok) {
        window.location.href = '/console/licencias/?created=1';
      } else {
        alert(j?.error || 'No se pudo guardar');
      }
    } catch (err) {
      console.error(err);
      alert('Error de red');
    }
  });

  // Init
  loadInit();
})();
