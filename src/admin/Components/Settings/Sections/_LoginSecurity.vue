<script type="text/babel">
import SettingRow from '../_SettingRow.vue';

export default {
    name: 'LoginSecuritySection',
    components: {SettingRow},
    props: {settings: {type: Object, required: true}}
};
</script>

<template>
    <div>
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

        <SettingRow :label="$t('Activity log')"
                    :description="$t('Login activity is always recorded. The attempt limit, the audit log and the login notifications all read from it, so it is not something that can be switched off.')">
            <span class="fls_2fa_pill fls_2fa_pill_on">{{ $t('Always on') }}</span>
        </SettingRow>
    </div>
</template>
