<script type="text/babel">
import {Search, ArrowLeftBold} from '@element-plus/icons-vue';

export default {
    name: 'EnrolledUsers',
    components: {
        ArrowLeftBold
    },
    data() {
        return {
            loading: false,
            resetting: 0,
            users: [],
            search: '',
            filter: 'all',
            summary: {enrolled: 0, total_users: 0},
            pagination: {
                total: 0,
                per_page: 20,
                current_page: 1
            },
            SearchIcon: Search
        }
    },
    methods: {
        fetchUsers() {
            this.loading = true;

            this.$get('two-fa/users', {
                page: this.pagination.current_page,
                search: this.search,
                filter: this.filter
            })
                .then(response => {
                    this.users = response.users.data;
                    this.summary = response.summary;
                    this.pagination.total = response.users.total;
                    this.pagination.per_page = response.users.per_page;
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        changePage(page) {
            this.pagination.current_page = page;
            this.fetchUsers();
        },
        applyFilter() {
            this.pagination.current_page = 1;
            this.fetchUsers();
        },
        /**
         * Turning someone's authenticator app off lowers what guards their account, so
         * it is spelled out rather than confirmed with a bare "are you sure".
         */
        confirmReset(user) {
            this.$confirm(
                this.$t('%s will be signed in by password alone until they set up a new one. Their recovery codes stop working straight away.', user.user_login),
                this.$t('Turn off the authenticator app?'),
                {
                    confirmButtonText: this.$t('Turn it off'),
                    cancelButtonText: this.$t('Cancel'),
                    type: 'warning'
                }
            )
                .then(() => {
                    this.resetUser(user);
                })
                .catch(() => {
                });
        },
        resetUser(user) {
            this.resetting = user.id;

            this.$post('two-fa/users/' + user.id + '/reset')
                .then(response => {
                    this.$notify.success(response.message);
                    this.summary = response.summary;

                    const index = this.users.findIndex(row => row.id === user.id);
                    if (index > -1) {
                        this.users[index] = response.user;
                    }
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .finally(() => {
                    this.resetting = 0;
                });
        },
        roleNames(user) {
            return user.roles.length ? user.roles.join(', ') : '—';
        }
    },
    mounted() {
        this.fetchUsers();
    }
};
</script>

<template>
    <div class="box_wrapper">
        <div class="box dashboard_box box_narrow">
        <div class="box_header" style="padding: 15px;font-size: 16px;">
            <div style="padding-top: 5px;" class="box_head">
                <el-button @click="$router.push({name: 'settings'})" size="small" style="margin-right: 10px;">
                    <el-icon>
                        <ArrowLeftBold/>
                    </el-icon>
                    <span style="margin-left: 4px;">{{ $t('Settings') }}</span>
                </el-button>
                {{ $t('Two-Factor Enrollment') }}
                <span class="fls_2fa_count">
                    {{ $t('%1s of %2s users have an authenticator app', summary.enrolled, summary.total_users) }}
                </span>
            </div>
            <div style="display: flex;" class="box_actions">
                <el-radio-group @change="applyFilter()" v-model="filter">
                    <el-radio-button value="all" size="default" :label="$t('All')"/>
                    <el-radio-button value="enrolled" size="default" :label="$t('Enrolled')"/>
                    <el-radio-button value="not_enrolled" size="default" :label="$t('Not enrolled')"/>
                </el-radio-group>
                <el-input clearable @keyup.native.enter="applyFilter()" style="width: 200px; margin-left: 10px;"
                          size="small" type="text" v-model="search" :placeholder="$t('Search users')">
                    <template #append>
                        <el-button @click="applyFilter()" :icon="SearchIcon"/>
                    </template>
                </el-input>
            </div>
        </div>
        <div class="box_body">
            <el-table v-loading="loading" :data="users" style="width: 100%">
                <el-table-column :label="$t('User')" min-width="200">
                    <template #default="scope">
                        <strong>{{ scope.row.display_name }}</strong>
                        <div class="fls_2fa_muted">{{ scope.row.user_login }} &middot; {{ scope.row.user_email }}</div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('Roles')" width="160">
                    <template #default="scope">
                        <span class="fls_2fa_muted">{{ roleNames(scope.row) }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('Authenticator App')" width="200">
                    <template #default="scope">
                        <template v-if="scope.row.totp_enrolled">
                            <span class="fls_2fa_pill fls_2fa_pill_on">{{ $t('Active') }}</span>
                            <div v-if="scope.row.activated_at" class="fls_2fa_muted">{{ scope.row.activated_at }}</div>
                        </template>
                        <template v-else-if="scope.row.totp_required">
                            <span class="fls_2fa_pill fls_2fa_pill_due">{{ $t('Required, not set up') }}</span>
                        </template>
                        <template v-else-if="scope.row.totp_allowed">
                            <span class="fls_2fa_pill">{{ $t('Not set up') }}</span>
                        </template>
                        <template v-else>
                            <span class="fls_2fa_muted">{{ $t('Not available') }}</span>
                        </template>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('Recovery codes')" width="140">
                    <template #default="scope">
                        <span v-if="!scope.row.totp_enrolled" class="fls_2fa_muted">—</span>
                        <span v-else :class="{'fls_2fa_low': scope.row.recovery_codes < 3}">
                            {{ scope.row.recovery_codes }} / {{ scope.row.recovery_total }}
                        </span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('Email Code')" width="110">
                    <template #default="scope">
                        <span v-if="scope.row.email_2fa" class="fls_2fa_pill fls_2fa_pill_on">{{ $t('On') }}</span>
                        <span v-else class="fls_2fa_muted">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('Lost device')" width="130" align="right">
                    <template #default="scope">
                        <el-button v-if="scope.row.totp_enrolled && scope.row.can_edit"
                                   size="small" type="danger" plain
                                   :loading="resetting === scope.row.id"
                                   @click="confirmReset(scope.row)">
                            {{ $t('Turn off') }}
                        </el-button>
                    </template>
                </el-table-column>

                <template #empty>
                    {{ $t('No users match this filter.') }}
                </template>
            </el-table>

            <div style="margin-top: 15px; text-align: right;">
                <el-pagination :background="false"
                               layout="total, prev, pager, next"
                               :hide-on-single-page="true"
                               :current-page="pagination.current_page"
                               :page-size="pagination.per_page"
                               :total="pagination.total"
                               @current-change="changePage"/>
            </div>
        </div>
        </div>
    </div>
</template>

<style lang="scss">
.fls_2fa_count {
    font-size: 13px;
    color: #606266;
    margin-left: 10px;
    font-weight: normal;
}

.fls_2fa_muted {
    color: #909399;
    font-size: 12px;
}

.fls_2fa_low {
    color: #b32d2e;
    font-weight: 600;
}

.fls_2fa_pill {
    display: inline-block;
    padding: 1px 9px;
    border-radius: 10px;
    font-size: 12px;
    background: #f0f2f5;
    color: #606266;

    &.fls_2fa_pill_on {
        background: #eaf6e5;
        color: #4a9c2d;
    }

    &.fls_2fa_pill_due {
        background: #fdf3e3;
        color: #b07d18;
    }
}
</style>
