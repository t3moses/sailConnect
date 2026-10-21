/**
 * Authentication Service Module
 * Handles user authentication and session management using API
 * Sign-in itself happens at the sailConnect auth service (see ssoService)
 */

import * as ApiService from './apiService.js';
import { clearToken, hasToken } from './tokenService.js';
import { signOutUrl, redirectToSignIn } from './ssoService.js';

/**
 * Sign out of every sailConnect application and return to the home page
 * @returns {void}
 */
export function signOut() {
    clearToken();
    window.location.href = signOutUrl();
}

/**
 * Check if user is signed in
 * @returns {boolean} True if the sailConnect "signed in" hint cookie exists
 */
export function isSignedIn() {
    return hasToken();
}

/**
 * Transform availabilities object to eventAvailability object
 * @param {Object} availabilities - Object with event IDs as keys and values
 *                                   For crew: status codes (0=not selected, 1=selected) — an
 *                                   entry's mere presence means the crew is registered/available;
 *                                   row absence (no entry) means withdrawn.
 *                                   For boats: berth counts (0, 1, 2, 3, 4)
 * @param {string} accountType - 'crew' or 'boat_owner'
 * @returns {Object} eventAvailability object { "Fri Jun 12": true, "Fri Jun 19": false, ... }
 */
function transformAvailabilities(availabilities, accountType) {
    if (!availabilities || typeof availabilities !== 'object') {
        return {};
    }

    const eventAvailability = {};
    Object.entries(availabilities).forEach(([eventId, value]) => {
        if (accountType === 'crew') {
            // Presence of an entry means the crew has a crew_availability row (registered/available).
            // The status value (0=not selected, 1=selected) doesn't affect availability itself.
            eventAvailability[eventId] = true;
        } else {
            // For boat owners: Any berth count > 0 means available (true)
            eventAvailability[eventId] = value > 0;
        }
    });

    return eventAvailability;
}

/**
 * Transform profile data from API format to legacy format
 * @param {Object} profileData - crewProfile or boatProfile from API
 * @param {string} accountType - 'crew' or 'boat_owner'
 * @returns {Object} Transformed profile
 */
function transformProfile(profileData, accountType) {
    if (!profileData) {
        console.warn('No profile data available, returning empty profile');
        return {};
    }

    if (accountType === 'crew') {
        return {
            firstName: profileData.firstName || '',
            lastName: profileData.lastName || '',
            membershipNumber: profileData.membershipNumber || '',
            skill: profileData.skill,
            experience: profileData.experience || '',
            whatsappGroup: profileData.socialPreference || false,
            mobile: profileData.mobile || ''
        };
    } else {
        // Boat owner profile - map from backend BoatResponse fields
        return {
            firstName: profileData.ownerFirstName || '',
            lastName: profileData.ownerLastName || '',
            phone: profileData.ownerMobile || '',
            boatName: profileData.displayName || '',
            minCrew: String(profileData.minBerths || 1),
            maxCrew: String(profileData.maxBerths || 4),
            requestFirstMate: profileData.assistanceRequired || false,
            whatsappGroup: profileData.socialPreference || false,
            willingToCrew: profileData.willingToCrew || false
        };
    }
}

/**
 * Transform /users/me API response to legacy user object format
 * @param {Object} apiResponse - Response from /users/me endpoint
 * @returns {Object} Transformed user object
 */
function transformUserMeResponse(apiResponse) {
    const { user, crewProfile, boatProfile } = apiResponse;

    // Determine which profile to use based on account type
    const profileData = user.accountType === 'crew' ? crewProfile : boatProfile;

    if (!profileData) {
        console.warn('No profile data found for user:', user.email);
    }

    // Build transformed user object
    const transformedUser = {
        // userId is the sailConnect user ID
        userId: String(user.id),
        email: user.email,
        accountType: user.accountType,

        // Password not returned by API (security best practice)
        // This field will be null - backend handles password verification
        password: null,

        // Transform profile structure based on account type
        profile: transformProfile(profileData, user.accountType),

        // Transform availabilities array to eventAvailability object
        eventAvailability: transformAvailabilities(profileData?.availabilities, user.accountType),

        // Raw integer berths per event for boat owners (e.g. { "Fri Jun 12": 3 })
        eventBerths: user.accountType !== 'crew' ? (profileData?.availabilities || {}) : {},

        // Map timestamp field names
        createdAt: user.createdAt,

        // Additional fields from API
        isAdmin: user.isAdmin
    };

    return transformedUser;
}

/**
 * Get current user data from API
 * Always fetches fresh data from server (no caching)
 * @returns {Promise<Object|null>} User object or null
 */
export async function getCurrentUser() {
    if (!hasToken()) return null;

    try {
        console.log('Fetching user from API /users/me...');
        const apiResponse = await ApiService.getUserMe();

        if (!apiResponse) {
            console.error('No user data returned from API');
            return null;
        }

        // Transform API response to legacy format
        const user = transformUserMeResponse(apiResponse);

        console.log('User fetched successfully:', user.email);
        return user;
    } catch (error) {
        console.error('Failed to get current user:', error);
        return null;
    }
}

/**
 * Require authentication - redirect to sign in page if not authenticated
 * @returns {boolean} True if user is authenticated
 */
export function requireAuth() {
    const signedIn = isSignedIn();
    if (!signedIn) {
        redirectToSignIn();
        return false;
    }
    return true;
}

/**
 * Enroll the signed-in sailConnect user in Sail Friday
 * @param {Object} userData - { accountType, profile }
 * @returns {Promise<Object>} Result object with success status
 */
export async function register(userData) {
    try {
        const response = await ApiService.register(userData);

        if (!response.success) {
            console.error('Registration failed:', response.error);
            return { success: false, error: response.error || 'Registration failed' };
        }

        return { success: true, userId: response.data.user.id };
    } catch (error) {
        console.error('Registration failed:', error);
        return { success: false, error: error.message || 'Registration failed. Please check your connection.' };
    }
}

/**
 * Get the signed-in sailConnect identity (name, email, membership)
 * @returns {Promise<Object|null>} Identity or null when it cannot be loaded
 */
export async function getIdentity() {
    try {
        return await ApiService.getIdentity();
    } catch (error) {
        console.error('Failed to get identity:', error);
        return null;
    }
}
