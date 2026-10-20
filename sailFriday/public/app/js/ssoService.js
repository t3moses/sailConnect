/**
 * Single Sign-On Service Module
 * Builds links to the sailConnect auth service, which owns sign-in, sign-out, registration,
 * password reset and the account profile for every sailConnect application.
 */

/**
 * Base URL of the auth service.
 * Production: sailFriday.sailConnect.org -> https://auth.sailConnect.org
 * Development: any other host -> same host on port 8001. Override with window.SAILCONNECT_AUTH_URL.
 * @returns {string} URL without trailing slash
 */
export function authBaseUrl() {
    if (window.SAILCONNECT_AUTH_URL) {
        return window.SAILCONNECT_AUTH_URL.replace(/\/$/, '');
    }
    const { protocol, hostname } = window.location;
    const match = hostname.match(/^[^.]+\.(sailconnect\.org)$/i);
    return match ? `${protocol}//auth.${match[1]}` : `${protocol}//${hostname}:8001`;
}

function withReturn(path, returnTo) {
    return `${authBaseUrl()}${path}?return=${encodeURIComponent(returnTo)}`;
}

/** @param {string} [returnTo] Where to come back to after signing in (default: current page) */
export function signInUrl(returnTo = window.location.href) {
    return withReturn('/signin', returnTo);
}

/** @param {string} [returnTo] Where to come back to after registering (default: current page) */
export function registerUrl(returnTo = window.location.href) {
    return withReturn('/register', returnTo);
}

/** Signs out of every sailConnect application, then returns here. */
export function signOutUrl(returnTo = new URL('index.html', window.location.href).href) {
    return withReturn('/signout', returnTo);
}

/** The user's sailConnect account page (name, email, password, membership). */
export function profileUrl() {
    return `${authBaseUrl()}/profile`;
}

/** Sends the browser to the auth service to sign in. */
export function redirectToSignIn(returnTo = window.location.href) {
    window.location.href = signInUrl(returnTo);
}
