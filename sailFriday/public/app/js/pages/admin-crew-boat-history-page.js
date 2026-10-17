/**
 * Admin Crew-Boat History Page
 * Stacked bar chart of past flotilla assignments: crew members (rows) x boats (stacked segments)
 */

import { requireAuth, getCurrentUser, signOut } from '../authService.js';
import { updateAuthenticatedNavigation, addAdminLink } from '../navigationService.js';
import { initHamburgerMenu } from '../hamburger.js';
import * as adminService from '../adminService.js';
import { showToast } from '../toast.js';

// Fixed categorical hue order (8 anchors), never cycled/reordered.
// Beyond 8 distinct boats, later boats reuse a hue at reduced opacity —
// identity for those boats leans on the legend label and tooltip text,
// not on color alone.
const CATEGORICAL_HUES = [
    '#2a78d6', // blue
    '#eb6834', // orange
    '#1baf7a', // aqua
    '#eda100', // yellow
    '#e87ba4', // magenta
    '#008300', // green
    '#4a3aa7', // violet
    '#e34948', // red
];

let tooltipEl;

// Initialize page
document.addEventListener('DOMContentLoaded', async () => {
    initHamburgerMenu();
    requireAuth();

    const user = await getCurrentUser();
    if (!user) {
        window.location.href = 'signin.html';
        return;
    }

    if (!user.isAdmin) {
        console.warn('Access denied: User is not an admin');
        window.location.href = 'dashboard.html';
        return;
    }

    updateAuthenticatedNavigation(user, signOut);
    addAdminLink(user);

    tooltipEl = document.getElementById('history-tooltip');

    await loadHistory();
});

/**
 * Load crew-boat history and render the chart + table
 */
async function loadHistory() {
    const emptyState = document.getElementById('empty-state');

    try {
        const records = await adminService.getCrewBoatHistory();

        if (!records || records.length === 0) {
            emptyState.innerHTML = '';
            const icon = document.createElement('div');
            icon.className = 'empty-state-icon';
            icon.textContent = '📈';
            const message = document.createElement('p');
            message.textContent = 'No past flotilla assignments yet.';
            emptyState.appendChild(icon);
            emptyState.appendChild(message);
            return;
        }

        emptyState.style.display = 'none';
        renderChart(records);
        renderTable(records);

        document.getElementById('chart-section').style.display = 'block';
        document.getElementById('table-section').style.display = 'block';
    } catch (error) {
        console.error('Failed to load crew-boat history:', error);
        showToast(error.message || 'Failed to load crew-boat history', 'error');
    }
}

/**
 * Build a stable color for each boat, in alphabetical boat order.
 * @param {string[]} boatNames Alphabetically sorted, de-duplicated boat names
 * @returns {Map<string, string>}
 */
function buildBoatColors(boatNames) {
    const colors = new Map();

    boatNames.forEach((boatName, index) => {
        const tier = Math.floor(index / CATEGORICAL_HUES.length);
        const slot = index % CATEGORICAL_HUES.length;
        const hex = CATEGORICAL_HUES[slot];
        const alpha = tier === 0 ? 1 : Math.max(0.3, 0.65 - (tier - 1) * 0.15);
        colors.set(boatName, hexToRgba(hex, alpha));
    });

    return colors;
}

function hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * Render the stacked bar chart and its legend
 * @param {Array<{crew_name: string, boat_name: string, count: number}>} records
 */
function renderChart(records) {
    // Group by crew, preserving the alphabetical order returned by the API
    const crewOrder = [];
    const crewSegments = new Map();
    const boatNameSet = new Set();

    records.forEach(({ crew_name: crewName, boat_name: boatName, count }) => {
        if (!crewSegments.has(crewName)) {
            crewSegments.set(crewName, []);
            crewOrder.push(crewName);
        }
        crewSegments.get(crewName).push({ boatName, count });
        boatNameSet.add(boatName);
    });

    const boatNames = Array.from(boatNameSet).sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
    const boatColors = buildBoatColors(boatNames);

    const crewTotals = new Map();
    let maxTotal = 0;
    crewOrder.forEach((crewName) => {
        const total = crewSegments.get(crewName).reduce((sum, seg) => sum + seg.count, 0);
        crewTotals.set(crewName, total);
        maxTotal = Math.max(maxTotal, total);
    });

    renderLegend(boatNames, boatColors);

    const chartContainer = document.getElementById('chart-container');
    chartContainer.innerHTML = '';

    crewOrder.forEach((crewName) => {
        chartContainer.appendChild(buildRow(crewName, crewSegments.get(crewName), crewTotals.get(crewName), maxTotal, boatColors));
    });
}

