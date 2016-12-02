<div class="panel panel-default accordion">
  <div class="panel-heading">
    <h4 class="panel-title">
      <a data-toggle="collapse" data-parent="#accordion" href="#collapse<?php echo the_id(); ?>">
        <?php echo the_title(); ?> 
      </a>
    </h4>
  </div>
  <div id="collapse<?php echo the_id(); ?>" class="panel-collapse collapse">
    <div class="panel-body">
      <?php echo the_content(); ?> 
    </div>
  </div>
</div>