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

  // Global XHR form handler
  $(document).on('submit', 'form[data-xhr] ', function(e){
    e.preventDefault();
    const $f = $(this);
    const url = $f.attr('action') || location.href;
    const method = ($f.attr('method')||'POST').toUpperCase();
    const isMultipart = ($f.attr('enctype')||'').toLowerCase().indexOf('multipart/form-data')>=0;
    const ajaxOpts = { url, type: method, headers: { 'Accept':'application/json' } };
    if (isMultipart) {
      const fd = new FormData(this);
      ajaxOpts.data = fd; ajaxOpts.processData = false; ajaxOpts.contentType = false;
    } else {
      ajaxOpts.data = $f.serialize();
    }
    $.ajax(ajaxOpts).done(function(resp){
      if (resp && resp.ok) {
        if (window.toastr) toastr.success(resp.message || 'Сохранено');
        if (resp.redirect) { window.location.href = resp.redirect; }
      } else {
        if (window.toastr) toastr.error((resp && (resp.error||resp.message)) || 'Ошибка');
      }
    }).fail(function(xhr){
      if (window.toastr) toastr.error('Ошибка сервера: '+xhr.status);
    });
  });

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

