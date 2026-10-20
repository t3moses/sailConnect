/**
 * API Service Module
 * Handles all HTTP requests to backend API
 */

import { API_CONFIG, buildApiUrl } from './config.js';
import { clearToken } from './tokenService.js';
import { redirectToSignIn } from './ssoService.js';
import { showInfo } from './toastService.js';

/**
 * Sleep for specified milliseconds
 * @param {number} ms - Milliseconds to sleep
 * @returns {Promise<void>}
 */
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Make HTTP request to API with automatic retry on 409 Conflict
 * @param {string} url - Full API URL
 * @param {Object} options - Fetch options
 * @param {number} retryCount - Current retry attempt (internal)
 * @returns {Promise<Object>} Response data
 */
async function makeRequest(url, options = {}, retryCount = 0) {
    try {
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };

        // Authentication is the shared sailConnect session cookie, sent automatically
        const response = await fetch(url, {
            ...options,
            headers,
            credentials: 'same-origin',
            signal: AbortSignal.timeout(API_CONFIG.TIMEOUT)
        });

        // Handle 401 Unauthorized - not signed in, or the sailConnect session expired
        if (response.status === 401) {
            clearToken();
            redirectToSignIn();
            throw new Error('Session expired. Please sign in again.');
        }

        // Handle 403 enrollment_required - signed in to sailConnect but no Sail Friday account yet
        if (response.status === 403) {
            const body = await response.clone().json().catch(() => null);
            if (body?.details?.code === 'enrollment_required') {
                const currentPage = window.location.pathname.split('/').pop();
                if (!currentPage.startsWith('account')) {
                    window.location.href = 'account.html';
                }
                throw new Error(body.error || 'Complete your Sail Friday registration to continue.');
            }
        }

        // Handle 409 Conflict - concurrent update in progress (retry with exponential backoff)
        if (response.status === 409) {
            const maxRetries = 3;
            const baseDelay = 2000; // 2 seconds

            if (retryCount < maxRetries) {
                const delay = baseDelay * Math.pow(1.5, retryCount); // Exponential backoff: 2s, 3s, 4.5s
                const attemptNumber = retryCount + 1;

                showInfo(
                    `Season update in progress. Retrying in ${Math.round(delay / 1000)} seconds... (Attempt ${attemptNumber}/${maxRetries})`,
                    delay
                );

                await sleep(delay);
                return makeRequest(url, options, retryCount + 1);
            } else {
                // Max retries exceeded
                const errorData = await response.json().catch(() => ({}));
                throw new Error(
                    errorData.message ||
                    'Server is busy processing updates. Please try again in a moment.'
                );
            }
        }

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || errorData.error || `HTTP ${response.status}: ${response.statusText}`);
        }

        if (response.status === 204 || response.status === 205) {
            return {};
        }

        return await response.json();
    } catch (error) {
        console.error('API Request failed:', error);

        if (error.name === 'TimeoutError') {
            throw new Error('Request timed out. Please check your connection.');
        }

        throw error;
    }
}

/**
 * GET request
 * @param {string} endpoint - API endpoint
 * @param {Object} params - URL parameters
 * @returns {Promise<Object>}
 */
export async function get(endpoint, params = {}) {
    const url = buildApiUrl(endpoint, params);
    return makeRequest(url, { method: 'GET' });
}

/**
 * POST request
 * @param {string} endpoint - API endpoint
 * @param {Object} data - Request body
 * @param {Object} params - URL parameters
 * @returns {Promise<Object>}
 */
export async function post(endpoint, data, params = {}) {
    const url = buildApiUrl(endpoint, params);
    return makeRequest(url, {
        method: 'POST',
        body: JSON.stringify(data)
    });
}

/**
 * PUT request
 * @param {string} endpoint - API endpoint
 * @param {Object} data - Request body
 * @param {Object} params - URL parameters
 * @returns {Promise<Object>}
 */
export async function put(endpoint, data, params = {}) {
    const url = buildApiUrl(endpoint, params);
    return makeRequest(url, {
        method: 'PUT',
        body: JSON.stringify(data)
    });
}

/**
 * PATCH request
 * @param {string} endpoint - API endpoint
 * @param {Object} data - Request body
 * @param {Object} params - URL parameters
 * @returns {Promise<Object>}
 */
export async function patch(endpoint, data, params = {}) {
    const url = buildApiUrl(endpoint, params);
    return makeRequest(url, {
        method: 'PATCH',
        body: JSON.stringify(data)
    });
}

/**
 * DELETE request
 * @param {string} endpoint - API endpoint
 * @param {Object} params - URL parameters
 * @returns {Promise<Object>}
 */
