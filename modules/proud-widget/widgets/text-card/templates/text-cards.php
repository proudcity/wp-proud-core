<?php

use Proud\Core;

if (!empty($textset)) :
    $class = ((int) $across === 3) ? 'card-columns-md-3' : 'card-columns-md-2';
?>

    <div class="card-columns card-columns-xs-2 card-columns-sm-2 <?php echo sanitize_html_class($class); ?> card-columns-equalize text-card"><!-- template-file: wp-proud-core/modules/proud-widget/widgets/text-widget/templates/text-card.php -->
        <?php foreach ($textset as $textcard) : ?>

            <?php if (isset($textcard['link_url']) && !empty($textcard['link_url'])) { ?>
                <a href="<?php echo esc_url($textcard['link_url']); ?>">
                <?php } ?>

                <div class="card-wrap">
                    <div class="card">
                        <div class="card-block">
                            <h3>
                                <?php echo esc_attr($textcard['text_title']); ?>
                            </h3>
                            <?php if (!empty($textcard['text'])) : ?>
                                <p class="margin-bottom-none"><?php echo $textcard['text']; ?></p>
                            <?php endif; ?>
                        </div><!-- /.card-block -->
                    </div><!-- /.card -->
                </div><!-- /.card-wrap -->

                <?php if (isset($textcard['link_url']) && !empty($textcard['link_url'])) { ?>
                </a>
            <?php } ?>

        <?php endforeach; ?>
    </div>
<?php endif; ?>
