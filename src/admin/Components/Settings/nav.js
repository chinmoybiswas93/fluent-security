/*
 * The settings sidebar.
 *
 * Everything configurable lives here, grouped by the question being answered rather
 * than by which screen it used to live on. The top bar keeps only the places you go to
 * look at something - the dashboard, the logs, a scan - so "where do I change this?"
 * has exactly one answer.
 *
 * Icons are inline SVG rather than an icon font or a component per glyph: there are a
 * dozen of them, they never change, and this keeps the sidebar a data structure.
 */

const icon = (paths) =>
    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
          stroke-linecap="round" stroke-linejoin="round" width="18" height="18">${paths}</svg>`;

export const chevronIcon = icon('<path d="M9 18l6-6-6-6"/>');

const icons = {
    shield: icon('<path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3z"/>'),
    lock: icon('<rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>'),
    key: icon('<circle cx="8" cy="12" r="3.5"/><path d="M11.5 12H20l-1.5 2M16.5 12v2.5"/>'),
    login: icon('<path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>'),
    form: icon('<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h4"/>'),
    redirect: icon('<path d="M4 7h11a5 5 0 0 1 0 10H9"/><path d="M12 14l-3 3 3 3"/>'),
    mail: icon('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>'),
    bell: icon('<path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>'),
    globe: icon('<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18"/>'),
    sliders: icon('<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="18" r="2"/>'),
    server: icon('<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>')
};

/**
 * @param vm the component, for $t
 */
export const settingsNav = (vm) => [
    {
        route: 'settings_general',
        title: vm.$t('General'),
        icon: icons.shield
    },
    {
        route: 'settings_login_security',
        title: vm.$t('Login Security'),
        icon: icons.lock
    },
    {
        route: 'settings_two_fa',
        title: vm.$t('Two-Factor Auth'),
        icon: icons.key,
        children: [
            {route: 'settings_two_fa', title: vm.$t('Methods')},
            {route: 'settings_two_fa_enrollment', title: vm.$t('Enrollment')}
        ]
    },
    {
        route: 'settings_login_methods',
        title: vm.$t('Login Methods'),
        icon: icons.login,
        children: [
            {route: 'settings_login_methods', title: vm.$t('Magic Login')},
            {route: 'settings_social_login', title: vm.$t('Social Login')}
        ]
    },
    {
        route: 'settings_auth_forms',
        title: vm.$t('Login & Signup Forms'),
        icon: icons.form,
        children: [
            {route: 'settings_auth_forms', title: vm.$t('Forms')},
            {route: 'settings_auth_customizer', title: vm.$t('Login Page Design')}
        ]
    },
    {
        route: 'settings_redirects',
        title: vm.$t('Login Redirects'),
        icon: icons.redirect
    },
    {
        route: 'settings_emails',
        title: vm.$t('System Emails'),
        icon: icons.mail,
        children: [
            {route: 'settings_emails', title: vm.$t('All Emails')},
            {route: 'settings_email_template', title: vm.$t('Template Design')}
        ]
    },
    {
        route: 'settings_notifications',
        title: vm.$t('Notifications'),
        icon: icons.bell
    },
    {
        route: 'settings_proxy',
        title: vm.$t('Visitor IP'),
        icon: icons.globe
    },
    {
        route: 'settings_advanced',
        title: vm.$t('Advanced'),
        icon: icons.sliders
    },
    {
        route: 'settings_server_mode',
        title: vm.$t('Remote Auth'),
        icon: icons.server,
        when: (appVars) => !!appVars.has_server_mode
    }
];
