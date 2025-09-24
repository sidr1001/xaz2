// Admin AJAX and modals
$(function(){
  $(document).ajaxError(function(_, xhr){
    console.error('AJAX error', xhr.status, xhr.responseText);
  });

  // Example modal confirm usage can be hooked per-page

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

// Placeholder for future AJAX enhancements
(function(){
  // Example: could attach global AJAX error handler here
})();

