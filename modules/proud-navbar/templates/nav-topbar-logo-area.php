<?php if ( $topbar_logo || $topbar_title ): ?>
    <ul class="logo-menu list-unstyled clearfix">
        <?php if ( $topbar_logo ): ?>
            <li class="h3">
                <a href="<?php echo $topbar_link; ?>" title="<?php echo $proud_topbar_title_attr; ?>" rel="home" id="header-logo-topbar" class="nav-logo same-window">
                    <?php echo $topbar_logo ?>
                </a>
            </li>
        <?php endif; ?>
        <?php if ( $topbar_title ): ?>
            <li class="h3">
                <a href="<?php echo $topbar_link; ?>" title="<?php echo $proud_topbar_title_attr; ?>" rel="home" class="navbar-brand nav-text topbar-title">
                    <strong><?php echo $topbar_title ?></strong>
                </a>
            </li>
         <?php endif; ?>
    </ul>
<?php endif; ?>