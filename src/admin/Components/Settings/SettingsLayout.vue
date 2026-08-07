<script type="text/babel">
import {settingsNav, chevronIcon} from './nav';

/**
 * How far below the viewport top a section counts as "the one you are looking at".
 * Roughly the sticky header, so the section under it is the one highlighted.
 */
const SPY_OFFSET = 140;

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

            for (let i = group.sections.length - 1; i >= 0; i--) {
                const el = document.getElementById('fls_section_' + group.sections[i].id);

                if (el && el.getBoundingClientRect().top <= SPY_OFFSET) {
                    this.activeSection = group.sections[i].id;
                    return;
                }
            }

            this.activeSection = group.sections.length ? group.sections[0].id : '';
        }
    },
    mounted() {
        window.addEventListener('scroll', this.spy, {passive: true});
        this.$nextTick(this.spy);
    },
    beforeUnmount() {
        window.removeEventListener('scroll', this.spy);
    },
    watch: {
        /*
         * The sections only exist once the page they live on has rendered, so the first
         * highlight has to wait for it.
         */
        $route() {
            this.activeSection = '';
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

        <div class="fls_settings_body">
            <router-view/>
        </div>
    </div>
</template>
