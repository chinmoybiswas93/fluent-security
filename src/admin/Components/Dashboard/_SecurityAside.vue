<script type="text/babel">
import icons from './icons';

/*
 * The right-hand column: what is protecting this site, and what is not yet.
 *
 * Deliberately not a second set of counters. The main column is what happened over a date
 * range; this is the site's standing configuration, which does not change when the range
 * does - so the two never say the same thing twice.
 */
export default {
    name: 'SecurityAside',
    props: {
        checklist: {
            type: Object,
            required: true
        },
        protection: {
            type: Object,
            required: true
        }
    },
    emits: ['applied'],
    data() {
        return {
            icons,
            installing: false,
            /* Which check is mid-request, so only its own button spins. */
            applying: ''
        }
    },
    computed: {
        score() {
            if (!this.checklist.total) {
                return 0;
            }

            return Math.round((this.checklist.done / this.checklist.total) * 100);
        },
        /*
         * Only the recommendations that apply to every site are scored. The rest are shown
         * under their own heading, because a site can be configured exactly right and still
         * not want them - and a score you cannot reach is a score you stop reading.
         */
        scoredItems() {
            return this.checklist.items.filter(item => item.scored);
        },
        unscoredItems() {
            return this.checklist.items.filter(item => !item.scored);
        },
        /*
         * A handful of standing facts, each with the same shape: a label, an answer, and
         * whether that answer is one to act on. Built here rather than in the template so
         * the ones that do not apply can simply be filtered out.
         */
        facts() {
            const twoFa = this.protection.two_fa;
            const scan = this.protection.scan;

            const items = [
                {
                    key: 'two_fa',
                    label: this.$t('Authenticator app'),
                    value: this.$t('%1s of %2s users', twoFa.enrolled, twoFa.total),
                    warning: twoFa.enrolled === 0,
                    route: 'settings_two_fa_enrollment'
                },
                {
                    key: 'scan',
                    label: this.$t('Last file scan'),
                    value: scan.last_checked || (scan.registered ? this.$t('Not run yet') : this.$t('Not set up')),
                    warning: !scan.registered || !scan.last_checked || !scan.is_ok,
                    route: 'security_scans'
                },
                {
                    key: 'retention',
                    label: this.$t('Logs kept for'),
                    value: this.protection.retention
                        ? this.$_n('%s day', '%s days', this.protection.retention)
                        : this.$t('Forever'),
                    warning: false,
                    route: 'settings_general'
                }
            ];

            if (this.protection.digest) {
                items.push({
                    key: 'digest',
                    label: this.$t('Summary email'),
                    value: this.protection.digest,
                    warning: false,
                    route: 'settings_general'
                });
            }

            return items;
        }
    },
    methods: {
        /*
         * A checklist row is a link to the setting that would tick it. The section is a
         * query parameter rather than a hash: the settings pane scrolls its own body, so
         * the browser's own fragment scrolling cannot reach it, and the layout reads this
         * on arrival instead (see SettingsLayout).
         */
        target(item) {
            const target = {name: item.route};

            if (item.section) {
                target.query = {section: item.section};
            }

            return target;
        },
        /*
         * The button label says what will happen, because these differ in kind: one writes
         * a setting from here, the other can only take you to where the work is done.
         */
        actionLabel(item) {
            return item.action === 'enable' ? this.$t('Enable') : this.$t('Set up');
        },
        applyCheck(item) {
            this.applying = item.key;

            this.$post('security-checks/' + item.key + '/apply')
                .then(response => {
                    this.$notify.success(response.message);
                    /*
                     * The response carries the recalculated checklist, so the score and any
                     * evidence that moved with it come from the server rather than being
                     * guessed at here.
                     */
                    this.appVars.auth_settings = response.settings;
                    this.$emit('applied', response.checklist);
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .finally(() => {
                    this.applying = '';
                });
        },
        installPlugin(plugin) {
            this.installing = true;

            this.$post('install-plugin', {plugin: plugin})
                .then(() => {
                    window.location.reload();
                })
                .catch(errors => {
                    this.$handleError(errors);
                    this.installing = false;
                });
        }
    }
}
</script>

<template>
    <aside class="fls_page_aside">
        <div class="fls_aside_block">
            <h3>
                {{ $t('Security Checklist') }}
                <small>{{ $t('%1s of %2s', checklist.done, checklist.total) }}</small>
            </h3>

            <div class="fls_dash_score">
                <div class="fls_dash_score_track">
                    <div class="fls_dash_score_fill" :style="{width: score + '%'}"></div>
                </div>
            </div>

            <ul class="fls_dash_checklist">
                <li v-for="item in scoredItems" :key="item.key" :class="'is_' + item.state">
                    <div class="fls_dash_check">
                        <span class="fls_dash_check_mark">
                            <span v-if="item.state === 'done'" v-html="icons.tick"></span>
                        </span>
                        <div class="fls_dash_check_body">
                            <router-link class="fls_dash_check_title" :to="target(item)">
                                {{ item.title }}
                            </router-link>
                            <p v-if="item.note" class="fls_dash_check_note">{{ item.note }}</p>
                        </div>
                        <el-button v-if="item.state === 'todo'" class="fls_dash_check_action"
                                   size="small" :loading="applying === item.key"
                                   @click="applyCheck(item)">
                            {{ actionLabel(item) }}
                        </el-button>
                    </div>
                </li>
            </ul>
        </div>

        <!--
            Everything the plugin will not recommend for every site: it either depends on
            what this site connects to, or it needs setting up somewhere else. Shown, with
            whatever is known about this site, but never counted against it.
        -->
        <div v-if="unscoredItems.length" class="fls_aside_block">
            <h3>{{ $t('Worth a Look') }}</h3>

            <ul class="fls_dash_checklist">
                <li v-for="item in unscoredItems" :key="item.key" :class="'is_' + item.state">
                    <div class="fls_dash_check">
                        <span class="fls_dash_check_mark">
                            <span v-if="item.state === 'done'" v-html="icons.tick"></span>
                            <span v-else-if="item.state === 'in_use'" class="fls_dash_check_dot"></span>
                        </span>
                        <div class="fls_dash_check_body">
                            <router-link class="fls_dash_check_title" :to="target(item)">
                                {{ item.title }}
                            </router-link>
                            <p v-if="item.note" class="fls_dash_check_note">{{ item.note }}</p>
                        </div>
                        <el-button v-if="item.state === 'todo'" class="fls_dash_check_action"
                                   size="small" :loading="applying === item.key"
                                   @click="item.action === 'enable' ? applyCheck(item) : $router.push(target(item))">
                            {{ actionLabel(item) }}
                        </el-button>
                    </div>
                </li>
            </ul>
        </div>

        <div class="fls_aside_block">
            <h3>{{ $t('At a Glance') }}</h3>

            <ul class="fls_dash_facts">
                <li v-for="fact in facts" :key="fact.key" :class="{is_warning: fact.warning}">
                    <span class="fls_dash_fact_label">{{ fact.label }}</span>
                    <router-link class="fls_dash_fact_value" :to="{name: fact.route}">
                        {{ fact.value }}
                    </router-link>
                </li>
            </ul>
        </div>

        <div v-if="!appVars.fluent_smtp_url" class="fls_aside_block fls_dash_promo">
            <h3>{{ $t('Are your emails arriving?') }}</h3>

            <p>
                {{ $t('Login alerts, magic links and two-factor codes are only as reliable as the email that carries them.') }}
            </p>

            <ul>
                <li>
                    <span v-html="icons.tickSmall"></span>
                    {{ $t('Sends through your own provider, not the web server') }}
                </li>
                <li>
                    <span v-html="icons.tickSmall"></span>
                    {{ $t('Logs every email, with a resend button') }}
                </li>
                <li>
                    <span v-html="icons.tickSmall"></span>
                    {{ $t('Free, with no premium version') }}
                </li>
            </ul>

            <el-button :loading="installing" @click="installPlugin('fluent-smtp')" type="primary">
                {{ $t('Install FluentSMTP') }}
            </el-button>
        </div>
    </aside>
</template>
