<template>
    <div>
        <crm-header :title="crm.t('automations.title')">
            <button v-if="crm.canWrite && !draft" type="button" class="crm-btn crm-btn-primary" @click="edit(null)"><i class="bx bx-plus"></i> {{ crm.t('automations.new') }}</button>
        </crm-header>

        <!-- AU-01 list -->
        <template v-if="!draft">
            <div v-if="loading" class="crm-card crm-muted">{{ crm.t('common.loading') }}</div>
            <div v-else-if="!items.length" class="crm-card crm-empty">
                <div class="crm-empty-icon"><i class="bx bx-bolt-circle"></i></div>
                <h4>{{ crm.t('automations.empty_title') }}</h4>
                <p class="crm-muted crm-small">{{ crm.t('automations.empty_body') }}</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap mt-2">
                    <button v-for="tpl in templates" :key="tpl.key" type="button" class="crm-btn crm-btn-soft crm-btn-sm" :disabled="!crm.canWrite" @click="edit(tpl.make())">{{ crm.t('automations.tpl_' + tpl.key) }}</button>
                </div>
            </div>
            <div v-else class="crm-grid" style="grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); align-items:start">
                <div class="crm-table-wrap">
                    <table class="crm-table" style="min-width:560px">
                        <thead><tr><th>{{ crm.t('common.name') }}</th><th>{{ crm.t('automations.trigger') }}</th><th>{{ crm.t('automations.runs') }}</th><th>{{ crm.t('automations.last_run') }}</th><th>{{ crm.t('common.status') }}</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="a in items" :key="a.id">
                                <td><strong>{{ a.name }}</strong><div class="crm-small crm-muted">{{ a.actions.map(x => crm.t('automations.action_' + x.type)).join(' → ') }}</div></td>
                                <td class="crm-small">{{ crm.t('automations.trigger_' + a.trigger) }}</td>
                                <td>{{ a.runs_count }}</td>
                                <td class="crm-small crm-muted">{{ a.last_run_at ? crm.ago(a.last_run_at) : '—' }}</td>
                                <td>
                                    <label class="crm-check" style="align-items:center"><input type="checkbox" :checked="a.is_active" :disabled="!crm.canWrite" @change="toggle(a)">
                                        <span :class="['crm-chip', a.is_active ? 'success' : '']">{{ a.is_active ? crm.t('automations.on') : crm.t('automations.off') }}</span></label>
                                </td>
                                <td style="white-space:nowrap">
                                    <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-sm" @click="edit(a)">{{ crm.t('common.edit') }}</button>
                                    <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-ghost crm-btn-sm crm-icon-btn" :aria-label="crm.t('common.delete')" @click="remove(a)"><i class="bx bx-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <aside class="crm-card">
                    <h3 class="crm-card-title mb-2">{{ crm.t('automations.recent_runs') }}</h3>
                    <p v-if="!runs.length" class="crm-small crm-muted mb-0">{{ crm.t('automations.no_runs') }}</p>
                    <div v-for="r in runs" :key="r.id" class="d-flex justify-content-between gap-2 py-1 crm-small" style="border-bottom:1px solid #f3f1f9">
                        <span class="min-w-0 text-truncate">{{ nameOf(r.automation_id) }} · {{ r.subject_type }} #{{ r.subject_id }}</span>
                        <span :class="['crm-chip plain', r.status === 'done' ? 'success' : 'danger']" :title="r.status === 'failed' ? JSON.stringify(r.result) : ''">{{ crm.t('automations.status_' + r.status) }}</span>
                    </div>
                </aside>
            </div>
        </template>

        <!-- AU-02 builder -->
        <template v-else>
            <div class="crm-toolbar">
                <button type="button" class="crm-btn crm-btn-ghost crm-icon-btn" :aria-label="crm.t('common.back')" @click="draft = null"><i class="bx bx-arrow-back bx-flip-horizontal-rtl"></i></button>
                <input v-model.trim="draft.name" class="crm-input" style="flex:1;min-width:220px;max-width:520px;font-weight:700" :placeholder="crm.t('automations.name_placeholder')" :aria-label="crm.t('common.name')">
                <span style="margin-inline-start:auto" class="d-flex gap-2 align-items-center">
                    <button type="button" class="crm-btn" :disabled="busy" @click="test"><i class="bx bx-test-tube"></i> {{ crm.t('automations.test_run') }}</button>
                    <label class="crm-check" style="align-items:center"><input v-model="draft.is_active" type="checkbox"> {{ draft.is_active ? crm.t('automations.on') : crm.t('automations.off') }}</label>
                    <button type="button" class="crm-btn crm-btn-primary" :disabled="busy || !draft.name || !draft.actions.length" @click="save"><i class="bx bx-check"></i> {{ crm.t('automations.publish') }}</button>
                </span>
            </div>
            <div v-if="testResult !== null" class="crm-alert info mb-3"><i class="bx bx-test-tube"></i> {{ crm.t(isTime(draft.trigger) ? 'automations.test_result_now' : 'automations.test_result', { count: testResult }) }}</div>

            <div style="max-width:620px;margin-inline:auto">
                <section class="crm-card mb-2" style="border-color:#c4b5fd">
                    <div class="crm-small" style="color:var(--crm-purple);font-weight:800;letter-spacing:.06em">{{ crm.t('automations.trigger').toUpperCase() }}</div>
                    <select v-model="draft.trigger" class="crm-select mt-2" :aria-label="crm.t('automations.trigger')">
                        <option v-for="t in schema.triggers" :key="t" :value="t">{{ crm.t('automations.trigger_' + t) }}</option>
                    </select>
                    <div v-if="draft.trigger === 'deal_stage_changed'" class="mt-2">
                        <label class="crm-label">{{ crm.t('automations.to_stage') }}</label>
                        <select v-model="draft.trigger_config.stage_id" class="crm-select"><option :value="null">{{ crm.t('common.any') }}</option><option v-for="s in allStages" :key="s.id" :value="s.id">{{ s.label }}</option></select>
                    </div>
                    <div v-if="isTime(draft.trigger)" class="mt-2">
                        <label class="crm-label">{{ crm.t('automations.idle_days') }}</label>
                        <input v-model.number="draft.trigger_config.days" type="number" min="1" max="365" class="crm-input" style="max-width:120px" dir="ltr">
                    </div>
                </section>
                <div class="text-center crm-muted">│</div>

                <section class="crm-card mb-2" style="border-color:#fcd34d">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="crm-small" style="color:var(--crm-amber);font-weight:800;letter-spacing:.06em">{{ crm.t('automations.conditions').toUpperCase() }}</div>
                        <button type="button" class="crm-btn crm-btn-ghost crm-btn-sm" @click="draft.conditions.push({ field: 'lead.source', operator: 'equals', value: '' })"><i class="bx bx-plus"></i> {{ crm.t('automations.add_condition') }}</button>
                    </div>
                    <p v-if="!draft.conditions.length" class="crm-small crm-muted mb-0 mt-1">{{ crm.t('automations.no_conditions') }}</p>
                    <div v-for="(c, i) in draft.conditions" :key="i" class="d-flex gap-2 mt-2 flex-wrap">
                        <select v-model="c.field" class="crm-select" style="flex:1;min-width:150px" :aria-label="crm.t('automations.field')"><option v-for="f in schema.fields" :key="f" :value="f">{{ crm.t('automations.field_' + f.replace('.', '_')) }}</option></select>
                        <select v-model="c.operator" class="crm-select" style="width:140px" :aria-label="crm.t('automations.operator')"><option v-for="o in schema.operators" :key="o" :value="o">{{ crm.t('automations.op_' + o) }}</option></select>
                        <template v-if="!['is_empty', 'not_empty'].includes(c.operator)">
                            <select v-if="valueOptions(c.field)" v-model="c.value" class="crm-select" style="width:160px" :aria-label="crm.t('common.value')"><option v-for="o in valueOptions(c.field)" :key="o.value" :value="o.value">{{ o.label }}</option></select>
                            <input v-else v-model="c.value" class="crm-input" style="width:160px" :aria-label="crm.t('common.value')">
                        </template>
                        <button type="button" class="crm-btn crm-btn-ghost crm-icon-btn" :aria-label="crm.t('common.delete')" @click="draft.conditions.splice(i, 1)"><i class="bx bx-x"></i></button>
                    </div>
                </section>

                <template v-for="(a, i) in draft.actions">
                    <div :key="'l' + i" class="text-center crm-muted">│</div>
                    <section :key="'a' + i" class="crm-card mb-2">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <div class="crm-small" style="color:var(--crm-blue);font-weight:800;letter-spacing:.06em">{{ crm.t('automations.action').toUpperCase() }} {{ i + 1 }}</div>
                            <span class="d-flex gap-1">
                                <button type="button" class="crm-btn crm-btn-ghost crm-btn-sm crm-icon-btn" :disabled="i === 0" aria-label="↑" @click="draft.actions.splice(i - 1, 0, draft.actions.splice(i, 1)[0])"><i class="bx bx-chevron-up"></i></button>
                                <button type="button" class="crm-btn crm-btn-ghost crm-btn-sm crm-icon-btn" :aria-label="crm.t('common.delete')" @click="draft.actions.splice(i, 1)"><i class="bx bx-trash"></i></button>
                            </span>
                        </div>
                        <select v-model="a.type" class="crm-select mt-2" :aria-label="crm.t('automations.action')"><option v-for="t in schema.actions" :key="t" :value="t">{{ crm.t('automations.action_' + t) }}</option></select>
                        <div class="mt-2">
                            <template v-if="a.type === 'create_task'">
                                <input v-model="a.subject" class="crm-input mb-2" :placeholder="crm.t('automations.task_subject')" :aria-label="crm.t('automations.task_subject')">
                                <div class="crm-row2">
                                    <label class="crm-small">{{ crm.t('automations.due_in') }}<input v-model.number="a.due_minutes" type="number" min="0" class="crm-input mt-1" dir="ltr"></label>
                                    <label class="crm-small">{{ crm.t('activities.assignee') }}
                                        <select v-model="a.assignee" class="crm-select mt-1"><option value="owner">{{ crm.t('automations.record_owner') }}</option><option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option></select></label>
                                </div>
                                <div class="crm-hint">{{ crm.t('automations.placeholders') }}</div>
                            </template>
                            <template v-else-if="a.type === 'assign_owner'">
                                <div class="crm-seg mb-2"><button type="button" :class="{ active: a.mode !== 'round_robin' }" @click="$set(a, 'mode', 'member')">{{ crm.t('automations.one_person') }}</button><button type="button" :class="{ active: a.mode === 'round_robin' }" @click="$set(a, 'mode', 'round_robin')">{{ crm.t('automations.round_robin') }}</button></div>
                                <template v-if="a.mode === 'round_robin'">
                                    <label v-for="m in crm.members" :key="m.id" class="crm-check crm-small py-1"><input v-model="a.member_ids" type="checkbox" :value="m.id"> {{ m.name }}</label>
                                </template>
                                <select v-else class="crm-select" :value="a.member_ids[0]" :aria-label="crm.t('common.owner')" @change="$set(a, 'member_ids', [Number($event.target.value)])">
                                    <option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                                <div class="crm-hint">{{ a.mode === 'round_robin' ? crm.t('automations.round_robin_hint') : crm.t('automations.one_person_hint') }}</div>
                            </template>
                            <input v-else-if="a.type === 'add_tag'" v-model="a.tag" class="crm-input" :placeholder="crm.t('common.tags')" :aria-label="crm.t('common.tags')">
                            <select v-else-if="a.type === 'set_lifecycle'" v-model="a.lifecycle" class="crm-select"><option v-for="l in crm.options.lifecycles" :key="l" :value="l">{{ crm.t('lifecycle.' + l) }}</option></select>
                            <select v-else-if="a.type === 'set_lead_status'" v-model="a.status" class="crm-select"><option v-for="s in ['new', 'contacted', 'qualified']" :key="s" :value="s">{{ crm.t('lead_status.' + s) }}</option></select>
                            <select v-else-if="a.type === 'move_deal_stage'" v-model="a.stage_id" class="crm-select"><option v-for="s in allStages" :key="s.id" :value="s.id">{{ s.label }}</option></select>
                            <template v-else-if="a.type === 'add_to_email_list'">
                                <select v-model="a.email_list_id" class="crm-select"><option v-for="l in emailLists" :key="l.id" :value="l.id">{{ l.name }}</option></select>
                                <div v-if="!emailLists.length" class="crm-hint">{{ crm.t('automations.no_email_lists') }}</div>
                            </template>
                            <template v-else-if="a.type === 'notify'">
                                <select v-model="a.member_id" class="crm-select mb-2"><option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option></select>
                                <input v-model="a.message" class="crm-input" :placeholder="crm.t('automations.message')" :aria-label="crm.t('automations.message')">
                                <div class="crm-hint">{{ crm.t('automations.notify_hint') }}</div>
                            </template>
                        </div>
                    </section>
                </template>
                <div class="text-center mt-2"><button type="button" class="crm-btn crm-btn-soft crm-btn-sm" :disabled="draft.actions.length >= 10" @click="draft.actions.push(blankAction())"><i class="bx bx-plus"></i> {{ crm.t('automations.add_action') }}</button></div>
            </div>
        </template>
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';

