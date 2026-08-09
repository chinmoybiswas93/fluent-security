<script type="text/babel">
/*
 * What actually changed in one file.
 *
 * A new file has nothing to compare against, so it is shown as it stands; a modified one is
 * shown against the official release. The diff is built into elements with classes rather
 * than into spans with inline colours: coloured text alone is unreadable on a dark
 * background, and a tinted line with a gutter mark says the same thing in both themes.
 */
export default {
    name: 'ViewFile',
    props: ['viewing_file'],
    data() {
        return {
            filePath: '',
            fileContent: '',
            originalFileContent: '',
            hasDiff: false,
            loading: true,
            error: ''
        }
    },
    methods: {
        getFileContent() {
            this.error = '';
            this.loading = true;

            this.$get('security-scan-settings/scan/view-file', {viewing_file: this.viewing_file})
                .then(response => {
                    this.filePath = response.filePath;
                    this.hasDiff = response.hasDiff;
                    this.fileContent = response.fileContent;
                    this.originalFileContent = response.originalFileContent;

                    if (response.hasDiff) {
                        this.$nextTick(() => {
                            this.renderDiff();
                        });
                    }
                })
                .catch(errors => {
                    this.$handleError(errors);
                    this.error = errors && errors.message ? errors.message : '';
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        renderDiff() {
            const target = this.$refs.fls_diff_viewer;

            if (!target || typeof Diff === 'undefined') {
                return;
            }

            const parts = Diff.diffLines(this.originalFileContent, this.fileContent);
            const fragment = document.createDocumentFragment();

            parts.forEach(part => {
                const line = document.createElement('span');

                line.className = 'fls_diff_part';

                if (part.added) {
                    line.classList.add('is_added');
                } else if (part.removed) {
                    line.classList.add('is_removed');
                }

                line.appendChild(document.createTextNode(part.value));
                fragment.appendChild(line);
            });

            target.innerHTML = '';
            target.appendChild(fragment);
        }
    },
    mounted() {
        this.getFileContent();
    }
}
</script>

<template>
    <div v-loading="loading" :element-loading-text="$t('Loading file…')">
        <p class="fls_file_view_path">{{ filePath }}</p>

        <pre v-if="error" class="fls_code">{{ error }}</pre>

        <el-input v-else-if="!hasDiff" type="textarea" :rows="24" v-model="fileContent"
                  :readonly="true"/>

        <pre v-else ref="fls_diff_viewer" class="fls_diff"></pre>
    </div>
</template>
