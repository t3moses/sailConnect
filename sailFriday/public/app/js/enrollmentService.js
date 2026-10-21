/**
 * Enrollment Service Module
 * Shows the signed-in sailConnect identity on the Sail Friday enrollment forms.
 */

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

/**
 * Fill #identity-summary with the identity that will be used for the Sail Friday profile
 * @param {Object|null} identity - { email, firstName, lastName, mobile, isMember, membershipNumber }
 * @param {string} profileUrl - Link to the sailConnect account page
 */
export async function renderIdentitySummary(identity, profileUrl) {
    const box = document.getElementById('identity-summary');
    if (!box) return;

    if (!identity) {
        box.textContent = 'Could not load your sailConnect account. Please reload the page.';
        return;
    }

    const membership = identity.isMember
        ? `NSC member no. ${escapeHtml(identity.membershipNumber)}`
        : 'Not an NSC member';
    const mobile = identity.mobile
        ? `Mobile ${escapeHtml(identity.mobile)}`
        : 'No mobile number on file';
    box.innerHTML = `
        <p><strong>${escapeHtml(identity.firstName)} ${escapeHtml(identity.lastName)}</strong>
        (${escapeHtml(identity.email)}) &middot; ${mobile} &middot; ${membership}</p>
        <p><small>These details come from your sailConnect account; Sail Friday does not store them.
        <a href="${escapeHtml(profileUrl)}">Change them there</a>.</small></p>`;
}
