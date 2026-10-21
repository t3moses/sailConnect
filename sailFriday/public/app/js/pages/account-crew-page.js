/**
 * Crew Registration Page Module
 * Handles crew member registration form (name, email, mobile number and membership come from sailConnect)
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

const whatsappCheckbox = document.getElementById('whatsapp_group');

document.querySelector('form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const submitButton = this.querySelector('[type="submit"]');
    const originalLabel = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Saving...';

    // The WhatsApp group uses the mobile number in the sailConnect account
    const wantsWhatsapp = whatsappCheckbox.checked;
    if (wantsWhatsapp && !identity?.mobile) {
        submitButton.disabled = false;
        submitButton.textContent = originalLabel;
        if (confirm('To join the WhatsApp group, add your mobile number to your sailConnect account. Open your sailConnect account now?')) {
            window.location.href = profileUrl();
        }
        return;
    }

    // Create user data object
    const userData = {
        accountType: 'crew',
        profile: {
            skill: parseInt(document.getElementById('skill').value, 10),
            experience: document.getElementById('experience').value.trim() || undefined,
            socialPreference: wantsWhatsapp
        }
    };

    // Enroll the signed-in sailConnect user as crew
    const result = await register(userData);

    if (result.success) {
        window.location.href = 'dashboard.html';
    } else {
        submitButton.disabled = false;
        submitButton.textContent = originalLabel;
        alert('Error: ' + result.error);
    }
});
