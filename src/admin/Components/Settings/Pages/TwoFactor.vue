<script type="text/babel">
import settingsPage from '../settingsPage';
import SettingsHeader from '../_SettingsHeader.vue';
import TwoFaSettings from '../../TwoFa/_TwoFaSettings.vue';

export default {
    name: 'TwoFactorSettings',
    mixins: [settingsPage],
    components: {SettingsHeader, TwoFaSettings}
};
</script>

<template>
    <div>
        <SettingsHeader :heading="$t('Two-Factor Authentication')"
                        :description="$t('Which second factors are available, and who has to use them.')"
                        :saving="saving" @save="saveSettings()"/>

        <div class="fls_settings_content">
            <el-skeleton v-if="!settings" :animated="true" :rows="6"/>
            <el-form v-else label-position="top">
                <TwoFaSettings :settings="settings" :user_roles="user_roles"/>
            </el-form>
        </div>
    </div>
</template>
