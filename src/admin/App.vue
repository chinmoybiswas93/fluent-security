<script type="text/babel">
export default {
    name: 'FluentAuthApp',
    data() {
        return {
            scrolled: false,
            navOpen: false,
            /*
             * Only destinations - places you go to look at something. Everything you go
             * to change lives behind Settings, in one sidebar, so there is never a
             * question of which of two menus a given option is under.
             */
            menuItems: [
                {route: 'dashboard', title: this.$t('Dashboard')},
                {route: 'logs', title: this.$t('Logs')},
                {route: 'security_scans', title: this.$t('Security Scans')},
                {route: 'settings_general', title: this.$t('Settings'), match: 'settings'}
            ]
        }
    },
    methods: {
        isActive(item) {
            const active = this.$route.meta ? this.$route.meta.active : '';

            return item.match ? active === item.match : active === item.route;
        },
        onScroll() {
            this.scrolled = window.scrollY > 10;
        }
    },
    watch: {
        $route(to) {
            this.navOpen = false;
            document.title = this.$t(to.meta.title) + ' | ' + this.$t('FluentAuth');
        }
    },
    created() {
        jQuery('.update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error').remove();
    },
    mounted() {
        window.addEventListener('scroll', this.onScroll);
        this.onScroll();
    },
    beforeUnmount() {
        window.removeEventListener('scroll', this.onScroll);
    }
}
</script>

<template>
    <div class="fframe_app">
        <div class="fls_app_bar" :class="{'is-scrolled': scrolled}">
            <div class="fls_app_logo">
                <router-link :to="{name: 'dashboard'}">
                    <img :src="appVars.asset_url + '/images/logo.png'" alt="FluentAuth"/>
                </router-link>
            </div>

            <button class="fls_app_bar_toggle" type="button" @click="navOpen = !navOpen"
                    :aria-label="$t('Menu')">
                <span class="dashicons dashicons-menu-alt3"></span>
            </button>

            <ul class="fls_app_nav" :class="{'is-open': navOpen}">
                <li v-for="item in menuItems" :key="item.route">
                    <router-link :to="{name: item.route}"
                                 :class="{'router-link-active': isActive(item)}">
                        {{ item.title }}
                    </router-link>
                </li>
            </ul>

            <div class="fls_app_bar_actions">
                <slot name="actions"/>
            </div>
        </div>

        <div class="ff_app_body">
            <router-view></router-view>
        </div>
    </div>
</template>