export async function deleteRequest(endpoint, params = {}) {
    const url = buildApiUrl(endpoint, params);
    return makeRequest(url, { method: 'DELETE' });
}

// ============================================
// Data-specific API calls (mirrors localStorage operations)
// ============================================

/**
 * Get all users from API
 * @returns {Promise<Array>}
 */
export async function getAllUsers() {
    const response = await get(API_CONFIG.ENDPOINTS.USERS);
    return response.data || [];
}

/**
 * Get user by ID from API
 * @param {string} userId
 * @returns {Promise<Object|null>}
 */
export async function getUserById(userId) {
    try {
        const response = await get(API_CONFIG.ENDPOINTS.USER_BY_ID, { id: userId });
        return response.data || null;
    } catch (error) {
        if (error.message.includes('404')) {
            return null;
        }
        throw error;
    }
}

/**
 * Get user by email from API
 * @param {string} email
 * @returns {Promise<Object|null>}
 */
export async function getUserByEmail(email) {
    try {
        const response = await get(API_CONFIG.ENDPOINTS.USER_BY_EMAIL, { email });
        return response.data || null;
    } catch (error) {
        if (error.message.includes('404')) {
            return null;
        }
        throw error;
    }
}

/**
 * Save users to API
 * @param {Array} users
 * @returns {Promise<Object>}
 */
export async function saveUsers(users) {
    return await post(API_CONFIG.ENDPOINTS.USERS, { users });
}

/**
 * Get all events from API (simple list without flotilla data)
 * Returns basic event information: eventId, date, startTime, finishTime, status
 * For flotilla assignments, use calendarService.getEventsWithFlotilla()
 * @returns {Promise<Array>}
 */
export async function getAllEvents() {
    const response = await get(API_CONFIG.ENDPOINTS.EVENTS);
    // API returns { success: true, data: { events: [...] } }
    return response.data?.events || [];
}

/**
 * Get single event by ID with flotilla data
 * @param {string} eventId - Event identifier
 * @returns {Promise<Object>} Event object with flotilla data
 */
export async function getEventById(eventId) {
    const response = await get(API_CONFIG.ENDPOINTS.EVENT_BY_ID, { id: eventId });
    // API returns { success: true, data: { event: {...}, flotilla: {...} } }
    return response.data || null;
}

/**
 * Save events to API
 * @param {Array} events
 * @returns {Promise<Object>}
 */
export async function saveEvents(events) {
    return await post(API_CONFIG.ENDPOINTS.EVENTS, { events });
}

/**
 * Get current session from API
 * @returns {Promise<Object|null>}
 */
export async function getSession() {
    try {
        const response = await get(API_CONFIG.ENDPOINTS.SESSION);
        return response.data || null;
    } catch (error) {
        if (error.message.includes('404')) {
            return null;
        }
        throw error;
    }
}

/**
 * Save session to API
 * @param {Object} session
 * @returns {Promise<Object>}
 */
export async function saveSession(session) {
    return await post(API_CONFIG.ENDPOINTS.SESSION, session);
}

/**
 * Clear session from API
 * @returns {Promise<Object>}
 */
export async function clearSession() {
    return await deleteRequest(API_CONFIG.ENDPOINTS.SESSION);
}

// ============================================
// Authentication API calls
// ============================================

/**
 * Enroll the signed-in sailConnect user in Sail Friday
 * @param {Object} userData - { accountType, profile }
 * @returns {Promise<Object>} { success, data: { user } }
 */
export async function register(userData) {
    return await post(API_CONFIG.ENDPOINTS.AUTH_REGISTER, userData);
}

/**
 * Get the signed-in sailConnect identity (works before enrollment)
 * @returns {Promise<Object|null>} { email, firstName, lastName, displayName, isMember, membershipNumber }
 */
export async function getIdentity() {
    const response = await get(API_CONFIG.ENDPOINTS.AUTH_IDENTITY);
    return response.data?.identity || null;
}

/**
 * Get current session (validate session)
 * @returns {Promise<Object|null>} { user } or null
 */
export async function getAuthSession() {
    try {
        const response = await get(API_CONFIG.ENDPOINTS.AUTH_SESSION);
        return response.user || null;
    } catch (error) {
        if (error.message.includes('401') || error.message.includes('404')) {
            return null;
        }
        throw error;
    }
}

/**
 * Get current user with full profile data from /users/me
 * @returns {Promise<Object|null>} { user, crewProfile, boatProfile } or null
 */
export async function getUserMe() {
    try {
        const response = await get(API_CONFIG.ENDPOINTS.USER_ME);
        return response.data || null;
    } catch (error) {
        if (error.message.includes('401') || error.message.includes('404')) {
            return null;
        }
        throw error;
    }
}
