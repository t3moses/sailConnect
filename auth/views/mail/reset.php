<?php use SailConnect\Auth\View; $e = [View::class, 'e']; ?>
<p>Hi <?= $e($u['first_name']) ?>,</p>
<p>Use the link below to choose a new Sail Connect password. It works once and expires in one hour.</p>
<p><a href="<?= $e($url) ?>"><?= $e($url) ?></a></p>
<p>If you did not ask for this, you can ignore this email.</p>
