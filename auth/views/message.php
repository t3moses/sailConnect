<?php use SailConnect\Auth\View; ?>
<div class="card">
  <h2><?= View::e($heading) ?></h2>
  <p><?= View::e($message) ?></p>
  <?php if (!empty($link)): ?><a class="btn" href="<?= View::e($link[0]) ?>"><?= View::e($link[1]) ?></a><?php endif; ?>
</div>
