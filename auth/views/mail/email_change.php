<?php use SailConnect\Auth\View; $e = [View::class, 'e']; ?>
<p>Hi <?= $e($u['first_name']) ?>,</p>
<p>Confirm this address as your new Sail Connect sign-in email (link valid for 24 hours):</p>
<p><a href="<?= $e($url) ?>"><?= $e($url) ?></a></p>
<p>If you did not ask for this, ignore this email and your address will not change.</p>
