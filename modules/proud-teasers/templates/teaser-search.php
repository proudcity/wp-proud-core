<div <?php post_class( "teaser-list search-teaser" ); ?>>
  <div class="row">
    <div class="col-md-12">
      <div class="search-title">
        <?php the_title( sprintf( '<h4 class="entry-title margin-top-large"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h4>' ); ?>
        <i class="fa fa-2x <?php echo $search_meta['icon'] ?>"></i>
      </div>
      <p><?php echo \Proud\Core\wp_trim_excerpt(); ?></p>
    </div>
  </div>
</div>