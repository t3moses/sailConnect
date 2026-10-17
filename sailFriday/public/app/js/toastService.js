/**
 * Toast Notification Service
 * Displays temporary notifications in a fixed position
 */

let toastContainer = null;

/**
 * Initialize toast container (called automatically)
 */
function initToastContainer() {
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }
}

/**
 * Show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - Type of toast: 'success', 'error', 'info'
 * @param {number} duration - How long to show in milliseconds (default: 3000)
 */
export function showToast(message, type = 'info', duration = 3000) {
    initToastContainer();

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    // Choose icon based on type
    const icons = {
        success: '✓',
        error: '✕',
        info: 'ℹ'
    };

    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || icons.info}</span>
        <span class="toast-message">${message}</span>
    `;

    // Add to container
    toastContainer.appendChild(toast);

    // Auto-remove after duration
    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => {
            toast.remove();
        }, 300); // Match animation duration
    }, duration);
}

/**
 * Show success toast
 * @param {string} message
 * @param {number} duration
 */
export function showSuccess(message, duration = 3000) {
    showToast(message, 'success', duration);
}

/**
 * Show error toast
 * @param {string} message
 * @param {number} duration
 */
export function showError(message, duration = 4000) {
    showToast(message, 'error', duration);
}

/**
 * Show info toast
 * @param {string} message
 * @param {number} duration
 */
export function showInfo(message, duration = 3000) {
    showToast(message, 'info', duration);
}
