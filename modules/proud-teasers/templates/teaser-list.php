<div <?php post_class("teaser"); ?>><!-- template-file: wp-proud-core/modules/proud-teasers/templates/teaser-list.php -->
    <div class="row">
        <?php if (\Proud\Core\TeaserList::has_thumbnail()) : ?>
            <div class="col-md-3 pull-right">
                <?php \Proud\Core\TeaserList::print_teaser_thumbnail(); ?>
            </div>
        <?php endif; ?>
        <div class="col-md-9 pull-left">
            <?php the_title(sprintf('<h3 class="h4 entry-title"><a href="%s" rel="bookmark">', esc_url(get_permalink())), '</a></h3>'); ?>
            <p class="text-muted"><?php echo __('Posted on', 'wp-proud-core') ?> <?php echo get_the_date(); ?></p>
            <?php do_action('teaser_search_matching', $post); ?>
            <?php echo \Proud\Core\wp_trim_excerpt(); ?>
        </div>
    </div>
</div>
