<a href="<?php echo $item['url'] ?>" title="<?php echo $item['title'] ?>" class="list-group-item<?php echo (!empty( $item['active'] ) ? ' active' : '') ?>"<?php echo (!empty( $item['active_trail'] ) ? ' data-active-click="' . $active . '"' : '') ?>><?php echo $item['title'] ?></a>