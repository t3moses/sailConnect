<?php use SailConnect\Auth\View; $e = [View::class, 'e']; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title) ?> · sailConnect</title>
<link rel="stylesheet" href="/css/auth.css">
</head>
<body>
<header class="top">
  <strong>sailConnect</strong>
  <nav>
    <?php if (!empty($me)): ?>
      <a href="/profile"><?= $e($me['display_name']) ?></a>
      <?php if ((int) $me['is_platform_admin']): ?><a href="/admin/users">Users</a><?php endif; ?>
      <a href="/signout">Sign out</a>
    <?php else: ?>
      <a href="/signin">Sign in</a><a href="/register">Create account</a>
    <?php endif; ?>
  </nav>
</header>
<main class="<?= !empty($wide) ? 'wide' : '' ?>">
<?= $content ?>
</main>
</body>
</html>
