// Agent panel JS placeholder
$(function(){
  // placeholder
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

