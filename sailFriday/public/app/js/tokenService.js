/**
 * Token Service Module
 * Handles JWT token storage using sessionStorage
 *
 * Why sessionStorage?
 * - Better security: Cleared when tab closes
 * - User must sign in again after closing browser
 * - Standard practice for modern SPAs
 * - Balances security and user experience
 */

const TOKEN_KEY = 'nsc_auth_token';

/**
 * Get JWT token from sessionStorage
 * @returns {string|null} Token string or null if not found
 */
export function getToken() {
    return sessionStorage.getItem(TOKEN_KEY);
}

/**
 * Store JWT token in sessionStorage
 * @param {string} token - JWT token string
 */
export function setToken(token) {
    if (!token) {
        console.error('Attempted to set empty token');
        return;
    }
    sessionStorage.setItem(TOKEN_KEY, token);
}

/**
 * Remove JWT token from sessionStorage
 */
export function clearToken() {
    sessionStorage.removeItem(TOKEN_KEY);
}

/**
 * Check if JWT token exists
 * @returns {boolean} True if token exists
 */
export function hasToken() {
    return sessionStorage.getItem(TOKEN_KEY) !== null;
}
