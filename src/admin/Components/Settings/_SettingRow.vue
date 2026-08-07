<script type="text/babel">
/**
 * One setting: what it is on the left, the control for it on the right.
 *
 * The shape matters more than it looks. A column of labels can be scanned without
 * reading a word of the explanations, which is how someone finds the one thing they
 * came to change.
 */
export default {
    name: 'SettingRow',
    props: {
        label: {
            type: String,
            default: ''
        },
        description: {
            type: String,
            default: ''
        },
        // For controls that need the full width, like a table or a group of cards.
        stacked: {
            type: Boolean,
            default: false
        }
    }
};
</script>

<template>
    <div class="fls_row" :class="{'fls_row_stacked': stacked}">
        <div class="fls_row_label">
            <slot name="label">
                <span class="fls_row_title">{{ label }}</span>
            </slot>
            <p v-if="description">{{ description }}</p>
        </div>
        <div class="fls_row_control">
            <slot/>
        </div>
    </div>
</template>
