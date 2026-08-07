<script type="text/babel">
import {settingsNav, chevronIcon} from './nav';

export default {
    name: 'SettingsLayout',
    data() {
        return {
            nav: settingsNav(this),
            chevron: chevronIcon
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
                        <span v-if="group.children" class="fls_nav_chevron" v-html="chevron"></span>
                    </router-link>

                    <ul v-if="group.children && isGroupActive(group)" class="fls_settings_subnav">
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
