<!DOCTYPE html>
<html lang="en" class="<?= $signedIn ? 'authenticated' : 'unauthenticated' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $h($title) ?> - NSC Match Race</title>
    <link rel="icon" href="/assets/knot-flat.png" type="image/png">
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <header>
        <div class="header-container">
            <a href="/" class="logo"><img src="/assets/NSC_SC_logo.png" alt="Nepean Sailing Club" style="height: 60px; width: auto;" /> Match Race</a>
            <button class="hamburger" id="hamburger" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav id="nav">
                <ul id="main-nav">
                    <li><a href="/" class="<?= $active === 'home' ? 'active' : '' ?>">Home</a></li>

                    <li class="nav-unauthenticated">
                        <a href="/signin" class="nav-btn nav-btn-secondary">Sign In</a>
                    </li>
                    <li class="nav-unauthenticated">
                        <a href="/join" class="nav-btn nav-btn-primary <?= $active === 'join' ? 'active' : '' ?>">Join Now</a>
                    </li>

                    <li class="nav-authenticated">
                        <a href="/dashboard" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                    </li>
                    <li class="nav-authenticated user-greeting">
                        <span>Hi, <span class="user-name"><?= $h($identity['first_name'] ?? '') ?></span>!</span>
                    </li>
                    <li class="nav-authenticated">
                        <a href="/signout" class="sign-out-btn">Sign Out</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>
    <div class="menu-overlay" id="menu-overlay"></div>

<?= $content ?>

    <footer>
        <div class="footer-content">
            <p><strong>Nepean Sailing Club — Match Race</strong></p>
            <p><a href="https://nsc.ca">NSC Website</a><?php if (!$signedIn): ?> | <a href="/join">Join Now</a><?php endif; ?></p>
            <p style="margin-top: 1rem; opacity: 0.7;">&copy; 2026 All Rights Reserved</p>
        </div>
    </footer>

    <script src="/js/mobile-menu.js"></script>
</body>
</html>
