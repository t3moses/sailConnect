/**
 * Dashboard Page Module
 * Handles dashboard page display and event availability management
 */

import { requireAuth, getCurrentUser, signOut } from '../authService.js';
import { updateAuthenticatedNavigation, addAdminLink } from '../navigationService.js';
import { getAllEvents, isDeadlinePassed } from '../eventService.js';
import { updateBatchAvailability, flagAssignedCrew, updateAssignedCrewSkill, removeCrewFromWhitelist, recalculateSeason } from '../userService.js';
import { get } from '../apiService.js';
import { API_CONFIG } from '../config.js';
import { showSuccess, showError } from '../toastService.js';

// Make signOut available globally for onclick handlers
window.signOut = signOut;

// Require authentication
if (!requireAuth()) {
    // requireAuth redirects to signin.html if not authenticated
}

// Get current user
const user = await getCurrentUser();
if (!user) {
    console.error('No user found, redirecting to sign in');
    alert('Session error. Please sign in again.');
    window.location.href = 'signin.html';
    throw new Error('No user found'); // Stop execution
}

console.log('User loaded successfully:', user.email);

// Update navigation with user's name and attach sign-out handler
updateAuthenticatedNavigation(user, signOut);

// Populate username in hero
document.getElementById('hero-username').textContent = user.profile.firstName;

// Add admin link if user is admin
addAdminLink(user);

// Populate account badge
const badge = document.getElementById('account-badge');
if (user.accountType === 'crew') {
    badge.textContent = '🌊 Crew Member';
    badge.classList.add('crew-member');
} else {
    badge.textContent = '⛵ Boat Owner';
    badge.classList.add('boat-owner');
}

// Populate profile details
const profileDetails = document.getElementById('profile-details');
let profileHTML = '';
console.log(user);
if (user.accountType === 'crew') {
    profileHTML = `
        <div class="profile-item">
            <span class="profile-label">Name:</span>
            <span class="profile-value">${user.profile.firstName} ${user.profile.lastName}</span>
        </div>
        <div class="profile-item">
            <span class="profile-label">Email:</span>
            <span class="profile-value">${user.email}</span>
        </div>
        ${user.membershipNumber ? `
        <div class="profile-item">
            <span class="profile-label">Membership Number:</span>
            <span class="profile-value">${user.profile.membershipNumber}</span>
        </div>
        ` : ''}
        <div class="profile-item">
            <span class="profile-label">WhatsApp Group:</span>
            <span class="profile-value">${user.profile.whatsappGroup ? 'Yes, enrolled' : 'Not enrolled'}</span>
        </div>
        ${user.profile.mobile ? `
        <div class="profile-item">
            <span class="profile-label">Mobile:</span>
            <span class="profile-value">${user.profile.mobile}</span>
        </div>
        ` : ''}
    `;
} else {
    profileHTML = `
        <div class="profile-item">
            <span class="profile-label">Name:</span>
            <span class="profile-value">${user.profile.firstName} ${user.profile.lastName}</span>
        </div>
        <div class="profile-item">
            <span class="profile-label">Email:</span>
            <span class="profile-value">${user.email}</span>
        </div>
        <div class="profile-item">
            <span class="profile-label">Phone:</span>
            <span class="profile-value">${user.profile.phone}</span>
        </div>
        <div class="profile-item">
            <span class="profile-label">Boat Name:</span>
            <span class="profile-value">${user.profile.boatName}</span>
        </div>
        <div class="profile-item">
            <span class="profile-label">Crew Capacity:</span>
            <span class="profile-value">${user.profile.minCrew || 1} - ${user.profile.maxCrew} crew members</span>
        </div>
        <div class="profile-item">
            <span class="profile-label">First Mate Requested:</span>
            <span class="profile-value">${user.profile.requestFirstMate ? 'Yes' : 'No'}</span>
        </div>
        <div class="profile-item">
            <span class="profile-label">WhatsApp Group:</span>
            <span class="profile-value">${user.profile.whatsappGroup ? 'Yes, enrolled' : 'Not enrolled'}</span>
        </div>
    `;
}

profileDetails.innerHTML = profileHTML;

/**
 * Populate user's boat assignments
 */
