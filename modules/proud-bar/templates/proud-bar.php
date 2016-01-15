<div class="proudbar">
  <div class="proudbar-title"><a href="//proudcity.com/how-it-works" target="_blank" title="How ProudCity works"><?php print $stage ?></a></div>
  
  <?php if ( 'example' !== $stage ): ?> 
    <a href="https://insights.hotjar.com/s?siteId=124068&surveyId=6063" class="proudbar-btn">Feedback</a>
    <a href="//proudcity.com/how-it-works" class="proudbar-btn proudbar-btn-circle" target="_blank" title="What is this?"><i class="fa fa-question"></i></a>
    <a href="//proudcity.com/plans" class="proudbar-btn proudbar-btn-circle" target="_blank" title="Remove this"><i class="fa fa-times"></i></a>
  <?php else: ?>
    <a href="//proudcity.com/start" class="proudbar-btn">Claim your city</a>
  <?php endif; ?>

  <a href="//proudcity.com" class="proudbar-logo" target="_blank">ProudCity</a>
</div>