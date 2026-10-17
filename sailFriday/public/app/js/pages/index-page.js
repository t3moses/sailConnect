/**
 * Index Page Module
 * Handles homepage personalization based on auth state
 */

import { isSignedIn, getCurrentUser, signOut } from '../authService.js';
import { updateAuthenticatedNavigation, addAdminLink } from '../navigationService.js';

// Update navigation and CTAs based on auth state
if (await isSignedIn()) {
    const user = await getCurrentUser();

    // Update navigation with authenticated user info
    updateAuthenticatedNavigation(user, signOut);
    addAdminLink(user);

    // Update hero CTA
    document.getElementById('hero-message').textContent = 'Welcome back, ' + user.profile.firstName + '! Ready for your next sailing adventure?';
    const heroCTA = document.getElementById('hero-cta');
    heroCTA.textContent = 'Go to Dashboard';
    heroCTA.href = 'dashboard.html';

    // Update bottom CTA
    document.getElementById('bottom-cta').innerHTML = '<a href="dashboard.html" class="btn btn-primary">Go to My Dashboard</a><a href="events.html" class="btn btn-secondary" style="margin-left: 1rem;">View Event Schedule</a>';
}
