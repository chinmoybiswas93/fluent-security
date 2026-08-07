<script type="text/babel">
import SettingRow from '../_SettingRow.vue';

export default {
    name: 'CoreSecuritySection',
    components: {SettingRow},
    props: {settings: {type: Object, required: true}}
};
</script>

<template>
    <div>
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
</template>
