<?php use SailConnect\Auth\View; $e = [View::class, 'e']; ?>
<p>Hi <?= $e($u['first_name']) ?>,</p>
<p>Your Sail Connect account is ready. Your display name is <strong><?= $e($u['display_name']) ?></strong> and you sign in with <?= $e($u['email_address']) ?>.</p>
<p>One sign-in works across every Sail Connect application. You can update your details any time at <a href="<?= $e($url) ?>/profile"><?= $e($url) ?>/profile</a>.</p>
