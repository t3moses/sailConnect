<?php use SailConnect\Auth\View; $e = [View::class, 'e'];
$notices = ['updated' => 'Updated.', 'last-admin' => 'There must always be at least one active platform admin.', 'cannot-deactivate-self' => 'You cannot deactivate your own account.', 'not-found' => 'User not found.']; ?>
<?php if (!empty($notices[$notice])): ?><p class="notice <?= $notice === 'updated' ? '' : 'bad' ?>"><?= $e($notices[$notice]) ?></p><?php endif; ?>
<div class="card">
  <h2>Users (<?= count($users) ?>)</h2>
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th>Name</th><th>Email</th><th>Member</th><th>Status</th><th>Admin</th><th>Registered</th></tr></thead>
    <tbody>
    <?php foreach ($users as $x): $self = $x['user_id'] === $meId; ?>
    <tr>
      <td><?= $e($x['first_name'] . ' ' . $x['last_name']) ?><br><span class="tag"><?= $e($x['display_name']) ?></span></td>
      <td><?= $e($x['email_address']) ?></td>
      <td><?= (int) $x['is_member'] ? $e('#' . $x['membership_number']) : '—' ?></td>
      <td>
        <form class="inline" method="post" action="/admin/users/active">
          <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="user_id" value="<?= $e($x['user_id']) ?>">
          <?php if ((int) $x['is_platform_active']): ?>Active <?php if (!$self): ?><button class="secondary" name="active" value="0">Deactivate</button><?php endif; ?>
          <?php else: ?>Deactivated <button class="secondary" name="active" value="1">Activate</button><?php endif; ?>
        </form>
      </td>
      <td>
        <form class="inline" method="post" action="/admin/users/admin">
          <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="user_id" value="<?= $e($x['user_id']) ?>">
          <?php if ((int) $x['is_platform_admin']): ?>Yes <button class="secondary" name="admin" value="0">Remove</button>
          <?php else: ?>No <button class="secondary" name="admin" value="1">Make admin</button><?php endif; ?>
        </form>
      </td>
      <td><?= $e(substr($x['created_at'], 0, 10)) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
