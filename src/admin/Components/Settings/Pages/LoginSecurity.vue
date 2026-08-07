<script type="text/babel">
import settingsPage from '../settingsPage';
import SettingsHeader from '../_SettingsHeader.vue';
import SettingRow from '../_SettingRow.vue';

export default {
    name: 'LoginSecuritySettings',
    mixins: [settingsPage],
    components: {SettingsHeader, SettingRow}
};
</script>

<template>
    <div>
        <SettingsHeader :heading="$t('Login Security')"
                        :description="$t('How many times an address may get a password wrong before it is shut out.')"
                        :saving="saving" @save="saveSettings()"/>

        <div class="fls_settings_content">
            <el-skeleton v-if="!settings" :animated="true" :rows="5"/>

            <template v-else>
                <div class="fls_card">
                    <div class="fls_card_body">
                        <SettingRow :label="$t('Failed attempts allowed')"
                                    :description="$t('Counted per IP address. Once the limit is reached, that address is blocked for the rest of the window.')">
                            <el-input type="number" :min="1" v-model="settings.login_try_limit" style="max-width: 160px;"/>
                        </SettingRow>

                        <SettingRow :label="$t('Window length')"
                                    :description="$t('How far back the count reaches, in minutes.')">
                            <el-input type="number" :min="1" v-model="settings.login_try_timing" style="max-width: 160px;"/>
                            <p>
                                {{
                                    $t('%1s failed attempts within %2s minutes will block that address.', settings.login_try_limit, settings.login_try_timing)
                                }}
                            </p>
                        </SettingRow>
                    </div>
                </div>

                <el-alert type="info" :closable="false" show-icon>
                    {{
                        $t('Login activity is always recorded. The attempt limit, the audit log and the login notifications all read from it, so it is not something that can be switched off.')
                    }}
                </el-alert>
            </template>
        </div>
    </div>
</template>
