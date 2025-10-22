// /console/asset/js/web/web.js
(() => {
  'use strict';

  /* ---------- helpers ---------- */
  const $  = (s, r=document) => r.querySelector(s);
  const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));
  const cfg = window.WEB_CFG || {};
  const CSRF = ($('meta[name="csrf"]')?.content || cfg.csrf || '').toString();

  const jGET  = (url, params={}) => {
    const u = new URL(url, location.origin);
    Object.entries(params).forEach(([k,v]) => { if(v!=null && v!=='') u.searchParams.set(k,v); });
    return fetch(u.toString(), {
      credentials: 'same-origin',
      headers: { 'Accept':'application/json' }
    }).then(r => r.json());
  };

  const jPOST = (url, data) => {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF': CSRF
      },
      body: JSON.stringify(data || {})
    }).then(async r => {
      const js = await r.json().catch(()=>({}));
      if (!r.ok) { const e = new Error(js.error || `HTTP_${r.status}`); e.payload = js; e.status = r.status; throw e; }
      return js;
    });
  };

  const toast = (msg, type='info') => {
    let t = $('#web-toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'web-toast';
      t.className = 'fixed bottom-4 right-4 z-50 px-4 py-2 rounded-lg shadow-md text-sm';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.background = type==='ok'   ? '#16a34a' :
                         type==='warn' ? '#f59e0b' :
                         type==='err'  ? '#dc2626' : '#374151';
    t.style.color = '#fff';
    t.style.opacity = '1';
    setTimeout(()=>{ t.style.transition='opacity .4s'; t.style.opacity='0'; }, 1800);
  };

  /* ---------- theme only (sin sidebar) ---------- */
  const bindTheme = () => {
    const themeBtn = $('#theme-toggle');
    const darkI = $('#theme-toggle-dark-icon');
    const lightI= $('#theme-toggle-light-icon');
    const applyTheme = t => {
      if (t === 'dark') { document.documentElement.classList.add('dark');  darkI?.classList.remove('hidden'); lightI?.classList.add('hidden'); }
      else              { document.documentElement.classList.remove('dark');darkI?.classList.add('hidden');   lightI?.classList.remove('hidden'); }
    };
    const saved = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyTheme(saved);
    themeBtn?.addEventListener('click', () => {
      const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
      localStorage.setItem('theme', next);
      applyTheme(next);
    });
  };

  /* ---------- color pickers + previews ---------- */
  const linkColor = (pickerId, inputId, swatchId) => {
    const p = $('#'+pickerId);
    const i = $('#'+inputId);
    const s = $('#'+swatchId);
    if (!i || !s) return;
    const set = (hex) => { if (s) s.style.backgroundColor = hex || 'transparent'; if (p) p.value = hex || '#000000'; i.value = hex || ''; };
    p?.addEventListener('input', e => set(e.target.value));
    i.addEventListener('input', e => {
      const v = e.target.value.trim();
      if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(v)) set(v);
    });
    return { set };
  };

  /* ---------- banner UI ---------- */
  const bannerUI = {
    toggle:  $('#home_banner_enabled'),
    options: $('#banner-options'),
    videoBox: $('#banner-video-section'),
    imageBox: $('#banner-image-section'),
    modeRadios: $$('input[type="radio"][name="home_banner_mode"]')
  };
  const applyBannerVisibility = () => {
    const enabled = !!bannerUI.toggle?.checked;
    if (bannerUI.options) bannerUI.options.style.display = enabled ? 'block' : 'none';
    const mode = (document.querySelector('input[name="home_banner_mode"]:checked')?.value) || 'video';
    if (bannerUI.videoBox && bannerUI.imageBox) {
      if (mode === 'video') { bannerUI.videoBox.classList.remove('hidden'); bannerUI.imageBox.classList.add('hidden'); }
      else                  { bannerUI.videoBox.classList.add('hidden');  bannerUI.imageBox.classList.remove('hidden'); }
    }
  };

  /* ---------- hydrate ---------- */
  const hydrate = async () => {
    try {
      const res = await jGET(cfg.endpoints.settings_get);
      if (!res?.ok) throw new Error('LOAD_FAIL');

      const data = res.data || {};

      // fill simple inputs/textareas by [data-key]
      $$('[data-key]').forEach(el => {
        const k = el.getAttribute('data-key');
        if (!k) return;
        if (el.type === 'checkbox') {
          el.checked = (String(data[k] ?? '0') === '1');
        } else if (el.type === 'radio') {
          // handled separately
        } else {
          // JSON array o líneas para videos
          if (k === 'home_banner_video_urls') {
            const v = data[k];
            if (Array.isArray(v)) el.value = v.join('\n');
            else el.value = (v ?? '');
          } else {
            el.value = (data[k] ?? '');
          }
        }
      });

      // radios
      const mode = String(data['home_banner_mode'] ?? 'video');
      $$('input[name="home_banner_mode"]').forEach(r => { r.checked = (r.value === mode); });

      // previews
      const logo = $('#logo_url'); const fav = $('#favicon_url');
      if (logo && $('#logo_preview')) $('#logo_preview').src = logo.value || '';
      if (fav  && $('#favicon_preview')) $('#favicon_preview').src = fav.value || '';

      // colors
      linkColor('primary_color_picker',   'theme_primary_color', 'primary_color_preview')?.set(data['theme_primary_color'] || '');
      linkColor('secondary_color_picker', 'theme_secondary_color','secondary_color_preview')?.set(data['theme_secondary_color'] || '');
      linkColor('footer_bg_picker',       'footer_bg_color',     'footer_bg_preview')?.set(data['footer_bg_color'] || '');
      linkColor('footer_text_picker',     'footer_text_color',   'footer_text_preview')?.set(data['footer_text_color'] || '');

      // banner UI
      if (bannerUI.toggle) bannerUI.toggle.checked = (String(data['home_banner_enabled'] ?? '0') === '1');
      applyBannerVisibility();

      toast('Cargado', 'ok');
    } catch (e) {
      toast('Error cargando configuración', 'err');
    }
  };

  /* ---------- collect + save ---------- */
  const collect = () => {
    const out = {};
    // radios
    const radioGroups = new Map();
    $$('input[type="radio"][data-key]').forEach(r => radioGroups.set(r.name || r.getAttribute('data-key'), true));
    radioGroups.forEach((_, name) => {
      const r = document.querySelector(`input[type="radio"][name="${name}"]:checked`);
      if (r) out[r.getAttribute('data-key')] = r.value;
    });

    // inputs + textareas
    $$('[data-key]').forEach(el => {
      const k = el.getAttribute('data-key');
      if (!k) return;
      if (el.type === 'radio') return;
      if (el.type === 'checkbox') { out[k] = el.checked ? '1' : '0'; return; }
      let v = el.value ?? '';
      if (['site_url','logo_url','favicon_url','hero_cta_url','legal_terms_url','legal_privacy_url',
           'vendor_register_url','vendor_login_url','home_banner_image_url'].includes(k)) v = v.trim();
      out[k] = v;
    });
    return out;
  };

  const bindSave = () => {
    const btn = $('#web-save-btn');
    if (!btn) return;
    btn.addEventListener('click', async () => {
      try {
        btn.disabled = true;
        btn.classList.add('opacity-70', 'cursor-wait');

        const payload = collect();
        const res = await jPOST(cfg.endpoints.settings_set, payload);
        if (res?.ok) {
          toast('Guardado', 'ok');
          await hydrate();
        } else {
          toast('Error al guardar', 'err');
        }
      } catch (e) {
        const field = e?.payload?.field ? ` (${e.payload.field})` : '';
        toast((e?.payload?.error || 'ERROR') + field, 'err');
      } finally {
        btn.disabled = false;
        btn.classList.remove('opacity-70', 'cursor-wait');
      }
    });
  };

  /* ---------- dynamics ---------- */
  const bindDynamics = () => {
    // image previews
    $('#logo_url')?.addEventListener('input', () => { const v = $('#logo_url').value.trim(); const img = $('#logo_preview'); if (img) img.src = v || ''; });
    $('#favicon_url')?.addEventListener('input', () => { const v = $('#favicon_url').value.trim(); const img = $('#favicon_preview'); if (img) img.src = v || ''; });
    $('#logo_preview')?.addEventListener('error', e => { e.currentTarget.src = 'https://placehold.co/120x40/f87171/ffffff?text=!'; });
    $('#favicon_preview')?.addEventListener('error', e => { e.currentTarget.src = 'https://placehold.co/48x48/f87171/ffffff?text=!'; });

    // banner switches
    bannerUI.toggle?.addEventListener('change', applyBannerVisibility);
    bannerUI.modeRadios.forEach(r => r.addEventListener('change', applyBannerVisibility));
  };

  /* ---------- init ---------- */
  document.addEventListener('DOMContentLoaded', async () => {
    bindTheme();
    bindDynamics();
    bindSave();
    await hydrate();
  });
})();
