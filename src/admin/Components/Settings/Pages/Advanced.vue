<script type="text/babel">
import settingsPage from '../settingsPage';
import SettingsHeader from '../_SettingsHeader.vue';
import SettingRow from '../_SettingRow.vue';

export default {
    name: 'AdvancedSettings',
    mixins: [settingsPage],
    components: {SettingsHeader, SettingRow}
};
</script>

<template>
    <div>
        <SettingsHeader :heading="$t('Advanced')"
                        :description="$t('Log retention and admin area access.')"
                        :saving="saving" @save="saveSettings()"/>

        <div class="fls_settings_content">
            <el-skeleton v-if="!settings" :animated="true" :rows="5"/>

            <div v-else class="fls_card">
                <div class="fls_card_body">
                    <SettingRow :label="$t('Delete logs older than')"
                                :description="$t('In days. Use 0 to keep them forever.')">
                        <el-input type="number" :min="0" v-model="settings.auto_delete_logs_day"
                                  style="max-width: 160px;"/>
                    </SettingRow>

                    <SettingRow :label="$t('Keep low level roles out of wp-admin')"
                                :description="$t('Hides the admin bar and redirects the chosen roles away from the dashboard.')">
                        <el-switch v-model="settings.disable_admin_bar" active-value="yes" inactive-value="no"/>
                    </SettingRow>

                    <SettingRow v-if="settings.disable_admin_bar === 'yes'"
                                :label="$t('Roles to keep out')">
                        <el-select clearable :multiple="true" v-model="settings.disable_bar_roles"
                                   style="width: 100%;">
                            <el-option v-for="(role, roleId) in low_level_roles" :value="roleId"
                                       :label="role" :key="roleId"></el-option>
                        </el-select>
                    </SettingRow>
                </div>
            </div>
        </div>
    </div>
</template>
