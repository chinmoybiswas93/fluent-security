<script type="text/babel">
export default {
    name: 'TwoFaSettings',
    props: {
        settings: {
            type: Object,
            required: true
        },
        user_roles: {
            type: Array,
            default: () => []
        }
    },
    computed: {
        /**
         * A role can only be required to use an authenticator app if it is allowed one.
         * Offering the rest would only let someone build a policy the server rejects,
         * so the choice is narrowed instead of the mistake being explained afterwards.
         */
        requirableRoles() {
            const allowed = this.settings.totp_2fa_roles;

            if (!allowed || !allowed.length) {
                return this.user_roles;
            }

            return this.user_roles.filter(role => allowed.includes(role.id));
        },
        requiredRoleTitles() {
            return this.user_roles
                .filter(role => (this.settings.totp_required_roles || []).includes(role.id))
                .map(role => role.title);
        }
    },
    watch: {
        /**
         * Narrowing who is allowed has to narrow who is required with it, or the policy
         * left behind is one the user cannot see on screen and cannot save.
         */
        'settings.totp_2fa_roles'(allowed) {
            if (!allowed || !allowed.length) {
                return;
            }

            this.settings.totp_required_roles = (this.settings.totp_required_roles || [])
                .filter(role => allowed.includes(role));
        },
        'settings.totp_2fa'(enabled) {
            if (enabled !== 'yes') {
                this.settings.totp_required_roles = [];
            }
        }
    }
};
</script>

<template>
    <div class="fls_login_settings">
        <h3>{{ $t('Two-Factor Authentication') }}</h3>
        <p style="margin-bottom: 20px;">
            {{ $t('A second factor is only worth the friction when it proves something the password did not. Each method below states what it proves, because that is what decides when it is asked for.') }}
        </p>

        <div class="fls_2fa_method" :class="{'fls_2fa_method_on': settings.totp_2fa === 'yes'}">
            <div class="fls_2fa_method_head">
                <div>
                    <strong>{{ $t('Authenticator App') }}</strong>
                    <span class="fls_2fa_tag fls_2fa_tag_strong">{{ $t('Strongest') }}</span>
                    <p>
                        {{ $t('A rotating code from an app on the user\'s phone. It proves a device, which neither an inbox nor a social account ever does, so it is asked for however the user signed in - including magic login and Google.') }}
                    </p>
                </div>
                <el-switch v-model="settings.totp_2fa" active-value="yes" inactive-value="no"/>
            </div>

            <div v-if="settings.totp_2fa === 'yes'" class="fls_2fa_method_body">
                <el-row :gutter="30">
                    <el-col :md="12" :sm="24">
                        <el-form-item :label="$t('Roles allowed to set one up')">
                            <el-select :placeholder="$t('Every role')" clearable :multiple="true"
                                       v-model="settings.totp_2fa_roles" style="width: 100%;">
                                <el-option v-for="role in user_roles" :value="role.id" :label="role.title"
                                           :key="role.id"></el-option>
                            </el-select>
                            <p>{{ $t('Users in these roles get the setup panel on their profile. Leave empty to offer it to everyone.') }}</p>
                        </el-form-item>
                    </el-col>
                    <el-col :md="12" :sm="24">
                        <el-form-item :label="$t('Roles that must set one up')">
                            <el-select :placeholder="$t('Nobody is forced')" clearable :multiple="true"
                                       v-model="settings.totp_required_roles" style="width: 100%;">
                                <el-option v-for="role in requirableRoles" :value="role.id" :label="role.title"
                                           :key="role.id"></el-option>
                            </el-select>
                            <p>{{ $t('Only roles allowed above can be listed here.') }}</p>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-alert v-if="requiredRoleTitles.length" type="warning" :closable="false" show-icon
                          style="margin-bottom: 10px;">
                    {{
                        $t('%s will be sent to their profile to set up an authenticator app, and cannot use the admin area until they have. They stay signed in while they do it, and the front end of the site is not affected.', requiredRoleTitles.join(', '))
                    }}
                </el-alert>

                <p class="fls_2fa_link">
                    <router-link :to="{name: 'settings_two_fa_enrollment'}">
                        {{ $t('See who has set one up') }} &rarr;
                    </router-link>
                    <span>
                        {{ $t('Check enrollment across your users, and turn it off for anyone who has lost their device.') }}
                    </span>
                </p>
            </div>
        </div>

        <div class="fls_2fa_method" :class="{'fls_2fa_method_on': settings.email2fa === 'yes'}">
            <div class="fls_2fa_method_head">
                <div>
                    <strong>{{ $t('Email Code') }}</strong>
                    <span class="fls_2fa_tag">{{ $t('Fallback') }}</span>
                    <p>
                        {{ $t('A one-time code sent to the account address. It proves the mailbox, so it is skipped after a magic link or a social login, which already proved the same thing. It is also the code sent when an account comes under attack.') }}
                    </p>
                </div>
                <el-switch v-model="settings.email2fa" active-value="yes" inactive-value="no"/>
            </div>

            <div v-if="settings.email2fa === 'yes'" class="fls_2fa_method_body">
                <el-row :gutter="30">
                    <el-col :md="12" :sm="24">
                        <el-form-item :label="$t('Roles that must use it')">
                            <el-select :placeholder="$t('Choose at least one role')" clearable :multiple="true"
                                       v-model="settings.email2fa_roles" style="width: 100%;">
                                <el-option v-for="role in user_roles" :value="role.id" :label="role.title"
                                           :key="role.id"></el-option>
                            </el-select>
                            <p>{{ $t('At least one role is needed while this is on.') }}</p>
                        </el-form-item>
                    </el-col>
                </el-row>
            </div>
        </div>

        <p v-if="settings.totp_2fa !== 'yes' && settings.email2fa !== 'yes'" class="fls_2fa_none">
            {{ $t('No second factor is enabled, so a password is all that stands between an attacker and these accounts.') }}
        </p>
    </div>
</template>

<style lang="scss">
.fls_2fa_method {
    background: #fff;
    border: 1px solid #e4e7ed;
    border-radius: 5px;
    padding: 16px 20px;
    margin-bottom: 15px;
    transition: border-color .3s;

    &.fls_2fa_method_on {
        border-color: #67c23a;
    }

    .fls_2fa_method_head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;

        p {
            margin: 6px 0 0;
            color: #606266;
            max-width: 720px;
        }
    }

    .fls_2fa_method_body {
        margin-top: 15px;
        padding-top: 5px;
        border-top: 1px solid #f0f2f5;
    }
}

.fls_2fa_tag {
    display: inline-block;
    margin-left: 8px;
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 11px;
    background: #f0f2f5;
    color: #606266;
    vertical-align: middle;

    &.fls_2fa_tag_strong {
        background: #eaf6e5;
        color: #4a9c2d;
    }
}

.fls_2fa_none {
    color: #b32d2e;
}

.fls_2fa_link {
    margin-top: 14px !important;

    a {
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
    }

    span {
        margin-left: 10px;
        color: #909399;
        font-size: 12px;
    }
}
</style>
