<script type="text/babel">
import icons from './icons';

/*
 * The scan's blind spot, as its own section.
 *
 * Only plugins and themes from the wordpress.org directory have an official copy to compare
 * against. Anything bought from a vendor, written for this site, or installed from a zip has no
 * published checksums, and no amount of scanning will produce any.
 *
 * Kept apart from the plugins and themes lists rather than mixed in with a grey tag, because
 * these are a different kind of answer: not "we looked and it was fine" but "we could not
 * look". Folding them in with the checked ones would let a reader skim the list and come away
 * believing the whole site had been verified.
 */
export default {
    name: 'UnverifiedList',
    props: {
        items: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            icons,
            open: false
        }
    },
    computed: {
        /* Plugins first, then themes, each alphabetical - a list to look yourself up in. */
        sorted() {
            return [...this.items].sort((a, b) => {
                if (a.type !== b.type) {
                    return a.type === 'plugin' ? -1 : 1;
                }

                return a.name.localeCompare(b.name);
            });
        }
    }
}
</script>

<template>
    <div class="fls_dcard">
        <div class="fls_dcard_head">
            <h2>{{ $t('Premium & Custom Extensions') }}</h2>
            <span class="fls_dcard_meta">{{ $_n('%s not verified', '%s not verified', items.length) }}</span>
        </div>

        <!-- The note belongs to the list, so it sits in the same block rather than its own. -->
        <div class="fls_dcard_body is_flush">
            <p class="fls_scan_section_note">{{ $t('__unverifiable_extensions_desc__') }}</p>

            <ul class="fls_scan_exts">
                <li v-for="item in (open ? sorted : sorted.slice(0, 5))"
                    :key="item.type + ':' + item.key" class="fls_scan_ext">
                    <div class="fls_scan_summary is_row is_static">
                        <span class="fls_scan_summary_icon"
                              v-html="item.type === 'theme' ? icons.theme : icons.plugin"></span>
                        <span class="fls_scan_summary_name" :title="item.name">{{ item.name }}</span>
                        <span v-if="item.version" class="fls_scan_group_version">{{ item.version }}</span>
                        <span class="fls_scan_summary_reason">{{ item.reason_label }}</span>
                    </div>
                </li>
            </ul>

            <div v-if="sorted.length > 5" class="fls_scan_exts_more">
                <el-button text size="small" @click="open = !open">
                    {{ open ? $t('Show fewer') : $_n('Show %s more', 'Show %s more', sorted.length - 5) }}
                </el-button>
            </div>
        </div>
    </div>
</template>