function buildRow(crewName, segments, total, maxTotal, boatColors) {
    const row = document.createElement('div');
    row.className = 'history-row';

    const label = document.createElement('div');
    label.className = 'history-row-label';
    label.textContent = crewName;
    label.title = crewName;
    row.appendChild(label);

    const track = document.createElement('div');
    track.className = 'history-row-track';

    const bar = document.createElement('div');
    bar.className = 'history-bar';
    bar.style.width = `${maxTotal > 0 ? (total / maxTotal) * 100 : 0}%`;

    segments.forEach(({ boatName, count }) => {
        const segment = document.createElement('div');
        segment.className = 'history-segment';
        segment.style.flex = `${count} 0 0%`;
        segment.style.backgroundColor = boatColors.get(boatName);
        segment.tabIndex = 0;
        segment.setAttribute('role', 'img');
        segment.setAttribute('aria-label', `${boatName}: ${formatFlotillaCount(count)} with ${crewName}`);

        const show = () => showTooltip(segment, crewName, boatName, count);
        segment.addEventListener('mouseenter', show);
        segment.addEventListener('focus', show);
        segment.addEventListener('mouseleave', hideTooltip);
        segment.addEventListener('blur', hideTooltip);

        bar.appendChild(segment);
    });

    track.appendChild(bar);
    row.appendChild(track);

    const value = document.createElement('div');
    value.className = 'history-value';
    value.textContent = String(total);
    row.appendChild(value);

    return row;
}

function renderLegend(boatNames, boatColors) {
    const legend = document.getElementById('legend-container');
    legend.innerHTML = '';

    const list = document.createElement('div');
    list.className = 'history-legend';

    boatNames.forEach((boatName) => {
        const item = document.createElement('div');
        item.className = 'history-legend-item';

        const swatch = document.createElement('span');
        swatch.className = 'history-legend-swatch';
        swatch.style.backgroundColor = boatColors.get(boatName);

        const label = document.createElement('span');
        label.textContent = boatName;

        item.appendChild(swatch);
        item.appendChild(label);
        list.appendChild(item);
    });

    legend.appendChild(list);
}

function formatFlotillaCount(count) {
    return `${count} flotilla${count === 1 ? '' : 's'}`;
}

function showTooltip(target, crewName, boatName, count) {
    if (!tooltipEl) return;

    tooltipEl.innerHTML = '';
    const strong = document.createElement('strong');
    strong.textContent = formatFlotillaCount(count);
    const rest = document.createTextNode(` — ${crewName} on ${boatName}`);
    tooltipEl.appendChild(strong);
    tooltipEl.appendChild(rest);

    const rect = target.getBoundingClientRect();
    tooltipEl.style.left = `${rect.left + rect.width / 2}px`;
    tooltipEl.style.top = `${rect.top - 10}px`;
    tooltipEl.style.transform = 'translate(-50%, -100%)';
    tooltipEl.classList.add('visible');
}

function hideTooltip() {
    if (!tooltipEl) return;
    tooltipEl.classList.remove('visible');
}

/**
 * Render the full-precision data table
 * @param {Array<{crew_name: string, boat_name: string, count: number}>} records
 */
function renderTable(records) {
    const container = document.getElementById('table-container');
    container.innerHTML = '';

    const table = document.createElement('table');
    table.className = 'data-table';

    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    ['Crew', 'Boat', 'Flotillas'].forEach((text) => {
        const th = document.createElement('th');
        th.textContent = text;
        headRow.appendChild(th);
    });
    thead.appendChild(headRow);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    records.forEach(({ crew_name: crewName, boat_name: boatName, count }) => {
        const row = document.createElement('tr');

        const crewCell = document.createElement('td');
        crewCell.textContent = crewName;
        row.appendChild(crewCell);

        const boatCell = document.createElement('td');
        boatCell.textContent = boatName;
        row.appendChild(boatCell);

        const countCell = document.createElement('td');
        countCell.textContent = String(count);
        row.appendChild(countCell);

        tbody.appendChild(row);
    });
    table.appendChild(tbody);

    container.appendChild(table);
}
