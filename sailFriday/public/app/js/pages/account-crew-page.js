/**
 * Crew Registration Page Module
 * Handles crew member registration form (name, email and membership come from sailConnect)
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

// Toggle mobile field visibility when WhatsApp checkbox changes
const whatsappCheckbox = document.getElementById('whatsapp_group');
const mobileGroup = document.getElementById('mobile-group');
const mobileInput = document.getElementById('mobile');

whatsappCheckbox.addEventListener('change', function() {
    if (this.checked) {
        mobileGroup.style.display = '';
        mobileInput.required = true;
    } else {
        mobileGroup.style.display = 'none';
        mobileInput.required = false;
        mobileInput.value = '';
    }
});

document.querySelector('form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const submitButton = this.querySelector('[type="submit"]');
    const originalLabel = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Saving...';

    // Validate mobile is provided when WhatsApp is checked
    const wantsWhatsapp = whatsappCheckbox.checked;
    const mobile = mobileInput.value.trim();
    if (wantsWhatsapp && !mobile) {
        submitButton.disabled = false;
        submitButton.textContent = originalLabel;
        alert('Please enter your mobile number to join the WhatsApp group.');
        mobileInput.focus();
        return;
    }

    // Create user data object
    const userData = {
        accountType: 'crew',
        profile: {
            skill: parseInt(document.getElementById('skill').value, 10),
            experience: document.getElementById('experience').value.trim() || undefined,
            socialPreference: wantsWhatsapp,
            ...(wantsWhatsapp && mobile ? { mobile } : {})
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
