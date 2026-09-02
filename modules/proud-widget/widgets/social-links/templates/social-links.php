<ul class="list-unstyled">
    <?php foreach ($social_accounts as $key => $account): ?>
        <?php
        /**
         * Ultimately this hacks around a random issue where an account is expected but has no data.
         * See: https://github.com/proudcity/wp-proudcity/issues/2764
         * There is an errant twitter account there that I can't defined anywhere so the $account
         * has no values and errors get thrown. This just drops it if there is no information in $account.
         */
        if (!isset($account)) continue;
        ?>
        <li><a title="<?php echo esc_attr($account['service']) ?>" href="<?php echo esc_url($account['url']) ?>" target="_blank" rel="noopener"><i aria-hidden="true" class="fa icon-even-width fa-<?php echo esc_attr(strtolower($account['service'])) ?>"></i><?php echo esc_html($account['service']) ?></a></li>
    <?php endforeach; ?>
</ul>
