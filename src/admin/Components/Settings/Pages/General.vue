<script type="text/babel">
import settingsPage from '../settingsPage';
import SettingsHeader from '../_SettingsHeader.vue';
import SettingRow from '../_SettingRow.vue';

export default {
    name: 'GeneralSettings',
    mixins: [settingsPage],
    components: {SettingsHeader, SettingRow},
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

            this.$notify.success(this.$t('Recommended settings have been applied across all sections. Review and save.'));
        }
    }
};
</script>

<template>
    <div>
        <SettingsHeader :heading="$t('General')"
                        :description="$t('Core hardening for the parts of WordPress that are exposed by default.')"
                        :saving="saving" @save="saveSettings()">
            <template #actions>
                <el-button size="small" @click="applyRecommended()">
                    {{ $t('Apply recommended') }}
                </el-button>
            </template>
        </SettingsHeader>

        <div class="fls_settings_content">
            <el-skeleton v-if="!settings" :animated="true" :rows="6"/>

            <div v-else class="fls_card">
                <div class="fls_card_body">
                    <SettingRow :label="$t('XML-RPC')"
                                :description="$t('An old remote publishing interface. Most sites never use it, and it is a standing target for password guessing because one request can carry many attempts.')">
                        <el-switch v-model="settings.disable_xmlrpc" active-value="yes" inactive-value="no"/>
                        <p>{{ $t('Recommended: disabled.') }}</p>
                    </SettingRow>

                    <SettingRow :label="$t('Application passwords')"
                                :description="$t('Lets external apps sign in over the REST API with their own password. Leave enabled only if something actually connects that way.')">
                        <el-switch v-model="settings.disable_app_login" active-value="yes" inactive-value="no"/>
                        <p>{{ $t('Switched on here means application passwords are turned off.') }}</p>
                    </SettingRow>

                    <SettingRow :label="$t('Public user listing')"
                                :description="$t('WordPress will list your usernames over the REST API to anyone who asks. Those names are half of every password guess.')">
                        <el-switch v-model="settings.disable_users_rest" active-value="yes" inactive-value="no"/>
                        <p>{{ $t('Recommended: disabled.') }}</p>
                    </SettingRow>

                    <SettingRow :label="$t('Secure signup form')"
                                :description="$t('Replaces the default registration form with one that verifies the email address before the account becomes usable.')">
                        <el-switch v-model="settings.secure_signup_form" active-value="yes" inactive-value="no"/>
                    </SettingRow>
                </div>
            </div>
        </div>
    </div>
</template>
