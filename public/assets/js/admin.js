// Admin AJAX and modals
$(function(){
  $(document).ajaxError(function(_, xhr){
    console.error('AJAX error', xhr.status, xhr.responseText);
  });

  // Example modal confirm usage can be hooked per-page

  // mark user interaction on daterange inputs
  $('input[name="created_range"], input[name="trip_range"]').on('change input', function(){
    $(this).data('userSet', true);
  });

  // daterange inputs -> hidden from/to fields
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
        autoApply: true
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
        autoApply: true
      });
    }
  }

  // Optional: if a badge is used to indicate active filters, ensure ranges count only when user interacted
  const $filters = $('#admin-filters');
  function countActive($form){
    let c=0;
    $form.find('input:not([type=hidden]), select, textarea').each(function(){
      const $el = $(this);
      const n = $el.attr('name'); if(!n) return;
      if(n==='created_range' || n==='trip_range'){
        const v = ($el.val()||'').toString().trim();
        const ok = $el.data('userSet') && /^(\d{4}-\d{2}-\d{2})\s*-\s*(\d{4}-\d{2}-\d{2})$/.test(v);
        if(ok) c++;
        return;
      }
      const v = $el.val(); if(v && v!=='' && n!=='page'){ c++; }
    });
    return c;
  }
  if($filters.length){
    const n = countActive($filters);
    if(n>0){
      const $badge = $('<span class="badge bg-primary ms-2">'+n+'</span>');
      $('h1').first().append($badge);
    }
  }
});

// Placeholder for future AJAX enhancements
(function(){
  // Example: could attach global AJAX error handler here
})();

