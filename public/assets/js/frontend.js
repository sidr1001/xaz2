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
});

