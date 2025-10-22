// /console/asset/js/facturacion/crear.js
(function () {
  'use strict';
  const qs = s => document.querySelector(s);
  const fmtMoney = n => new Intl.NumberFormat('es-DO',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(n||0));

  const form = qs('#frm-crear-factura');
  if (!form) return;

  const monto = qs('#monto');
  const descuento = qs('#descuento');
  const totalOut = qs('#total-preview');
  const proveedor = qs('#proveedor_id');
  const cliente = qs('#crm_cliente_id');
  const clienteNombre = qs('#cliente_nombre');
  const clienteEmail = qs('#cliente_email');
  const metodo = qs('#metodo_pago');
  const notas = qs('#notas');

  const CFG = window.FACTURAS_CREAR_CFG || {
    endpoint: '/console/facturacion/facturas/ajax/crear.php',
    csrf: (document.querySelector('meta[name="csrf"]')||{}).content || ''
  };

  function recalc() {
    const m = Number(monto.value || 0);
    const d = Number(descuento.value || 0);
    const t = Math.max(0, m - d);
    if (totalOut) totalOut.textContent = '$' + fmtMoney(t);
  }

  [monto, descuento].forEach(x => x && x.addEventListener('input', recalc));
  document.addEventListener('DOMContentLoaded', recalc);

  form.addEventListener('submit', async e => {
    e.preventDefault();

    // Validaciones mínimas
    const m = Number(monto.value || 0);
    const d = Number(descuento.value || 0);
    if (isNaN(m) || m <= 0) return alert('Monto inválido.');
    if (isNaN(d) || d < 0) return alert('Descuento inválido.');
    if (!proveedor?.value) return alert('Proveedor requerido.');
    if (!cliente?.value && !clienteNombre?.value) return alert('Cliente requerido.');

    const payload = {
      proveedor_id: Number(proveedor.value || 0) || null,
      crm_cliente_id: cliente?.value ? Number(cliente.value) : null,
      cliente_nombre: clienteNombre?.value || '',
      cliente_email: clienteEmail?.value || '',
      valla_id: (qs('#valla_id')?.value ? Number(qs('#valla_id').value) : null),
      monto: m,
      descuento: d,
      notas: notas?.value || '',
      metodo_pago: metodo?.value || 'transferencia'
    };

    try {
      const res = await fetch(CFG.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF': CFG.csrf },
        body: JSON.stringify(payload)
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const out = await res.json();
      if (out && out.ok) {
        window.location.href = `/console/facturacion/facturas/ver.php?id=${out.id}`;
      } else {
        alert(out?.error || 'No se pudo crear la factura.');
      }
    } catch (err) {
      alert('Error: ' + err.message);
    }
  });

  // TODO: Autocomplete de clientes y carga de cuentas bancarias cuando tengas endpoints listos.
})();
