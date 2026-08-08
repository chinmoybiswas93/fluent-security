/*
 * The scan screen's glyphs.
 *
 * Inline SVG strings, the same way the dashboard does it (see Dashboard/icons.js): there
 * are a handful, they never change, and it keeps the panels that use them declarative.
 */

const icon = (paths, size = 20) =>
    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
          stroke-linecap="round" stroke-linejoin="round" width="${size}" height="${size}">${paths}</svg>`;

export default {
    // States
    shield: icon('<path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3z"/>', 26),
    shieldTick: icon('<path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3z"/><path d="M9 11.8l2.2 2.2L15 10"/>', 26),
    radar: icon('<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4"/><path d="M12 12l6-6"/>', 26),

    // Verdicts
    alert: icon('<path d="M12 4.5L3 19.5h18L12 4.5z"/><path d="M12 10v4"/><path d="M12 17h.01"/>', 16),
    tick: icon('<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>', 16),
    mute: icon('<path d="M11 5L6 9H3v6h3l5 4V5z"/><path d="M16 9l5 6"/><path d="M21 9l-5 6"/>', 16),

    // Groups and rows
    folder: icon('<path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/>', 18),
    plugin: icon('<path d="M9 3v4"/><path d="M15 3v4"/><path d="M6 7h12v5a6 6 0 0 1-12 0V7z"/><path d="M12 18v3"/>', 18),
    /* A paint roller: at 18px a palette's thumb hole and blobs read as a face. */
    theme: icon('<rect x="3.5" y="4" width="12" height="5" rx="1.5"/><path d="M15.5 6.5h3a2 2 0 0 1 2 2v1a2 2 0 0 1-2 2h-6a2 2 0 0 0-2 2v.5"/><rect x="8.5" y="15" width="4" height="5" rx="1.2"/>', 18),
    unknown: icon('<circle cx="12" cy="12" r="9"/><path d="M9.6 9.4a2.5 2.5 0 0 1 4.9.7c0 1.7-2.5 1.9-2.5 3.4"/><path d="M12 17h.01"/>', 18),
    file: icon('<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/>', 18),
    eye: icon('<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>', 16),
    more: icon('<circle cx="6" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="18" cy="12" r="1.4" fill="currentColor" stroke="none"/>', 16),

    // Summary rows
    wordpress: icon('<circle cx="12" cy="12" r="9"/><path d="M4.2 8.5h4.3l3 8 1.7-4.6-1.7-3.4h3.6"/><path d="M15.2 8.5h3.6l-3.4 8.6"/>', 18),
    chevron: icon('<path d="M8.5 10.5l3.5 3.5 3.5-3.5"/>', 16),

    // Aside
    clock: icon('<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.2 2"/>', 18),
    calendar: icon('<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17"/><path d="M8 3.5v3"/><path d="M16 3.5v3"/>', 18)
};
