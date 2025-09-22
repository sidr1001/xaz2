// Agent panel JS placeholder
$(function(){
  // Persist off-canvas open state
  const key = 'agentSidebarOpen';
  const $canvas = $('#agentSidebar');
  const last = localStorage.getItem(key);
  if(last === '1'){
    const off = bootstrap.Offcanvas.getOrCreateInstance($canvas[0]);
    off.show();
  }
  $canvas.on('shown.bs.offcanvas', function(){ localStorage.setItem(key, '1'); });
  $canvas.on('hidden.bs.offcanvas', function(){ localStorage.setItem(key, '0'); });
  // Badge for active filters on agent dashboard
  const $af = $('#agent-filters');
  const $btn = $('button[data-bs-target="#agentSidebar"]');
  function countActive($form){
    let c=0; $form.find('input,select').each(function(){
      const n=$(this).attr('name'); if(!n) return;
      const v=$(this).val(); if(v && v!=='' && n!=='page'){ c++; }
    });
    return c;
  }
  if($af.length){
    const n = countActive($af);
    if(n>0){
      const $badge = $('<span class="badge bg-primary ms-2">'+n+'</span>');
      $('h1 .btn-group, h1').first().append($badge);
    }
  }
});

