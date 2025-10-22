// /console/asset/js/unlock-sort.js
// Desbloqueo por 3 clics rápidos para permitir “rastrear y soltar” temporalmente.
(function(global){
  function toast(msg, ms){
    const el = document.createElement('div');
    el.className = 'unlock-toast';
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(()=>{ el.remove(); }, ms||1800);
  }

  function attach({target, onUnlock, onLock, unlockMs=60000, thresholdMs=800}){
    if(!target){ target = document.body; }
    let clicks = 0, timer = null, lockTimer = null, unlocked = false;

    function resetClicks(){ clicks = 0; clearTimeout(timer); timer=null; }

    function lock(){
      if(!unlocked) return;
      unlocked = false;
      if (typeof onLock==='function') onLock();
      toast('Bloqueado', 1200);
    }

    function unlock(){
      unlocked = true;
      if (typeof onUnlock==='function') onUnlock(unlockMs);
      toast('Desbloqueado: rastrear y soltar activo por 60s', 1800);
      clearTimeout(lockTimer);
      lockTimer = setTimeout(lock, unlockMs);
    }

    target.addEventListener('click', ()=>{
      clicks++;
      if(!timer){
        timer = setTimeout(()=>{ resetClicks(); }, thresholdMs);
      }
      if(clicks>=3){
        resetClicks();
        if(!unlocked) unlock(); else lock();
      }
    });

    // Bloquear al cambiar de pestaña o al abandonar
    window.addEventListener('blur', lock);
    document.addEventListener('visibilitychange', ()=>{ if(document.hidden) lock(); });

    // Exponer métodos de control opcional
    return { lock, unlock, isUnlocked: ()=>unlocked };
  }

  global.UnlockSort = { attach };
})(window);
