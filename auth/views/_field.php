<?php use SailConnect\Auth\View; /** @var string $name @var string $label @var string $type @var array $errors @var mixed $value @var string $hint */ ?>
<label for="<?= View::e($name) ?>"><?= View::e($label) ?></label>
<input type="<?= View::e($type ?? 'text') ?>" id="<?= View::e($name) ?>" name="<?= View::e($name) ?>"
  <?= ($type ?? 'text') === 'password' ? '' : 'value="' . View::e((string) ($value ?? '')) . '"' ?>
  <?= !empty($autocomplete) ? 'autocomplete="' . View::e($autocomplete) . '"' : '' ?> required>
<?php if (!empty($hint)): ?><p class="hint"><?= View::e($hint) ?></p><?php endif; ?>
<?php if (!empty($errors[$name])): ?><p class="err"><?= View::e($errors[$name]) ?></p><?php endif; ?>
