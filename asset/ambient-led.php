<?php
// /console/asset/ambient-led.php — Fondo LED oscuro sin morado, con paleta del logo
declare(strict_types=1);
if (defined('CM_AMBIENT_INIT')) return;
define('CM_AMBIENT_INIT', true);
?>
<style>
  :root{
    /* Intensidad y blur global del glow */
    --aurora-opacity:.50;     /* 0..1  (sube/baja brillo) */
    --aurora-blur:32px;       /* desenfoque de la aurora */
    --aurora-speed:26;        /* grados/seg animación */

    /* Paleta del logo (RGB) */
    --c-red:   239, 68,  68;   /* #EF4444 */
    --c-orange:245,158, 11;    /* #F59E0B */
    --c-yellow:250,204, 21;    /* #FACC15 */
    --c-green:  34,197, 94;    /* #22C55E */
    --c-cyan:   20,184,166;    /* #14B8A6 */
    --c-blue:   37, 99, 235;   /* #2563EB */
  }

  html,body{ background:#020307; }

  /* Contenedor de fondo */
  #cm-ambient-led{
    position:fixed; inset:0; z-index:-1; pointer-events:none; overflow:hidden;
    /* base negra con leve viñeta superior */
    background:
      radial-gradient(120% 160% at 50% -20%, rgba(1,3,8,.92) 0%, rgba(1,3,8,.80) 48%, rgba(0,0,0,.66) 100%);
  }

  /* Capa AURORA: colores del logo en anillo distante, muy sutil */
  #cm-ambient-led::before{
    content:""; position:absolute; inset:-12% -12%;
    opacity:var(--aurora-opacity);
    filter:blur(var(--aurora-blur)) saturate(110%);
    mix-blend-mode:screen;
    background:
      /* anillo multicolor, lejos y suave */
      conic-gradient(from var(--cm-aurora-angle, 0deg) at 50% 58%,
        rgba(var(--c-red),   .00) 0%,
        rgba(var(--c-red),   .10)  8%,
        rgba(var(--c-orange),.10) 16%,
        rgba(var(--c-yellow),.10) 26%,
        rgba(var(--c-green), .08) 36%,
        rgba(var(--c-cyan),  .08) 48%,
        rgba(var(--c-blue),  .10) 60%,
        rgba(var(--c-blue),  .00) 72%,
        rgba(var(--c-yellow),.06) 84%,
        rgba(var(--c-red),   .00) 100%)
      ,
      /* realce lateral sutil en azul/cian y rojo/naranja */
      radial-gradient(42% 32% at 85% 20%, rgba(var(--c-blue), .06) 0%, rgba(0,0,0,0) 70%),
      radial-gradient(44% 34% at 15% 10%, rgba(var(--c-red),  .05) 0%, rgba(0,0,0,0) 70%);
    transform:rotate(2deg) scale(1.06);
  }

  /* Capa REFLEJO inferior “a distancia” sin contaminar el centro */
  #cm-ambient-led::after{
    content:""; position:absolute; inset:0; pointer-events:none; mix-blend-mode:screen;
    background:
      radial-gradient(80% 55% at 50% 115%,
        rgba(var(--c-blue), .06) 0%,
        rgba(var(--c-green),.05) 22%,
        rgba(255,255,255,.04) 34%,
        rgba(0,0,0,0) 70%),
      /* viñeta negra para preservar contraste */
      radial-gradient(82% 62% at 50% 120%, rgba(0,0,0,.50) 0%, rgba(0,0,0,0) 62%);
  }

  /* Respeto a reduced motion: deja la aurora fija */
  @media (prefers-reduced-motion: reduce){
    :root{ --aurora-opacity:.46; }
  }
</style>

<div id="cm-ambient-led" aria-hidden="true"></div>

<script>
/* Animación suave del ángulo (solo CSS var). Pausa en pestaña oculta. */
(function(){
  if (window.__cm_ambient_css__) return; window.__cm_ambient_css__ = true;
  let raf=null, start=performance.now();
  const sp = parseFloat(getComputedStyle(document.documentElement)
              .getPropertyValue('--aurora-speed')) || 26; // grados/seg
  function step(ts){
    const a = ((ts - start)/1000) * sp;
    document.documentElement.style.setProperty('--cm-aurora-angle', a+'deg');
    raf = requestAnimationFrame(step);
  }
  function play(){ cancelAnimationFrame(raf); raf=requestAnimationFrame(step); }
  function pause(){ cancelAnimationFrame(raf); }
  document.addEventListener('visibilitychange', ()=>{ document.hidden ? pause() : play(); });
  const mq = matchMedia('(prefers-reduced-motion: reduce)');
  mq.addEventListener?.('change', ()=>{ mq.matches ? pause() : play(); });
  mq.matches ? pause() : play();

  // API opcional por si quieres forzar on/off desde consola
  window.ambientLedEnable = play;
  window.ambientLedDisable = pause;
})();
</script>
