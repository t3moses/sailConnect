<?php use SailConnect\Auth\View; /** @var string $name @var string $label @var string $type @var array $errors @var mixed $value @var string $hint */ ?>
<div class="form-group">
<label for="<?= View::e($name) ?>"><?= View::e($label) ?></label>
<input type="<?= View::e($type ?? 'text') ?>" id="<?= View::e($name) ?>" name="<?= View::e($name) ?>"
  <?= ($type ?? 'text') === 'password' ? '' : 'value="' . View::e((string) ($value ?? '')) . '"' ?>
  <?= !empty($autocomplete) ? 'autocomplete="' . View::e($autocomplete) . '"' : '' ?> <?= empty($optional) ? 'required' : '' ?>>
<?php if (!empty($hint)): ?><small class="hint"><?= View::e($hint) ?></small><?php endif; ?>
<?php if (!empty($errors[$name])): ?><p class="err"><?= View::e($errors[$name]) ?></p><?php endif; ?>
</div>
