<?php use SailConnect\Auth\View; $e = [View::class, 'e']; ?>
<div class="card">
  <h2>Choose a new password</h2>
  <form method="post" action="/reset-password">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="token" value="<?= $e($token) ?>">
    <?= $this->partial('_field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'errors' => $errors, 'hint' => $rules, 'autocomplete' => 'new-password']) ?>
    <?= $this->partial('_field', ['name' => 'password_confirm', 'label' => 'Confirm new password', 'type' => 'password', 'errors' => $errors, 'autocomplete' => 'new-password']) ?>
    <button type="submit">Change password</button>
  </form>
</div>
