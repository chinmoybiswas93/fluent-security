<script type="text/babel">
import settingsPage from '../settingsPage';
import SettingsHeader from '../_SettingsHeader.vue';
import SettingsSection from '../_SettingsSection.vue';

import CoreSecuritySection from '../Sections/_CoreSecurity.vue';
import LoginSecuritySection from '../Sections/_LoginSecurity.vue';
import MagicLoginSection from '../Sections/_MagicLogin.vue';
import NotificationsSection from '../Sections/_Notifications.vue';
import AdvancedSection from '../Sections/_Advanced.vue';
import TwoFaSettings from '../../TwoFa/_TwoFaSettings.vue';
import ProxySettings from '../../_ProxySettings.vue';

/**
 * Every setting that lives in the one saved option, on one page.
 *
 * They are together because they are saved together - one Save button writes the whole
 * option - and split into separate routes they would each have had to load and post
 * the entire thing anyway. The sidebar scrolls between them instead.
 */
export default {
    name: 'GeneralSettings',
    mixins: [settingsPage],
    components: {
        SettingsHeader,
        SettingsSection,
        CoreSecuritySection,
        LoginSecuritySection,
        MagicLoginSection,
        NotificationsSection,
        AdvancedSection,
        TwoFaSettings,
        ProxySettings
    },
    methods: {
        applyRecommended() {
            /*
             * Spread what is already saved first. Saving replaces the whole option, so
             * a key missing from this literal is a key erased.
             */
            this.settings = {
                ...this.settings,
                disable_xmlrpc: 'yes',
                disable_app_login: 'no',
                disable_users_rest: 'yes',
                secure_signup_form: 'yes',
                login_try_limit: 5,
                login_try_timing: 30,
                auto_delete_logs_day: 30,
                notification_user_roles: ['administrator', 'editor', 'author'],
                notification_email: '{admin_email}',
                notify_on_blocked: 'no',
                magic_login: 'no',
                magic_restricted_roles: [],
                magic_link_primary: 'no',
                email2fa: 'yes',
                email2fa_roles: ['administrator', 'editor', 'author'],
                totp_2fa: 'yes',
                totp_2fa_roles: [],
                /*
                 * Recommended, not imposed: forcing enrollment sends people to a setup
                 * screen they cannot leave, which is not something a button labelled
                 * "recommended settings" should decide for an administrator.
                 */
                totp_required_roles: this.settings.totp_required_roles || [],
                disable_admin_bar: 'yes',
                disable_bar_roles: ['subscriber'],
                // Server topology, not a preference - never overwrite it with a default.
                trusted_proxies: this.settings.trusted_proxies || '',
                proxy_ip_header: this.settings.proxy_ip_header || ''
            };

            this.$notify.success(this.$t('Recommended settings have been applied. Review the sections and save.'));
        }
    }
};
</script>

<template>
    <div>
        <SettingsHeader :heading="$t('Settings')"
                        :description="$t('Everything saved together, in one place.')"
                        :saving="saving" @save="saveSettings()">
            <template #actions>
                <el-button size="small" @click="applyRecommended()">
                    {{ $t('Apply recommended') }}
                </el-button>
            </template>
        </SettingsHeader>

        <div class="fls_settings_content">
            <el-skeleton v-if="!settings" :animated="true" :rows="8"/>

            <el-form v-else label-position="top">
                <SettingsSection id="core" :title="$t('Core Security')"
                                 :description="$t('The parts of WordPress that are exposed by default.')">
                    <CoreSecuritySection :settings="settings"/>
                </SettingsSection>

                <SettingsSection id="login_security" :title="$t('Login Security')"
                                 :description="$t('How many times an address may get a password wrong before it is shut out.')">
                    <LoginSecuritySection :settings="settings"/>
                </SettingsSection>

                <SettingsSection id="two_fa" :title="$t('Two-Factor Authentication')"
                                 :description="$t('A second factor is only worth the friction when it proves something the password did not. Each method states what it proves, because that is what decides when it is asked for.')">
                    <TwoFaSettings :settings="settings" :user_roles="user_roles"/>
                </SettingsSection>

                <SettingsSection id="magic_login" :title="$t('Magic Login')"
                                 :description="$t('Signing in from a link sent to the account address, with no password typed at all.')">
                    <MagicLoginSection :settings="settings" :user_roles="user_roles"/>
                </SettingsSection>

                <SettingsSection id="notifications" :title="$t('Notifications')"
                                 :description="$t('What the plugin emails you about, and where it sends it.')">
                    <NotificationsSection :settings="settings" :user_roles="user_roles"/>
                </SettingsSection>

                <SettingsSection id="visitor_ip" :title="$t('Visitor IP')"
                                 :description="$t('Where a visitor\'s address is read from. The attempt limit counts per address, so this decides whether it counts the right people.')">
                    <ProxySettings :settings="settings" :detection="proxy_detection"
                                   :config_locked="proxy_config_locked"/>
                </SettingsSection>

                <SettingsSection id="advanced" :title="$t('Advanced')"
                                 :description="$t('Log retention and admin area access.')">
                    <AdvancedSection :settings="settings" :low_level_roles="low_level_roles"/>
                </SettingsSection>
            </el-form>
        </div>
    </div>
</template>
