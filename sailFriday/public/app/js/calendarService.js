/**
 * Calendar Service Module
 * Handles fetching and formatting event data with flotilla information
 * for calendar/schedule display views
 */

import * as ApiService from './apiService.js';

/**
 * Get all events with full flotilla data from /api/flotillas endpoint
 * @returns {Promise<Array>} Array of event objects with flotilla information
 */
export async function getEventsWithFlotilla() {
    try {
        const response = await ApiService.get('/flotillas');

        // API returns { success: true, data: { flotillas: [...] } }
        if (response.success && response.data && response.data.flotillas) {
            return response.data.flotillas;
        }

        return [];
    } catch (error) {
        console.error('Failed to fetch events with flotilla data:', error);
        throw error;
    }
}

/**
 * Format date string for display
 * Converts "2026-05-29" to "Friday, May 29"
 * @param {string} dateString - Date in YYYY-MM-DD format
 * @returns {string} Formatted date string
 */
export function formatDisplayDate(dateString) {
    try {
        const date = new Date(dateString + 'T12:00:00'); // Add time to avoid timezone issues
        const options = { weekday: 'long', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    } catch (error) {
        console.error('Error formatting date:', dateString, error);
        return dateString;
    }
}

/**
 * Check if event has any crewed boats assigned
 * @param {Object} event - Event object
 * @returns {boolean} True if event has crewed boats
 */
export function hasCrewedBoats(event) {
    return event.flotilla &&
           event.flotilla.crewedBoats &&
           event.flotilla.crewedBoats.length > 0;
}

/**
 * Check if event has any waitlisted items (boats or crews)
 * @param {Object} event - Event object
 * @returns {boolean} True if event has waitlisted boats or crews
 */
export function hasWaitlist(event) {
    if (!event.flotilla) return false;

    const hasWaitlistedBoats = event.flotilla.waitlistedBoats &&
                                event.flotilla.waitlistedBoats.length > 0;
    const hasWaitlistedCrews = event.flotilla.waitlistedCrews &&
                                event.flotilla.waitlistedCrews.length > 0;

    return hasWaitlistedBoats || hasWaitlistedCrews;
}

/**
 * Get waitlisted boats for an event
 * @param {Object} event - Event object
 * @returns {Array} Array of waitlisted boats
 */
export function getWaitlistedBoats(event) {
    return event.flotilla?.waitlistedBoats || [];
}

/**
 * Get waitlisted crews for an event
 * @param {Object} event - Event object
 * @returns {Array} Array of waitlisted crews
 */
export function getWaitlistedCrews(event) {
    return event.flotilla?.waitlistedCrews || [];
}

/**
 * Check if event deadline has passed (10 AM on event day)
 * @param {string} dateString - Event date in YYYY-MM-DD format
 * @returns {boolean} True if deadline has passed
 */
export function isDeadlinePassed(dateString) {
    const eventDateTime = new Date(dateString + 'T10:00:00');
    const now = new Date();
    return now > eventDateTime;
}

/**
 * Get event status display text
 * @param {Object} event - Event object
 * @returns {string} Status text (e.g., "upcoming", "past", "today")
 */
export function getEventStatus(event) {
    const eventDate = new Date(event.date + 'T12:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    eventDate.setHours(0, 0, 0, 0);

    if (eventDate.getTime() === today.getTime()) {
        return 'today';
    } else if (eventDate < today) {
        return 'past';
    } else {
        return 'upcoming';
    }
}
