<ul class="list-unstyled">
  <?php foreach ($social_accounts as $key => $account): ?>
    <li><a href="<?php echo $account['url'] ?>" target="_blank"><i class="fa icon-even-width fa-<?php echo strtolower( $account['service'] ) ?>"></i> Find us on <?php echo $account['service'] ?></a></li>
  <?php endforeach; ?>
</ul>