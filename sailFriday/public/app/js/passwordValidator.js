/**
 * Password Validation Utility
 * Provides consistent password validation across all forms
 * Matches PhpPasswordService.meetsRequirements() logic
 */

/**
 * Validate password meets all requirements
 * @param {string} password - Password to validate
 * @returns {Object} - Object with isValid boolean and error message if invalid
 */
export function validatePassword(password) {
    // Check minimum length
    if (password.length < 8) {
        return {
            isValid: false,
            error: 'Password must be at least 8 characters long.'
        };
    }

    // Check for at least one uppercase letter
    if (!/[A-Z]/.test(password)) {
        return {
            isValid: false,
            error: 'Password must contain at least one uppercase letter (A-Z).'
        };
    }

    // Check for at least one lowercase letter
    if (!/[a-z]/.test(password)) {
        return {
            isValid: false,
            error: 'Password must contain at least one lowercase letter (a-z).'
        };
    }

    // Check for at least one number
    if (!/[0-9]/.test(password)) {
        return {
            isValid: false,
            error: 'Password must contain at least one number (0-9).'
        };
    }

    return { isValid: true };
}

/**
 * Get password requirements HTML for display
 * @returns {string} - HTML string with requirements list
 */
export function getPasswordRequirementsHTML() {
    return `
        <ul class="password-requirements-list">
            <li>At least 8 characters</li>
            <li>One uppercase letter (A-Z)</li>
            <li>One lowercase letter (a-z)</li>
            <li>One number (0-9)</li>
        </ul>
    `;
}
