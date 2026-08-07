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
            colors: colors,
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
