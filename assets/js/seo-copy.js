(function(){
  const blk = document.querySelector('#seo-copy');
  if(!blk) return;

  // Auto-colapsar si el bloque supera cierto alto
  const limit = 420; // px
  if(blk.scrollHeight > limit){
    blk.classList.add('is-collapsed');
    const btn = document.createElement('button');
    btn.className = 'seo-toggle';
    btn.type = 'button';
    btn.setAttribute('aria-expanded','false');
    btn.textContent = 'Leer más';
    blk.insertAdjacentElement('afterend', btn);

    btn.addEventListener('click', () => {
      const collapsed = blk.classList.toggle('is-collapsed');
      btn.setAttribute('aria-expanded', String(!collapsed));
      btn.textContent = collapsed ? 'Leer más' : 'Mostrar menos';
      if(!collapsed) blk.scrollIntoView({behavior:'smooth', block:'start'});
    });
  }

  // Mejoras UX para la nube de keywords
  const cloud = document.querySelector('.kw-cloud');
  if(cloud){
    // Scroll suave para muchas etiquetas
    cloud.addEventListener('click', (e)=>{
      const a = e.target.closest('a.tag'); if(!a) return;
      // Permite navegación normal, pero puedes prevenir y hacer SPA si tienes router
    });

    // Accesibilidad: navegación con flechas
    const tags = [...cloud.querySelectorAll('.tag')];
    tags.forEach((t,i)=>{
      t.setAttribute('tabindex','0');
      t.addEventListener('keydown', (ev)=>{
        if(ev.key==='ArrowRight'){ ev.preventDefault(); (tags[i+1]||tags[0]).focus(); }
        if(ev.key==='ArrowLeft'){ ev.preventDefault(); (tags[i-1]||tags[tags.length-1]).focus(); }
      });
    });
  }
})();
