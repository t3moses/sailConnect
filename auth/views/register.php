<?php use SailConnect\Auth\View; $e = [View::class, 'e']; $v += ['email_address' => '', 'email_confirm' => '', 'first_name' => '', 'last_name' => '', 'is_member' => false, 'membership_number' => '', 'mobile_number' => '']; ?>
<div class="card">
  <h2>Create your account</h2>
  <p class="hint">One account works across all Sail Connect applications.</p>
  <form method="post" action="/register<?= $return !== '' ? '?return=' . $e(urlencode($return)) : '' ?>">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="return" value="<?= $e($return) ?>">
    <?= $this->partial('_field', ['name' => 'first_name', 'label' => 'First name', 'value' => $v['first_name'], 'errors' => $errors, 'autocomplete' => 'given-name']) ?>
    <?= $this->partial('_field', ['name' => 'last_name', 'label' => 'Last name', 'value' => $v['last_name'], 'errors' => $errors, 'autocomplete' => 'family-name']) ?>
    <?= $this->partial('_field', ['name' => 'email_address', 'label' => 'Email address', 'type' => 'email', 'value' => $v['email_address'], 'errors' => $errors, 'autocomplete' => 'email']) ?>
    <?= $this->partial('_field', ['name' => 'email_confirm', 'label' => 'Confirm email address', 'type' => 'email', 'value' => $v['email_confirm'], 'errors' => $errors]) ?>
    <?= $this->partial('_field', ['name' => 'mobile_number', 'label' => 'Mobile number (optional)', 'type' => 'tel', 'optional' => true, 'value' => $v['mobile_number'], 'errors' => $errors, 'autocomplete' => 'tel']) ?>
    <?= $this->partial('_field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'errors' => $errors, 'hint' => $rules, 'autocomplete' => 'new-password']) ?>
    <?= $this->partial('_field', ['name' => 'password_confirm', 'label' => 'Confirm password', 'type' => 'password', 'errors' => $errors, 'autocomplete' => 'new-password']) ?>

    <label class="check"><input type="checkbox" name="is_member" value="1" id="is_member" <?= $v['is_member'] ? 'checked' : '' ?>> I am a club member</label>
    <div id="member_box">
      <label for="membership_number">Membership number</label>
      <input type="text" id="membership_number" name="membership_number" value="<?= $e($v['membership_number']) ?>" maxlength="20">
      <?php if (!empty($errors['membership_number'])): ?><p class="err"><?= $e($errors['membership_number']) ?></p><?php endif; ?>
    </div>

    <label class="check"><input type="checkbox" name="agree_terms" value="1" required>
      <span>I have read and agree to the <?php if ($termsUrl !== ''): ?><a href="<?= $e($termsUrl) ?>" target="_blank" rel="noopener">terms of use</a><?php else: ?>terms of use<?php endif; ?>.</span></label>
    <?php if (!empty($errors['agree_terms'])): ?><p class="err"><?= $e($errors['agree_terms']) ?></p><?php endif; ?>

    <div class="cf-turnstile" data-sitekey="<?= $e($siteKey) ?>" style="margin-top:16px"></div>
    <?php if (!empty($errors['captcha'])): ?><p class="err"><?= $e($errors['captcha']) ?></p><?php endif; ?>
    <button type="submit">Create account</button>
  </form>
  <p class="hint">Already registered? <a href="/signin<?= $return !== '' ? '?return=' . $e(urlencode($return)) : '' ?>">Sign in</a></p>
</div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<script>
  const cb = document.getElementById('is_member'), box = document.getElementById('member_box');
  const sync = () => { box.hidden = !cb.checked; };
  cb.addEventListener('change', sync); sync();
</script>
