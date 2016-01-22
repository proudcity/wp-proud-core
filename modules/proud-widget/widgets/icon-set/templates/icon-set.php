<div class="card-columns card-columns-xs-1 card-columns-sm-2 card-columns-md-4 card-columns-equalize">
  <?php foreach ($iconset as $icon) : ?>
    <div class="card-wrap"><a href="<?print $icon['url'] ?>" class="card text-center card-btn card-block">
      <i class="fa <?print $icon['icon'] ?> fa-3x"></i>
      <h3><?print $icon['title'] ?></h3>
    </a></div><!--seperate-->
  <?php endforeach; ?>
</div>