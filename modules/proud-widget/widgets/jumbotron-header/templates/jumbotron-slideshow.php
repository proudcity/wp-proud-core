<div id="<?php print $random_id ?>" class="carousel slide" data-ride="carousel">
    <!-- Wrapper for slides -->
    <div class="carousel-inner" role="listbox">
        <?php foreach ($instance['slideshow'] as $key => $slide) : ?>
            <div class="item<?php echo $key === 0 ? ' active' : '' ?>">
                <div class="jumbo-header jumbotron-header-container">
                    <div class="<?php print implode(' ', $classes) ?>" style="<?php print implode('', $arr_styles) ?>">
                        <?php if (!empty($slide['resp_img'])) {
                            Proud\Core\print_responsive_image($slide['resp_img'], $resp_img_classes);
                        } ?>
                        <div class="container">
                            <div class="row">
                                <div class="<?php echo $jumbotron_col_classes; ?>" style="float:none;margin-left:auto;margin-right:auto;text-align:center;">
                                    <div class="jumbotron-bg">
                                        <div class="jumbotron-bg-mask"></div>
                                        <div class="carousel-caption-jumbotron">
                                            <?php if (!empty($slide['slide_title'])) : ?>
                                                <h2 class="h1"><?php print $slide['slide_title']; ?></h2>
                                            <?php endif; ?>
                                            <?php if (!empty($slide['description'])) : ?>
                                                <p class="lead"><?php print $slide['description']; ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($slide['link_url'])) : ?>
                                                <a class="btn btn-primary" href="<?php print $slide['link_url']; ?>"><?php print $slide['link_title']; ?></a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Controls -->
    <a class="left carousel-control" href="#<?php print $random_id ?>" role="button" data-slide="prev">
        <span class="fa fa-chevron-left" aria-hidden="true"></span>
        <span class="sr-only">Previous</span>
    </a>
    <a class="right carousel-control" href="#<?php print $random_id ?>" role="button" data-slide="next">
        <span class="fa fa-chevron-right"></span>
        <span class="sr-only">Next</span>
    </a>
</div>