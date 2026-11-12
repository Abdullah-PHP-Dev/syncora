<template>
    <div>
        <crm-header :title="crm.t('contacts.title')">
            <button v-if="crm.canWrite" type="button" class="crm-btn" @click="dupes = true"><i class="bx bx-git-merge"></i> {{ crm.t('duplicates.title') }}</button>
            <button v-if="crm.canWrite" type="button" class="crm-btn" @click="importing = true"><i class="bx bx-import"></i> {{ crm.t('import.title') }}</button>
            <a class="crm-btn" :href="exportUrl"><i class="bx bx-export"></i> {{ crm.t('common.export') }}</a>
            <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-primary" @click="drawer = true"><i class="bx bx-plus"></i> {{ crm.t('contacts.new') }}</button>
        </crm-header>

        <nav class="crm-tabs" role="tablist">
            <button v-for="tb in tabs" :key="tb.key" type="button" role="tab" :aria-selected="tab === tb.key" :class="['crm-tab', { active: tab === tb.key }]" @click="setTab(tb.key)">
                {{ crm.t('contacts.tab_' + tb.key) }}<span class="count crm-ltr">{{ crm.number(counts[tb.key] || 0) }}</span>
            </button>
        </nav>

        <div class="crm-toolbar">
            <select v-model="segmentId" class="crm-select" :aria-label="crm.t('segments.title')" @change="reload">
                <option value="">{{ crm.t('segments.all') }}</option>
                <option v-for="s in segments" :key="s.id" :value="s.id">{{ s.name }} ({{ s.count }})</option>
            </select>
            <input v-model="q" class="crm-input" type="search" style="min-width:240px" :placeholder="crm.t('common.search')" :aria-label="crm.t('common.search')" @input="debounced">
            <select v-model="filters.lifecycle" class="crm-select" :aria-label="crm.t('contacts.lifecycle')" @change="reload">
                <option value="">{{ crm.t('contacts.lifecycle') }}: {{ crm.t('common.any') }}</option>
                <option v-for="l in crm.options.lifecycles" :key="l" :value="l">{{ crm.t('lifecycle.' + l) }}</option>
            </select>
            <select v-model="filters.source" class="crm-select" :aria-label="crm.t('common.source')" @change="reload">
                <option value="">{{ crm.t('common.source') }}: {{ crm.t('common.any') }}</option>
                <option v-for="s in crm.options.sources" :key="s" :value="s">{{ crm.sourceLabel(s) }}</option>
            </select>
            <select v-model="filters.owner_id" class="crm-select" :aria-label="crm.t('common.owner')" @change="reload">
                <option value="">{{ crm.t('common.owner') }}: {{ crm.t('common.any') }}</option>
                <option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <label class="crm-check crm-small"><input v-model="filters.has_conversation" type="checkbox" @change="reload"> {{ crm.t('inbox.linked') }}</label>
            <input v-model.trim="filters.tag" class="crm-input" style="min-width:110px;width:120px" :placeholder="crm.t('common.tags')" :aria-label="crm.t('common.tags')" @change="reload">
            <button v-if="activeFilters" type="button" class="crm-btn crm-btn-ghost crm-btn-sm" @click="clearFilters">{{ crm.t('common.clear') }}</button>
            <button v-if="crm.canWrite && (activeFilters || q)" type="button" class="crm-btn crm-btn-soft crm-btn-sm" @click="saveSegment"><i class="bx bx-bookmark-plus"></i> {{ crm.t('segments.save') }}</button>
            <template v-if="segmentId && crm.canWrite">
                <button type="button" class="crm-btn crm-btn-soft crm-btn-sm" @click="emailModal = { list: emailLists[0] ? emailLists[0].id : '', name: '' }"><i class="bx bx-envelope"></i> {{ crm.t('segments.to_email') }}</button>
                <button type="button" class="crm-btn crm-btn-ghost crm-icon-btn crm-btn-sm" :aria-label="crm.t('common.delete')" @click="deleteSegment"><i class="bx bx-trash"></i></button>
            </template>
        </div>

        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width:36px"><input v-if="crm.canWrite" type="checkbox" :checked="allSelected" :aria-label="crm.t('common.all')" @change="toggleAll"></th>
                        <th class="sortable" @click="sortBy('first_name')">{{ crm.t('common.name') }} <i v-if="sort === 'first_name'" :class="['bx', dir === 'asc' ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt']"></i></th>
                        <th>{{ crm.t('common.phone') }}</th>
                        <th class="crm-hide-sm">{{ crm.t('common.company') }}</th>
                        <th>{{ crm.t('contacts.lifecycle') }}</th>
                        <th class="crm-hide-sm">{{ crm.t('common.source') }}</th>
                        <th class="sortable" @click="sortBy('score')">{{ crm.t('common.score') }}</th>
                        <th>{{ crm.t('common.owner') }}</th>
                        <th class="sortable" @click="sortBy('last_activity_at')">{{ crm.t('common.last_activity') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading"><td colspan="9" class="crm-muted">{{ crm.t('common.loading') }}</td></tr>
                    <tr v-for="c in rows" v-else :key="c.id" :class="{ selected: selected.includes(c.id) }">
                        <td><input v-if="crm.canWrite" v-model="selected" type="checkbox" :value="c.id" :aria-label="c.name"></td>
                        <td>
                            <div class="crm-person">
                                <span :class="['crm-avatar', crm.avatarClass(c.id)]">{{ crm.initials(c.name) }}</span>
                                <div class="min-w-0"><a :href="c.url" class="crm-person-name">{{ c.name }}</a><div class="crm-person-sub crm-ltr">{{ c.email }}</div></div>
                            </div>
                        </td>
                        <td><span class="crm-ltr">{{ c.phone || '—' }}</span></td>
                        <td class="crm-hide-sm">{{ c.company ? c.company.name : '—' }}</td>
                        <td><span :class="['crm-chip', c.lifecycle]">{{ crm.t('lifecycle.' + c.lifecycle) }}</span></td>
                        <td class="crm-hide-sm">{{ crm.sourceLabel(c.source) }}</td>
                        <td><span class="crm-score">{{ c.score }}<span class="crm-score-bar"><span :style="{ width: c.score + '%', background: crm.scoreColor(c.score) }"></span></span></span></td>
                        <td>
                            <span v-if="c.owner" class="d-inline-flex gap-1 align-items-center"><span :class="['crm-avatar sm', crm.avatarClass(c.owner.id)]">{{ crm.initials(c.owner.name) }}</span>{{ c.owner.name.split(' ')[0] }}</span>
                            <span v-else class="crm-muted">{{ crm.t('common.unassigned') }}</span>
                        </td>
                        <td class="crm-muted crm-small">{{ crm.ago(c.last_activity_at || c.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!loading && !rows.length" class="crm-empty">
                <div class="crm-empty-icon"><i class="bx bx-user-plus"></i></div>
                <h4>{{ crm.t('contacts.empty_title') }}</h4>
                <p class="crm-muted crm-small">{{ crm.t('contacts.empty_body') }}</p>
                <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-primary mt-2" @click="drawer = true"><i class="bx bx-plus"></i> {{ crm.t('contacts.new') }}</button>
            </div>
            <div v-if="meta.total" class="crm-pager">
                <span>{{ crm.t('common.showing', { from: (meta.page - 1) * 25 + 1, to: Math.min(meta.page * 25, meta.total), total: crm.number(meta.total) }) }}</span>
                <span class="d-flex gap-2">
                    <button type="button" class="crm-btn crm-btn-sm" :disabled="meta.page <= 1" @click="go(meta.page - 1)">{{ crm.t('common.prev') }}</button>
                    <button type="button" class="crm-btn crm-btn-sm" :disabled="meta.page >= meta.last_page" @click="go(meta.page + 1)">{{ crm.t('common.next') }}</button>
                </span>
            </div>
        </div>

        <div v-if="selected.length" class="crm-bulkbar" role="toolbar">
            <span>{{ crm.t('contacts.selected', { count: selected.length }) }}</span>
            <select class="crm-btn crm-btn-sm" :aria-label="crm.t('contacts.assign_owner')" @change="bulk('assign', { owner_id: Number($event.target.value) }); $event.target.value = ''">
                <option value="">{{ crm.t('contacts.assign_owner') }}</option>
                <option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <select class="crm-btn crm-btn-sm" :aria-label="crm.t('contacts.change_stage')" @change="bulk('lifecycle', { lifecycle: $event.target.value }); $event.target.value = ''">
                <option value="">{{ crm.t('contacts.change_stage') }}</option>
                <option v-for="l in crm.options.lifecycles" :key="l" :value="l">{{ crm.t('lifecycle.' + l) }}</option>
            </select>
            <button type="button" class="crm-btn crm-btn-sm" @click="addTags"><i class="bx bx-purchase-tag"></i> {{ crm.t('contacts.add_tags') }}</button>
            <button type="button" class="crm-btn crm-btn-sm" @click="bulkDelete"><i class="bx bx-trash"></i> {{ crm.t('common.delete') }}</button>
            <button type="button" class="crm-btn crm-btn-sm crm-icon-btn" :aria-label="crm.t('common.close')" @click="selected = []"><i class="bx bx-x"></i></button>
        </div>

        <crm-contact-drawer v-if="drawer" @close="drawer = false" @saved="reload" />
        <crm-import-modal v-if="importing" @close="importing = false" @imported="reload" />
        <crm-duplicates-modal v-if="dupes" @close="dupes = false" @merged="reload" />
        <crm-modal v-if="emailModal" :title="crm.t('segments.to_email')" icon="bx-envelope" width="460px" :close-label="crm.t('common.close')" @close="emailModal = null">
            <label class="crm-label">{{ crm.t('segments.email_list') }}</label>
            <select v-model="emailModal.list" class="crm-select mb-2"><option value="">{{ crm.t('segments.new_list') }}</option><option v-for="l in emailLists" :key="l.id" :value="l.id">{{ l.name }}</option></select>
            <input v-if="!emailModal.list" v-model.trim="emailModal.name" class="crm-input" :placeholder="crm.t('segments.new_list_name')">
            <p class="crm-hint">{{ crm.t('segments.email_hint') }}</p>
            <template #footer>
                <button type="button" class="crm-btn" @click="emailModal = null">{{ crm.t('common.cancel') }}</button>
                <button type="button" class="crm-btn crm-btn-primary" :disabled="!emailModal.list && !emailModal.name" @click="sendToEmail">{{ crm.t('segments.send') }}</button>
            </template>
        </crm-modal>
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';
import CrmContactDrawer from '../ui/CrmContactDrawer.vue';
import CrmImportModal from '../ui/CrmImportModal.vue';
import CrmDuplicatesModal from '../ui/CrmDuplicatesModal.vue';
import CrmModal from '../ui/CrmModal.vue';

export default {
    name: 'CrmContacts',
    components: { CrmHeader, CrmContactDrawer, CrmImportModal, CrmDuplicatesModal, CrmModal },
    inject: ['crm'],
    data: () => ({
        rows: [], meta: {}, counts: {}, loading: true, tab: 'all', q: '', sort: 'created_at', dir: 'desc', page: 1,
        filters: { lifecycle: '', source: '', owner_id: '', has_conversation: false, tag: '' }, selected: [], drawer: false, timer: null,
        importing: false, dupes: false, segments: [], segmentId: '', emailLists: [], emailModal: null,
        tabs: [{ key: 'all' }, { key: 'mine' }, { key: 'hot' }, { key: 'customers' }, { key: 'unassigned' }],
    }),
    computed: {
        params() { return { tab: this.tab, q: this.q, sort: this.sort, dir: this.dir, page: this.page, ...this.filters, has_conversation: this.filters.has_conversation ? 1 : '', segment_id: this.segmentId }; },
        exportUrl() { const p = new URLSearchParams(Object.entries(this.params).filter(([, v]) => v !== '' && v !== null)); return `${this.crm.urls.export}?${p}`; },
        allSelected() { return this.rows.length && this.rows.every((r) => this.selected.includes(r.id)); },
        activeFilters() { return Object.values(this.filters).some(Boolean); },
    },
    created() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('company_id')) this.filters.company_id = params.get('company_id');
        if (params.get('new')) this.drawer = true;
        this.load();
        this.loadSegments();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const res = await this.crm.api.get('contacts', this.params);
                Object.assign(this, { rows: res.data, meta: res.meta, counts: res.counts });
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.loading = false;
        },
        reload() { this.page = 1; this.selected = []; this.load(); },
        debounced() { clearTimeout(this.timer); this.timer = setTimeout(this.reload, 300); },
        setTab(key) { this.tab = key; this.reload(); },
        go(page) { this.page = page; this.load(); },
        sortBy(column) { this.dir = this.sort === column && this.dir === 'desc' ? 'asc' : 'desc'; this.sort = column; this.reload(); },
        clearFilters() { this.filters = { lifecycle: '', source: '', owner_id: '', has_conversation: false, tag: '' }; this.reload(); },
        async loadSegments() {
            const res = await this.crm.api.get('segments').catch(() => ({ data: [], email_lists: [] }));
            this.segments = res.data; this.emailLists = res.email_lists;
        },
        async saveSegment() {
            const name = window.prompt(this.crm.t('segments.name'));
            if (!name) return;
            try {
                const res = await this.crm.api.post('segments', { name, filters: { ...this.filters, q: this.q } });
                await this.loadSegments();
                this.segmentId = res.data.id;
                this.crm.notify(this.crm.t('common.saved'));
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        async deleteSegment() {
            if (!window.confirm(this.crm.t('common.confirm_delete'))) return;
            try { await this.crm.api.del(`segments/${this.segmentId}`); this.segmentId = ''; await this.loadSegments(); this.reload(); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        async sendToEmail() {
            const { list, name } = this.emailModal;
            try {
                const res = await this.crm.api.post(`segments/${this.segmentId}/email-list`, list ? { email_list_id: list } : { new_list: name });
                this.emailModal = null;
                this.crm.notify(this.crm.t('segments.sent', { added: res.added, list: res.list.name, skipped: res.skipped }));
                this.loadSegments();
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        toggleAll(e) { this.selected = e.target.checked ? this.rows.map((r) => r.id) : []; },
        async bulk(action, extra) {
            if (!extra || Object.values(extra).some((v) => v === '' || Number.isNaN(v))) return;
            try {
                const res = await this.crm.api.post('contacts/bulk', { ids: this.selected, action, ...extra });
                this.crm.notify(`${this.crm.t('common.saved')} (${res.updated})`);
                this.reload();
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        addTags() {
            const value = window.prompt(this.crm.t('common.tags_hint'));
            if (value) this.bulk('tag', { tags: value.split(',').map((t) => t.trim()).filter(Boolean) });
        },
        bulkDelete() { if (window.confirm(this.crm.t('common.confirm_delete'))) this.bulk('delete', {}); },
    },
};
</script>
