<?php use SailConnect\Auth\View; $e = [View::class, 'e']; ?>
<div class="card">
  <h2>Join or sign in</h2>
  <p class="hint">Enter your email address. We will find your Sail Connect account, or help you create one.</p>
  <?php if ($error !== ''): ?><p class="notice bad"><?= $e($error) ?></p><?php endif; ?>
  <form method="post" action="/start">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="return" value="<?= $e($return) ?>">
    <?= $this->partial('_field', ['name' => 'email_address', 'label' => 'Email address', 'type' => 'email', 'value' => $email, 'errors' => [], 'autocomplete' => 'username']) ?>
    <button type="submit">Continue</button>
  </form>
</div>
