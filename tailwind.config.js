/** @type {import('tailwindcss').Config} */

/*
 * The tokens are copied from FluentCart rather than approximated, so a user moving
 * between the two plugins is looking at the same greys, the same spacing steps and the
 * same radii. If that palette changes there, it should be copied again rather than
 * drifted towards.
 */
const colors = require("./src/admin/styles/tokens/color");
const spacing = require('./src/admin/styles/tokens/spacing');
const borderRadius = require('./src/admin/styles/tokens/borderRadius');
const fontSize = require('./src/admin/styles/tokens/fontSize');

/*
 * The colours the app actually paints with, named for what they are for.
 *
 * Each one is a CSS variable rather than a value, because it has two values - see
 * src/admin/styles/_theme.scss, where both themes are declared. That is the whole
 * mechanism: `@apply bg-surface` is correct in light and dark, `@apply bg-white` is
 * correct in one of them.
 *
 * The ramps above are still here and still used for anything that does not change between
 * themes - a chart series, a brand colour, a shadow. Reach for these first.
 */
const themed = {
    // Surfaces, from the page up: the page itself, a card on it, a well in the card.
    surface: 'var(--fls-surface)',
    'surface-sunk': 'var(--fls-surface-sunk)',
    'surface-raised': 'var(--fls-surface-raised)',

    // Rules and outlines.
    hairline: 'var(--fls-border)',
    'hairline-strong': 'var(--fls-border-strong)',

    // Text, from the loudest to the quietest.
    'ink-head': 'var(--fls-heading)',
    ink: 'var(--fls-text)',
    'ink-mid': 'var(--fls-text-mid)',
    'ink-light': 'var(--fls-text-light)',
    'ink-link': 'var(--fls-link)',

    // The brand colour, what goes on top of it, and a tint of it.
    accent: 'var(--fls-accent)',
    'accent-on': 'var(--fls-accent-contrast)',
    'accent-wash': 'var(--fls-accent-wash)',

    // Statuses: a band's fill and border, a chip's fill, and the text for all three.
    'danger-wash': 'var(--fls-danger-wash)',
    'danger-bg': 'var(--fls-danger-bg)',
    'danger-line': 'var(--fls-danger-line)',
    'danger-fg': 'var(--fls-danger-fg)',

    'caution-wash': 'var(--fls-warning-wash)',
    'caution-bg': 'var(--fls-warning-bg)',
    'caution-line': 'var(--fls-warning-line)',
    'caution-fg': 'var(--fls-warning-fg)',

    'ok-wash': 'var(--fls-success-wash)',
    'ok-bg': 'var(--fls-success-bg)',
    'ok-line': 'var(--fls-success-line)',
    'ok-fg': 'var(--fls-success-fg)',

    'quiet-bg': 'var(--fls-neutral-bg)',
    'quiet-fg': 'var(--fls-neutral-fg)'
};

module.exports = {
    darkMode: ['selector', '.fluent_theme_dark'],

    /*
     * Every utility is scoped under the app's own root element. This runs inside
     * wp-admin next to whatever else is installed, so utilities must not escape into
     * the surrounding page.
     */
    important: '#fluent_auth_app',

    content: [
        './src/admin/**/*.{vue,js}',
        './app/Views/**/*.php'
    ],

    corePlugins: {
        // WordPress supplies its own base styles; resetting them would break wp-admin.
        preflight: false
    },

    theme: {
        extend: {
            colors: {...colors, ...themed},
            borderRadius: borderRadius,
            borderWidth: {
                '0.5': '.5px'
            },
            screens: {
                '1xl': '1360px'
            }
        },
        fontFamily: {
            display: ['Inter'],
            body: ['Inter']
        },
        spacing: spacing,
        fontSize: fontSize
    },

    plugins: []
};
