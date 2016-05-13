<div class="proudbar">
  <div class="proudbar-title pull-left"><a href="//proudcity.com/how-it-works" target="_blank" title="How ProudCity works"><?php print $stage ?></a></div>
  
  <?php if ( 'example' === $stage || 'demo' === $stage ): ?> 
    <a href="//proudcity.com/start" class="proudbar-btn">Get your free BETA</a>  
  <?php else: ?>
    <span class="hidden-xs">Welcome to our future website!</span>
    <a href="/feedback" class="proudbar-btn ga-event" data-ga-event="feedbackClick" data-mode="2">Feedback</a>
    <?php if (current_user_can( 'manage_options' )): ?> 
      <a href="//proudcity.com/plans" class="proudbar-btn proudbar-btn-circle pull-right" target="_blank" title="Remove this"><i class="fa fa-times"></i></a>
    <?php endif; ?>
  <?php endif; ?>

</div>

