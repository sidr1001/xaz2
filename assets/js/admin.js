// Admin AJAX and modals
$(function(){
  $(document).ajaxError(function(_, xhr){
    console.error('AJAX error', xhr.status, xhr.responseText);
  });

  // Global Toastr defaults
  if (window.toastr) {
    toastr.options = {
      closeButton: true,
      progressBar: true,
      positionClass: 'toast-bottom-right',
      timeOut: 4000
    };
  }

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
});

// Placeholder for future AJAX enhancements
(function(){
  // Example: could attach global AJAX error handler here
})();

