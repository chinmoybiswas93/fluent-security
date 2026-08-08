<script type="text/babel">
import icons from './icons';
import CoreSection from './_CoreSection.vue';
import ExtensionSection from './_ExtensionSection.vue';
import ScanProgress from './_ScanProgress.vue';
import UnverifiedList from './_UnverifiedList.vue';

/*
 * The left-hand column: what the scan makes of this site, subject by subject.
 *
 * Four standing sections - core, plugins, themes, and the things that cannot be checked - each
 * summarised to a verdict you open for the detail. They are always on screen, including before
 * anything has been run, because the list of what is installed is itself the answer to "what
 * would a scan cover"; the rows simply fill in their verdicts as a scan reaches them.
 *
 * The alternative, and what this replaced, was a flat run of cards for whatever happened to
 * have findings. That reads well with three findings and not at all with sixty, and it can
 * never say the most reassuring thing there is to say: this plugin was checked, and it is fine.
 */
export default {
    name: 'ScanResults',
    components: {
        CoreSection,
        ExtensionSection,
        ScanProgress,
        UnverifiedList
    },
    props: {
        scanState: {
            type: String,
            required: true
        },
        /* Core's scan payload. */
        results: {
            type: Object,
            default: null
        },
        hasIssues: {
            type: Boolean,
            default: false
        },
        willAlert: {
            type: Boolean,
            default: false
        },
        ignores: {
            type: Object,
            required: true
        },
        settings: {
            type: Object,
            required: true
        },
        errorMessage: {
            type: String,
            default: ''
        },
        staleWarning: {
            type: Boolean,
            default: false
        },
        /* Every installed plugin, each with its result attached if it has one. */
        plugins: {
            type: Array,
            default: () => []
        },
        themes: {
            type: Array,
            default: () => []
        },
        /* Installed, but with no official copy to compare against. */
        unverified: {
            type: Array,
            default: () => []
        },
        progress: {
            type: Object,
            default: () => ({phase: 'core', done: 0, total: 0, current: ''})
        },
        /* The extensions in flight right now, as "type:key" - the walk runs a few at a time. */
        checkingKeys: {
            type: Array,
            default: () => []
        }
    },
    emits: ['scan'],
    data() {
        return {
            icons
        }
    },
    computed: {
        scanning() {
            return this.scanState === 'scanning';
        },
        /* Only while core is the phase in flight, so its row can say so. */
        checkingCore() {
            return this.scanning && this.progress.phase === 'core';
        },
        pending() {
            return this.scanning ? this.checkingKeys : [];
        },
        totalFindings() {
            const inExtensions = [...this.plugins, ...this.themes].reduce((total, item) => {
                if (!item.result || !item.result.verifiable) {
                    return total;
                }

                return total + Object.keys(item.result.files || {}).length + (item.result.truncated || 0);
            }, 0);

            const coreFiles = this.results && this.results.files
                ? Object.keys(this.results.files).reduce(
                    (total, key) => total + Object.keys(this.results.files[key]).length, 0
                )
                : 0;

            const coreFolders = this.results && this.results.folders ? this.results.folders.length : 0;

            return inExtensions + coreFiles + coreFolders;
        },
        /* Said once at the top, so the size of the problem is known before any of it is opened. */
        verdictSummary() {
            const changed = [...this.plugins, ...this.themes].filter(item =>
                item.result && item.result.verifiable
                && (Object.keys(item.result.files || {}).length || item.result.truncated)
            ).length;

            const parts = [this.$_n('%s file', '%s files', this.totalFindings)];

            if (changed) {
                parts.push(this.$_n('in %s extension', 'in %s extensions', changed));
            }

            return parts.join(' · ');
        }
    }
}
</script>

<template>
    <!-- Where a running scan has got to. Above the sections, which keep filling in beneath it. -->
    <div v-if="scanning" class="fls_dcard">
        <scan-progress :phase="progress.phase"
                       :done="progress.done"
                       :total="progress.total"
                       :current="progress.current"/>
    </div>

    <!-- The verdict, once there is one. -->
    <div v-else-if="scanState === 'done' && hasIssues" class="fls_scan_verdict"
         :class="willAlert ? 'is_danger' : 'is_warning'">
        <span class="fls_scan_verdict_icon" v-html="willAlert ? icons.alert : icons.mute"></span>
        <div>
            <h2 v-if="willAlert">{{ $t('Some files are not what WordPress.org published') }}</h2>
            <h2 v-else>{{ $t('Only changes you have already accepted') }}</h2>
            <p v-if="willAlert">{{ $t('__file_change_detected__') }}</p>
            <p v-else>{{ $t('__scanner_result_dec_normal__') }}</p>
            <p class="fls_scan_verdict_count">{{ verdictSummary }}</p>
        </div>
    </div>

    <div v-else-if="scanState === 'done'" class="fls_scan_verdict is_success">
        <span class="fls_scan_verdict_icon" v-html="icons.tick"></span>
        <div>
            <h2>{{ $t('Awesome! Everything looks good!') }}</h2>
            <p>{{ $t('Every file checked matches the official release on WordPress.org.') }}</p>
        </div>
    </div>

    <!--
        Nothing run yet. What scanning is for is already said in the page heading, and the
        sections below say what it would cover, so this is only what happened last time and
        the way to start.
    -->
    <div v-else class="fls_scan_intro">
        <p v-if="errorMessage" class="fls_scan_state_error">{{ errorMessage }}</p>
        <p v-else-if="staleWarning">
            <span v-html="$t('__last_scan_warning__', settings.last_checked_human)"></span>
        </p>
        <el-button type="primary" @click="$emit('scan')">{{ $t('Start Scan') }}</el-button>
    </div>

    <core-section :results="results" :ignores="ignores" :checking="checkingCore"
                  :ever-scanned="!!settings.last_checked_human"/>

    <extension-section :title="$t('Plugins')"
                       :items="plugins"
                       :ignored-files="ignores.files"
                       :checking-keys="pending"
                       :empty-text="$t('No plugins from the WordPress.org directory are installed.')"/>

    <extension-section :title="$t('Themes')"
                       :items="themes"
                       :ignored-files="ignores.files"
                       :checking-keys="pending"
                       :empty-text="$t('No themes from the WordPress.org directory are installed.')"/>

    <unverified-list v-if="unverified.length" :items="unverified"/>
</template>
