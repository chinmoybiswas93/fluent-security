<script type="text/babel">
/**
 * One block of settings, framed the same way on every screen.
 *
 * `id` is only used by the pages the sidebar scrolls through - it is what ties a sidebar
 * entry to the block it jumps to, and the two are built from the same list, so a link
 * cannot point at a block that is not there. Screens with a route of their own leave it
 * off and just take the frame.
 */
export default {
    name: 'SettingsCard',
    props: {
        id: {type: String, default: ''},
        title: {type: String, default: ''},
        description: {type: String, default: ''}
    }
};
</script>

<template>
    <section :id="id ? 'fls_section_' + id : null" class="fls_card" :class="{'fls_section': !!id}">
        <div v-if="title || $slots.actions" class="fls_card_head">
            <div class="fls_card_head_text">
                <h2>
                    {{ title }}
                    <slot name="badge"/>
                </h2>
                <p v-if="description">{{ description }}</p>
            </div>

            <div v-if="$slots.actions" class="fls_card_head_actions">
                <slot name="actions"/>
            </div>
        </div>

        <div class="fls_card_body">
            <slot/>
        </div>
    </section>
</template>
