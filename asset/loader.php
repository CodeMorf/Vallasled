<?php
/**
 * /console/asset/loader.php
 * Loader con fondo aurora oscuro (sin morado) + progreso % (mín. 2s)
 * API:
 *   window.consoleLoaderShow()
 *   window.consoleLoaderHide()
 *   window.consoleWithLoader(promise)
 */
declare(strict_types=1);
if (defined('CM_LOADER_INIT')) return;
define('CM_LOADER_INIT', true);
?>
<style>
  :root{
    /* Fondo aurora */
    --aurora-opacity:.50;
    --aurora-blur:34px;
    --aurora-speed:26;            /* grados/seg */

    /* Paleta sin morado (logo) */
    --c-red:   239, 68,  68;      /* #EF4444 */
    --c-orange:245,158, 11;       /* #F59E0B */
    --c-yellow:250,204, 21;       /* #FACC15 */
    --c-green:  34,197, 94;       /* #22C55E */
    --c-cyan:   20,184,166;       /* #14B8A6 */
    --c-blue:   37, 99, 235;      /* #2563EB */

    /* HUD */
    --cm-ring-track: 203,213,225; /* gris claro */
    --cm-ring-fill:  240,245,250; /* blanco lejano */
    --cm-tick:       86,190,140;  /* verde sutil */
  }

  /* Overlay del loader (incluye fondo) */
  #cm-loader{
    position:fixed; inset:0; z-index:9999;
    display:none; opacity:0; pointer-events:none;
    background:
      radial-gradient(120% 160% at 50% -20%,
        rgba(1,3,8,.94) 0%, rgba(1,3,8,.86) 48%, rgba(0,0,0,.72) 100%); /* base negra */
    transition:opacity .22s ease;
  }
  #cm-loader.cm-show{ display:block; opacity:1; }

  /* Fondo aurora dentro del loader */
  #cm-loader::before{
    content:""; position:absolute; inset:-12% -12%;
    opacity:var(--aurora-opacity);
    filter:blur(var(--aurora-blur)) saturate(110%);
    mix-blend-mode:screen;
    background:
      conic-gradient(from var(--cm-aurora-angle,0deg) at 50% 58%,
        rgba(var(--c-red),   .00) 0%,
        rgba(var(--c-red),   .10)  8%,
        rgba(var(--c-orange),.10) 16%,
        rgba(var(--c-yellow),.10) 26%,
        rgba(var(--c-green), .08) 36%,
        rgba(var(--c-cyan),  .08) 48%,
        rgba(var(--c-blue),  .10) 60%,
        rgba(var(--c-blue),  .00) 72%,
        rgba(var(--c-yellow),.06) 84%,
        rgba(var(--c-red),   .00) 100%),
      radial-gradient(42% 32% at 85% 20%, rgba(var(--c-blue), .06) 0%, rgba(0,0,0,0) 70%),
      radial-gradient(44% 34% at 15% 10%, rgba(var(--c-red),  .05) 0%, rgba(0,0,0,0) 70%);
    transform:rotate(2deg) scale(1.06);
  }
  @supports not (mix-blend-mode: screen){
    #cm-loader::before{ mix-blend-mode:normal; opacity:calc(var(--aurora-opacity) * .65); }
  }
  /* Reflejo inferior lejano */
  #cm-loader::after{
    content:""; position:absolute; inset:0; pointer-events:none; mix-blend-mode:screen;
    background:
      radial-gradient(80% 55% at 50% 115%,
        rgba(var(--c-blue), .06) 0%,
        rgba(var(--c-green),.05) 22%,
        rgba(255,255,255,.04) 34%,
        rgba(0,0,0,0) 70%),
      radial-gradient(82% 62% at 50% 120%, rgba(0,0,0,.50) 0%, rgba(0,0,0,0) 62%);
  }

  /* Canvas del anillo */
  #cm-loader-fg{
    position:absolute; inset:0; margin:auto;
    width:min(58vmin,360px); height:min(58vmin,360px);
  }

  /* HUD de progreso */
  .cm-progress{
    position:absolute; left:50%; top:50%; transform:translate(-50%,-50%);
    display:flex; flex-direction:column; align-items:center; gap:.35rem;
    user-select:none;
  }
  .cm-progress-value{
    font:700 clamp(24px, 6vmin, 40px)/1 ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;
    letter-spacing:.02em; color:#e5e7eb; text-shadow:0 1px 2px rgba(0,0,0,.5);
  }
  .cm-progress-sub{
    font:600 11px/1 ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;
    letter-spacing:.12em; text-transform:uppercase; color:#a3aab6;
  }

  @media (prefers-reduced-motion: reduce){
    :root{ --aurora-opacity:.46; }
  }
