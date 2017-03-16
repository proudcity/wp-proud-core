<ol class="breadcrumb">
  <?php foreach ($active_trail as $item): ?>
    <?php if( !empty( $item['active'] ) ): ?>
    <li class="active">
      <?php echo $item['title'] ?>
    </li>
    <?php else: ?>
    <li>
      <a href="<?php echo $item['url'] ?>" title="<?php echo $item['title'] ?>"><?php echo $item['title'] ?></a>
    </li>
    <?php endif; ?>
  <?php endforeach; ?>
</ol>
