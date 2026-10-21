<?php use SailConnect\Auth\View; $e = [View::class, 'e']; ?>
<p>Hi <?= $e($u['first_name']) ?>,</p>
<p>The sign-in email address for your Sail Connect account was changed to <?= $e($new) ?>. If this was not you, contact the club administrator immediately.</p>
