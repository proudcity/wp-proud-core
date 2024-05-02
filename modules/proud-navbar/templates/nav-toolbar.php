<h2 id="action-toolbar-label" class="sr-only">Action toolbar</h2><!-- wp-proud-core/modules/proud-navbar/templates/nav-toolbar.php -->
<ul class="btn-toolbar pull-left list-unstyled clearfix" aria-labelledby="action-toolbar-label">
    <?php do_action('proud_nav_toolbar_pre_buttons'); ?>
    <?php
    if (!get_option('proud_hide_toolbar_nav')) : ?>
        <?php foreach ($action_buttons as $button) : ?>
            <li<?php if (!empty($button['dropdowns'])) : ?> class="dropdown" <?php endif; ?>>
                <?php
                if ($button['data_key'] && $button['data_key'] === 'google_translate') : ?>
                    <?php the_widget(
                        'GoogleTranslate',
                        [
                            'id' => 'navbar_translate',
                            'navbar' => true
                        ]
                    ); ?>
                <?php elseif ($button['data_key'] && $button['data_key'] === 'font_size') : ?>
                    <?php echo the_widget(
                        'FontSize',
                        [
                            'id' => 'navbar_fontsize',
                            'navbar' => true
                        ]
                    ); ?>
                <?php else : ?>
                    <a title="<?php echo $button['title'] ?>" <?php if ($button['data_key']) : ?>data-proud-navbar="<?php echo $button['data_key'] ?>" <?php endif; ?><?php echo $button['data_attrs'] ?> href="<?php echo $button['href'] ?>" class="<?php echo $button['classes'] ?>"><i aria-hidden="true" class="fa <?php echo $button['icon'] ?>"></i> <?php echo $button['title'] ?></a>
                <?php endif; ?>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
</ul>
<ul class="btn-toolbar pull-right list-unstyled clearfix">
    <li><a title="<?php echo $search_button['title'] ?>" <?php if ($search_button['data_key']) : ?> data-proud-navbar="<?php echo $search_button['data_key'] ?>" <?php endif; ?><?php echo $search_button['data_attrs'] ?> href="<?php echo $search_button['href'] ?>" class="<?php echo $search_button['classes'] ?>"><i aria-hidden="true" class="fa <?php echo $search_button['icon'] ?>"></i> <?php echo $search_button['title'] ?></a></li>
</ul>
