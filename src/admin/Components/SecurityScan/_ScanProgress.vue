<script type="text/babel">
import icons from './icons';

/*
 * Where a running scan has got to.
 *
 * A core-only scan was one request, so a sweeping bar was honest enough. Checking every
 * plugin and theme takes one request each and can run to a minute or more on a large site,
 * and an indeterminate bar for that long reads as a hang. So this says the real thing: which
 * of the three phases is running, and how far through its list it is.
 *
 * The phases stay on screen after they finish rather than being replaced, because "core was
 * fine" is worth seeing while the plugins are still going.
 */
export default {
    name: 'ScanProgress',
    props: {
        /* core | plugins | themes */
        phase: {
            type: String,
            required: true
        },
        done: {
            type: Number,
            default: 0
        },
        total: {
            type: Number,
            default: 0
        },
        /* The plugin or theme being checked right now, named so the wait is legible. */
        current: {
            type: String,
            default: ''
        }
    },
    data() {
        return {
            icons
        }
    },
    computed: {
        phases() {
            const order = ['core', 'plugins', 'themes'];
            const at = order.indexOf(this.phase);

            return [
                {key: 'core', label: this.$t('Core')},
                {key: 'plugins', label: this.$t('Plugins')},
                {key: 'themes', label: this.$t('Themes')}
            ].map((item, index) => ({
                ...item,
                state: index < at ? 'done' : (index === at ? 'active' : 'waiting')
            }));
        },
        /*
         * Core has no count of its own - it is one request - so it shows as a phase that is
         * simply under way. Only the two that walk a list get a proportion.
         */
        percent() {
            if (!this.total) {
                return null;
            }

            return Math.min(100, Math.round((this.done / this.total) * 100));
        },
        headline() {
            if (this.phase === 'core') {
                return this.$t('Checking WordPress core…');
            }

            if (this.phase === 'themes') {
                return this.$t('Checking themes…');
            }

            return this.$t('Checking plugins…');
        }
    }
}
</script>

<template>
    <div class="fls_scan_state is_working">
        <span class="fls_scan_state_icon" v-html="icons.radar"></span>
        <h2>{{ headline }}</h2>

        <p v-if="current" class="fls_scan_current">{{ current }}</p>
        <p v-else>{{ $t('Comparing this site against the official releases on WordPress.org.') }}</p>

        <!-- A real proportion where there is one to give, and the sweep where there is not. -->
        <div v-if="percent !== null" class="fls_scan_meter">
            <div class="fls_scan_meter_bar" :style="{width: percent + '%'}"></div>
        </div>
        <div v-else class="fls_scan_progress"></div>

        <p v-if="total" class="fls_scan_meter_count">{{ done }} / {{ total }}</p>

        <ol class="fls_scan_phases">
            <li v-for="item in phases" :key="item.key" :class="'is_' + item.state">
                <span class="fls_scan_phase_mark" v-html="item.state === 'done' ? icons.tick : ''"></span>
                <span>{{ item.label }}</span>
            </li>
        </ol>
    </div>
</template>
