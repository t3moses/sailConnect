<?php use SailConnect\Auth\View; $e = [View::class, 'e'];
$notices = ['signed-out' => 'You have been signed out of all Sail Connect applications.', 'password-reset' => 'Your password was changed. Please sign in.']; ?>
<div class="card">
  <h2>Sign in</h2>
  <?php if (!empty($notices[$notice])): ?><p class="notice"><?= $e($notices[$notice]) ?></p><?php endif; ?>
  <?php if (!empty($errors['form'])): ?><p class="notice bad"><?= $e($errors['form']) ?></p><?php endif; ?>
  <form method="post" action="/signin">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="return" value="<?= $e($return) ?>">
    <?= $this->partial('_field', ['name' => 'email_address', 'label' => 'Email address', 'type' => 'email', 'value' => $email, 'errors' => $errors, 'autocomplete' => 'username']) ?>
    <?= $this->partial('_field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'errors' => $errors, 'autocomplete' => 'current-password']) ?>
    <button type="submit">Sign in</button>
  </form>
  <p class="hint"><a href="/forgot-password">Forgot your password?</a> · <a href="/register<?= $return !== '' ? '?return=' . $e(urlencode($return)) : '' ?>">Create an account</a></p>
</div>
