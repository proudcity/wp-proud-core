<div <?php post_class( "teaser search-teaser" ); ?>>
  <div class="row">
    <div class="col-md-12">
      <div class="search-title">
        <h4 class="entry-title margin-top-large"><?php echo $proudsearch->get_post_link($post) ?></h4>
        <i class="fa fa-2x <?php echo $search_meta['icon'] ?>"></i>
      </div>
      <p><?php echo \Proud\Core\wp_trim_excerpt('', false); ?></p>
      <?php echo $proudsearch->get_post_link($post, 'See more') ?>
    </div>
  </div>
</div>