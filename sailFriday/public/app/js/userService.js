/**
 * User Service Module
 * Handles user profile operations via API
 */

import { deleteRequest, patch, post } from './apiService.js';
import { API_CONFIG } from './config.js';

/**
 * Update user profile
 * @param {string} userId - User ID (ignored - backend uses JWT token from request)
 * @param {Object} updates - Object with fields to update
 * @returns {Promise<Object>} Result object with success status
 */
export async function updateUser(userId, updates) {
    try {
        console.log('Updating user profile via API:', updates);

        // Call PATCH /api/users/me endpoint
        // Backend uses JWT token to identify user, so userId is ignored
        const response = await patch(API_CONFIG.ENDPOINTS.USER_ME, updates);

        if (response?.success === false) {
            console.error('Profile update failed:', response.error);
            return { success: false, error: response.error || 'Failed to update profile' };
        }

        console.log('Profile updated successfully');
        return { success: true, data: response?.data };
    } catch (error) {
        console.error('Error updating profile:', error);
        return { success: false, error: error.message || 'Failed to update profile' };
    }
}

/**
 * Update availability for multiple events in a single request.
 * @param {Array<{eventId: string, isAvailable: boolean, berths?: number}>} availabilities
 * @returns {Promise<{success: boolean, data?: any, error?: string}>}
 */
export async function updateBatchAvailability(availabilities) {
    try {
        const response = await patch(API_CONFIG.ENDPOINTS.USER_AVAILABILITY, { availabilities });

        if (response?.success === false) {
            console.error('Batch availability update failed:', response.error);
            return { success: false, error: response.error || 'Failed to update availability' };
        }

        console.log('Batch availability updated successfully');
        return { success: true, data: response?.data };
    } catch (error) {
        console.error('Error updating batch availability:', error);
        return { success: false, error: error.message || 'Failed to update availability' };
    }
}

/**
 * Flag crew members assigned to the caller's boat, decrementing each flagged
 * crew's commitment rank by the number of times they were flagged.
 * @param {Array<{eventId: string, crewKey: string}>} flags
 * @returns {Promise<{success: boolean, data?: any, error?: string}>}
 */
export async function flagAssignedCrew(flags) {
    try {
        const response = await post(API_CONFIG.ENDPOINTS.ASSIGNMENT_CREW_FLAGS, { flags });

        if (response?.success === false) {
            console.error('Flagging crew failed:', response.error);
            return { success: false, error: response.error || 'Failed to flag crew' };
        }

        return { success: true, data: response?.data };
    } catch (error) {
        console.error('Error flagging crew:', error);
        return { success: false, error: error.message || 'Failed to flag crew' };
    }
}

/**
 * Correct the skill level of a crew member assigned to the caller's boat for
 * a past event.
 * @param {string} eventId
 * @param {string} crewKey
 * @param {number} skill
 * @returns {Promise<{success: boolean, data?: any, error?: string}>}
 */
export async function updateAssignedCrewSkill(eventId, crewKey, skill) {
    try {
        const response = await patch(API_CONFIG.ENDPOINTS.ASSIGNMENT_CREW_SKILL, { eventId, crewKey, skill });

        if (response?.success === false) {
            console.error('Updating crew skill failed:', response.error);
            return { success: false, error: response.error || 'Failed to update skill' };
        }

        return { success: true, data: response?.data };
    } catch (error) {
        console.error('Error updating crew skill:', error);
        return { success: false, error: error.message || 'Failed to update skill' };
    }
}

/**
 * Remove the caller's boat from the whitelist of a crew member who was
 * assigned to their boat for a past event.
 * @param {string} eventId
 * @param {string} crewKey
 * @returns {Promise<{success: boolean, data?: any, error?: string}>}
 */
export async function removeCrewFromWhitelist(eventId, crewKey) {
    try {
        const response = await deleteRequest(API_CONFIG.ENDPOINTS.ASSIGNMENT_CREW_WHITELIST_ENTRY, { eventId, crewKey });

        if (response?.success === false) {
            console.error('Removing crew from whitelist failed:', response.error);
            return { success: false, error: response.error || 'Failed to remove boat from whitelist' };
        }

        return { success: true, data: response?.data };
    } catch (error) {
        console.error('Error removing crew from whitelist:', error);
        return { success: false, error: error.message || 'Failed to remove boat from whitelist' };
    }
}

/**
 * Re-run the season update pipeline (ranking, selection, flotilla generation)
 * using current database contents.
 * @returns {Promise<{success: boolean, data?: any, error?: string}>}
 */
export async function recalculateSeason() {
    try {
        const response = await post(API_CONFIG.ENDPOINTS.ASSIGNMENT_RECALCULATE, {});

        if (response?.success === false) {
            console.error('Recalculating season failed:', response.error);
            return { success: false, error: response.error || 'Failed to recalculate assignments' };
        }

        return { success: true, data: response?.data };
    } catch (error) {
        console.error('Error recalculating season:', error);
        return { success: false, error: error.message || 'Failed to recalculate assignments' };
    }
}
