/**
 * Account Selection Page Module
 * Handles account type selection and routing
 */

import { isSignedIn } from '../authService.js';

// Check if already signed in
if (await isSignedIn()) {
    window.location.href = 'dashboard.html';
}

document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    const accountType = document.querySelector('input[name="account_type"]:checked').value;
    window.location.href = accountType === 'crew' ? 'account_crew.html' : 'account_boat.html';
});
