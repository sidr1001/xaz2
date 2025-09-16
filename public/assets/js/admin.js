// Admin AJAX and modals
$(function(){
  $(document).ajaxError(function(_, xhr){
    console.error('AJAX error', xhr.status, xhr.responseText);
  });

  // Example modal confirm usage can be hooked per-page
});

// Placeholder for future AJAX enhancements
(function(){
  // Example: could attach global AJAX error handler here
})();

