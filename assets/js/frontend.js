$(function(){
  // AJAX filters with pagination load more
  const $list = $('#tours-list');
  const $filters = $('#filters');
  let page = 1;
  function load(reset){
    const data = $filters.serialize() + '&ajax=1&page=' + page;
    $.get('/', data, function(html){
      if(reset){ $list.html(html); } else { $list.append(html); }
    });
  }
  $('#apply-filters').on('click', function(e){ e.preventDefault(); page=1; load(true); });
  $('#load-more').on('click', function(e){ e.preventDefault(); page++; load(false); });

  // Price slider sync
  const $range = $('#price_range');
  const $min = $('#min_price');
  const $max = $('#max_price');
  if($range.length){
    const minAttr = parseInt($range.attr('min')||'0',10);
    const maxAttr = parseInt($range.attr('max')||'0',10);
    const startMin = parseInt($min.val()||minAttr,10);
    const startMax = parseInt($max.val()||maxAttr,10);
    // single slider controls max; keep min in input
    $range.val(startMax);
    $range.on('input change', function(){ $max.val($(this).val()); });
    $min.on('input change', function(){ if(parseInt($(this).val()||0,10) > parseInt($range.val()||0,10)){ $(this).val($range.val()); } });
  }
});

