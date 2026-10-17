/**
 * FAQ Page Module
 * Handles FAQ page personalization based on auth state
 */

import { isSignedIn, getCurrentUser, signOut } from '../authService.js';
import { updateAuthenticatedNavigation, addAdminLink } from '../navigationService.js';

// Update navigation based on auth state
if (await isSignedIn()) {
    const user = await getCurrentUser();
    updateAuthenticatedNavigation(user, signOut);
    addAdminLink(user);
}
