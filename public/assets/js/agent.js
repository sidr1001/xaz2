// Agent panel JS placeholder
$(function(){
  // placeholder
  // Badge for active filters on agent dashboard
  const $af = $('#agent-filters');
  const $btn = $('button[data-bs-target="#agentSidebar"]');
  function countActive($form){
    let c=0; $form.find('input:not([type=hidden]), select, textarea').each(function(){
      const n=$(this).attr('name'); if(!n) return;
      const v=$(this).val(); if(v && v!=='' && n!=='page'){ c++; }
    });
    // Do not count hidden date fields unless user set range explicitly
    const createdRange = $form.find('input[name="created_range"]').val();
    const tripRange = $form.find('input[name="trip_range"]').val();
    if(!createdRange){ $form.find('input[name="created_from"],input[name="created_to"]').val(''); }
    if(!tripRange){ $form.find('input[name="trip_from"],input[name="trip_to"]').val(''); }
    return c;
  }
  if($af.length){
    const n = countActive($af);
    if(n>0){
      const $badge = $('<span class="badge bg-primary ms-2">'+n+'</span>');
      $('h1 .btn-group, h1').first().append($badge);
    }
  }

  // daterange inputs -> hidden from/to fields for agent bookings
  function bindRange(inputSelector, fromName, toName){
    const $inp = $(inputSelector);
    if(!$inp.length) return;
    function apply(){
      const val = ($inp.val()||'').toString().trim();
      const m = val.match(/^(\d{4}-\d{2}-\d{2})\s*-\s*(\d{4}-\d{2}-\d{2})$/);
      const from = m ? m[1] : '';
      const to = m ? m[2] : '';
      $(`input[name="${fromName}"]`).val(from);
      $(`input[name="${toName}"]`).val(to);
    }
    $inp.on('change blur', apply);
    apply();
  }
  bindRange('input[name="created_range"]','created_from','created_to');
  bindRange('input[name="trip_range"]','trip_from','trip_to');

  // Initialize Litepicker range pickers if available
  if (window.Litepicker) {
    const cr = document.querySelector('input[name="created_range"]');
    if (cr) {
      new Litepicker({
        element: cr,
        singleMode: false,
        format: 'YYYY-MM-DD',
        numberOfMonths: 2,
        numberOfColumns: 2,
        autoApply: true,
        onSelect: function(start, end){
          if(start && end){
            $(cr).val(start.format('YYYY-MM-DD')+' - '+end.format('YYYY-MM-DD')).trigger('change');
          }
        }
      });
    }
    const tr = document.querySelector('input[name="trip_range"]');
    if (tr) {
      new Litepicker({
        element: tr,
        singleMode: false,
        format: 'YYYY-MM-DD',
        numberOfMonths: 2,
        numberOfColumns: 2,
        autoApply: true,
        onSelect: function(start, end){
          if(start && end){
            $(tr).val(start.format('YYYY-MM-DD')+' - '+end.format('YYYY-MM-DD')).trigger('change');
          }
        }
      });
    }
  }
});

