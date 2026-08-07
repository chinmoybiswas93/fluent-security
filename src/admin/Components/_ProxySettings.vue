<script type="text/babel">
export default {
    name: 'ProxySettings',
    props: {
        settings: {
            type: Object,
            required: true
        },
        detection: {
            type: Object,
            default: () => ({status: 'none', headers: []})
        },
        config_locked: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            revealed: false
        }
    },
    computed: {
        status() {
            return this.detection.status || 'none';
        },
        /*
         * Cloudflare needs nothing configured, and a site with no sign of a proxy
         * needs nothing either - but "no evidence" is not proof, and a proxy that
         * strips its own headers would look exactly like this. So the fields are
         * folded away rather than taken off the page.
         */
        collapsed() {
            if (this.revealed || this.config_locked) {
                return false;
            }

            if (this.settings.trusted_proxies || this.settings.proxy_ip_header) {
                return false;
            }

            return this.status === 'none' || this.status === 'cloudflare';
        },
        headline() {
            const map = {
                detected: this.$t('This site is behind a reverse proxy'),
                cloudflare: this.$t('Cloudflare detected'),
                possible: this.$t('This site may be behind a reverse proxy'),
                none: this.$t('No reverse proxy detected')
            };

            return map[this.status] || map.none;
        },
        summary() {
            if (this.status === 'detected') {
                if (this.detection.configured) {
                    return this.$t('Requests reach WordPress from %s, and a trusted proxy is configured below.', this.detection.remote_addr);
                }

                return this.$t('Requests reach WordPress from %s, which is an address inside your own network, so something is relaying them. Until that relay is declared below, every visitor is recorded as the same IP address.', this.detection.remote_addr);
            }

            if (this.status === 'cloudflare') {
                return this.$t('Visitor addresses are read from Cloudflare automatically, and only for connections that actually came from a Cloudflare edge. There is nothing to configure unless another proxy sits between Cloudflare and this server.');
            }

            if (this.status === 'possible') {
                return this.$t('Forwarding headers are present, but they arrived over a public connection and any visitor can send them, so this is not proof. Only declare a proxy below if you know one is there.');
            }

            return this.$t('Requests reach WordPress directly from the visitor, so addresses are already accurate and nothing needs configuring here.');
        },
        /**
         * The only case worth interrupting somebody for: the attempt limit is counting
         * every visitor as one person, and nothing else on the site would say so.
         */
        needsAttention() {
            return !!this.detection.needs_attention;
        },
        canSuggest() {
            return !this.config_locked
                && this.detection.suggested_proxy
                && this.settings.trusted_proxies !== this.detection.suggested_proxy;
        }
    },
    methods: {
        applySuggestion() {
            this.settings.trusted_proxies = this.detection.suggested_proxy;

            if (this.detection.suggested_header) {
                this.settings.proxy_ip_header = this.detection.suggested_header;
            }

            this.$notify.info(this.$t('Filled in from this request. Review it and save to apply.'));
        }
    }
};
</script>

<template>
    <div class="fls_login_settings">
        <h3>
            {{ $t('Visitor IP Detection') }}
            <span class="fls_proxy_state" :class="'fls_proxy_state_' + status">{{ headline }}</span>
        </h3>

        <p style="margin-bottom: 15px;">{{ summary }}</p>

        <el-alert v-if="needsAttention" type="warning" :closable="false" show-icon style="margin-bottom: 15px;">
            {{
                $t('The login attempt limit works per IP address. While every visitor looks like %s, one person failing to log in counts against everybody.', detection.remote_addr)
            }}
        </el-alert>

        <div class="fls_proxy_facts">
            <span>
                <em>{{ $t('Connection from') }}</em>
                <code>{{ detection.remote_addr || '—' }}</code>
            </span>
            <span>
                <em>{{ $t('Recorded as your IP') }}</em>
                <code>{{ detection.resolved_ip || '—' }}</code>
            </span>
            <span v-if="detection.vendor">
                <em>{{ $t('Looks like') }}</em>
                <code>{{ detection.vendor }}</code>
            </span>
        </div>

        <div v-if="detection.headers && detection.headers.length" class="fls_proxy_headers">
            <em>{{ $t('Forwarding headers on this request') }}</em>
            <ul>
                <li v-for="header in detection.headers" :key="header.header">
                    <code>{{ header.header }}</code>: {{ header.value }}
                </li>
            </ul>
        </div>

        <p v-if="collapsed" class="fls_proxy_reveal">
            <a href="#" @click.prevent="revealed = true">{{ $t('Configure a reverse proxy anyway') }}</a>
            <span>{{ $t('Only needed if you know one is there and it is not being detected.') }}</span>
        </p>

        <template v-else>
            <el-alert v-if="config_locked" type="info" :closable="false" show-icon style="margin: 15px 0;">
                {{ $t('These values are defined in wp-config.php and take precedence over the fields below.') }}
            </el-alert>

            <p v-if="canSuggest" class="fls_proxy_suggest">
                <el-button size="small" type="primary" plain @click="applySuggestion()">
                    {{ $t('Use %s', detection.suggested_proxy) }}
                </el-button>
                <span>{{ $t('Fills these in from the request you are making now. Nothing is trusted until you save.') }}</span>
            </p>

            <el-row :gutter="30">
                <el-col :md="12" :sm="24">
                    <el-form-item :label="$t('Trusted proxy IP addresses or ranges')">
                        <el-input type="textarea" :rows="3" v-model="settings.trusted_proxies"
                                  placeholder="127.0.0.1, 10.0.0.0/8"/>
                        <p>
                            {{ $t('One per line or comma separated. CIDR ranges and IPv6 are supported. Leave empty to always use the direct connection address, which cannot be spoofed.') }}
                        </p>
                    </el-form-item>
                </el-col>
                <el-col :md="12" :sm="24">
                    <el-form-item :label="$t('Header the proxy sends the visitor IP in')">
                        <el-input v-model="settings.proxy_ip_header" placeholder="X-Forwarded-For"/>
                        <p>
                            {{ $t('Defaults to X-Forwarded-For. This header is only read for requests arriving from one of the trusted proxies above.') }}
                        </p>
                    </el-form-item>
                </el-col>
            </el-row>
        </template>
    </div>
</template>

<style lang="scss">
.fls_proxy_state {
    display: inline-block;
    margin-left: 10px;
    padding: 2px 10px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: normal;
    vertical-align: middle;
    background: #f0f2f5;
    color: #606266;

    &.fls_proxy_state_detected {
        background: #fdf3e3;
        color: #b07d18;
    }

    &.fls_proxy_state_cloudflare {
        background: #eaf6e5;
        color: #4a9c2d;
    }
}

.fls_proxy_facts {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 30px;
    margin-bottom: 12px;

    em {
        display: block;
        font-style: normal;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #909399;
    }

    code {
        background: #fff;
        border: 1px solid #e4e7ed;
        border-radius: 3px;
        padding: 1px 6px;
    }
}

.fls_proxy_headers {
    margin-bottom: 12px;

    em {
        font-style: normal;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #909399;
    }

    ul {
        margin: 4px 0 0;
        padding: 0;
        list-style: none;
        font-size: 12px;
        color: #606266;
        word-break: break-all;
    }
}

.fls_proxy_reveal {
    a {
        font-weight: 600;
        text-decoration: none;
    }

    span {
        margin-left: 10px;
        color: #909399;
        font-size: 12px;
    }
}

.fls_proxy_suggest {
    margin: 15px 0 !important;

    span {
        margin-left: 10px;
        color: #909399;
        font-size: 12px;
    }
}
</style>