export default {
    name: 'CrmAutomations',
    components: { CrmHeader },
    inject: ['crm'],
    data: () => ({ items: [], runs: [], emailLists: [], schema: { triggers: [], actions: [], fields: [], operators: [] }, loading: true, draft: null, busy: false, testResult: null }),
    computed: {
        allStages() { return this.crm.pipelines.flatMap((p) => p.stages.map((s) => ({ id: s.id, label: `${p.name} · ${s.name}` }))); },
        templates() {
            const owner = this.crm.actorId;
            return [
                { key: 'new_lead', make: () => ({ name: this.crm.t('automations.tpl_new_lead'), trigger: 'lead_created', trigger_config: {}, conditions: [], is_active: false,
                    actions: [{ type: 'assign_owner', mode: 'round_robin', member_ids: this.crm.members.map((m) => m.id) }, { type: 'create_task', subject: this.crm.t('automations.tpl_call_subject'), due_minutes: 15, assignee: 'owner' }] }) },
                { key: 'idle_deal', make: () => ({ name: this.crm.t('automations.tpl_idle_deal'), trigger: 'deal_idle', trigger_config: { days: 7 }, conditions: [], is_active: false,
                    actions: [{ type: 'create_task', subject: this.crm.t('automations.tpl_idle_subject'), due_minutes: 60, assignee: 'owner' }] }) },
                { key: 'won', make: () => ({ name: this.crm.t('automations.tpl_won'), trigger: 'deal_won', trigger_config: {}, conditions: [], is_active: false,
                    actions: [{ type: 'add_tag', tag: 'customer' }, { type: 'notify', member_id: owner, message: this.crm.t('automations.tpl_won_message') }] }) },
            ];
        },
    },
    created() { this.load(); },
    methods: {
        async load() {
            this.loading = true;
            try {
                const res = await this.crm.api.get('automations');
                Object.assign(this, { items: res.data, runs: res.runs, emailLists: res.email_lists, schema: res.schema });
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.loading = false;
        },
        isTime(trigger) { return ['lead_idle', 'deal_idle'].includes(trigger); },
        nameOf(id) { return (this.items.find((a) => a.id === id) || {}).name || '#' + id; },
        blankAction() { return { type: 'create_task', subject: '', due_minutes: 60, assignee: 'owner', member_ids: [] }; },
        edit(a) {
            this.testResult = null;
            const src = a ? JSON.parse(JSON.stringify(a)) : { name: '', trigger: 'lead_created', trigger_config: {}, conditions: [], actions: [this.blankAction()], is_active: false };
            src.trigger_config = src.trigger_config || {};
            src.conditions = src.conditions || [];
            src.actions = src.actions.map((x) => ({ member_ids: [], ...x }));
            this.draft = src;
        },
        payload() {
            const { id, name, trigger, trigger_config, conditions, actions, is_active } = this.draft;
            return { id, name, trigger, trigger_config, conditions, actions, is_active };
        },
        valueOptions(field) {
            const opts = {
                'contact.lifecycle': this.crm.options.lifecycles.map((l) => ({ value: l, label: this.crm.t('lifecycle.' + l) })),
                'lead.status': this.crm.options.leadStatuses.map((s) => ({ value: s, label: this.crm.t('lead_status.' + s) })),
                'deal.stage_id': this.allStages.map((s) => ({ value: s.id, label: s.label })),
                'deal.pipeline_id': this.crm.pipelines.map((p) => ({ value: p.id, label: p.name })),
                'contact.owner_id': this.crm.members.map((m) => ({ value: m.id, label: m.name })),
                'deal.owner_id': this.crm.members.map((m) => ({ value: m.id, label: m.name })),
            };
            if (['lead.source', 'contact.source', 'deal.source'].includes(field)) return this.crm.options.sources.map((s) => ({ value: s, label: this.crm.sourceLabel(s) }));
            return opts[field] || null;
        },
        async test() {
            this.busy = true;
            try { this.testResult = (await this.crm.api.post('automations/preview', this.payload())).matches; } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.busy = false;
        },
        async save() {
            this.busy = true;
            try {
                const body = this.payload();
                if (body.id) await this.crm.api.put(`automations/${body.id}`, body); else await this.crm.api.post('automations', body);
                this.crm.notify(this.crm.t('common.saved'));
                this.draft = null;
                this.load();
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.busy = false;
        },
        async toggle(a) { try { await this.crm.api.post(`automations/${a.id}/toggle`); this.load(); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); } },
        async remove(a) {
            if (!window.confirm(this.crm.t('common.confirm_delete'))) return;
            try { await this.crm.api.del(`automations/${a.id}`); this.load(); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
    },
};
</script>
