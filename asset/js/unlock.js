// /console/asset/js/unlock.js
(function(){
  var clicks = 0, last = 0, TTL = 600; // ms
  var KEY = 'unlockVallas';
  function set(on){ document.body.classList.toggle('unlock', !!on); localStorage.setItem(KEY, on?'1':'0'); toast(on? 'Modo rastreo habilitado' : 'Modo rastreo bloqueado'); }
  function toast(msg){
    var t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText='position:fixed;bottom:16px;left:50%;transform:translateX(-50%);background:#111827;color:#fff;padding:8px 12px;border-radius:10px;font:500 14px Inter,system-ui;z-index:9999;opacity:.98';
    document.body.appendChild(t); setTimeout(function(){ t.remove(); }, 1500);
  }
  document.addEventListener('click', function(e){
    var now = Date.now();
    if (now - last > TTL) { clicks = 0; }
    clicks++; last = now;
    if (clicks >= 3) { clicks = 0; set(!(document.body.classList.contains('unlock'))); }
  }, true);
  // estado inicial
  if (localStorage.getItem(KEY) === '1') document.addEventListener('DOMContentLoaded', function(){ document.body.classList.add('unlock'); });
})();
