<script type="text/babel">
import icons from './icons';
import FileRows from './_FileRows.vue';

/*
 * One plugin or theme, as a line you can open.
 *
 * Every installed extension gets a row whether or not anything was found in it. That is the
 * point of the list: "no changes" against a name you recognise is the reassurance, and a list
 * that only showed problems could never give it - you would have no way to tell a plugin that
 * passed from one that was never looked at.
 */
export default {
    name: 'ExtensionRow',
    components: {
        FileRows
    },
    props: {
        /* An inventory target with its scan result attached, if it has one yet. */
        item: {
            type: Object,
            required: true
        },
        ignoredFiles: {
            type: Array,
            default: () => []
        },
        /* True while this is the extension being checked. */
        checking: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            icons,
            open: false
        }
    },
    computed: {
        result() {
            return this.item.result || null;
        },
        findings() {
            return this.result && this.result.files ? this.result.files : {};
        },
        truncated() {
            return this.result && this.result.truncated ? this.result.truncated : 0;
        },
        count() {
            return Object.keys(this.findings).length + this.truncated;
        },
        /* pending -> checking -> clean | changed | unverified. */
        state() {
            if (this.checking) {
                return 'checking';
            }

            if (!this.result) {
                return 'pending';
            }

            if (!this.result.verifiable) {
                return 'unverified';
            }

            return this.count ? 'changed' : 'clean';
        },
        statusLabel() {
            if (this.state === 'checking') {
                return this.$t('Checking…');
            }

            if (this.state === 'pending') {
                return this.$t('Not checked yet');
            }

            if (this.state === 'unverified') {
                return this.result.reason_label || this.$t('Could not be verified');
            }

            if (this.state === 'clean') {
                return this.$t('No changes');
            }

            return this.$_n('%s change', '%s changes', this.count);
        },
        statusTag() {
            if (this.state === 'clean') {
                return 'is_success';
            }

            return this.state === 'changed' ? 'is_warning' : 'is_neutral';
        },
        icon() {
            return this.item.type === 'theme' ? icons.theme : icons.plugin;
        },
        /* Where the files sit, which is also how the ignore list names them. */
        rootPath() {
            return '/' + String(this.item.rel_path || '').replace(/^\/+|\/+$/g, '') + '/';
        },
        scope() {
            return {type: this.item.type, key: this.item.key};
        },
        canOpen() {
            return this.state === 'changed';
        }
    }
}
</script>

<template>
    <li class="fls_scan_ext" :class="{is_open: open}">
        <component :is="canOpen ? 'button' : 'div'"
                   :type="canOpen ? 'button' : null"
                   class="fls_scan_summary is_row"
                   :class="{is_open: open, is_static: !canOpen}"
                   @click="canOpen && (open = !open)">
            <span class="fls_scan_summary_icon" v-html="icon"></span>

            <span class="fls_scan_summary_name" :title="item.name">{{ item.name }}</span>

            <span v-if="item.version" class="fls_scan_group_version">{{ item.version }}</span>

            <span class="fls_tag" :class="statusTag">{{ statusLabel }}</span>

            <span v-if="canOpen" class="fls_scan_chevron" v-html="icons.chevron"></span>
        </component>

        <div v-if="open" class="fls_scan_detail">
            <p class="fls_scan_detail_head">{{ rootPath }}</p>
            <file-rows :files="findings"
                       :scope="scope"
                       :root-path="rootPath"
                       :truncated="truncated"
                       :ignored-files="ignoredFiles"/>
        </div>
    </li>
</template>
