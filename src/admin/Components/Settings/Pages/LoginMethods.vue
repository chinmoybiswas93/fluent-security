<script type="text/babel">
import settingsPage from '../settingsPage';
import SettingsHeader from '../_SettingsHeader.vue';
import SettingRow from '../_SettingRow.vue';

export default {
    name: 'LoginMethodsSettings',
    mixins: [settingsPage],
    components: {SettingsHeader, SettingRow}
};
</script>

<template>
    <div>
        <SettingsHeader :heading="$t('Magic Login')"
                        :description="$t('Signing in from a link sent to the account address, with no password typed at all.')"
                        :saving="saving" @save="saveSettings()"/>

        <div class="fls_settings_content">
            <el-skeleton v-if="!settings" :animated="true" :rows="5"/>

            <div v-else class="fls_card">
                <div class="fls_card_body">
                    <SettingRow :label="$t('Enable magic login')"
                                :description="$t('Redeeming the link proves the mailbox, so an emailed second-factor code is not asked for afterwards. An authenticator app still is.')">
                        <el-switch v-model="settings.magic_login" active-value="yes" inactive-value="no"/>
                    </SettingRow>

                    <template v-if="settings.magic_login === 'yes'">
                        <SettingRow :label="$t('Roles it is not offered to')"
                                    :description="$t('Leave empty to offer it to everyone.')">
                            <el-select :placeholder="$t('Offered to every role')" clearable :multiple="true"
                                       v-model="settings.magic_restricted_roles" style="width: 100%;">
                                <el-option v-for="role in user_roles" :value="role.id" :label="role.title"
                                           :key="role.id"></el-option>
                            </el-select>
                        </SettingRow>

                        <SettingRow :label="$t('Make it the primary method')"
                                    :description="$t('Shows the magic link form first on the login page, with the password form behind a link.')">
                            <el-switch v-model="settings.magic_link_primary" active-value="yes" inactive-value="no"/>
                        </SettingRow>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
