<script type="text/babel">
import settingsPage from '../settingsPage';
import SettingsHeader from '../_SettingsHeader.vue';
import SettingRow from '../_SettingRow.vue';

export default {
    name: 'NotificationSettings',
    mixins: [settingsPage],
    components: {SettingsHeader, SettingRow},
    data() {
        return {
            digest_items: {
                daily: this.$t('Daily'),
                sun: this.$t('Every Sunday'),
                mon: this.$t('Every Monday'),
                tue: this.$t('Every Tuesday'),
                wed: this.$t('Every Wednesday'),
                thu: this.$t('Every Thursday'),
                fri: this.$t('Every Friday'),
                sat: this.$t('Every Saturday'),
                monthly: this.$t('Every Month (1st day of every month)')
            }
        }
    },
    computed: {
        wantsEmail() {
            return this.settings
                && (this.settings.notification_user_roles.length
                    || this.settings.notify_on_blocked === 'yes'
                    || this.settings.digest_summary);
        }
    }
};
</script>

<template>
    <div>
        <SettingsHeader :heading="$t('Notifications')"
                        :description="$t('What the plugin emails you about, and where it sends it.')"
                        :saving="saving" @save="saveSettings()"/>

        <div class="fls_settings_content">
            <el-skeleton v-if="!settings" :animated="true" :rows="6"/>

            <div v-else class="fls_card">
                <div class="fls_card_body">
                    <SettingRow :label="$t('Tell me when these roles sign in')"
                                :description="$t('A sign-in by a high privilege account is worth knowing about. Leave empty for none.')">
                        <el-select clearable :multiple="true" v-model="settings.notification_user_roles"
                                   :placeholder="$t('No sign-in notifications')" style="width: 100%;">
                            <el-option v-for="role in user_roles" :value="role.id" :label="role.title"
                                       :key="role.id"></el-option>
                        </el-select>
                    </SettingRow>

                    <SettingRow :label="$t('Tell me when someone is blocked')"
                                :description="$t('Sent when an address hits the failed attempt limit.')">
                        <el-switch v-model="settings.notify_on_blocked" active-value="yes" inactive-value="no"/>
                    </SettingRow>

                    <SettingRow :label="$t('Summary report')"
                                :description="$t('A digest of login activity on a schedule.')">
                        <el-select v-model="settings.digest_summary" style="max-width: 320px;">
                            <el-option value="" :label="$t('Do not send a summary')"></el-option>
                            <el-option v-for="(day, dayName) in digest_items" :key="dayName"
                                       :value="dayName" :label="day"></el-option>
                        </el-select>
                    </SettingRow>

                    <SettingRow v-if="wantsEmail" :label="$t('Send them to')"
                                :description="$t('Comma separate for more than one. {admin_email} is the site administration address.')">
                        <el-input type="text" v-model="settings.notification_email"/>
                    </SettingRow>
                </div>
            </div>
        </div>
    </div>
</template>
