// /console/asset/js/proveedores/index.js
(function () {
  "use strict";

  // ---------- Utils ----------
  const $ = (s, ctx = document) => ctx.querySelector(s);
  const $$ = (s, ctx = document) => Array.from(ctx.querySelectorAll(s));
  const cfg = window.PROVEEDORES_CFG || {};

  const csrf =
    document.querySelector('meta[name="csrf"]')?.content ||
    cfg.csrf ||
    "";

  // Enviar siempre JSON explícito + varios headers CSRF por compatibilidad
  const baseHeaders = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
    "X-CSRF": csrf,
    "X_CSRF": csrf,
    "X-CSRF-Token": csrf
  };

  const fetchJSON = async (url, opts = {}) => {
    const o = {
      method: "GET",
      headers: { ...baseHeaders, ...(opts.headers || {}) },
      credentials: "same-origin",
      ...opts
    };
    if (o.body && !(o.body instanceof FormData)) {
      o.headers["Content-Type"] = "application/json; charset=utf-8";
    }
    const r = await fetch(url, o);
    let data = null;
    try { data = await r.json(); } catch {}
    if (!r.ok) {
      const msg = data?.error
        ? `${data?.msg || "Error"}: ${data.error}`
        : (data?.msg || `HTTP ${r.status}`);
      throw new Error(msg);
    }
    return data ?? {};
  };

  const debounce = (fn, ms = 250) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  // ---------- Theme (sin tocar sidebar) ----------
  const applyTheme = (t) => {
    document.documentElement.classList.toggle("dark", t === "dark");
    $("#theme-toggle-dark-icon")?.classList.toggle("hidden", t !== "dark");
    $("#theme-toggle-light-icon")?.classList.toggle("hidden", t === "dark");
  };
  const saved = localStorage.getItem("theme") || (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
  applyTheme(saved);
  $("#theme-toggle")?.addEventListener("click", () => {
    const n = document.documentElement.classList.contains("dark") ? "light" : "dark";
    localStorage.setItem("theme", n);
    applyTheme(n);
  });

  // ---------- Estado/UI ----------
  let rows = [];
  const tbody  = $("#providers-tbody");
  const empty  = $("#empty-state");
  const search = $("#search-filter");

  // ---------- Modal ----------
  const modal = $("#provider-modal");
  const openModal = (title) => {
    $("#modal-title").textContent = title || "";
    modal.classList.add("is-open");
  };
  const closeModal = () => { modal.classList.remove("is-open"); };

  $("#close-modal-btn")?.addEventListener("click", closeModal);
  $("#cancel-btn")?.addEventListener("click", closeModal);
  modal?.addEventListener("click", (e) => { if (e.target === modal) closeModal(); });

  // ---------- Planes ----------
  async function loadPlanes() {
    const data = await fetchJSON(cfg.planes);
    const items = data.items || [];
    const sel = $("#plan_id");
    sel.innerHTML =
      `<option value="">Sin plan</option>` +
      items.map(p => `<option value="${p.id}">${p.nombre}</option>`).join("");
  }

  // ---------- Render tabla ----------
  function render() {
    const q = (search.value || "").toLowerCase();
    const list = rows.filter(p =>
      (p.nombre || "").toLowerCase().includes(q) ||
      (p.contacto || "").toLowerCase().includes(q) ||
      (p.email || "").toLowerCase().includes(q)
    );

    tbody.innerHTML = "";
    empty.classList.toggle("hidden", list.length > 0);

    list.forEach(p => {
      const estadoPill = (st) => {
        const cls = {
          "activa":"bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-300",
          "pendiente":"bg-yellow-100 text-yellow-800 dark:bg-yellow-500/20 dark:text-yellow-300",
          "expirada":"bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300",
          "inactivo":"bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300"
        }[st] || "bg-gray-100";
        const cap = st ? st[0].toUpperCase() + st.slice(1) : "Inactivo";
        return `<span class="text-xs font-semibold rounded-full ${cls} px-2 py-0.5">${cap}</span>`;
      };

      const tr = document.createElement("tr");
      tr.className = "hover:bg-[var(--main-bg)]";
      tr.dataset.id = p.id;
      tr.innerHTML = `
        <td class="py-3 px-4 font-semibold text-[var(--text-primary)]">${p.nombre}</td>
        <td class="py-3 px-4 hidden md:table-cell">
          <p>${p.contacto || "N/A"}</p>
          <p class="text-xs text-[var(--text-secondary)]">${p.telefono || "N/A"}</p>
        </td>
        <td class="py-3 px-4 hidden lg:table-cell">
          <div class="flex flex-col">
            <span class="font-semibold">${p.plan_nombre || "Sin Plan"}</span>
            ${estadoPill(p.plan_estado || "inactivo")}
          </div>
        </td>
        <td class="py-3 px-4">
          <label class="inline-flex relative items-center cursor-pointer select-none">
            <input type="checkbox" class="sr-only" ${p.estado ? "checked" : ""} data-action="toggle">
            <span class="switch"></span>
          </label>
        </td>
        <td class="py-3 px-4 text-center">
          <button class="icon-btn" title="Editar" data-action="edit"><i class="fas fa-pencil-alt"></i></button>
          <button class="icon-btn" title="Eliminar" data-action="delete"><i class="fas fa-trash-alt"></i></button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }

  // ---------- Carga inicial ----------
  async function load() {
    const data = await fetchJSON(cfg.listar);
    rows = data.items || [];
    render();
  }

  // ---------- Acciones ----------
  $("#add-provider-btn")?.addEventListener("click", async () => {
    $("#provider-form").reset();
    $("#provider-id").value = "";
    await loadPlanes();
    openModal("Agregar Nuevo Proveedor");
  });

  $("#provider-form")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const id  = $("#provider-id").value.trim();
    const rawPlan = $("#plan_id").value;
    const payload = {
      id: id || undefined,
      nombre:   $("#nombre").value.trim(),
      contacto: $("#contacto").value.trim(),
      email:    $("#email").value.trim(),
      telefono: $("#telefono").value.trim(),
      direccion:$("#direccion").value.trim(),
      plan_id:  rawPlan === "" ? null : parseInt(rawPlan, 10)
    };
    if (!payload.nombre) { alert("Nombre requerido"); return; }

    try {
      const res = await fetchJSON(cfg.guardar, { method: "POST", body: JSON.stringify(payload) });
      if (res.ok) { closeModal(); await load(); }
      else { alert(res.msg || "Error al guardar"); }
    } catch (err) {
      alert(err.message || "Error al guardar");
      console.error(err);
    }
  });

  // Toggle estado por change en el input (evita clicks en el <span>)
  tbody?.addEventListener("change", async (e) => {
    const input = e.target;
    if (!input.matches('input[type="checkbox"][data-action="toggle"]')) return;
    const tr = input.closest("tr"); if (!tr) return;
    const id = parseInt(tr.dataset.id, 10);
    const nuevo = input.checked ? 1 : 0;

    try {
      const res = await fetchJSON(cfg.guardar, { method: "POST", body: JSON.stringify({ id, estado: nuevo }) });
      if (!res.ok) { input.checked = !nuevo; alert(res.msg || "No se pudo actualizar estado"); }
    } catch (err) {
      input.checked = !nuevo;
      console.error(err);
      alert(err.message || "No se pudo actualizar estado");
    }
  });

  // Editar / Eliminar
  tbody?.addEventListener("click", async (e) => {
    const tr = e.target.closest("tr"); if (!tr) return;
    const id = parseInt(tr.dataset.id, 10);
    const btn = e.target.closest("[data-action]"); if (!btn) return;
    const act = btn.dataset.action || "";

    if (act === "edit") {
      const row = rows.find(x => x.id === id);
      if (!row) return;
      $("#provider-form").reset();
      $("#provider-id").value = row.id;
      $("#nombre").value   = row.nombre || "";
      $("#contacto").value = row.contacto || "";
      $("#email").value    = row.email || "";
      $("#telefono").value = row.telefono || "";
      $("#direccion").value= row.direccion || "";
      await loadPlanes();
      $("#plan_id").value = row.plan_id ? String(row.plan_id) : "";
      openModal("Editar Proveedor");
      return;
    }

    if (act === "delete") {
      if (!confirm("¿Eliminar proveedor?")) return;
      try {
        const res = await fetchJSON(cfg.eliminar, { method: "POST", body: JSON.stringify({ id }) });
        if (res.ok) await load(); else alert(res.msg || "Error al eliminar");
      } catch (err) {
        console.error(err);
        alert(err.message || "Error al eliminar");
      }
      return;
    }
  });

  search?.addEventListener("input", debounce(render, 150));

  // go
  load().catch(console.error);
})();