</style>

<!-- Loader + fondo -->
<div id="cm-loader" aria-hidden="true" role="status" aria-live="polite">
  <canvas id="cm-loader-fg" width="360" height="360"></canvas>
  <div class="cm-progress">
    <div id="cm-loader-percent" class="cm-progress-value">0%</div>
    <div class="cm-progress-sub">Cargando</div>
  </div>
</div>

<script>
(function(){
  if (window.__cm_loader__) return; window.__cm_loader__ = true;

  /* ===== Animación de aurora (CSS var) ===== */
  let rafAng=null, angStart=performance.now();
  const sp = parseFloat(getComputedStyle(document.documentElement)
                .getPropertyValue('--aurora-speed')) || 26;
  function stepAng(ts){
    const a = ((ts - angStart)/1000) * sp;
    document.documentElement.style.setProperty('--cm-aurora-angle', a+'deg');
    rafAng = requestAnimationFrame(stepAng);
  }
  function playAng(){ cancelAnimationFrame(rafAng); rafAng=requestAnimationFrame(stepAng); }
  function pauseAng(){ cancelAnimationFrame(rafAng); }
  document.addEventListener('visibilitychange', ()=>{ document.hidden ? pauseAng() : playAng(); });
  const mq = matchMedia('(prefers-reduced-motion: reduce)');
  mq.addEventListener?.('change', ()=>{ mq.matches ? pauseAng() : playAng(); });
  mq.matches ? pauseAng() : playAng();

  /* ===== Canvas ring + % ===== */
  const root  = document.getElementById('cm-loader');
  const cv    = document.getElementById('cm-loader-fg');
  const ctx   = cv.getContext('2d', { alpha:true });
  const pctEl = document.getElementById('cm-loader-percent');

  let raf=null, t0=0, running=false, startedAt=0;
  const MIN_MS = 2000;
  let prog=0, finishing=false;

  function dprResize(){
    const dpr = Math.max(1, Math.min(2, devicePixelRatio || 1));
    const r = Math.min(innerWidth, innerHeight, 640);
    const css = Math.min(r*0.58, 360);
    cv.style.width = css+'px'; cv.style.height = css+'px';
    cv.width  = Math.floor(css*dpr); cv.height = Math.floor(css*dpr);
    ctx.setTransform(dpr,0,0,dpr,0,0);
  }

  function draw(t){
    const W=cv.clientWidth, H=cv.clientHeight, cx=W/2, cy=H/2, R=Math.min(W,H)/2.8, R2=R+12;
    ctx.clearRect(0,0,W,H);

    // Pista
    ctx.lineWidth=2; ctx.strokeStyle='rgba(var(--cm-ring-track), .14)';
    ctx.beginPath(); ctx.arc(cx,cy,R,0,Math.PI*2); ctx.stroke();

    // Progreso
    const a0=-Math.PI/2, a1=a0+(Math.PI*2)*(prog/100);
    ctx.save();
    ctx.lineCap='round'; ctx.lineWidth=6;
    ctx.shadowBlur=12; ctx.shadowColor='rgba(var(--cm-ring-fill), .65)';
    const g2=ctx.createConicGradient(a0,cx,cy);
    g2.addColorStop(0.00,'rgba(var(--cm-ring-fill), 0)');
    g2.addColorStop(Math.max(0.001, prog/100),'rgba(var(--cm-ring-fill), .70)');
    ctx.strokeStyle=g2;
    ctx.beginPath(); ctx.arc(cx,cy,R,a0,a1,false); ctx.stroke();
    ctx.restore();

    // Tick verde
    ctx.fillStyle='rgba(var(--cm-tick), .18)';
    ctx.beginPath(); ctx.arc(cx+Math.cos(a1)*R, cy+Math.sin(a1)*R, 3.2, 0, Math.PI*2); ctx.fill();

    // Halo
    const halo=ctx.createRadialGradient(cx,cy,2,cx,cy,R2+36);
    halo.addColorStop(0,'rgba(230,236,243,.20)');
    halo.addColorStop(1,'rgba(230,236,243,0)');
    ctx.fillStyle=halo; ctx.beginPath(); ctx.arc(cx,cy,R2+36,0,Math.PI*2); ctx.fill();

    // Partículas
    const N=32;
    for(let i=0;i<N;i++){
      const ang=(i/N)*Math.PI*2 + t*0.004;
      const wob=Math.sin(t*0.006 + i)*2.2;
      const rr=R+wob, x=cx+Math.cos(ang)*rr, y=cy+Math.sin(ang)*rr;
      const sz=0.8 + (Math.sin(t*0.01 + i)*0.5+0.5)*1.1;
      const al=0.16 + (Math.cos(t*0.006 + i)*0.5+0.5)*0.32;
      ctx.fillStyle='rgba(255,255,255,'+al.toFixed(3)+')';
      ctx.beginPath(); ctx.arc(x,y,sz,0,Math.PI*2); ctx.fill();
    }
  }

  function updateProgress(now){
    if (!running) return;
    const elapsed = now - startedAt;
    if (!finishing){
      const t90 = MIN_MS * 0.85;
      if (elapsed <= t90) prog = Math.min(90, (elapsed / t90) * 90);
      else                prog = Math.min(96, 90 + (elapsed - t90) / 1200 * 6);
    } else {
      prog += 420 * (1/60);
      if (prog >= 100) prog = 100;
    }
    pctEl.textContent = Math.round(prog) + '%';
  }

  function loop(ts){
    if (!running) return;
    if (!t0) t0 = ts;
    updateProgress(ts);
    draw(ts - t0);
    raf = requestAnimationFrame(loop);
  }

  function start(){
    if (running) return;
    running = true; finishing=false; prog=0;
    startedAt = performance.now();
    root.classList.add('cm-show');
    dprResize(); t0=0; pctEl.textContent='0%';
    raf = requestAnimationFrame(loop);
    document.body.classList.add('overflow-hidden');
  }

  function reallyStop(){
    running=false;
    if (raf) cancelAnimationFrame(raf), raf=null;
    root.classList.remove('cm-show');
    document.body.classList.remove('overflow-hidden');
  }

  function stop(){
    const elapsed = Math.max(0, performance.now() - startedAt);
    const wait = Math.max(0, MIN_MS - elapsed);
    setTimeout(()=>{
      finishing = true;
      const finishTick = ()=>{
        if (!running) return;
        if (prog < 100){ pctEl.textContent = Math.round(prog) + '%'; requestAnimationFrame(finishTick); }
        else { pctEl.textContent='100%'; setTimeout(reallyStop, 100); }
      };
      requestAnimationFrame(finishTick);
    }, wait);
  }

  window.consoleLoaderShow = start;
  window.consoleLoaderHide = stop;
  window.consoleWithLoader = function(promise){
    try{ start(); }catch(e){}
    const done = ()=>{ try{ stop(); }catch(e){} };
    return Promise.resolve(promise).then((v)=>{ done(); return v; }, (err)=>{ done(); throw err; });
  };

  /* Auto-hooks */
  window.addEventListener('beforeunload', start, {passive:true});
  window.addEventListener('pageshow', stop, {passive:true});
  window.addEventListener('load', stop, {once:true, passive:true});
  window.addEventListener('resize', dprResize, {passive:true});

  document.addEventListener('click', function(e){
    const a = e.target.closest ? e.target.closest('.sidebar a[href]') : null;
    if (!a) return;
    const url = a.getAttribute('href') || '';
    if (url.startsWith('#') || url.startsWith('javascript:')) return;
    start();
  }, {capture:true});
})();
</script>
