<script type="text/babel">
import {settingsNav, chevronIcon} from './nav';

/**
 * Clearance below the sticky header before a section counts as "the one you are looking
 * at". A clicked section lands a little below the header - scroll-margin-top plus the
 * scroll-padding wp-admin adds for its admin bar - so the line has to sit below where
 * the click leaves it, or clicking an entry highlights the one above it.
 */
const SPY_TOLERANCE = 24;

/** Fallback for the header's bottom edge, used only before it has rendered. */
const HEADER_BOTTOM = 140;

export default {
    name: 'SettingsLayout',
    data() {
        return {
            nav: settingsNav(this),
            chevron: chevronIcon,
            activeSection: ''
        }
    },
    computed: {
        visibleNav() {
            return this.nav.filter(group => !group.when || group.when(this.appVars));
        }
    },
    methods: {
        /**
         * A group counts as current when the page showing belongs to it, so its
         * children stay open while you move between them.
         */
        isGroupActive(group) {
            const current = this.$route.name;

            if (group.route === current) {
                return true;
            }

            return (group.children || []).some(child => child.route === current);
        },
        scrollToSection(id) {
            const el = document.getElementById('fls_section_' + id);

            if (!el) {
                return;
            }

            // scroll-margin-top on the section keeps it clear of the sticky header.
            el.scrollIntoView({behavior: 'smooth', block: 'start'});
            this.activeSection = id;
        },
        /**
         * The viewport line a section has to cross to count as the current one. Measured
         * from the header rather than hard-coded, so it stays right if the header grows.
         */
        spyLine() {
            const header = document.querySelector('.fls_settings_header');
            const bottom = header ? header.getBoundingClientRect().bottom : HEADER_BOTTOM;

            return bottom + SPY_TOLERANCE;
        },
        /**
         * Highlights the section currently under the header.
         *
         * Walks from the bottom up and takes the first one that has passed the line, so
         * the last section still highlights when the page cannot scroll far enough to
         * bring it to the top.
         */
        spy() {
            const group = this.visibleNav.find(item => item.sections && this.isGroupActive(item));

            if (!group) {
                return;
            }

            /*
             * At the end of the pane you are looking at the end of the pane. The last
             * section can sit too low to ever cross the line - there is no scroll left
             * to bring it up there - so reaching the bottom is what selects it.
             *
             * Only when there is a bottom to reach: a pane with nothing to scroll is
             * trivially "at the bottom", which on first paint - before the settings have
             * loaded and given it any height - would light up the last section.
             */
            const pane = this.$refs.pane;
            const scrollable = pane && pane.scrollHeight > pane.clientHeight;

            if (scrollable && pane.scrollTop + pane.clientHeight >= pane.scrollHeight - 2) {
                this.activeSection = group.sections[group.sections.length - 1].id;
                return;
            }

            const line = this.spyLine();

            for (let i = group.sections.length - 1; i >= 0; i--) {
                const el = document.getElementById('fls_section_' + group.sections[i].id);

                if (el && el.getBoundingClientRect().top <= line) {
                    this.activeSection = group.sections[i].id;
                    return;
                }
            }

            this.activeSection = group.sections.length ? group.sections[0].id : '';
        }
    },
    mounted() {
        /*
         * The pane scrolls, not the window - a scroll container's events do not reach
         * the window, so listening there would leave the highlight frozen. The window
         * listener stays for the narrow layout, where the pane is not a scroller and the
         * page moves instead.
         */
        this.$refs.pane.addEventListener('scroll', this.spy, {passive: true});
        window.addEventListener('scroll', this.spy, {passive: true});
        this.$nextTick(this.spy);
    },
    beforeUnmount() {
        if (this.$refs.pane) {
            this.$refs.pane.removeEventListener('scroll', this.spy);
        }

        window.removeEventListener('scroll', this.spy);
    },
    watch: {
        /*
         * The sections only exist once the page they live on has rendered, so the first
         * highlight has to wait for it.
         */
        $route() {
            this.activeSection = '';

            // The pane keeps its scroll position between screens; a new one starts at its top.
            if (this.$refs.pane) {
                this.$refs.pane.scrollTop = 0;
            }

            this.$nextTick(() => setTimeout(this.spy, 50));
        }
    }
};
</script>

<template>
    <div class="fls_settings">
        <div class="fls_settings_nav">
            <div class="fls_settings_nav_title">
                {{ $t('Settings') }}
            </div>

            <ul>
                <li v-for="group in visibleNav" :key="group.route"
                    :class="{'is-active': isGroupActive(group)}">
                    <router-link :to="{name: group.route}">
                        <span class="fls_nav_icon" v-html="group.icon"></span>
                        <span>{{ group.title }}</span>
                        <span v-if="group.children || group.sections" class="fls_nav_chevron"
                              v-html="chevron"></span>
                    </router-link>

                    <!-- Sections of one page: these scroll rather than navigate. -->
                    <ul v-if="group.sections && isGroupActive(group)" class="fls_settings_subnav">
                        <li v-for="section in group.sections" :key="section.id">
                            <a href="#" @click.prevent="scrollToSection(section.id)"
                               :class="{'is-current': activeSection === section.id}">
                                {{ section.title }}
                            </a>
                        </li>
                    </ul>

                    <!-- Separate screens, with their own data and their own saving. -->
                    <ul v-else-if="group.children && isGroupActive(group)" class="fls_settings_subnav">
                        <li v-for="child in group.children" :key="child.route">
                            <router-link :to="{name: child.route}">{{ child.title }}</router-link>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>

        <div ref="pane" class="fls_settings_body">
            <router-view/>
        </div>
    </div>
</template>
