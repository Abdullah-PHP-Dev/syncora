<template>
    <div>
        <crm-header :title="crm.t('leads.title')">
            <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-primary" @click="openCreate"><i class="bx bx-plus"></i> {{ crm.t('leads.new') }}</button>
        </crm-header>

        <nav class="crm-tabs" role="tablist">
            <button v-for="s in tabs" :key="s" type="button" role="tab" :class="['crm-tab', { active: status === s }]" @click="setStatus(s)">
                {{ s === '' ? crm.t('leads.tab_all') : crm.t('lead_status.' + s) }}<span class="count">{{ counts[s === '' ? 'all' : s] || 0 }}</span>
            </button>
        </nav>
        <div class="crm-toolbar">
            <input v-model="q" class="crm-input" type="search" style="min-width:240px" :placeholder="crm.t('common.search')" :aria-label="crm.t('common.search')" @input="debounced">
            <select v-model="owner" class="crm-select" :aria-label="crm.t('common.owner')" @change="reload">
                <option value="">{{ crm.t('common.owner') }}: {{ crm.t('common.any') }}</option>
                <option value="none">{{ crm.t('common.unassigned') }}</option>
                <option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <select v-model="source" class="crm-select" :aria-label="crm.t('common.source')" @change="reload">
                <option value="">{{ crm.t('common.source') }}: {{ crm.t('common.any') }}</option>
                <option v-for="s in crm.options.sources" :key="s" :value="s">{{ crm.sourceLabel(s) }}</option>
            </select>
            <button v-if="selected.length && crm.canWrite" type="button" class="crm-btn crm-btn-soft" @click="assignModal = true"><i class="bx bx-user-check"></i> {{ crm.t('leads.assign') }} ({{ selected.length }})</button>
        </div>

        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead><tr>
                    <th style="width:36px"></th><th>{{ crm.t('leads.lead') }}</th><th>{{ crm.t('common.source') }}</th><th>{{ crm.t('common.status') }}</th>
                    <th class="crm-hide-sm">{{ crm.t('leads.interest') }}</th><th>{{ crm.t('leads.estimated_value') }}</th><th>{{ crm.t('common.owner') }}</th>
                    <th>{{ crm.t('common.created') }}</th><th></th>
                </tr></thead>
                <tbody>
                    <tr v-if="loading"><td colspan="9" class="crm-muted">{{ crm.t('common.loading') }}</td></tr>
                    <tr v-for="l in rows" v-else :key="l.id" :class="{ selected: selected.includes(l.id) }">
                        <td><input v-if="crm.canWrite" v-model="selected" type="checkbox" :value="l.id" :aria-label="l.contact && l.contact.name"></td>
                        <td><div class="crm-person"><span :class="['crm-avatar', crm.avatarClass(l.contact.id)]">{{ crm.initials(l.contact.name) }}</span>
                            <div class="min-w-0"><a :href="l.contact.url" class="crm-person-name">{{ l.contact.name }}</a><div class="crm-person-sub crm-ltr">{{ l.contact.phone || l.contact.email }}</div></div></div></td>
                        <td>{{ crm.sourceLabel(l.source) }}<div v-if="l.campaign" class="crm-small crm-muted">{{ l.campaign }}</div></td>
                        <td><span :class="['crm-chip', l.status]">{{ crm.t('lead_status.' + l.status) }}</span><div v-if="l.disqualify_reason" class="crm-small crm-muted">{{ crm.t('reasons.' + l.disqualify_reason) }}</div></td>
                        <td class="crm-hide-sm crm-small" style="max-width:220px" dir="auto">{{ l.interest || '—' }}</td>
                        <td class="crm-ltr">{{ l.estimated_value !== null ? crm.money(l.estimated_value, l.currency) : '—' }}</td>
                        <td>{{ l.owner ? l.owner.name : crm.t('common.unassigned') }}</td>
                        <td class="crm-small crm-muted">{{ crm.ago(l.created_at) }}</td>
                        <td style="white-space:nowrap">
                            <template v-if="crm.canWrite && !['converted', 'unqualified'].includes(l.status)">
                                <button v-if="l.status === 'new'" type="button" class="crm-btn crm-btn-sm crm-btn-ghost" @click="setLeadStatus(l, 'contacted')">{{ crm.t('leads.mark_contacted') }}</button>
                                <button type="button" class="crm-btn crm-btn-sm crm-btn-soft" @click="openConvert(l)">{{ crm.t('leads.convert') }}</button>
                                <button type="button" class="crm-btn crm-btn-sm crm-btn-ghost crm-icon-btn" :aria-label="crm.t('leads.disqualify')" :title="crm.t('leads.disqualify')" @click="disq = { lead: l, reason: 'not_interested', note: '' }"><i class="bx bx-block"></i></button>
                            </template>
                            <span v-else-if="l.status === 'converted'" class="crm-small crm-muted"><i class="bx bx-check-circle" style="color:var(--crm-green)"></i> {{ crm.t('lead_status.converted') }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!loading && !rows.length" class="crm-empty">
                <div class="crm-empty-icon"><i class="bx bx-target-lock"></i></div>
                <h4>{{ crm.t('leads.empty_title') }}</h4><p class="crm-muted crm-small">{{ crm.t('leads.empty_body') }}</p>
            </div>
            <div v-if="meta.last_page > 1" class="crm-pager">
                <span>{{ crm.t('common.showing', { from: (meta.page - 1) * 25 + 1, to: Math.min(meta.page * 25, meta.total), total: meta.total }) }}</span>
                <span class="d-flex gap-2">
                    <button type="button" class="crm-btn crm-btn-sm" :disabled="meta.page <= 1" @click="go(meta.page - 1)">{{ crm.t('common.prev') }}</button>
                    <button type="button" class="crm-btn crm-btn-sm" :disabled="meta.page >= meta.last_page" @click="go(meta.page + 1)">{{ crm.t('common.next') }}</button>
                </span>
            </div>
        </div>

        <!-- L-03 Create lead -->
        <crm-drawer v-if="create" :title="crm.t('leads.new')" :subtitle="crm.t('leads.new_hint')" :close-label="crm.t('common.close')" @close="create = null">
            <div class="crm-row2">
                <div class="crm-field"><label class="crm-label" for="ld-name">{{ crm.t('leads.full_name') }} *</label><input id="ld-name" v-model.trim="create.name" class="crm-input" maxlength="160"></div>
                <div class="crm-field"><label class="crm-label" for="ld-phone">{{ crm.t('common.phone') }} *</label><input id="ld-phone" v-model.trim="create.phone" class="crm-input" dir="ltr" inputmode="tel"></div>
            </div>
            <div class="crm-field"><label class="crm-label" for="ld-email">{{ crm.t('common.email') }}</label><input id="ld-email" v-model.trim="create.email" type="email" class="crm-input" dir="ltr"></div>
            <div class="crm-row2">
                <div class="crm-field"><label class="crm-label" for="ld-src">{{ crm.t('common.source') }}</label>
                    <select id="ld-src" v-model="create.source" class="crm-select"><option v-for="s in crm.options.sources" :key="s" :value="s">{{ crm.sourceLabel(s) }}</option></select></div>
                <div class="crm-field"><label class="crm-label" for="ld-camp">{{ crm.t('leads.campaign') }}</label><input id="ld-camp" v-model.trim="create.campaign" class="crm-input" :placeholder="crm.t('common.optional')"></div>
            </div>
            <div class="crm-field"><label class="crm-label" for="ld-int">{{ crm.t('leads.interest') }}</label><textarea id="ld-int" v-model="create.interest" class="crm-textarea"></textarea></div>
            <div class="crm-row2">
                <div class="crm-field"><label class="crm-label" for="ld-owner">{{ crm.t('common.owner') }}</label>
                    <select id="ld-owner" v-model="create.owner_id" class="crm-select"><option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option></select></div>
                <div class="crm-field"><label class="crm-label" for="ld-val">{{ crm.t('leads.estimated_value') }} ({{ crm.currency }})</label><input id="ld-val" v-model.number="create.estimated_value" type="number" min="0" class="crm-input" dir="ltr"></div>
            </div>
            <label class="crm-check"><input v-model="create.follow_up" type="checkbox"> {{ crm.t('leads.follow_up_task') }}</label>
            <template #footer>
                <button type="button" class="crm-btn" @click="create = null">{{ crm.t('common.cancel') }}</button>
                <button type="button" class="crm-btn crm-btn-primary" :disabled="busy || !create.name || !(create.phone || create.email)" @click="saveLead"><i class="bx bx-check"></i> {{ crm.t('leads.create') }}</button>
            </template>
        </crm-drawer>

        <!-- L-04 Assign -->
        <crm-modal v-if="assignModal" :title="crm.t('leads.assign_title', { count: selected.length })" icon="bx-user-check" :close-label="crm.t('common.close')" @close="assignModal = false">
            <label v-for="m in crm.members" :key="m.id" class="crm-check py-2" style="border-bottom:1px solid #f3f1f9;align-items:center">
                <input v-model="assignTo" type="radio" :value="m.id">
                <span :class="['crm-avatar sm', crm.avatarClass(m.id)]">{{ crm.initials(m.name) }}</span><strong>{{ m.name }}</strong>
            </label>
            <p class="crm-hint mt-2"><i class="bx bx-lock-alt"></i> {{ crm.t('leads.assign_hint') }}</p>
            <template #footer>
                <button type="button" class="crm-btn" @click="assignModal = false">{{ crm.t('common.cancel') }}</button>
                <button type="button" class="crm-btn crm-btn-primary" :disabled="!assignTo || busy" @click="assign">{{ crm.t('leads.assign') }}</button>
            </template>
        </crm-modal>

        <!-- L-05 Convert -->
        <crm-modal v-if="conv" :title="crm.t('leads.convert_title')" :subtitle="conv.lead.contact.name" icon="bx-check-circle" :close-label="crm.t('common.close')" @close="conv = null">
            <div class="crm-alert info mb-3"><i class="bx bx-user"></i> {{ crm.t('leads.keep_person', { name: conv.lead.contact.name }) }}</div>
            <div class="crm-field"><label class="crm-label" for="cv-co">{{ crm.t('leads.company_optional') }}</label><input id="cv-co" v-model.trim="conv.company_name" class="crm-input"></div>
            <label class="crm-check mb-2"><input v-model="conv.create_deal" type="checkbox"> <strong>{{ crm.t('leads.create_deal') }}</strong></label>
            <div v-if="conv.create_deal" class="crm-card mb-3" style="padding:14px">
                <div class="crm-row2">
                    <div class="crm-field"><label class="crm-label" for="cv-dn">{{ crm.t('leads.deal_name') }}</label><input id="cv-dn" v-model.trim="conv.deal_name" class="crm-input"></div>
                    <div class="crm-field"><label class="crm-label" for="cv-dv">{{ crm.t('common.value') }} ({{ crm.currency }})</label><input id="cv-dv" v-model.number="conv.value" type="number" min="0" class="crm-input" dir="ltr"></div>
                </div>
                <div class="crm-row2">
                    <div class="crm-field"><label class="crm-label" for="cv-p">{{ crm.t('leads.pipeline') }}</label>
                        <select id="cv-p" v-model="conv.pipeline_id" class="crm-select" @change="conv.stage_id = stagesFor(conv.pipeline_id)[0].id"><option v-for="p in crm.pipelines" :key="p.id" :value="p.id">{{ p.name }}</option></select></div>
                    <div class="crm-field"><label class="crm-label" for="cv-s">{{ crm.t('leads.stage') }}</label>
                        <select id="cv-s" v-model="conv.stage_id" class="crm-select"><option v-for="s in stagesFor(conv.pipeline_id)" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
                </div>
                <div class="crm-row2">
                    <div class="crm-field mb-0"><label class="crm-label" for="cv-c">{{ crm.t('leads.expected_close') }}</label><input id="cv-c" v-model="conv.expected_close_date" type="date" class="crm-input"></div>
                    <div class="crm-field mb-0"><label class="crm-label" for="cv-o">{{ crm.t('common.owner') }}</label>
                        <select id="cv-o" v-model="conv.owner_id" class="crm-select"><option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option></select></div>
                </div>
            </div>
            <div class="crm-field mb-0"><label class="crm-label" for="cv-t">{{ crm.t('leads.follow_up') }}</label><input id="cv-t" v-model.trim="conv.task_subject" class="crm-input" :placeholder="crm.t('common.optional')"></div>
            <template #footer>
                <button type="button" class="crm-btn" @click="conv = null">{{ crm.t('common.cancel') }}</button>
                <button type="button" class="crm-btn crm-btn-primary" :disabled="busy || (conv.create_deal && !conv.deal_name)" @click="convert"><i class="bx bx-check"></i> {{ crm.t('leads.convert') }}</button>
            </template>
        </crm-modal>

        <!-- L-06 Disqualify -->
        <crm-modal v-if="disq" :title="crm.t('leads.disqualify_title')" :subtitle="disq.lead.contact.name" icon="bx-x" tone="danger" width="480px" :close-label="crm.t('common.close')" @close="disq = null">
            <label class="crm-label">{{ crm.t('leads.reason') }}</label>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button v-for="r in crm.options.disqualifyReasons" :key="r" type="button" :class="['crm-btn crm-btn-sm', disq.reason === r ? 'crm-btn-soft' : '']" :aria-pressed="disq.reason === r" @click="disq.reason = r">{{ crm.t('reasons.' + r) }}</button>
            </div>
            <label class="crm-label" for="dq-note">{{ crm.t('leads.note') }}</label>
            <textarea id="dq-note" v-model="disq.note" class="crm-textarea"></textarea>
            <template #footer>
                <button type="button" class="crm-btn" @click="disq = null">{{ crm.t('common.cancel') }}</button>
                <button type="button" class="crm-btn crm-btn-danger" :disabled="busy" @click="disqualify">{{ crm.t('leads.disqualify') }}</button>
            </template>
        </crm-modal>
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';
import CrmDrawer from '../ui/CrmDrawer.vue';
import CrmModal from '../ui/CrmModal.vue';

export default {
    name: 'CrmLeads',
    components: { CrmHeader, CrmDrawer, CrmModal },
    inject: ['crm'],
    data: () => ({
        rows: [], meta: {}, counts: {}, loading: true, status: '', q: '', owner: '', source: '', page: 1, timer: null, busy: false,
        selected: [], create: null, assignModal: false, assignTo: null, conv: null, disq: null,
        tabs: ['', 'new', 'contacted', 'qualified', 'unqualified', 'converted'],
    }),
    created() { this.load(); if (new URLSearchParams(window.location.search).get('new')) this.openCreate(); },
    methods: {
        async load() {
            this.loading = true;
            try {
                const res = await this.crm.api.get('leads', { status: this.status, q: this.q, owner_id: this.owner, source: this.source, page: this.page });
                Object.assign(this, { rows: res.data, meta: res.meta, counts: res.counts });
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.loading = false;
        },
        reload() { this.page = 1; this.selected = []; this.load(); },
        debounced() { clearTimeout(this.timer); this.timer = setTimeout(this.reload, 300); },
        setStatus(s) { this.status = s; this.reload(); },
        go(page) { this.page = page; this.load(); },
        stagesFor(pipelineId) { const p = this.crm.pipelines.find((x) => x.id === pipelineId); return p ? p.stages.filter((s) => s.kind === 'open') : []; },
        openCreate() { this.create = { name: '', phone: '', email: '', source: 'phone', campaign: '', interest: '', owner_id: this.crm.actorId, estimated_value: null, follow_up: true }; },
        async run(fn, done) {
            this.busy = true;
            try { await fn(); this.crm.notify(done || this.crm.t('common.saved')); this.load(); return true; } catch (e) { this.crm.notify(this.crm.error(e), 'error'); return false; } finally { this.busy = false; }
        },
        async saveLead() { if (await this.run(() => this.crm.api.post('leads', { ...this.create, currency: this.crm.currency }))) this.create = null; },
        async assign() { if (await this.run(() => this.crm.api.post('leads/assign', { ids: this.selected, owner_id: this.assignTo }))) { this.assignModal = false; this.selected = []; } },
        setLeadStatus(lead, status) { this.run(() => this.crm.api.patch(`leads/${lead.id}`, { status })); },
        openConvert(lead) {
            const pipeline = this.crm.pipelines[0] || { stages: [] };
            const stage = pipeline.stages.find((s) => s.kind === 'open' && s.sort_order > 0) || pipeline.stages[0];
            this.conv = {
                lead, company_name: lead.contact.company ? lead.contact.company.name : '', create_deal: true,
                deal_name: lead.interest ? lead.interest.slice(0, 60) : lead.contact.name, value: lead.estimated_value || 0,
                pipeline_id: pipeline.id, stage_id: stage && stage.id, expected_close_date: '', owner_id: lead.owner ? lead.owner.id : this.crm.actorId, task_subject: '',
            };
        },
        async convert() {
            const { lead, ...data } = this.conv;
            const payload = { ...data, currency: this.crm.currency, expected_close_date: data.expected_close_date || null, company_name: data.company_name || null, task_subject: data.task_subject || null };
            if (await this.run(() => this.crm.api.post(`leads/${lead.id}/convert`, payload), this.crm.t('leads.converted_ok'))) this.conv = null;
        },
        async disqualify() {
            const { lead, reason, note } = this.disq;
            if (await this.run(() => this.crm.api.post(`leads/${lead.id}/disqualify`, { reason, note }))) this.disq = null;
        },
    },
};
</script>
