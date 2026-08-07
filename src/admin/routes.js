import Dashboard from './Components/Dashboard.vue';
import Logs from './Components/Logs.vue';

import SettingsLayout from './Components/Settings/SettingsLayout.vue';
import GeneralSettings from './Components/Settings/Pages/General.vue';
import EnrolledUsers from './Components/TwoFa/EnrolledUsers.vue';

import AuthShortcodes from './Components/AuthShortcodes.vue';
import LoginRedirects from './Components/LoginRedirects.vue';
import SocialAuthSettings from './Components/SocialAuthSettings.vue';
import CustomWpEmails from './Components/CustomWpEmails/AllEmails.vue';
import EditWpEmail from './Components/CustomWpEmails/EditWpEmail.vue';
import TemplateSettings from './Components/CustomWpEmails/TemplateSettings.vue';
import SecurityScans from './Components/SecurityScan/index.vue';
import RegisterPromt from './Components/SecurityScan/RegisterPromt.vue';
import AuthCustomizer from './Components/AuthCustomizer/AuthCustomizer.vue';
import ServerMode from './Components/ServerMode/ServerMode.vue';

/*
 * Everything configurable is a child of /settings, so the sidebar is the one place to
 * look for a setting. The top bar keeps only the places you go to look at something.
 */
const settingsChildren = [
    {
        path: '',
        name: 'settings_general',
        component: GeneralSettings,
        meta: {title: 'Settings'}
    },
    {
        path: 'two-factor-enrollment',
        name: 'settings_two_fa_enrollment',
        component: EnrolledUsers,
        meta: {title: 'Two-Factor Enrollment'}
    },
    {
        path: 'social-login',
        name: 'settings_social_login',
        component: SocialAuthSettings,
        meta: {title: 'Social Login'}
    },
    {
        path: 'auth-forms',
        name: 'settings_auth_forms',
        component: AuthShortcodes,
        meta: {title: 'Login/Signup Forms'}
    },
    {
        path: 'login-page-design',
        name: 'settings_auth_customizer',
        component: AuthCustomizer,
        meta: {title: 'Login Page Design'}
    },
    {
        path: 'redirects',
        name: 'settings_redirects',
        component: LoginRedirects,
        meta: {title: 'Login Redirects'}
    },
    {
        path: 'emails',
        name: 'settings_emails',
        component: CustomWpEmails,
        meta: {title: 'System Emails'}
    },
    {
        path: 'emails/template',
        name: 'settings_email_template',
        component: TemplateSettings,
        meta: {title: 'Email Template Design'}
    },
    {
        path: 'emails/:email_id/edit',
        name: 'settings_edit_email',
        component: EditWpEmail,
        props: true,
        meta: {title: 'Edit Email'}
    },
    {
        path: 'remote-auth',
        name: 'settings_server_mode',
        component: ServerMode,
        meta: {title: 'Remote Auth'}
    }
];

export var routes = [
    {
        path: '/',
        name: 'dashboard',
        component: Dashboard,
        meta: {
            active: 'dashboard',
            title: 'Dashboard'
        }
    },
    {
        path: '/logs',
        name: 'logs',
        component: Logs,
        meta: {
            active: 'logs',
            title: 'Auth Logs'
        }
    },
    {
        path: '/security-scans',
        name: 'security_scans',
        component: SecurityScans,
        meta: {
            active: 'security_scans',
            title: 'Security Scans'
        }
    },
    {
        path: '/security-scans/register',
        name: 'security_scan_register',
        component: RegisterPromt,
        meta: {
            active: 'security_scans',
            title: 'Security Scans'
        }
    },
    {
        path: '/settings',
        component: SettingsLayout,
        children: settingsChildren.map(route => ({
            ...route,
            meta: {...route.meta, active: 'settings'}
        }))
    }
];
