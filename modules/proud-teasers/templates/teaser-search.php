<div <?php post_class( "teaser search-teaser" ); ?>>
  <div class="row">
    <div class="col-md-12">
      <div class="search-title">
        <h4 class="entry-title margin-top-large"><?php echo $proudsearch->get_post_link($post) ?></h4>
        <i aria-hidden="true" class="fa <?php echo $search_meta['icon'] ?>"></i>
      </div>
      <?php if( $post->post_type === 'event' ): ?>
      <p class="text-muted"><?php echo get_the_date(); ?></p>
      <?php endif; ?>
      <p><?php echo \Proud\Core\wp_trim_excerpt( '', false, true ); ?></p>
      <?php echo $proudsearch->get_post_link($post, 'See more') ?>
    </div>
  </div>
</div>