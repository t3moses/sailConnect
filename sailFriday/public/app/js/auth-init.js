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
 * This script checks the sailConnect hint cookie synchronously (no async/await) to determine
 * if the user is signed in, then adds a CSS class to <html> element that controls
 * which navigation items are visible via CSS rules.
 */
(function() {
    // Synchronous check of the "signed in" hint cookie set by the sailConnect auth service
    const hasToken = document.cookie.split('; ').some(c => c.startsWith('sc_signed_in='));

    // Add class to <html> element before page renders
    if (hasToken) {
        document.documentElement.classList.add('authenticated');
    } else {
        document.documentElement.classList.add('unauthenticated');
    }
})();
