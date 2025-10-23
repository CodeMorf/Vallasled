<?php
// /config/loader.php
declare(strict_types=1);

/**
 * Loader con logo + canvas animado SOLO para rutas /console/.
 * Se omite en /console/(ajax|api), JSON, HEAD, OPTIONS y CLI.
 * Inyección segura antes de </head> sin tocar tus vistas.
 *
 * Personalización opcional:
 *   define('CM_LOADER_LOGO_URL', 'https://auth.vallasled.com/admin/assets/logo.png');
 */

if (!function_exists('console_loader_bootstrap')) {
    function console_loader_bootstrap(): void
    {
        if (PHP_SAPI === 'cli') return;

        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = (string)parse_url($uri, PHP_URL_PATH) ?: '/';

        // Filtrar rutas válidas
        if (!preg_match('#^/console(?:/|$)#i', $path)) return;
        if (preg_match('#/(ajax|api)(?:/|$)#i', $path)) return;

        // Métodos sin UI
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'HEAD' || $method === 'OPTIONS') return;

        // Solicitudes XHR/JSON/SSE
        $xh = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($xh === 'xmlhttprequest') return;
        $acc = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        if (stripos($acc, 'application/json') !== false || stripos($acc, 'text/event-stream') !== false) return;

        if (defined('CM_CONSOLE_LOADER_STARTED')) return;
        define('CM_CONSOLE_LOADER_STARTED', true);

        $defaultLogo = defined('CM_LOADER_LOGO_URL')
            ? (string)CM_LOADER_LOGO_URL
            : 'https://auth.vallasled.com/admin/assets/logo.png';

        ob_start(function (string $out) use ($defaultLogo): string {
            // Inyectar solo en HTML completo
            if (stripos($out, '</head>') === false || stripos($out, '<body') === false) return $out;
            if (stripos($out, 'id="cm-console-loader-css"') !== false) return $out;

            $logoEsc = htmlspecialchars($defaultLogo, ENT_QUOTES, 'UTF-8');

            $injection = <<<HTML
    <style id="cm-console-loader-css">
      :root{
        --cm-z: 2147483000;
        --cm-bg-dark: rgba(10,13,18,.96);
        --cm-fg-dark: #e5e7eb;
        --cm-bg-light: rgba(255,255,255,.96);
        --cm-fg-light: #0f172a;
      }
      #cmLoader{
        position:fixed; inset:0; display:none;
        align-items:center; justify-content:center;
        z-index: var(--cm-z);
        -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;
        transition: opacity .15s ease;
      }
      html.is-loading #cmLoader{ display:flex; }
      #cmLoader.theme-dark{ background: var(--cm-bg-dark); color: var(--cm-fg-dark); }
      #cmLoader.theme-light{ background: var(--cm-bg-light); color: var(--cm-fg-light); }

      #cmLoaderCanvas{ position:absolute; inset:0; width:100%; height:100%; display:block; }
      #cmLoader .wrap{ position:relative; display:flex; flex-direction:column; align-items:center; gap:14px; }
      #cmLoader img{
        display:block; height: clamp(40px,9vmin,72px);
        width:auto; object-fit:contain; filter: drop-shadow(0 2px 10px rgba(0,0,0,.35));
      }
      @media (prefers-reduced-motion: reduce){
        #cmLoader{ transition:none; }
      }
    </style>
    <script id="cm-console-loader-js">
    (function(){
      'use strict';
      var D=document, H=D.documentElement;
      var DEF_LOGO="{$logoEsc}";
      var running=false, rafId=0;

      // === Utils ===
      function lum(rgb){ var m=String(rgb).match(/rgba?\\((\\d+),(\\d+),(\\d+)/); if(!m) return 255;
        var r=+m[1], g=+m[2], b=+m[3]; return 0.2126*r+0.7152*g+0.0722*b; }
      function detectTheme(){
        if(H.classList.contains('dark')) return 'dark';
        var sb=D.querySelector('#sidebar, .sidebar, [data-sidebar]');
        var ref=(sb && getComputedStyle(sb).backgroundColor) || getComputedStyle(D.body).backgroundColor || 'rgb(255,255,255)';
        return lum(ref)<128 ? 'dark' : 'light';
      }
      function pickLogo(){
        var m=D.querySelector('meta[name="app-logo"]');
        var cand=[DEF_LOGO];
        if(window.CM_LOADER_LOGO) cand.unshift(window.CM_LOADER_LOGO);
        if(m && m.content) cand.unshift(m.content);
        var b=D.querySelector('#brand-logo, .brand img, header img[alt*=logo i]');
        if(b && b.src) cand.unshift(b.src);
        cand.push('/asset/img/logo.svg','/asset/img/logo.png','/logo.svg','/logo.png','/favicon.png');
        for(var i=0;i<cand.length;i++){
          try{ var u=new URL(cand[i], location.href);
               if(u.protocol==='http:'||u.protocol==='https:'||u.protocol==='data:') return u.href; }catch(e){}
        }
        return DEF_LOGO;
      }

      // === UI root ===
      var root=D.createElement('div');
      root.id='cmLoader';
      root.innerHTML='<canvas id="cmLoaderCanvas" aria-hidden="true"></canvas><div class="wrap"><img id="cmLoaderLogo" alt="logo"></div>';

      // Adjuntar lo antes posible, aunque <body> aún no exista
      (D.body||D.documentElement).appendChild(root);
      if(!D.body){
        document.addEventListener('DOMContentLoaded', function(){
          if(root.parentNode!==D.body && D.body) D.body.appendChild(root);
        }, {once:true});
      }

      function applyTheme(){
        var t=detectTheme();
        root.classList.toggle('theme-dark', t==='dark');
        root.classList.toggle('theme-light', t!=='dark');
      }
      applyTheme();
      D.getElementById('cmLoaderLogo').src = pickLogo();

      // === Canvas Particles (optimizado) ===
      var canvas=D.getElementById('cmLoaderCanvas');
      var ctx=canvas.getContext('2d',{alpha:true});
      var PI=Math.PI, TAU=PI*2, particles=[], tick=0, last=0;
      var cw=0,ch=0,min=0,dpr=1, maxP=420, globalAngle=0;

      function fit(){
        dpr=Math.min(window.devicePixelRatio||1, 2);
        cw=window.innerWidth||300; ch=window.innerHeight||300; min=Math.min(cw,ch)*0.6;
        canvas.width=Math.floor(cw*dpr); canvas.height=Math.floor(ch*dpr);
        canvas.style.width=cw+'px'; canvas.style.height=ch+'px';
        ctx.setTransform(dpr,0,0,dpr,0,0);
        ctx.globalCompositeOperation='lighter';
      }

      function Particle(opt){
        this.x=opt.x; this.y=opt.y;
        this.angle=opt.angle; this.speed=opt.speed; this.accel=opt.accel;
        this.radius=7; this.decay=0.01; this.life=1;
      }
      Particle.prototype.step=function(i){
        this.speed+=this.accel;
        this.x+=Math.cos(this.angle)*this.speed;
        this.y+=Math.sin(this.angle)*this.speed;
        this.angle+=PI/64;
        this.accel*=1.01;
        this.life-=this.decay;
        if(this.life<=0) particles.splice(i,1);
      };
      Particle.prototype.draw=function(i){
        var col='hsla('+(tick+this.life*120)+',100%,60%,'+this.life+')';
        ctx.fillStyle=col; ctx.strokeStyle=col;
        ctx.beginPath();
        if(particles[i-1]){ ctx.moveTo(this.x,this.y); ctx.lineTo(particles[i-1].x,particles[i-1].y); }
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(this.x,this.y,Math.max(0.001,this.life*this.radius),0,TAU);
        ctx.fill();
        var size=Math.random()*1.25;
        ctx.fillRect(
          (this.x + (Math.random()-0.5)*35*this.life)|0,
          (this.y + (Math.random()-0.5)*35*this.life)|0,
          size,size
        );
      };

      function step(){
        if(particles.length<maxP){
          particles.push(new Particle({
            x: cw/2 + Math.cos(tick/20)*min/2,
            y: ch/2 + Math.sin(tick/20)*min/2,
            angle: globalAngle, speed: 0, accel: 0.01
          }));
        }
        for(var i=particles.length-1;i>=0;i--) particles[i].step(i);
        globalAngle += PI/3;
      }
      function draw(){
        ctx.clearRect(0,0,cw,ch);
        for(var i=0;i<particles.length;i++) particles[i].draw(i);
      }
      function loop(t){
        if(!running) return;
        rafId=window.requestAnimationFrame(loop);
        if(!last) last=t;
        if(t-last>=1000/60){ last=t; step(); draw(); tick++; }
      }
      function start(){ if(running) return; running=true; fit(); loop(0); }
      function stop(){ running=false; if(rafId) cancelAnimationFrame(rafId); }

      window.addEventListener('resize', fit);
      document.addEventListener('visibilitychange', function(){ if(document.hidden) stop(); else if(root.style.display!=='none') start(); });

      // === Mostrar/Ocultar ===
      function show(){ H.classList.add('is-loading'); root.style.display='flex'; start(); }
      function hide(){ H.classList.remove('is-loading'); root.style.display='none'; stop(); }

      // Mostrar al inicio, ocultar al cargar
      show();
      window.addEventListener('load', hide, {once:true});
      window.addEventListener('pageshow', hide);

      // Navegación interna dentro de /console
      function allow(e){ return !(e.defaultPrevented||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||(e.button!==0)); }
      function sameOrigin(u){ try{ var x=new URL(u,location.href); return x.origin===location.origin; }catch(e){ return false; } }
      function isConsole(u){ try{ var p=new URL(u,location.href).pathname; return /^\\/console(\\/|$)/i.test(p)&&!/(?:\\/ajax\\/|\\/api\\/)/i.test(p);}catch(e){return false;} }

      D.body.addEventListener('click', function(e){
        var a=e.target && e.target.closest ? e.target.closest('a') : null;
        if(!a||!allow(e)) return;
        if(!a.href || a.target==='_blank' || a.hasAttribute('download')) return;
        if(!sameOrigin(a.href) || !isConsole(a.href)) return;
        var u=new URL(a.href, location.href);
        if(u.hash && u.pathname===location.pathname) return;
        e.preventDefault(); show(); setTimeout(function(){ location.href=u.href; }, 60);
      }, {capture:true});

      D.body.addEventListener('submit', function(e){
        var f=e.target;
        if(!(f instanceof HTMLFormElement)) return;
        if(f.hasAttribute('data-ajax') && f.getAttribute('data-ajax')!=='false') return;
        var action=f.getAttribute('action') || location.href;
        if(!sameOrigin(action) || !isConsole(action)) return;
        show();
      }, {capture:true});

      // Fallback
      window.addEventListener('beforeunload', function(){ show(); });
    })();
    </script>
HTML;

            $res = preg_replace('#</head>#i', $injection . '</head>', $out, 1);
            return is_string($res) ? $res : $out;
        });
    }
}
