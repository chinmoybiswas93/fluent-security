<script type="text/babel">
import SettingRow from '../_SettingRow.vue';
import SettingToggle from '../_SettingToggle.vue';

export default {
    name: 'AdvancedSection',
    components: {SettingRow, SettingToggle},
    props: {
        settings: {type: Object, required: true},
        low_level_roles: {type: Object, default: () => ({})}
    }
};
</script>

<template>
    <div>
        <SettingRow :label="$t('Delete logs older than')"
                    :description="$t('In days. Use 0 to keep them forever.')">
            <el-input type="number" :min="0" v-model="settings.auto_delete_logs_day" style="max-width: 160px;"/>
        </SettingRow>

        <SettingToggle v-model="settings.disable_admin_bar"
                       :label="$t('Keep low level roles out of wp-admin')"
                       :description="$t('Hides the admin bar and redirects the chosen roles away from the dashboard.')"/>

        <SettingRow v-if="settings.disable_admin_bar === 'yes'" :label="$t('Roles to keep out')">
            <el-select clearable :multiple="true" v-model="settings.disable_bar_roles" style="width: 100%;">
                <el-option v-for="(role, roleId) in low_level_roles" :value="roleId"
                           :label="role" :key="roleId"></el-option>
            </el-select>
        </SettingRow>
    </div>
</template>
