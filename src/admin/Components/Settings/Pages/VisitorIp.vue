<script type="text/babel">
import settingsPage from '../settingsPage';
import SettingsHeader from '../_SettingsHeader.vue';
import ProxySettings from '../../_ProxySettings.vue';

export default {
    name: 'VisitorIpSettings',
    mixins: [settingsPage],
    components: {SettingsHeader, ProxySettings}
};
</script>

<template>
    <div>
        <SettingsHeader :heading="$t('Visitor IP')"
                        :description="$t('Where the plugin reads a visitor\'s address from. The attempt limit counts per address, so this decides whether it counts the right people.')"
                        :saving="saving" @save="saveSettings()"/>

        <div class="fls_settings_content">
            <el-skeleton v-if="!settings" :animated="true" :rows="5"/>
            <el-form v-else label-position="top">
                <ProxySettings :settings="settings" :detection="proxy_detection"
                               :config_locked="proxy_config_locked"/>
            </el-form>
        </div>
    </div>
</template>
