jQuery(function($) {
  $(document).ready(function() {
    // Show WR PageBuilder if tab "Page Builder" is active
    if ($("#wr_active_tab").val() != "1") {
      $("#content-tmce").click();
    }
  });
});