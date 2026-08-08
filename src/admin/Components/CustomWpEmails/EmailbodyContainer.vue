<script>
/**
 * Renders the sample email in an iframe so its styles cannot touch the admin page.
 *
 * Both the content and the colours are painted by one method rather than a watcher
 * each. They used to fight: setting the content replaced the whole body, which threw
 * away the footer text the colour watcher had just written into it, and whichever ran
 * last won. Painting in one pass makes the order explicit.
 */
export default {
    name: 'EmailbodyContainer',
    props: ['content', 'style_config'],
    created() {
        // Held outside data() on purpose: a DOM node has no business being reactive.
        this.styleNode = null;
    },
    methods: {
        /** The iframe's document, once it exists. */
        doc() {
            const frame = this.$refs.ifr;

            if (!frame) {
                return null;
            }

            return frame.contentDocument || frame.contentWindow.document;
        },
        paint() {
            const doc = this.doc();

            if (!doc) {
                return;
            }

            doc.body.innerHTML = this.content || ' ';

            const config = this.style_config;

            if (!config) {
                return;
            }

            /*
             * One stylesheet, rewritten in place. This used to append a fresh <style>
             * on every change, which is once per frame while a colour picker is being
             * dragged - the head filled up with hundreds of them.
             */
            if (!this.styleNode || !this.styleNode.isConnected) {
                this.styleNode = doc.createElement('style');
                this.styleNode.type = 'text/css';
                doc.head.appendChild(this.styleNode);
            }

            this.styleNode.textContent = [
                `body, .body_wrap { background-color: ${config.body_bg} !important; }`,
                `body .footer_table { color: ${config.footer_content_color} !important; }`,
                `body .content_wrap { background-color: ${config.content_bg} !important; color: ${config.content_color} !important; }`,
                `blockquote { background-color: ${config.highlight_bg} !important; color: ${config.highlight_color} !important; }`,
                `blockquote p { color: ${config.highlight_color} !important; }`
            ].join('\n');

            // Only present in the sample markup, so never assume it is there.
            const footer = doc.querySelector('.footer_text');

            if (footer) {
                footer.innerHTML = config.footer_text || '';
            }
        },
        schedulePaint() {
            this.$nextTick(this.paint);
        }
    },
    watch: {
        content: {
            immediate: true,
            handler: 'schedulePaint'
        },
        style_config: {
            deep: true,
            handler: 'schedulePaint'
        }
    },
    mounted() {
        this.paint();
    }
};
</script>

<template>
    <div class="fls_email_frame">
        <iframe ref="ifr" frameborder="0" allowFullScreen mozallowfullscreen webkitallowfullscreen></iframe>
    </div>
</template>
