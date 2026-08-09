<script type="text/babel">
import SettingsHeader from '../_SettingsHeader.vue';
import SettingsCard from '../_SettingsCard.vue';

/**
 * The IP allow and block lists.
 *
 * Its own screen with its own save, rather than a block on the general settings page.
 * Everything there lives in one option that is written whole by one Save button; these
 * lists are their own option with their own endpoint, and putting a second, independently
 * saved thing inside that form is how you end up with a Save button that saves some of
 * what is on screen.
 *
 * The two lists are shown as one screen but they are not the same kind of thing, and the
 * copy says so: an allow list entry skips the attempt limit and nothing else.
 */
export default {
    name: 'IpRulesSettings',
    components: {SettingsHeader, SettingsCard},
    data() {
        return {
            loading: true,
            saving: false,
            rules: {allow: [], block: []},
            restricted_roles: [],
            roles: [],
            current_ip: '',
            allow_paused: false,
            max_entries: 200
        }
    },
    computed: {
        /*
         * The restriction is refused unless the address you are reading this from is on the
         * list, so the screen says whether it is before you turn it on rather than after.
         */
        currentIpIsAllowed() {
            return this.rules.allow.some(row => row.is_current && !row.is_expired);
        }
    },
    methods: {
        fetchRules() {
            this.loading = true;

            this.$get('ip-rules')
                .then(response => {
                    this.applyState(response);
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        applyState(state) {
            this.rules = state.rules;
            this.restricted_roles = state.restricted_roles;
            this.roles = state.roles;
            this.current_ip = state.current_ip;
            this.allow_paused = state.allow_paused;
            this.max_entries = state.max_entries;
        },
        addRow(type) {
            this.rules[type].push({ip: '', label: '', expires_at: '', is_expired: false, is_current: false});
        },
        removeRow(type, index) {
            this.rules[type].splice(index, 1);
        },
        /* The current address, prefilled - it is the one people came here to add. */
        addCurrentIp() {
            this.rules.allow.push({
                ip: this.current_ip,
                label: this.$t('This computer'),
                expires_at: '',
                is_expired: false,
                is_current: true
            });
        },
        saveRules() {
            this.saving = true;

            this.$post('ip-rules', {rules: this.rules, restricted_roles: this.restricted_roles})
                .then(response => {
                    this.$notify.success(response.message);
                    this.applyState(response);
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .finally(() => {
                    this.saving = false;
                });
        }
    },
    mounted() {
        this.fetchRules();
    }
};
</script>

<template>
    <div>
        <SettingsHeader :heading="$t('IP Access Rules')"
                        :description="$t('Addresses that are never locked out, and addresses that are never let in.')"
                        :saving="saving" :disabled="loading" @save="saveRules()"/>

        <div class="fls_settings_content" v-loading="loading">
            <SettingsCard :title="$t('Allow list')"
                          :description="$t('These addresses are never locked out by the failed attempt limit.')">
                <!--
                    Said before the list rather than after it: somebody reading this to decide
                    whether an allow list is safe should not have to scroll past the box that
                    adds one to find out what it does not do.
                -->
                <p class="fls_note">
                    {{ $t('Being on this list skips the failed attempt limit and nothing else. It is not a trusted network: two-factor authentication still applies, and every attempt is still written to the log.') }}
                </p>

                <el-alert v-if="allow_paused" type="warning" :closable="false" show-icon
                          class="fls_row_alert"
                          :title="$t('The allow list is paused')">
                    {{ $t('Something in front of this site is relaying requests, and no proxy has been declared - so every visitor arrives as the same address. Exempting that address would exempt everyone, so nothing on this list is being applied.') }}
                    <router-link :to="{name: 'settings_general', query: {section: 'visitor_ip'}}">
                        {{ $t('Set up the proxy') }}
                    </router-link>
                </el-alert>

                <table v-if="rules.allow.length" class="fls_rules_table">
                    <thead>
                    <tr>
                        <th>{{ $t('Address or range') }}</th>
                        <th>{{ $t('What is it for?') }}</th>
                        <th>{{ $t('Expires') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(row, index) in rules.allow" :key="'allow' + index"
                        :class="{is_expired: row.is_expired}">
                        <td>
                            <el-input v-model="row.ip" placeholder="203.0.113.4"/>
                            <span v-if="row.is_current" class="fls_rules_flag">{{ $t('Your address') }}</span>
                        </td>
                        <td><el-input v-model="row.label" :placeholder="$t('Office VPN')"/></td>
                        <td>
                            <el-date-picker v-model="row.expires_at" type="date" value-format="YYYY-MM-DD"
                                            :placeholder="$t('Never')" style="width: 100%;"/>
                            <span v-if="row.is_expired" class="fls_rules_flag is_warning">{{ $t('Expired') }}</span>
                        </td>
                        <td class="fls_rules_remove">
                            <el-button text @click="removeRow('allow', index)">
                                <span class="dashicons dashicons-trash"></span>
                            </el-button>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <p v-else class="fls_note">
                    {{ $t('Nothing is exempt. Your address right now is %s.', current_ip) }}
                    <a href="#" @click.prevent="addCurrentIp()">{{ $t('Add it to the allow list') }}</a>
                </p>

                <!--
                    Under the list rather than in the card header: a new row is appended to
                    the bottom, so this is where it appears and where the cursor already is.
                -->
                <div class="fls_rules_add">
                    <el-button :disabled="rules.allow.length >= max_entries" @click="addRow('allow')">
                        {{ $t('Add address') }}
                    </el-button>
                </div>
            </SettingsCard>

            <SettingsCard :title="$t('Restrict sign-in to the allow list')"
                          :description="$t('Pick the roles that may only sign in from an address on the allow list above. Everyone else is unaffected.')">
                <div class="fls_row fls_row_stacked">
                    <div class="fls_row_label">
                        <label>{{ $t('Restricted roles') }}</label>
                        <p>
                            {{ $t('Anyone in these roles signing in from anywhere else is refused - by password, by magic link, by social login and over the REST API alike.') }}
                        </p>
                    </div>
                    <div class="fls_row_control">
                        <el-select v-model="restricted_roles" multiple filterable
                                   :placeholder="$t('No role is restricted')">
                            <el-option v-for="role in roles" :key="role.id"
                                       :value="role.id" :label="role.title"/>
                        </el-select>
                    </div>
                </div>

                <!--
                    Shown before saving rather than as a rejection afterwards. Turning this
                    on from an address that is not on the list locks you out of the screen
                    that would let you undo it.
                -->
                <el-alert v-if="restricted_roles.length && !currentIpIsAllowed" type="error"
                          :closable="false" show-icon class="fls_row_alert"
                          :title="$t('Your own address is not on the allow list')">
                    {{ $t('Saving this would lock you out immediately, so it will be refused. Add %s to the allow list first.', current_ip) }}
                </el-alert>

                <p class="fls_note">
                    {{ $t('If the allow list is ever emptied or every entry expires, the restriction stops applying rather than locking everyone out. A %s constant in wp-config.php turns it off outright.', 'FLUENT_AUTH_DISABLE_IP_RESTRICTION') }}
                </p>
            </SettingsCard>

            <SettingsCard :title="$t('Block list')"
                          :description="$t('These addresses are refused before a password is even checked.')">
                <table v-if="rules.block.length" class="fls_rules_table">
                    <thead>
                    <tr>
                        <th>{{ $t('Address or range') }}</th>
                        <th>{{ $t('What is it for?') }}</th>
                        <th>{{ $t('Expires') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(row, index) in rules.block" :key="'block' + index"
                        :class="{is_expired: row.is_expired}">
                        <td>
                            <el-input v-model="row.ip" placeholder="203.0.113.4"/>
                            <span v-if="row.is_current" class="fls_rules_flag is_danger">
                                {{ $t('This is your own address') }}
                            </span>
                        </td>
                        <td><el-input v-model="row.label" :placeholder="$t('Brute force source')"/></td>
                        <td>
                            <el-date-picker v-model="row.expires_at" type="date" value-format="YYYY-MM-DD"
                                            :placeholder="$t('Never')" style="width: 100%;"/>
                            <span v-if="row.is_expired" class="fls_rules_flag is_warning">{{ $t('Expired') }}</span>
                        </td>
                        <td class="fls_rules_remove">
                            <el-button text @click="removeRow('block', index)">
                                <span class="dashicons dashicons-trash"></span>
                            </el-button>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <p v-else class="fls_note">
                    {{ $t('Nothing is blocked. The dashboard lists the addresses trying hardest to get in, with a button to block each one.') }}
                </p>

                <div class="fls_rules_add">
                    <el-button :disabled="rules.block.length >= max_entries" @click="addRow('block')">
                        {{ $t('Add address') }}
                    </el-button>
                </div>
            </SettingsCard>
        </div>
    </div>
</template>
