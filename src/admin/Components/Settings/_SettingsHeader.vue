<script type="text/babel">
export default {
    name: 'SettingsHeader',
    props: {
        heading: {
            type: String,
            default: ''
        },
        description: {
            type: String,
            default: ''
        },
        saving: {
            type: Boolean,
            default: false
        },
        showSave: {
            type: Boolean,
            default: true
        },
        saveText: {
            type: String,
            default: ''
        }
    },
    emits: ['save']
};
</script>

<template>
    <div class="fls_settings_header">
        <div>
            <h1>{{ heading }}</h1>
            <p v-if="description" class="fls_settings_header_desc">{{ description }}</p>
        </div>

        <div class="fls_settings_header_actions">
            <slot name="actions"/>

            <el-button v-if="showSave" type="primary" size="small" :loading="saving" @click="$emit('save')">
                {{ saveText || $t('Save') }}
            </el-button>
        </div>
    </div>
</template>
