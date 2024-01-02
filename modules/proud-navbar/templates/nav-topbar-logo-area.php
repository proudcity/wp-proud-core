<?php if ( $topbar_logo || $topbar_title ): ?>
    <ul class="logo-menu list-unstyled clearfix">
        <?php if ( $topbar_logo ): ?>
            <li class="h3">
                <a href="<?php echo esc_url( $topbar_link ); ?>" title="<?php echo esc_attr( $topbar_title_attr ); ?>" rel="home" id="header-logo-topbar" class="nav-logo same-window">
                    <?php echo wp_kses( $topbar_logo, 'post' ); ?>
                </a>
            </li>
        <?php endif; ?>
        <?php if ( $topbar_title ): ?>
            <li class="h3">
                <a href="<?php echo esc_url( $topbar_link ); ?>" title="<?php echo esc_attr( $topbar_title_attr ); ?>" rel="home" class="navbar-brand nav-text topbar-title">
                    <?php echo esc_attr( $topbar_title ); ?>
                </a>
            </li>
         <?php endif; ?>
    </ul>
<?php endif; ?>
