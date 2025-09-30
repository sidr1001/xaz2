// Agent panel JS placeholder
$(function(){
  // future: agent-specific AJAX actions

  // Global Toastr defaults
  if (window.toastr) {
    toastr.options = {
      closeButton: true,
      progressBar: true,
      positionClass: 'toast-bottom-right',
      timeOut: 4000
    };
  }

  // Ensure CSRF header is set for all AJAX
  (function(){ var t=document.querySelector('meta[name="csrf-token"]'); if(t){ $.ajaxSetup({ headers: { 'X-CSRF-Token': t.getAttribute('content') } }); } })();

  // Global XHR form handler (agent)
  $(document).on('submit', 'form[data-xhr]', function(e){
    e.preventDefault();
    const $f = $(this);
    const url = $f.attr('action') || location.href;
    const method = ($f.attr('method')||'POST').toUpperCase();
    const token = $('meta[name="csrf-token"]').attr('content') || '';
    const isMultipart = ($f.attr('enctype')||'').toLowerCase().indexOf('multipart/form-data')>=0;
    const ajaxOpts = { url, type: method, headers: { 'Accept':'application/json', 'X-CSRF-Token': token } };
    if (isMultipart) {
      const fd = new FormData(this);
      if (token && !fd.has('_csrf')) { fd.append('_csrf', token); }
      ajaxOpts.data = fd; ajaxOpts.processData = false; ajaxOpts.contentType = false;
    } else {
      const qs = $f.serialize();
      ajaxOpts.data = qs + (qs ? '&' : '') + '_csrf=' + encodeURIComponent(token);
    }
    $.ajax(ajaxOpts).done(function(resp){
      if (resp && resp.ok){
        if (resp.message && window.toastr) toastr.success(resp.message);
        if (resp.redirect) { window.location.href = resp.redirect; return; }
        if ($f.is('[data-success-reload]')) { window.location.reload(); }
      } else {
        if (resp && resp.errors) {
          Object.keys(resp.errors).forEach(function(n){ var $fld=$f.find('[name="'+n+'"]'); if($fld.length){ $fld.addClass('is-invalid'); $('<div class="form-text text-danger"></div>').text(resp.errors[n]).insertAfter($fld);} });
        }
        if (window.toastr) toastr.error((resp && (resp.error||resp.message)) || 'Ошибка');
      }
    }).fail(function(xhr){ if (window.toastr) toastr.error('Ошибка сервера: '+xhr.status); });
  });

  // Ensure non-AJAX forms carry CSRF hidden input
  $(document).on('submit', 'form:not([data-xhr])', function(){
    const $f = $(this);
    if ($f.find('input[name="_csrf"]').length === 0) {
      const token = $('meta[name="csrf-token"]').attr('content');
      if (token) { $('<input type="hidden" name="_csrf">').val(token).appendTo($f); }
    }
  });

  // mark user interaction on daterange inputs
  $('input[name="created_range"], input[name="trip_range"]').on('change input', function(){
    $(this).data('userSet', true);
  });

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
});

