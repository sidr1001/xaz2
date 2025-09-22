// Shared UI helpers (price slider)
window.initPriceSlider = function(formSelector){
  var $ = window.jQuery;
  if (typeof noUiSlider === 'undefined') return;
  var $form = $(formSelector);
  var $min = $form.find('#min_price');
  var $max = $form.find('#max_price');
  if ($min.length===0 || $max.length===0) return;
  var min = parseInt($min.attr('min')||$min.val()||'0',10);
  var max = parseInt($max.attr('max')||$max.val()||'0',10);
  var startMin = parseInt($min.val()||min,10);
  var startMax = parseInt($max.val()||max,10);
  var holder = $('<div class="mt-2" id="priceSlider"></div>');
  $max.closest('.col-12, .col-sm-1, .col-sm-2').after(holder);
  noUiSlider.create(holder[0], { start: [startMin, startMax], connect: true, range: { min: min, max: max }, step: 1 });
  holder[0].noUiSlider.on('update', function(values){
    $min.val(Math.round(values[0]));
    $max.val(Math.round(values[1]));
  });
};
