/**
 * Synchronous Authentication Initializer
 *
 * This runs as a blocking script in <head> to set auth state before page renders.
 * Prevents flash of unauthenticated navigation (FOUC).
 *
 * IMPORTANT: This script MUST be included before the stylesheet in <head>:
 * <script src="js/auth-init.js"></script>
 * <link rel="stylesheet" href="css/styles.css">
 *
 * This script checks sessionStorage synchronously (no async/await) to determine
 * if a JWT token exists, then adds a CSS class to <html> element that controls
 * which navigation items are visible via CSS rules.
 */
(function() {
    const TOKEN_KEY = 'nsc_auth_token';

    // Synchronous check - sessionStorage.getItem() is NOT async
    const hasToken = sessionStorage.getItem(TOKEN_KEY) !== null;

    // Add class to <html> element before page renders
    if (hasToken) {
        document.documentElement.classList.add('authenticated');
    } else {
        document.documentElement.classList.add('unauthenticated');
    }
})();
