    <a href="<?php echo esc_url($item['url']); ?>">

        <div class="card-wrap">
            <div class="card">
                <div class="card-block">
                    <h3>
                        <?php echo esc_attr($item['title']); ?>
                    </h3>
                    <?php if (!empty($item['excerpt'])) : ?>
                        <p class="card-excerpt"><?php echo esc_html($item['excerpt']); ?></p>
                    <?php endif; ?>
                </div><!-- /.card-block -->
            </div><!-- /.card -->
        </div><!-- /.card-wrap -->

    </a>
