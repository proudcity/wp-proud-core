<ul class="list-unstyled">
  <?php foreach ($social_accounts as $key => $account): ?>
    <li><a title="<?php echo $account['service'] ?>" href="<?php echo $account['url'] ?>" target="_blank"><i aria-hidden="true" class="fa icon-even-width fa-<?php echo strtolower( $account['service'] ) ?>"></i> <?php echo $account['service'] ?></a></li>
  <?php endforeach; ?>
</ul>