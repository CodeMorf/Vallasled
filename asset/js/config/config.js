// /console/asset/js/config/config.js
/* eslint-disable */
(function(){
  "use strict";

  const $  = (s, r=document)=>r.querySelector(s);
  const $$ = (s, r=document)=>[...r.querySelectorAll(s)];
  const CSRF = document.querySelector('meta[name="csrf"]')?.getAttribute('content') || '';
  const cfg = window.CFG || { endpoints:{} };

  // toast minimal
  const toast = document.getElementById('toast') || (()=>{ const t=document.createElement('div'); t.id='toast'; t.style.position='fixed'; t.style.right='16px'; t.style.bottom='16px'; t.style.zIndex='70'; t.style.display='none'; t.innerHTML='<div id="toast-msg" class="px-4 py-2 rounded-lg bg-[var(--card-bg)] border border-[var(--border-color)] shadow"></div>'; document.body.appendChild(t); return t; })();
  const toastMsg = document.getElementById('toast-msg') || toast.querySelector('#toast-msg');
  function showToast(msg){ toastMsg.textContent=msg; toast.style.display='block'; setTimeout(()=>toast.style.display='none', 2200); }

  async function postForm(url, data){
    const body = new URLSearchParams({ csrf: CSRF, ...data });
    const res = await fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  // Tabs
  function setupTabs(){
    const tabs = $$('.tab-button');
    const contents = $$('.tab-content');
    tabs.forEach(tab=>{
      tab.addEventListener('click', ()=>{
        tabs.forEach(t=>t.classList.remove('active'));
        tab.classList.add('active');
        const target = tab.getAttribute('data-tab');
        contents.forEach(c=>{ c.classList.toggle('hidden', c.id!==target); });
      });
    });
  }

  // Cargar opciones
  async function loadAll(){
    const res = await postForm(cfg.endpoints.opciones, {});
    if (res.error) { showToast(res.error); return; }
    const d = res.data || {};

    // General
    $('#site-name').value = d.general?.site_name || '';
    $('#admin-email').value = d.general?.admin_email || '';
    $('#site-desc').value = d.general?.site_desc || '';
    $('#company-phone').value = d.general?.company_phone || '';
    $('#support-whatsapp').value = d.general?.support_whatsapp || '';

    // APIs
    $('#google-maps-api').value = d.apis?.google_maps_api || '';
    $('#openai-api').value = d.apis?.openai_api || '';
    $('#openai-model').value = d.apis?.openai_model || '';
    $('#cron-key').value = d.apis?.cron_key || '';

    // SMTP
    $('#smtp-host').value = d.smtp?.smtp_host || '';
    $('#smtp-port').value = d.smtp?.smtp_port || '587';
    $('#smtp-user').value = d.smtp?.smtp_user || '';
    $('#smtp-pass').value = d.smtp?.smtp_pass || '';
    $('#smtp-from-email').value = d.smtp?.smtp_from_email || '';
    $('#smtp-from-name').value = d.smtp?.smtp_from_name || '';

    // Payments
    $('#stripe-pk').value = d.payments?.stripe_pk || '';
    $('#stripe-sk').value = d.payments?.stripe_sk || '';
    $('#stripe-currency').value = d.payments?.stripe_currency || 'usd';
    $('#vendor-comision').value = d.payments?.vendor_comision || '10.00';

    // Appearance
    $('#logo-url').value = d.appearance?.logo_url || '';
    $('#favicon-url').value = d.appearance?.favicon_url || '';
    $('#primary-color').value = d.appearance?.primary_color || '#4f46e5';
    $('#secondary-color').value = d.appearance?.secondary_color || '#111827';

    // Maps
    $('#map-provider').value = d.maps?.map_provider || 'google';
    $('#map-style').value = d.maps?.map_style || 'google.roadmap';
    $('#map-lat').value = d.maps?.map_lat || '18.4860580';
    $('#map-lng').value = d.maps?.map_lng || '-69.9312120';
    $('#map-zoom').value = d.maps?.map_zoom || '12';

    // Integrations
    $('#g-client-id').value = d.integrations?.g_client_id || '';
    $('#g-client-secret').value = d.integrations?.g_client_secret || '';
    $('#g-redirect-uri').value = d.integrations?.g_redirect_uri || '';
  }

  // Guardar por sección
  async function save(section, data){
    const res = await postForm(cfg.endpoints.guardar, {
      section,
      payload: JSON.stringify(data || {})
    });
    if (res.error) { showToast(res.error); return; }
    showToast('Guardado');
  }

  function bindSaves(){
    $('#btn-save-general')?.addEventListener('click', (e)=>{ e.preventDefault();
      save('general', {
        site_name: $('#site-name').value.trim(),
        admin_email: $('#admin-email').value.trim(),
        site_desc: $('#site-desc').value.trim(),
        company_phone: $('#company-phone').value.trim(),
        support_whatsapp: $('#support-whatsapp').value.trim()
      }).catch(()=>showToast('Error guardando'));
    });

    $('#btn-save-apis')?.addEventListener('click', (e)=>{ e.preventDefault();
      save('apis', {
        google_maps_api: $('#google-maps-api').value.trim(),
        openai_api: $('#openai-api').value.trim(),
        openai_model: $('#openai-model').value.trim(),
        cron_key: $('#cron-key').value.trim()
      }).catch(()=>showToast('Error guardando'));
    });

    $('#btn-save-smtp')?.addEventListener('click', (e)=>{ e.preventDefault();
      save('smtp', {
        smtp_host: $('#smtp-host').value.trim(),
        smtp_port: $('#smtp-port').value.trim(),
        smtp_user: $('#smtp-user').value.trim(),
        smtp_pass: $('#smtp-pass').value,
        smtp_from_email: $('#smtp-from-email').value.trim(),
        smtp_from_name: $('#smtp-from-name').value.trim()
      }).catch(()=>showToast('Error guardando'));
    });

    $('#btn-save-payments')?.addEventListener('click', (e)=>{ e.preventDefault();
      save('payments', {
        stripe_pk: $('#stripe-pk').value.trim(),
        stripe_sk: $('#stripe-sk').value.trim(),
        stripe_currency: $('#stripe-currency').value.trim(),
        vendor_comision: $('#vendor-comision').value.trim()
      }).catch(()=>showToast('Error guardando'));
    });

    $('#btn-save-appearance')?.addEventListener('click', (e)=>{ e.preventDefault();
      save('appearance', {
        logo_url: $('#logo-url').value.trim(),
        favicon_url: $('#favicon-url').value.trim(),
        primary_color: $('#primary-color').value,
        secondary_color: $('#secondary-color').value
      }).catch(()=>showToast('Error guardando'));
    });

    $('#btn-save-maps')?.addEventListener('click', (e)=>{ e.preventDefault();
      save('maps', {
        map_provider: $('#map-provider').value,
        map_style: $('#map-style').value,
        map_lat: $('#map-lat').value.trim(),
        map_lng: $('#map-lng').value.trim(),
        map_zoom: $('#map-zoom').value.trim()
      }).catch(()=>showToast('Error guardando'));
    });

    $('#btn-save-integrations')?.addEventListener('click', (e)=>{ e.preventDefault();
      save('integrations', {
        g_client_id: $('#g-client-id').value.trim(),
        g_client_secret: $('#g-client-secret').value.trim(),
        g_redirect_uri: $('#g-redirect-uri').value.trim()
      }).catch(()=>showToast('Error guardando'));
    });
  }

  (async function init(){
    try{
      setupTabs();
      bindSaves();
      await loadAll();
    }catch(e){ console.error(e); showToast('Error inicializando'); }
  })();

})();
