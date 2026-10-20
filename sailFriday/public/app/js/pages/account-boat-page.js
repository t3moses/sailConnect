/**
 * Boat Owner Registration Page Module
 * Handles boat owner registration form
 */

import { getCurrentUser, getIdentity, register } from '../authService.js';
import { hasToken } from '../tokenService.js';
import { registerUrl, profileUrl } from '../ssoService.js';
import { renderIdentitySummary } from '../enrollmentService.js';

// Sign-up (name, email, password, membership) happens at sailConnect. Send visitors there first,
// then they come back here to choose how they take part in Sail Friday.
if (!hasToken()) {
    window.location.href = registerUrl();
} else if (await getCurrentUser()) {
    // Already enrolled in Sail Friday
    window.location.href = 'dashboard.html';
} else {
    await renderIdentitySummary(await getIdentity(), profileUrl());
}

document.querySelector('form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const submitButton = this.querySelector('[type="submit"]');
    const originalLabel = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Saving...';

    // Create user data object
    const userData = {
        accountType: 'boat_owner',
        profile: {
            ownerMobile: document.getElementById('phone').value,
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
