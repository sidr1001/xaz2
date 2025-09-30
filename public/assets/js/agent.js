// Agent panel compiled JS
$(function(){
  // CSRF header for AJAX
  (function(){ var t=document.querySelector('meta[name="csrf-token"]'); if(t){ $.ajaxSetup({ headers: { 'X-CSRF-Token': t.getAttribute('content') } }); } })();

  // Global XHR form handler (agent)
  $(document).on('submit', 'form[data-xhr]', function(e){
    console.log('agent xhr submit');
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
        if (window.toastr) toastr.error((resp && (resp.error||resp.message)) || 'Ошибка');
      }
    }).fail(function(xhr){ if (window.toastr) toastr.error('Ошибка сервера: '+xhr.status); });
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

  // If a countActive badge is used on the page, improve its logic
  const $filtersForm = $('#agent-filters');
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
  if($filtersForm.length){
    const n = countActive($filtersForm);
    if(n>0){
      const $badge = $('<span class="badge bg-primary ms-2">'+n+'</span>');
      $('h1 .btn-group, h1').first().append($badge);
    }
  }
});

