<?php
  use Proud\Core;

if (!empty($imageset)) :
    $class = ((int) $across === 3) ? 'card-columns-md-3' : 'card-columns-md-2';

    // The card image is full bleed, so it renders as wide as the card rather
    // than sitting at its intrinsic 300px inside it. WordPress derives the
    // sizes attribute from the requested size and would emit
    // '(max-width: 300px) 100vw, 300px', understating the rendered width and
    // keeping the browser on the 300w candidate.
    //
    // The breakpoint is 780px, not Bootstrap's stock 768px: proudcity-patterns
    // sets $screen-sm to 780px, and that is where card-columns-xs-2 hands over
    // to card-columns-sm-2. Below it the grid is two up, so each card is
    // roughly half the viewport.
    //
    // Above it the width depends on the column count. With $container-lg at
    // 1064px and a 1.25rem column gap, three across is about 330px of card and
    // two across about 500px. A sidebar narrows both, so these are upper
    // bounds -- deliberately, since overstating sizes costs bandwidth while
    // understating it renders blurry.
    $image_sizes = ((int) $across === 3)
        ? '(max-width: 779px) 50vw, 340px'
        : '(max-width: 779px) 50vw, 500px';
    ?>

<div class="card-columns card-columns-xs-2 card-columns-sm-2 <?php echo $class ?> card-columns-equalize image-set-cards"><!-- template-file: wp-proud-core/modules/proud-widget/widgets/image-set/templates/image-cards.php -->
    <?php foreach ( $imageset as $image ) : ?>
        <?php if (!empty($image['link_title']) && !empty($image['link_url']) && !empty($image['image'])) : ?>
            <?php
            // Resolve the attachment before deciding whether to emit the
            // thumbnail wrapper. .card-img-top is a fixed-ratio box in the
            // theme, so emitting it for an attachment that no longer resolves
            // would reserve a blank band above the title. Previously the same
            // wrapper collapsed to zero height and went unnoticed.
            $meta = is_numeric($image['image'])
                  ? Core\build_responsive_image_meta($image['image'], 'card-thumb', 'card-thumb')
                  : null;
            $has_image = !empty($meta['src']);
            if ($has_image) {
                $meta['size'] = $image_sizes;
            }
            ?>
            <div class="card-wrap"><div class="card">
                <?php if ($has_image) : ?>
                    <div class="card-img-top text-center">
                        <a href="<?php print Core\esc_link_url( $image['link_url'] ) ?>"<?php if ( !empty( $image['external'] ) ): ?> target="_blank" rel="noopener"<?php endif; ?>>
                            <?php Core\print_responsive_image($meta, [], true); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="card-block">
                    <div class="h3 margin-top-none">
                        <a href="<?php print Core\esc_link_url( $image['link_url'] ) ?>"<?php if ( !empty( $image['external'] ) ): ?> target="_blank" rel="noopener"<?php endif; ?>>
                            <?php print Core\esc_widget_title( $image['link_title'] ) ?>
                        </a>
                    </div>
                        <?php if (!empty($image['text'])) : ?>
                            <p class="margin-bottom-none"><?php echo esc_html( $image['text'] ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
