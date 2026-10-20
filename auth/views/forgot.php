<?php use SailConnect\Auth\View; $e = [View::class, 'e']; ?>
<div class="card">
  <h1>Reset your password</h1>
  <?php if ($sent): ?>
    <p class="notice">If an account exists for that address, a reset link is on its way. It expires in one hour.</p>
  <?php else: ?>
    <form method="post" action="/forgot-password">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <?= $this->partial('_field', ['name' => 'email_address', 'label' => 'Email address', 'type' => 'email', 'value' => $email, 'errors' => []]) ?>
      <button type="submit">Send reset link</button>
    </form>
  <?php endif; ?>
  <p class="hint"><a href="/signin">Back to sign in</a></p>
</div>