async function populateAssignments() {
    const container = document.getElementById('assignments-container');

    // Show loading state
    container.innerHTML = '<div class="loading-state" style="text-align: center; padding: 2rem; color: var(--text-gray);">Loading assignments...</div>';

    try {
        // Fetch assignments from API
        const response = await get(API_CONFIG.ENDPOINTS.ASSIGNMENTS);
        const assignments = response.data?.assignments || [];

        // Filter for assignments where a boat has actually been matched
        const boatAssignments = assignments.filter(a => a.boatName);

        if (boatAssignments.length === 0) {
            // Show empty state
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">⛵</div>
                    <p><strong>No assignments yet</strong></p>
                    <p>Mark your availability above to get matched with a boat and crew!</p>
                </div>
            `;
            return;
        }

        // Clear container
        container.innerHTML = '';

        const isBoatOwner = user.accountType !== 'crew';

        // Crew detail data for the boat owner's crew-detail modal, keyed by
        // "eventId|crewKey" (see openCrewDetailModal / the click handler below).
        crewDetailData.clear();

        // Render each assignment
        boatAssignments.forEach(assignment => {
            const card = document.createElement('div');
            card.className = 'assignment-card';

            // Format date for display (parse as local date to avoid timezone issues)
            const [year, month, day] = assignment.eventDate.split('-').map(Number);
            const date = new Date(year, month - 1, day); // month is 0-indexed
            const displayDate = date.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric'
            });

            // Format time range
            const timeRange = `${formatTime(assignment.startTime)} - ${formatTime(assignment.finishTime)}`;

            // Build crewmates HTML. For boat owners, every crewmate name is a
            // button that opens a detail modal (editable for past events,
            // read-only for future ones - see openCrewDetailModal). Crew
            // members viewing their own crewmates always see a plain tag.
            const eventHasPassed = hasEventOccurred(assignment.eventDate, assignment.finishTime);

            // Crew members see their own display name first in the crew list,
            // styled the same as their crewmates' tags.
            const selfTag = (!isBoatOwner && assignment.displayName)
                ? `<span class="crew-tag">${assignment.displayName}</span>`
                : '';

            let crewmatesHTML = '';
            if ((assignment.crewmates && assignment.crewmates.length > 0) || selfTag) {
                const tags = (assignment.crewmates || []).map(c => {
                    if (!isBoatOwner) {
                        return `<span class="crew-tag">${c.display_name}</span>`;
                    }

                    const detailKey = `${assignment.eventId}|${c.key}`;
                    crewDetailData.set(detailKey, {
                        eventId: assignment.eventId,
                        crewKey: c.key,
                        displayName: c.display_name,
                        skill: c.skill,
                        membershipRank: c.membership_rank,
                        experience: c.experience,
                        commitmentRank: c.commitment_rank,
                        initialCommitmentRank: c.initial_commitment_rank,
                        isPast: eventHasPassed
                    });
                    return `<button type="button" class="crew-tag crew-tag-btn"
                                   data-detail-key="${detailKey}">${c.display_name}</button>`;
                }).join('');
                crewmatesHTML = `<div class="assignment-crew">${selfTag}${tags}</div>`;
            }

            card.innerHTML = `
                <div class="assignment-date">${displayDate} • ${timeRange}</div>
                <div class="assignment-boat">⛵ ${assignment.boatName}</div>
                ${crewmatesHTML}
            `;

            container.appendChild(card);
        });

        container.querySelectorAll('.crew-tag-btn').forEach(btn => {
            btn.addEventListener('click', () => openCrewDetailModal(crewDetailData.get(btn.dataset.detailKey)));
        });
    } catch (error) {
        console.error('Failed to load assignments:', error);
        container.innerHTML = '<div class="alert alert-error">Failed to load assignments. Please refresh the page.</div>';
    }
}

const crewDetailData = new Map();

const SKILL_LABELS = ['Novice', 'Competent crew', 'Competent first mate'];

// Whether the currently-open crew detail modal is for a past event - set by
// openCrewDetailModal, read by the Done button handler to decide whether
// closing the form should trigger a season recalculation (see below).
let currentModalIsPast = false;

/**
 * Open the crew detail modal for a boat owner's crewmate. Future events show
 * everything read-only, including a "No shows" count. Past events let the
 * owner correct skill (dropdown, saved on change) and flag a no-show, which
 * withdraws the crew from future events and may deactivate their account.
 */
function openCrewDetailModal(detail) {
    if (!detail) {
        return;
    }

    document.getElementById('crew-detail-name').textContent = detail.displayName || '';
    document.getElementById('crew-detail-membership').textContent = detail.membershipRank === 1 ? 'Member' : 'Non-member';
    document.getElementById('crew-detail-experience').textContent = detail.experience || '—';

    const skillDisplay = document.getElementById('crew-detail-skill-display');
    const skillSelect = document.getElementById('crew-detail-skill-select');
    const noShowsGroup = document.getElementById('crew-detail-no-shows-group');
    const noShowsValue = document.getElementById('crew-detail-no-shows-value');
    const noShowActionGroup = document.getElementById('crew-detail-no-show-action-group');
    const noShowBtn = document.getElementById('crew-detail-no-show');
    const removeWhitelistBtn = document.getElementById('crew-detail-remove-whitelist');
    const removeWhitelistHint = document.getElementById('crew-detail-remove-whitelist-hint');

    currentModalIsPast = detail.isPast;

    if (detail.isPast) {
        skillDisplay.classList.add('hidden');
        skillSelect.classList.remove('hidden');
        skillSelect.value = String(detail.skill);
        skillSelect.disabled = false;
        skillSelect.dataset.eventId = detail.eventId;
        skillSelect.dataset.crewKey = detail.crewKey;

        noShowsGroup.classList.add('hidden');

        noShowActionGroup.classList.remove('hidden');
        noShowBtn.classList.remove('hidden');
        noShowBtn.disabled = false;
        noShowBtn.dataset.eventId = detail.eventId;
        noShowBtn.dataset.crewKey = detail.crewKey;

        removeWhitelistHint.classList.remove('hidden');
        removeWhitelistBtn.classList.remove('hidden');
        removeWhitelistBtn.disabled = false;
        removeWhitelistBtn.dataset.eventId = detail.eventId;
        removeWhitelistBtn.dataset.crewKey = detail.crewKey;
    } else {
        skillDisplay.classList.remove('hidden');
        skillDisplay.textContent = SKILL_LABELS[detail.skill] ?? '—';
        skillSelect.classList.add('hidden');

        noShowsGroup.classList.remove('hidden');
        noShowsValue.textContent = String(detail.initialCommitmentRank - detail.commitmentRank);

        noShowActionGroup.classList.add('hidden');
        noShowBtn.classList.add('hidden');

        removeWhitelistHint.classList.add('hidden');
        removeWhitelistBtn.classList.add('hidden');
    }

    document.getElementById('crew-detail-modal').classList.remove('hidden');
}

function hideCrewDetailModal() {
    document.getElementById('crew-detail-modal').classList.add('hidden');
}

// Closing a past-event form may have changed data the selection algorithm
// depends on (skill correction, no-show/commitment rank), so re-run the
// season update pipeline and reload the dashboard's assignments. Future-event
// forms are read-only, so closing them needs no recalculation.
document.getElementById('crew-detail-done').addEventListener('click', async () => {
    const wasPast = currentModalIsPast;
    hideCrewDetailModal();

    if (!wasPast) {
        return;
    }

    const container = document.getElementById('assignments-container');
    container.innerHTML = '<div class="loading-state" style="text-align: center; padding: 2rem; color: var(--text-gray);">Recalculating assignments...</div>';

    const result = await recalculateSeason();
    if (!result.success) {
        showError(result.error || 'Failed to recalculate assignments');
    }

    await populateAssignments();
});

const crewDetailModal = document.getElementById('crew-detail-modal');
crewDetailModal.addEventListener('click', (e) => {
    if (e.target === crewDetailModal) {
        hideCrewDetailModal();
    }
});

document.getElementById('crew-detail-skill-select').addEventListener('change', async (e) => {
    const select = e.target;
    const { eventId, crewKey } = select.dataset;
    const newSkill = parseInt(select.value, 10);

    select.disabled = true;
    const result = await updateAssignedCrewSkill(eventId, crewKey, newSkill);
    select.disabled = false;

    if (!result.success) {
        showError(result.error || 'Failed to update skill');
        return;
    }

    const detail = crewDetailData.get(`${eventId}|${crewKey}`);
    if (detail) {
        detail.skill = newSkill;
    }
    showSuccess('Skill updated.');
});

document.getElementById('crew-detail-no-show').addEventListener('click', async (e) => {
    const btn = e.target;
    const { eventId, crewKey } = btn.dataset;

    btn.disabled = true;
    const result = await flagAssignedCrew([{ eventId, crewKey }]);

    if (!result.success) {
        btn.disabled = false;
        showError(result.error || 'Failed to record no-show');
        return;
    }

    const flagged = result.data?.flagged?.[0];

    if (!flagged) {
        btn.disabled = false;
        return;
    }

    const detail = crewDetailData.get(`${eventId}|${crewKey}`);
    if (detail) {
        detail.commitmentRank = flagged.rank_commitment;
    }

    if (flagged.active === false) {
        showSuccess('No-show recorded. Crew marked inactive.');
        // Already at rock bottom and marked inactive - nothing left to flag.
        btn.disabled = true;
    } else {
        showSuccess('No-show recorded.');
        btn.disabled = false;
    }
});

document.getElementById('crew-detail-remove-whitelist').addEventListener('click', async (e) => {
    const btn = e.target;
    const { eventId, crewKey } = btn.dataset;

    btn.disabled = true;
    const result = await removeCrewFromWhitelist(eventId, crewKey);

    if (!result.success) {
        btn.disabled = false;
        showError(result.error || 'Failed to remove boat from whitelist');
        return;
    }

    showSuccess('Boat removed from whitelist.');
    hideCrewDetailModal();

    const container = document.getElementById('assignments-container');
    container.innerHTML = '<div class="loading-state" style="text-align: center; padding: 2rem; color: var(--text-gray);">Recalculating assignments...</div>';

    const recalcResult = await recalculateSeason();
    if (!recalcResult.success) {
        showError(recalcResult.error || 'Failed to recalculate assignments');
    }

    await populateAssignments();
});

/**
 * Check whether an event has already finished (mirrors the server's
 * EventRepository::findPastEvents definition: event_date/finish_time < now).
 */
function hasEventOccurred(eventDate, finishTime) {
    return new Date() > new Date(`${eventDate}T${finishTime}`);
}

/**
 * Format time from HH:MM:SS to H:MM AM/PM
 */
function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':').map(Number);
    const period = hours >= 12 ? 'PM' : 'AM';
    const displayHours = hours % 12 || 12;
    return `${displayHours}:${minutes.toString().padStart(2, '0')} ${period}`;
}

// Populate event availability controls (dropdown for boat owners, checkbox for crew)
async function populateEventAvailability() {
    const availabilityList = document.getElementById('availability-list');

    try {
        // Check blackout window using server time before rendering controls
        const statusResponse = await get(API_CONFIG.ENDPOINTS.STATUS);
        const isBlackout = statusResponse?.data?.isBlackout === true;

        if (isBlackout) {
            availabilityList.innerHTML = `
                <div class="alert alert-info">
                    <strong>Registration is currently closed.</strong><br>
                    Availability cannot be changed during the event (10:00 AM – 6:00 PM).
                    Please come back after the event ends.
                </div>
            `;
            return;
        }

        const events = await getAllEvents();
        const isBoatOwner = user.accountType !== 'crew';

        events.forEach(event => {
            const deadlinePassed = isDeadlinePassed(event.date);
            const itemDiv = document.createElement('div');
            itemDiv.className = 'availability-item' + (deadlinePassed ? ' disabled' : '');

            if (isBoatOwner) {
                const maxBerths = parseInt(user.profile.maxCrew, 10) || 0;
                // persistedBerths is undefined when no DB row exists yet
                const persistedBerths = user.eventBerths[event.eventId];
                const displayBerths = persistedBerths ?? maxBerths;

                // Build options 0..maxBerths
                let options = `<option value="0"${displayBerths === 0 ? ' selected' : ''}>Not available</option>`;
                for (let i = 1; i <= maxBerths; i++) {
                    options += `<option value="${i}"${displayBerths === i ? ' selected' : ''}>${i} berth${i !== 1 ? 's' : ''}</option>`;
                }

                // data-original is '' when no row exists so any save triggers a write
                itemDiv.innerHTML = `
                    <select class="berths-select"
                            data-event-date="${event.eventId}"
                            data-original="${persistedBerths ?? ''}"
                            ${deadlinePassed ? 'disabled' : ''}>
                        ${options}
                    </select>
                    <label class="availability-date">${event.displayDate || event.eventId}</label>
                    ${deadlinePassed ? '<span class="deadline-warning">Deadline Passed</span>' : ''}
                `;

                availabilityList.appendChild(itemDiv);

                const select = itemDiv.querySelector('select.berths-select');
                select.addEventListener('change', () => {
                    const newBerths = parseInt(select.value, 10);
                    // '' means no row in DB yet; treat as always-dirty so saving at max still persists
                    const originalRaw = select.dataset.original;
                    const originalBerths = originalRaw === '' ? null : parseInt(originalRaw, 10);
                    saveAvailabilityChange({
                        element: select,
                        type: 'boat',
                        eventDate: event.eventId,
                        newValue: newBerths,
                        originalValue: originalBerths,
                        payload: { eventId: event.eventId, isAvailable: newBerths > 0, berths: newBerths }
                    });
                });
            } else {
                const isAvailable = user.eventAvailability[event.eventId] || false;

                itemDiv.innerHTML = `
                    <input type="checkbox"
                           id="event-${event.eventId}"
                           data-event-date="${event.eventId}"
                           data-original="${isAvailable}"
                           ${isAvailable ? 'checked' : ''}
                           ${deadlinePassed ? 'disabled' : ''}>
                    <label for="event-${event.eventId}" class="availability-date">
                        ${event.displayDate || event.eventId}
                    </label>
                    ${deadlinePassed ? '<span class="deadline-warning">Deadline Passed</span>' : ''}
                `;

                availabilityList.appendChild(itemDiv);

                const checkbox = itemDiv.querySelector('input[type="checkbox"]');
                checkbox.addEventListener('change', () => {
                    const isChecked = checkbox.checked;
                    const originalValue = checkbox.dataset.original === 'true';
                    saveAvailabilityChange({
                        element: checkbox,
                        type: 'crew',
                        eventDate: event.eventId,
                        newValue: isChecked,
                        originalValue,
                        payload: { eventId: event.eventId, isAvailable: isChecked }
                    });
                });
            }
        });
    } catch (error) {
        console.error('Failed to load events:', error);
        availabilityList.innerHTML = '<div class="alert alert-error">Failed to load events. Please refresh the page.</div>';
    }
}

// Call the async function
populateEventAvailability();

// Load user's boat assignments
populateAssignments();

/**
 * Save a single availability change (boat berths or crew checkbox) immediately
 * when its control is changed, reverting the control on failure.
 */
async function saveAvailabilityChange(change) {
    const { element, type, eventDate, newValue, originalValue, payload } = change;

    element.disabled = true;

    const result = await updateBatchAvailability([payload]);

    element.disabled = false;

    if (!result.success) {
        if (type === 'boat') {
            if (originalValue !== null) element.value = String(originalValue);
        } else {
            element.checked = originalValue;
        }
        showError(result.error || 'Failed to update availability');
        return;
    }

    element.dataset.original = String(newValue);
    if (type === 'boat') {
        user.eventBerths[eventDate] = newValue;
        user.eventAvailability[eventDate] = newValue > 0;
    } else {
        user.eventAvailability[eventDate] = newValue;
    }

    showSuccess('Availability updated successfully! Your assignments have been refreshed.');

    // Reload assignments in case they changed
    await populateAssignments();

    // Smoothly scroll to assignments section so user can see updates
    setTimeout(() => {
        const assignmentsSection = document.getElementById('assignments-container');
        if (assignmentsSection) {
            assignmentsSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }, 300); // Small delay to let assignments load
}
