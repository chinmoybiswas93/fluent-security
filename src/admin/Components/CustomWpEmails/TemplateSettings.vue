<script type="text/babel">
import EmailbodyContainer from "./EmailbodyContainer.vue";
import WPEditor from "./_wp_editor.vue";
import SettingsHeader from '../Settings/_SettingsHeader.vue';
import SettingsCard from '../Settings/_SettingsCard.vue';
import SettingRow from '../Settings/_SettingRow.vue';

export default {
    name: 'TemplateSettings',
    components: {WPEditor, EmailbodyContainer, SettingsHeader, SettingsCard, SettingRow},
    data() {
        return {
            settings: null,
            defaultContent: '',
            loading: false,
            saving: false,
            showingPreview: true,
            defaultColors: {},
            /*
             * Declared once rather than repeated as six near-identical colour fields.
             * The @active-change handler is what makes the preview move while you are
             * still dragging in the picker, before the value is committed.
             */
            colors: [
                {key: 'body_bg', label: 'Page background', description: 'Behind the email itself.'},
                {key: 'content_bg', label: 'Content background', description: 'The card the message sits on.'},
                {key: 'content_color', label: 'Text', description: 'Body copy.'},
                {key: 'highlight_bg', label: 'Button background', description: 'Behind a call to action.'},
                {key: 'highlight_color', label: 'Button text', description: 'On top of it.'},
                {key: 'footer_content_color', label: 'Footer text', description: 'The small print at the bottom.'}
            ]
        }
    },
    methods: {
        fetchSettings() {
            this.loading = true;

            this.$get('wp-default-emails/template-settings')
                .then(response => {
                    this.settings = response.settings;
                    this.defaultContent = response.default_content;
                    this.defaultColors = response.default_colors;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        saveSettings() {
            this.saving = true;

            this.$post('wp-default-emails/save-template-settings', {settings: this.settings})
                .then(response => {
                    this.$notify.success(response.message);
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .finally(() => {
                    this.saving = false;
                });
        },
        setDefaultColors() {
            let defaultColors = this.defaultColors;

            for (let key in defaultColors) {
                this.settings[key] = defaultColors[key];
            }
        }
    },
    mounted() {
        this.fetchSettings();
    }
}
</script>

<template>
    <div>
        <SettingsHeader :heading="$t('Email Template Design')"
                        :description="$t('How every system email looks, and who it comes from.')"
                        :saving="saving" @save="saveSettings()">
            <template #actions>
                <el-button size="small" @click="$router.push({name: 'settings_emails'})">
                    {{ $t('Back to emails') }}
                </el-button>
            </template>
        </SettingsHeader>

        <div class="fls_settings_content" v-loading="loading">
            <el-skeleton v-if="!settings" :animated="true" :rows="6"/>

            <el-form v-else label-position="top">
                <SettingsCard :title="$t('Colours')"
                              :description="$t('Applied to every system email at once.')">
                    <template #actions>
                        <el-button size="small" @click="setDefaultColors()">{{ $t('Reset') }}</el-button>
                    </template>

                    <SettingRow v-for="color in colors" :key="color.key"
                                :label="$t(color.label)" :description="$t(color.description)">
                        <el-color-picker v-model="settings[color.key]" :show-alpha="false"
                                         @active-change="(picked) => { settings[color.key] = picked; }"/>
                    </SettingRow>
                </SettingsCard>

                <SettingsCard :title="$t('Preview')"
                              :description="$t('Sample content in the colours above.')">
                    <template #actions>
                        <el-button size="small" @click="showingPreview = !showingPreview">
                            {{ showingPreview ? $t('Hide') : $t('Show') }}
                        </el-button>
                    </template>

                    <div v-if="defaultContent && showingPreview" class="fls_email_preview">
                        <emailbody-container :style_config="settings" :content="defaultContent"/>
                    </div>
                </SettingsCard>

                <SettingsCard :title="$t('Footer')"
                              :description="$t('Appears at the bottom of every system email.')">
                    <SettingRow stacked :label="$t('Footer text')">
                        <WPEditor :height="80" v-model="settings.footer_text"/>
                    </SettingRow>
                </SettingsCard>

                <SettingsCard :title="$t('Sender')"
                              :description="$t('Leave these empty to keep whatever WordPress or your mail plugin already uses.')">
                    <SettingRow :label="$t('From address')">
                        <el-input v-model="settings.from_email" :placeholder="$t('Enter email address')"/>
                    </SettingRow>

                    <SettingRow :label="$t('From name')">
                        <el-input type="text" v-model="settings.from_name" :placeholder="$t('Enter from name')"/>
                    </SettingRow>

                    <SettingRow :label="$t('Reply-to address')"
                                :description="$t('Where a reply goes, if that is not the sending address.')">
                        <el-input v-model="settings.reply_to_email" :placeholder="$t('Enter reply email address')"/>
                    </SettingRow>

                    <SettingRow :label="$t('Reply-to name')">
                        <el-input type="text" v-model="settings.reply_to_name" :placeholder="$t('Enter reply to name')"/>
                    </SettingRow>
                </SettingsCard>
            </el-form>
        </div>
    </div>
</template>

<style lang="scss">
.fls_email_preview {
    padding: 16px 0;
}
</style>
