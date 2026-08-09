/*
 * The scan screen's glyphs.
 *
 * Inline SVG strings, the same way the dashboard does it (see Dashboard/icons.js): there
 * are a handful, they never change, and it keeps the panels that use them declarative.
 */

const icon = (paths, size = 20) =>
    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
          stroke-linecap="round" stroke-linejoin="round" width="${size}" height="${size}">${paths}</svg>`;

/* The WordPress logo keeps its own viewBox and is filled, not stroked. */
const wordpressMark = (size = 18) =>
    `<svg viewBox="0 0 122.52 122.523" fill="currentColor" width="${size}" height="${size}"
          aria-hidden="true"><path d="m8.708 61.26c0 20.802 12.089 38.779 29.619 47.298l-25.069-68.686c-2.916 6.536-4.55 13.769-4.55 21.388z"/><path d="m96.74 58.608c0-6.495-2.333-10.993-4.334-14.494-2.664-4.329-5.161-7.995-5.161-12.324 0-4.831 3.664-9.328 8.825-9.328.233 0 .454.029.681.042-9.35-8.566-21.807-13.796-35.489-13.796-18.36 0-34.513 9.42-43.91 23.688 1.233.037 2.395.063 3.382.063 5.497 0 14.006-.667 14.006-.667 2.833-.167 3.167 3.994.337 4.329 0 0-2.847.335-6.015.501l19.138 56.925 11.501-34.493-8.188-22.434c-2.83-.166-5.511-.501-5.511-.501-2.832-.166-2.5-4.496.332-4.329 0 0 8.679.667 13.843.667 5.496 0 14.006-.667 14.006-.667 2.835-.167 3.168 3.994.337 4.329 0 0-2.853.335-6.015.501l18.992 56.494 5.242-17.517c2.272-7.269 4.001-12.49 4.001-16.989z"/><path d="m62.184 65.857-15.768 45.819c4.708 1.384 9.687 2.141 14.846 2.141 6.12 0 11.989-1.058 17.452-2.979-.141-.225-.269-.464-.374-.724z"/><path d="m107.376 36.046c.226 1.674.354 3.471.354 5.404 0 5.333-.996 11.328-3.996 18.824l-16.053 46.413c15.624-9.111 26.133-26.038 26.133-45.426.001-9.137-2.333-17.729-6.438-25.215z"/><path d="m61.262 0c-33.779 0-61.262 27.481-61.262 61.26 0 33.783 27.483 61.263 61.262 61.263 33.778 0 61.265-27.48 61.265-61.263-.001-33.779-27.487-61.26-61.265-61.26zm0 119.715c-32.23 0-58.453-26.223-58.453-58.455 0-32.23 26.222-58.451 58.453-58.451 32.229 0 58.45 26.221 58.45 58.451 0 32.232-26.221 58.455-58.45 58.455z"/></svg>`;

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
    /*
     * The WordPress mark itself, from the official SVG, rather than a W drawn out of the two
     * strokes the rest of these are made of - at 18px an approximation of it reads as a smudge.
     *
     * Filled rather than stroked, so it does not go through icon(): the fill is currentColor, so
     * it still takes the row's text colour and works in both themes.
     */
    wordpress: wordpressMark(18),
    chevron: icon('<path d="M8.5 10.5l3.5 3.5 3.5-3.5"/>', 16),

    // Aside
    clock: icon('<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.2 2"/>', 18),
    calendar: icon('<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17"/><path d="M8 3.5v3"/><path d="M16 3.5v3"/>', 18)
};
