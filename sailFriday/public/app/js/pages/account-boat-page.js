/**
 * Boat Owner Registration Page Module
 * Handles boat owner registration form (name, email, mobile number and membership come from sailConnect)
 */

import { getCurrentUser, getIdentity, register } from '../authService.js';
import { hasToken } from '../tokenService.js';
import { registerUrl, profileUrl } from '../ssoService.js';
import { renderIdentitySummary } from '../enrollmentService.js';

let identity = null;

// Sign-up (name, email, password, mobile number, membership) happens at sailConnect. Send visitors there first,
// then they come back here to choose how they take part in Sail Friday.
if (!hasToken()) {
    window.location.href = registerUrl();
} else if (await getCurrentUser()) {
    // Already enrolled in Sail Friday
    window.location.href = 'dashboard.html';
} else {
    identity = await getIdentity();
    await renderIdentitySummary(identity, profileUrl());
}

document.querySelector('form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const submitButton = this.querySelector('[type="submit"]');
    const originalLabel = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Saving...';

    // Boat owners are reached by phone, using the mobile number in the sailConnect account
    if (!identity?.mobile) {
        submitButton.disabled = false;
        submitButton.textContent = originalLabel;
        if (confirm('Boat owners need a mobile number. Add one to your sailConnect account now?')) {
            window.location.href = profileUrl();
        }
        return;
    }

    // Create user data object
    const userData = {
        accountType: 'boat_owner',
        profile: {
            displayName: document.getElementById('boat_name').value,
            minBerths: document.getElementById('min_crew').value,
            maxBerths: document.getElementById('max_crew').value,
            assistanceRequired: document.getElementById('request_first_mate').checked,
            socialPreference: document.getElementById('whatsapp_group').checked,
            willingToCrew: document.getElementById('willing_to_crew').checked
        }
    };

    // Enroll the signed-in sailConnect user as a boat owner
    const result = await register(userData);

    if (result.success) {
        window.location.href = 'dashboard.html';
    } else {
        submitButton.disabled = false;
        submitButton.textContent = originalLabel;
        alert('Error: ' + result.error);
    }
});
