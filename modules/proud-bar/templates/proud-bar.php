<div class="proudbar">
  <div class="proudbar-title pull-left"><a href="//proudcity.com/how-it-works" target="_blank" title="How ProudCity works"><?php print $stage ?></a></div>
  
  <?php if ( 'example' === $stage ): ?> 
    <a href="//proudcity.com" class="proudbar-logo pull-right" target="_blank">ProudCity</a>
    Get started with ProudCity
    <a href="//proudcity.com/start" class="proudbar-btn">Claim your city</a>  
  <?php else: ?>
    <span class="hidden-xs">Welcome to our future website!</span>
    <a href="https://proudcity.typeform.com/to/duZiun" class="proudbar-btn typeform-share" data-mode="2">Feedback</a>
    <?php if (current_user_can( 'manage_options' )): ?> 
      <a href="//proudcity.com/plans" class="proudbar-btn proudbar-btn-circle pull-right" target="_blank" title="Remove this"><i class="fa fa-times"></i></a>
    <?php endif; ?>
  <?php endif; ?>

</div>
<script>(function(){var qs,js,q,s,d=document,gi=d.getElementById,ce=d.createElement,gt=d.getElementsByTagName,id='typef_orm',b='https://s3-eu-west-1.amazonaws.com/share.typeform.com/';if(!gi.call(d,id)){js=ce.call(d,'script');js.id=id;js.src=b+'share.js';q=gt.call(d,'script')[0];q.parentNode.insertBefore(js,q)}id=id+'_';if(!gi.call(d,id)){qs=ce.call(d,'link');qs.rel='stylesheet';qs.id=id;qs.href=b+'share-button.css';s=gt.call(d,'head')[0];s.appendChild(qs,s)}})()</script>
