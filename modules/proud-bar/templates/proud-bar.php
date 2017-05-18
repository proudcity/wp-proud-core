<div class="proudbar">
  <?php if('new' !== $stage): ?><div class="proudbar-title pull-left">
    <a href="//proudcity.com/how-it-works" target="_blank" title="How ProudCity works"><?php print $stage ?></a>
  </div><?php endif; ?>
  
  <?php if ( 'example' === $stage || 'demo' === $stage ): ?> 
    <a href="//proudcity.com/start" class="proudbar-btn">Find your city!</a> 
  <?php else: ?>
    <span class="hidden-xs">
      <?php if(!empty($custom_language)): ?>
        <?php echo $custom_language; ?>
      <?php else: ?>
        Welcome to our <?php if ('new' === $stage): ?>new<?php else: ?>future<?php endif; ?> website!
      <?php endif;?>
      </span>
    <a href="/feedback" class="proudbar-btn ga-event" data-ga-event="feedbackClick" data-mode="2">Feedback</a>
    <?php if (current_user_can( 'manage_options' ) && 'new' !== $stage): ?> 
      <a href="//proudcity.com/plans" class="proudbar-btn proudbar-btn-circle pull-right" target="_blank" title="Remove this"><i aria-hidden="true" class="fa fa-times"></i></a>
    <?php endif; ?>
  <?php endif; ?>

</div>

