<script type="text/babel">
/**
 * One block of the settings page, and the thing the sidebar scrolls to.
 *
 * The id is what ties the two together: the sidebar builds its links from the same
 * list of sections, so a section that exists here is reachable there by construction.
 */
export default {
    name: 'SettingsSection',
    props: {
        id: {type: String, required: true},
        title: {type: String, default: ''},
        description: {type: String, default: ''}
    }
};
</script>

<template>
    <section :id="'fls_section_' + id" class="fls_card fls_section">
        <div class="fls_card_head">
            <h2>
                {{ title }}
                <slot name="badge"/>
            </h2>
            <p v-if="description">{{ description }}</p>
        </div>
        <div class="fls_card_body">
            <slot/>
        </div>
    </section>
</template>
