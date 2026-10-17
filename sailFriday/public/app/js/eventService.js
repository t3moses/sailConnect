/**
 * Event Service Module
 * Manages sailing events and availability deadlines via API
 */

import * as ApiService from './apiService.js';

/**
 * Get all events from API
 * @returns {Promise<Array>} Array of event objects
 */
export async function getAllEvents() {
    try {
        return await ApiService.getAllEvents();
    } catch (error) {
        console.error('Failed to get events:', error);
        return [];
    }
}

/**
 * Get event by ID
 * @param {string} eventId - Event ID
 * @returns {Promise<Object|null>} Event object or null
 */
export async function getEventById(eventId) {
    const events = await getAllEvents();
    return events.find(e => e.eventId === eventId) || null;
}

/**
 * Get event by date
 * @param {string} eventDate - Event date (YYYY-MM-DD)
 * @returns {Promise<Object|null>} Event object or null
 */
export async function getEventByDate(eventDate) {
    const events = await getAllEvents();
    return events.find(e => e.date === eventDate) || null;
}

/**
 * Check if registration deadline has passed for an event
 * Deadline is 10 AM on the event day
 * @param {string} eventDate - Event date (YYYY-MM-DD)
 * @returns {boolean} True if deadline has passed
 */
export function isDeadlinePassed(eventDate) {
    const eventDateTime = new Date(eventDate + 'T10:00:00');
    const now = new Date();
    return now > eventDateTime;
}

/**
 * Get upcoming events (not past deadline)
 * @returns {Promise<Array>} Array of upcoming event objects
 */
export async function getUpcomingEvents() {
    const events = await getAllEvents();
    return events.filter(event => !isDeadlinePassed(event.date));
}

/**
 * Get past events (deadline has passed)
 * @returns {Promise<Array>} Array of past event objects
 */
export async function getPastEvents() {
    const events = await getAllEvents();
    return events.filter(event => isDeadlinePassed(event.date));
}
