<?php if ($topbar_official) : ?>
    <div class="official-topbar-outer">
        <?php if ($topbar_logo) : ?>
            <span class="h3">
                <a href="<?php echo get_logo_link_url(); ?>" title="Home" rel="home" id="header-logo-topbar" class="nav-logo same-window">
                    <?php echo $topbar_logo ?>
                </a>
            </span>
        <?php endif; ?>
        <div class="h3 official-title">An official website of the United States government <button type="button" class="btn btn-link" data-toggle="collapse" data-target="#collapseWhy" aria-expanded="false" aria-controls="collapseWhy">Here's how you know</button></div>
        <div class="official-topbar-row-outer row">
            <div class="col-md-12">
                <div class="collapse official-topbar-collapse" id="collapseWhy">
                    <div class="well">
                        <div class="row">
                            <div class="teaser media col-md-6">
                                <div class="media-left">
                                    <i aria-hidden="true" class="fa fa-landmark"></i>
                                </div>
                                <div class="media-body">
                                    <h3 class="entry-title media-heading">Official websites use .gov</h3>
                                    <p>A .<strong>gov </strong> website belongs to an official government organization in the United States.</p>
                                </div>
                            </div>
                            <div class="teaser media col-md-6">
                                <div class="media-left">
                                    <i aria-hidden="true" class="fa fa-lock"></i>
                                </div>
                                <div class="media-body">
                                    <h3 class="entry-title media-heading">Secure .gov websites use HTTPS</h3>
                                    <p>EA lock (<strong><i aria-hidden="true" class="fa fa-lock"></i></strong>) or https:// means you’ve safely connected to the .gov website. Share sensitive information only on official, secure websites.

                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($topbar_logo || $topbar_title) : ?>
    <ul class="logo-menu list-unstyled clearfix">
        <?php if ($topbar_logo) : ?>
            <li class="h3">
                <a href="<?php echo $topbar_link; ?>" title="Home" rel="home" id="header-logo-topbar" class="nav-logo same-window">
                    <?php echo $topbar_logo ?>
                </a>
            </li>
        <?php endif; ?>
        <?php if ($topbar_title) : ?>
            <li class="h3">
                <a href="<?php echo $topbar_link; ?>" title="Home" rel="home" class="navbar-brand nav-text topbar-title">
                    <strong><?php echo $topbar_title ?></strong>
                </a>
            </li>
        <?php endif; ?>
    </ul>
<?php endif; ?>