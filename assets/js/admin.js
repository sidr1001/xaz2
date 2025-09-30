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

  // Ensure non-AJAX forms carry CSRF token
  $(document).on('submit', 'form:not([data-xhr])', function(){
    const $f = $(this);
    if ($f.find('input[name="_csrf"]').length === 0) {
      const token = $('meta[name="csrf-token"]').attr('content');
      if (token) { $('<input type="hidden" name="_csrf">').val(token).appendTo($f); }
    }
  });

  // Global XHR form handler
  $(document).on('submit', 'form[data-xhr]', function(e){
    e.preventDefault();
    const $f = $(this);
    // clear previous errors
    $f.find('.is-invalid').removeClass('is-invalid');
    $f.find('.js-error').remove();
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
        if (resp.redirect) { window.location.href = resp.redirect; return; }
        if ($f.is('[data-success-reload]')) { window.location.reload(); return; }
      } else {
        if (resp && resp.errors && typeof resp.errors === 'object') {
          Object.keys(resp.errors).forEach(function(name){
            const $field = $f.find('[name="'+name+'"]');
            if ($field.length) {
              $field.addClass('is-invalid');
              $('<div class="form-text text-danger js-error"></div>').text(resp.errors[name]).insertAfter($field);
            }
          });
        }
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

