<?php use SailConnect\Auth\View; $e = [View::class, 'e']; ?>
<p>A new user registered on sailConnect.</p>
<ul>
  <li>Name: <?= $e($u['first_name'] . ' ' . $u['last_name']) ?> (<?= $e($u['display_name']) ?>)</li>
  <li>Email: <?= $e($u['email_address']) ?></li>
  <li>Member: <?= (int) $u['is_member'] ? 'Yes, #' . $e($u['membership_number']) : 'No' ?></li>
  <li>Terms accepted: <?= $e($u['terms_accepted_at']) ?> UTC</li>
  <li>User ID: <?= $e($u['user_id']) ?></li>
</ul>
