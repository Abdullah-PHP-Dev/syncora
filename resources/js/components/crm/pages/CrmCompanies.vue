<template>
    <div>
        <crm-header :title="crm.t('companies.title')">
            <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-primary" @click="drawer = true"><i class="bx bx-plus"></i> {{ crm.t('companies.new') }}</button>
        </crm-header>
        <div class="crm-toolbar">
            <input v-model="q" class="crm-input" type="search" style="min-width:260px" :placeholder="crm.t('common.search')" :aria-label="crm.t('common.search')" @input="debounced">
        </div>
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead><tr>
                    <th>{{ crm.t('common.name') }}</th><th>{{ crm.t('companies.industry') }}</th><th class="crm-hide-sm">{{ crm.t('companies.city') }}</th>
                    <th>{{ crm.t('companies.contacts_count') }}</th><th>{{ crm.t('companies.open_deals') }}</th><th>{{ crm.t('companies.open_value') }}</th><th>{{ crm.t('common.owner') }}</th>
                </tr></thead>
                <tbody>
                    <tr v-if="loading"><td colspan="7" class="crm-muted">{{ crm.t('common.loading') }}</td></tr>
                    <tr v-for="c in rows" v-else :key="c.id">
                        <td><div class="crm-person"><span :class="['crm-avatar', crm.avatarClass(c.id)]" style="border-radius:9px">{{ crm.initials(c.name) }}</span>
                            <div class="min-w-0"><a :href="c.url" class="crm-person-name">{{ c.name }}</a><div class="crm-person-sub crm-ltr">{{ c.domain }}</div></div></div></td>
                        <td>{{ c.industry || '—' }}</td><td class="crm-hide-sm">{{ c.city || '—' }}</td>
                        <td>{{ c.contacts_count }}</td><td>{{ c.open_deals_count }}</td><td class="crm-ltr">{{ crm.money(c.open_value) }}</td>
                        <td>{{ c.owner ? c.owner.name : crm.t('common.unassigned') }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!loading && !rows.length" class="crm-empty">
                <div class="crm-empty-icon"><i class="bx bx-buildings"></i></div>
                <h4>{{ crm.t('companies.empty_title') }}</h4><p class="crm-muted crm-small">{{ crm.t('companies.empty_body') }}</p>
            </div>
            <div v-if="meta.last_page > 1" class="crm-pager">
                <span>{{ crm.t('common.showing', { from: (meta.page - 1) * 25 + 1, to: Math.min(meta.page * 25, meta.total), total: meta.total }) }}</span>
                <span class="d-flex gap-2">
                    <button type="button" class="crm-btn crm-btn-sm" :disabled="meta.page <= 1" @click="go(meta.page - 1)">{{ crm.t('common.prev') }}</button>
                    <button type="button" class="crm-btn crm-btn-sm" :disabled="meta.page >= meta.last_page" @click="go(meta.page + 1)">{{ crm.t('common.next') }}</button>
                </span>
            </div>
        </div>
        <crm-company-drawer v-if="drawer" @close="drawer = false" @saved="load" />
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';
import CrmCompanyDrawer from '../ui/CrmCompanyDrawer.vue';

export default {
    name: 'CrmCompanies',
    components: { CrmHeader, CrmCompanyDrawer },
    inject: ['crm'],
    data: () => ({ rows: [], meta: {}, q: '', page: 1, loading: true, drawer: false, timer: null }),
    created() { this.load(); },
    methods: {
        async load() {
            this.loading = true;
            try { const res = await this.crm.api.get('companies', { q: this.q, page: this.page }); this.rows = res.data; this.meta = res.meta; } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.loading = false;
        },
        debounced() { clearTimeout(this.timer); this.timer = setTimeout(() => { this.page = 1; this.load(); }, 300); },
        go(page) { this.page = page; this.load(); },
    },
};
</script>
