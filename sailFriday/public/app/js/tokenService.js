/**
 * Session Hint Module
 *
 * The real session is an HttpOnly cookie managed by the sailConnect auth service, so scripts
 * cannot read it. The auth service also sets a readable marker cookie (sc_signed_in) so pages can
 * pick signed-in or signed-out navigation synchronously. It is only a hint: the API decides.
 */

const HINT_COOKIE = 'sc_signed_in';

/**
 * Check whether the sailConnect "signed in" hint cookie is present
 * @returns {boolean} True if the hint exists
 */
export function hasToken() {
    return document.cookie.split('; ').some(c => c.startsWith(HINT_COOKIE + '='));
}

/**
 * Drop a stale hint (the server rejected the session). The auth service sets it on the parent
 * domain, so clear it for both the parent domain and this host.
 */
export function clearToken() {
    const expired = `${HINT_COOKIE}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
    const parts = window.location.hostname.split('.');
    if (parts.length > 2) {
        document.cookie = `${expired}; domain=.${parts.slice(1).join('.')}`;
    }
    document.cookie = expired;
}
