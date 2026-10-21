<?php use SailConnect\Auth\View; $e = [View::class, 'e']; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title) ?> - sailConnect</title>
<link rel="icon" href="/assets/knot-flat.png" type="image/png">
<link rel="stylesheet" href="/css/auth.css">
</head>
<body>
<header>
  <div class="header-container">
    <a href="/" class="logo"><img src="/assets/NSC_SC_logo.png" alt="Nepean Sailing Club"> sailConnect</a>
    <nav>
      <ul>
        <?php if (!empty($me)): ?>
          <li><a href="/profile"><?= $e($me['display_name']) ?></a></li>
          <?php if ((int) $me['is_platform_admin']): ?><li><a href="/admin/users">Users</a></li><?php endif; ?>
          <li><a href="/signout" class="nav-btn nav-btn-secondary">Sign out</a></li>
        <?php else: ?>
          <li><a href="/signin" class="nav-btn nav-btn-secondary">Sign in</a></li>
          <li><a href="/register" class="nav-btn nav-btn-primary">Create account</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
</header>

<section class="hero">
  <div class="hero-content">
    <h1><?= $e($title) ?></h1>
  </div>
</section>

<main>
<?php if (empty($wide)): ?><div class="form-container"><?= $content ?></div><?php else: ?><?= $content ?><?php endif; ?>
</main>

<footer>
  <div class="footer-content">
    <p><strong>Nepean Sailing Club &mdash; sailConnect</strong></p>
    <p><a href="https://nsc.ca">NSC Website</a></p>
    <p style="margin-top: 1rem; opacity: 0.7;">&copy; 2026 All Rights Reserved</p>
  </div>
</footer>
</body>
</html>
