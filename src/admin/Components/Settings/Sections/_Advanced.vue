<script type="text/babel">
import SettingRow from '../_SettingRow.vue';

/**
 * Keeping roles out of wp-admin used to be two settings: a switch, and the list of roles
 * it applied to. The switch never carried any meaning of its own - switched on with no
 * roles chosen it did nothing, because both handlers return early on an empty list - so
 * it is gone and the list is the whole setting. `disable_admin_bar` is still stored; the
 * server derives it from the list on save, so the two cannot disagree.
 */
export default {
    name: 'AdvancedSection',
    components: {SettingRow},
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

        <SettingRow :label="$t('Keep these roles out of wp-admin')"
                    :description="$t('Hides the admin bar, and sends them to the front page if they try to open the dashboard. Leave it empty to let everyone in. Anyone who can publish posts is never kept out.')">
            <el-select clearable :multiple="true" v-model="settings.disable_bar_roles"
                       :placeholder="$t('Everyone can reach wp-admin')">
                <el-option v-for="(role, roleId) in low_level_roles" :value="roleId"
                           :label="role" :key="roleId"></el-option>
            </el-select>
        </SettingRow>
    </div>
</template>
