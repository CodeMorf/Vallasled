/* eslint-disable */
(function () {
  "use strict";

  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];
  const cfg = window.REPORTES_CFG || {};
  const CSRF = document.querySelector('meta[name="csrf"]')?.getAttribute('content') || '';

  // UI
  const selTipo = $('#report-type');
  const inpDesde = $('#date-from');
  const inpHasta = $('#date-to');
  const btnGen = $('#generate-report');
  const btnExp = $('#export-csv');
  const title = $('#report-title');
  const tbl = $('#report-table');
  const thead = tbl?.querySelector('thead');
  const tbody = tbl?.querySelector('tbody');
  const placeholder = $('#report-placeholder');
  const pager = $('#report-pager');
  const pgPrev = $('#pg-prev');
  const pgNext = $('#pg-next');
  const pgInfo = $('#pg-info');

  const nf = new Intl.NumberFormat('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const fmtDate = (v) => v ? new Date(v).toLocaleDateString('es-DO') : '—';

  // Estado
  let state = {
    tipo: 'facturas',
    desde: '',
    hasta: '',
    limit: (cfg.page && cfg.page.limit) || 200,
    offset: 0,
    total: 0,
    columns: [],
    rows: []
  };

  // Definiciones por tipo
  const REPORTS = {
    facturas: {
      title: 'Reporte de Facturas',
      dateField: 'fecha_generacion',
      columns: [
        { key: 'id', title: 'ID' },
        { key: 'valla_id', title: 'ID Valla' },
        { key: 'cliente_nombre', title: 'Cliente' },
        { key: 'monto', title: 'Monto' },
        { key: 'comision_monto', title: 'Comisión' },
        { key: 'estado', title: 'Estado' },
        { key: 'fecha_generacion', title: 'Fecha' }
      ]
    },
    vallas: {
      title: 'Reporte de Vallas',
      dateField: 'fecha_creacion',
      columns: [
        { key: 'id', title: 'ID Valla' },
        { key: 'nombre', title: 'Nombre' },
        { key: 'tipo', title: 'Tipo' },
        { key: 'provincia_id', title: 'ID Provincia' },
        { key: 'proveedor_id', title: 'ID Proveedor' },
        { key: 'precio', title: 'Precio' },
        { key: 'estado_valla', title: 'Estado' },
        { key: 'fecha_creacion', title: 'Creada' }
      ]
    },
    licencias: {
      title: 'Reporte de Licencias',
      dateField: 'fecha_emision',
      columns: [
        { key: 'id', title: 'ID' },
        { key: 'titulo', title: 'Título' },
        { key: 'proveedor_id', title: 'ID Proveedor' },
        { key: 'valla_id', title: 'ID Valla' },
        { key: 'estado', title: 'Estado' },
        { key: 'periodicidad', title: 'Periodicidad' },
        { key: 'fecha_emision', title: 'Emisión' },
        { key: 'fecha_vencimiento', title: 'Vencimiento' }
      ]
    },
    clientes: {
      title: 'Reporte de Clientes CRM',
      dateField: 'creado',
      columns: [
        { key: 'id', title: 'ID' },
        { key: 'nombre', title: 'Nombre' },
        { key: 'empresa', title: 'Empresa' },
        { key: 'proveedor_id', title: 'ID Proveedor' },
        { key: 'creado', title: 'Creado' }
      ]
    },
    proveedores: {
      title: 'Reporte de Proveedores',
      dateField: 'creado',
      columns: [
        { key: 'id', title: 'ID' },
        { key: 'nombre', title: 'Nombre' },
        { key: 'contacto', title: 'Contacto' },
        { key: 'estado', title: 'Estado' },
        { key: 'creado', title: 'Creado' }
      ]
    }
  };

  function setLoading(on) {
    btnGen?.toggleAttribute('disabled', on);
    btnExp?.toggleAttribute('disabled', on || !state.rows.length);
  }

  function renderHead(cols) {
    thead.innerHTML = '';
    const tr = document.createElement('tr');
    tr.className = 'border-b border-[var(--border-color)] text-sm font-semibold text-[var(--text-secondary)]';
    cols.forEach(c => {
      const th = document.createElement('th');
      th.className = 'px-4 py-3';
      th.textContent = c.title;
      tr.appendChild(th);
    });
    thead.appendChild(tr);
  }

  function pill(htmlClass, text) {
    const span = document.createElement('span');
    span.className = `px-3 py-1 text-xs font-semibold rounded-full ${htmlClass}`;
    span.textContent = text;
    return span;
  }

  function renderBody(cols, rows) {
    tbody.innerHTML = '';
    rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.className = 'hover:bg-[var(--main-bg)] border-b border-[var(--border-color)] text-sm';
      cols.forEach(c => {
        const td = document.createElement('td');
        td.className = 'px-4 py-3';

        let val = r[c.key];
        if (val === null || val === undefined) val = '—';

        // formateos
        if (['monto','precio','comision_monto','total'].includes(c.key) && typeof val === 'number') {
          td.textContent = nf.format(val);
        } else if (/fecha/i.test(c.key)) {
          td.textContent = fmtDate(val);
        } else if (c.key === 'estado' && state.tipo === 'facturas') {
          const isOk = String(val) === 'pagado';
          td.appendChild(pill(isOk
            ? 'text-green-800 bg-green-200 dark:bg-green-500/20 dark:text-green-300'
            : 'text-amber-800 bg-amber-200 dark:bg-amber-500/20 dark:text-amber-300', String(val)));
        } else if ((c.key === 'estado_valla') || (c.key === 'estado' && state.tipo === 'proveedores')) {
          const isOk = (String(val) === 'activa') || (String(val) === '1');
          td.appendChild(pill(isOk
            ? 'text-green-800 bg-green-200 dark:bg-green-500/20 dark:text-green-300'
            : 'text-red-800 bg-red-200 dark:bg-red-500/20 dark:text-red-300', String(val) === '1' ? 'Activo' : String(val) === '0' ? 'Inactivo' : String(val)));
        } else {
          td.textContent = val;
        }

        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
  }

  function renderPager() {
    if (state.total <= state.limit) {
      pager.classList.add('hidden');
      return;
    }
    pager.classList.remove('hidden');
    const from = state.total ? state.offset + 1 : 0;
    const to = Math.min(state.offset + state.limit, state.total);
    pgInfo.textContent = `${from}–${to} de ${state.total}`;
    pgPrev.toggleAttribute('disabled', state.offset <= 0);
    pgNext.toggleAttribute('disabled', state.offset + state.limit >= state.total);
  }

  async function fetchJSON(url, data) {
    const body = new URLSearchParams({ csrf: CSRF, ...data });
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  async function cargarKPIs() {
    try {
      const k = await fetchJSON(cfg.endpoints.resumen, {
        tipo: state.tipo, desde: state.desde, hasta: state.hasta
      });
      $('#kpi-reg').textContent = k.registros ?? '—';
      $('#kpi-ok').textContent = k.ok ?? '—';
      $('#kpi-pend').textContent = k.pendientes ?? '—';
      $('#kpi-total').textContent = (typeof k.total_monto === 'number') ? nf.format(k.total_monto) : (k.total ?? '—');
    } catch { /* noop */ }
  }

  async function generar(offset = 0) {
    state.tipo = selTipo.value;
    state.desde = inpDesde.value || '';
    state.hasta = inpHasta.value || '';
    state.offset = Math.max(0, offset);

    const def = REPORTS[state.tipo];
    title.textContent = def.title;
    setLoading(true);
    try {
      const data = await fetchJSON(cfg.endpoints.generar, {
        tipo: state.tipo,
        desde: state.desde,
        hasta: state.hasta,
        limit: String(state.limit),
        offset: String(state.offset)
      });

      state.columns = data.columns || def.columns;
      state.rows = data.rows || [];
      state.total = Number(data.total || state.rows.length || 0);

      renderHead(state.columns);
      renderBody(state.columns, state.rows);

      placeholder.classList.add('hidden');
      tbl.classList.remove('hidden');

      renderPager();
      btnExp.removeAttribute('disabled');

      // KPIs
      cargarKPIs();
    } catch (e) {
      console.error(e);
      alert('Error al generar el reporte.');
    } finally {
      setLoading(false);
    }
  }

  // Eventos
  btnGen?.addEventListener('click', (e) => { e.preventDefault(); generar(0); });
  pgPrev?.addEventListener('click', (e) => { e.preventDefault(); generar(Math.max(0, state.offset - state.limit)); });
  pgNext?.addEventListener('click', (e) => { e.preventDefault(); generar(state.offset + state.limit); });

  btnExp?.addEventListener('click', (e) => {
    e.preventDefault();
    if (!state.columns.length) return;
    const q = new URLSearchParams({
      csrf: CSRF,
      tipo: state.tipo,
      desde: state.desde,
      hasta: state.hasta
    });
    // descarga directa
    const url = `${cfg.endpoints.exportar}?${q.toString()}`;
    window.location.href = url;
  });

  // init opcional: no autogenera para no cargar de más
})();
