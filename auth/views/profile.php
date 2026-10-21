<?php use SailConnect\Auth\View; $e = [View::class, 'e'];
$notices = ['welcome' => 'Welcome to Sail Connect! A confirmation was sent to your email address.', 'saved' => 'Profile saved.', 'password-saved' => 'Password changed. Other devices were signed out.', 'email-sent' => 'Confirmation link sent to the new address. Your email will change once you click it.'];
$f = $v + ['first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'is_member' => (bool) $u['is_member'], 'membership_number' => (string) $u['membership_number'], 'mobile_number' => (string) $u['mobile_number']]; ?>
<?php if (!empty($notices[$notice])): ?><p class="notice"><?= $e($notices[$notice]) ?></p><?php endif; ?>
<div class="card">
  <h2>Your profile</h2>
  <p class="hint">Display name: <strong><?= $e($u['display_name']) ?></strong> · <?= $e($u['email_address']) ?></p>
  <form method="post" action="/profile">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <?= $this->partial('_field', ['name' => 'first_name', 'label' => 'First name', 'value' => $f['first_name'], 'errors' => $errors]) ?>
    <?= $this->partial('_field', ['name' => 'last_name', 'label' => 'Last name', 'value' => $f['last_name'], 'errors' => $errors]) ?>
    <?= $this->partial('_field', ['name' => 'mobile_number', 'label' => 'Mobile number (optional)', 'type' => 'tel', 'optional' => true, 'value' => $f['mobile_number'], 'errors' => $errors, 'autocomplete' => 'tel']) ?>
    <label class="check"><input type="checkbox" name="is_member" value="1" id="is_member" <?= $f['is_member'] ? 'checked' : '' ?>> I am a club member</label>
    <div id="member_box">
      <label for="membership_number">Membership number</label>
      <input type="text" id="membership_number" name="membership_number" value="<?= $e($f['membership_number']) ?>" maxlength="20">
      <?php if (!empty($errors['membership_number'])): ?><p class="err"><?= $e($errors['membership_number']) ?></p><?php endif; ?>
    </div>
    <button type="submit">Save</button>
  </form>
</div>
<div class="card">
  <h2>Change email address</h2>
  <form method="post" action="/profile/email">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <?= $this->partial('_field', ['name' => 'new_email', 'label' => 'New email address', 'type' => 'email', 'value' => '', 'errors' => $errors]) ?>
    <?= $this->partial('_field', ['name' => 'new_email_confirm', 'label' => 'Confirm new email address', 'type' => 'email', 'value' => '', 'errors' => []]) ?>
    <?= $this->partial('_field', ['name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'errors' => ['current_password' => $errors['email_password'] ?? ''], 'autocomplete' => 'current-password']) ?>
    <button type="submit">Send confirmation link</button>
  </form>
</div>
<div class="card">
  <h2>Change password</h2>
  <form method="post" action="/profile/password">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <?= $this->partial('_field', ['name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'errors' => ['current_password' => $errors['pw_current'] ?? ''], 'autocomplete' => 'current-password']) ?>
    <?= $this->partial('_field', ['name' => 'new_password', 'label' => 'New password', 'type' => 'password', 'errors' => $errors, 'hint' => $rules, 'autocomplete' => 'new-password']) ?>
    <?= $this->partial('_field', ['name' => 'new_password_confirm', 'label' => 'Confirm new password', 'type' => 'password', 'errors' => [], 'autocomplete' => 'new-password']) ?>
    <button type="submit">Change password</button>
  </form>
</div>
<script>
  const cb = document.getElementById('is_member'), box = document.getElementById('member_box');
  const sync = () => { box.hidden = !cb.checked; };
  cb.addEventListener('change', sync); sync();
</script>
