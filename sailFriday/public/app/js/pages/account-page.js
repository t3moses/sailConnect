/**
 * Account Selection Page Module
 * Handles account type selection and routing
 */

import { getCurrentUser } from '../authService.js';

// Already enrolled in Sail Friday? (Signed in to sailConnect but not enrolled stays here.)
if (await getCurrentUser()) {
    window.location.href = 'dashboard.html';
}

document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    const accountType = document.querySelector('input[name="account_type"]:checked').value;
    window.location.href = accountType === 'crew' ? 'account_crew.html' : 'account_boat.html';
});
