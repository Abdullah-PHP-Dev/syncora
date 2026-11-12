<template>
    <div>
        <crm-header :title="crm.t('leads.lead')" :crumbs="[{ label: crm.t('nav.leads'), url: crm.pageUrl('leads') }, { label: l ? l.contact.name : '…' }]" />
        <div v-if="!l" class="crm-card crm-muted">{{ crm.t('common.loading') }}</div>
        <template v-else>
            <section class="crm-card mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                    <div class="crm-person" style="gap:14px">
                        <span :class="['crm-avatar', crm.avatarClass(l.contact.id)]" style="width:48px;height:48px;font-size:17px">{{ crm.initials(l.contact.name) }}</span>
                        <div class="min-w-0">
                            <h2 style="font-size:21px;font-weight:750;margin:0" dir="auto">{{ l.contact.name }}</h2>
                            <div class="crm-small crm-muted d-flex gap-3 flex-wrap mt-1">
                                <span v-if="l.contact.phone" class="crm-ltr"><i class="bx bx-phone"></i> {{ l.contact.phone }}</span>
                                <span v-if="l.contact.email" class="crm-ltr"><i class="bx bx-envelope"></i> {{ l.contact.email }}</span>
                                <span><i class="bx bx-purchase-tag"></i> {{ crm.sourceLabel(l.source) }}</span>
                                <a :href="l.contact.url"><i class="bx bx-user"></i> {{ crm.t('leads.view_contact') }}</a>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <span :class="['crm-chip', 'band-' + l.band]" style="font-size:13px">{{ l.score }} · {{ crm.t('bands.' + l.band) }}</span>
                        <template v-if="crm.canWrite && open">
                            <select class="crm-select" style="width:auto" :value="l.owner ? l.owner.id : ''" :aria-label="crm.t('leads.assign')" @change="patch({ owner_id: Number($event.target.value) || null })">
                                <option value="">{{ crm.t('common.unassigned') }}</option>
                                <option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                            <button type="button" class="crm-btn" style="color:var(--crm-red)" @click="disq = true"><i class="bx bx-block"></i> {{ crm.t('leads.disqualify') }}</button>
                            <button type="button" class="crm-btn crm-btn-primary" @click="conv = true"><i class="bx bx-check-circle"></i> {{ crm.t('leads.convert') }}</button>
                        </template>
                        <a v-else-if="l.deal" :href="l.deal.url" class="crm-btn crm-btn-soft"><i class="bx bx-briefcase"></i> {{ l.deal.name }}</a>
                    </div>
                </div>
                <!-- Status path -->
                <div class="crm-path mt-3" role="list">
                    <button v-for="s in data.statuses" :key="s" type="button" role="listitem" :disabled="!crm.canWrite || !open || s === 'converted'"
                        :class="['crm-btn crm-btn-sm', s === l.status ? 'crm-btn-primary' : passed(s) ? 'crm-btn-soft' : '']" @click="setStatus(s)">
                        <i v-if="passed(s)" class="bx bx-check"></i> {{ crm.opt('lead_status', s) }}
                    </button>
                    <span v-if="l.status === 'unqualified'" class="crm-btn crm-btn-sm crm-btn-danger" style="cursor:default">{{ crm.t('lead_status.unqualified') }} · {{ crm.opt('reasons', l.disqualify_reason || '') }}</span>
                </div>
            </section>

            <div class="crm-detail-grid">
                <aside class="d-flex flex-column gap-3">
                    <div class="crm-card">
                        <h3 class="crm-card-title mb-3">{{ crm.t('leads.details') }}</h3>
                        <dl class="crm-dl">
                            <dt>{{ crm.t('common.owner') }}</dt><dd>{{ l.owner ? l.owner.name : crm.t('common.unassigned') }}</dd>
                            <dt>{{ crm.t('leads.estimated_value') }}</dt><dd class="crm-ltr">{{ l.estimated_value !== null ? crm.money(l.estimated_value, l.currency) : '—' }}</dd>
                            <dt>{{ crm.t('common.created') }}</dt><dd>{{ crm.date(l.created_at, true) }}</dd>
                            <dt>{{ crm.t('leads.first_response') }}</dt>
                            <dd :style="l.first_response_minutes > 60 ? 'color:var(--crm-red)' : ''">{{ l.first_response_minutes === null ? crm.t('leads.no_reply_yet') : crm.t('leads.minutes_short', { n: l.first_response_minutes }) }}</dd>
                        </dl>
                        <template v-if="l.interest"><div class="crm-small crm-muted mt-3" style="font-weight:600">{{ crm.t('leads.interest') }}</div><p class="crm-bubble mb-0" dir="auto">{{ l.interest }}</p></template>
                    </div>
                    <div class="crm-card">
                        <h3 class="crm-card-title mb-3">{{ crm.t('leads.attribution') }}</h3>
                        <dl class="crm-dl">
                            <dt>{{ crm.t('common.source') }}</dt><dd>{{ crm.sourceLabel(l.source) }}</dd>
                            <dt>{{ crm.t('leads.campaign') }}</dt><dd dir="auto">{{ l.campaign || l.meta.campaign_name || '—' }}</dd>
                            <template v-if="l.meta.ad_name"><dt>{{ crm.t('leads.ad') }}</dt><dd dir="auto">{{ l.meta.ad_name }}</dd></template>
                            <template v-if="l.meta.form_name || l.meta.page_name"><dt>{{ crm.t('leads.form') }}</dt><dd dir="auto">{{ l.meta.form_name || l.meta.page_name }}</dd></template>
                            <template v-if="l.external_lead_id"><dt>{{ crm.t('leads.external_id') }}</dt><dd class="crm-ltr crm-small">{{ l.external_lead_id }}</dd></template>
                        </dl>
                    </div>
                </aside>

                <section class="d-flex flex-column gap-3 min-w-0">
                    <div class="crm-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="crm-card-title mb-0">{{ crm.t('leads.qualification') }}</h3>
                            <span class="d-flex gap-2 align-items-center">
                                <span class="crm-small crm-muted">{{ crm.t('leads.criteria_met', { n: metCount, total: criteria.length }) }}</span>
                                <button v-if="crm.canWrite && open" type="button" class="crm-btn crm-btn-soft crm-btn-sm" :disabled="aiBusy" :title="crm.t('leads.ai_fill_hint')" @click="aiQualify">
                                    <i :class="['bx', aiBusy ? 'bx-loader-alt bx-spin' : 'bxs-magic-wand']"></i> {{ crm.t('leads.ai_fill') }}</button>
                            </span>
                        </div>
                        <div v-for="c in criteria" :key="c" class="crm-check-row">
                            <input :id="'q-' + c" v-model="q[c].met" type="checkbox" :disabled="!crm.canWrite" @change="dirty = true">
                            <div class="flex-grow-1 min-w-0">
                                <label :for="'q-' + c" style="font-weight:600;font-size:13px">{{ crm.t('leads.criteria.' + c) }}</label>
                                <span v-if="q[c].by === 'ai'" class="crm-tag ms-1"><i class="bx bx-bot"></i> AI</span>
                                <input v-model.trim="q[c].note" class="crm-input mt-1" style="height:30px" :placeholder="crm.t('leads.criteria_note')" :aria-label="crm.t('leads.criteria.' + c)" :disabled="!crm.canWrite" dir="auto" maxlength="300" @input="dirty = true">
                            </div>
                        </div>
                        <div v-if="crm.canWrite" class="d-flex justify-content-end mt-2">
                            <button type="button" class="crm-btn crm-btn-primary crm-btn-sm" :disabled="!dirty || busy" @click="saveQualification">{{ crm.t('common.save') }}</button>
                        </div>
                    </div>

                    <div class="crm-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="crm-card-title mb-0"><i class="bx bxl-whatsapp"></i> {{ crm.t('leads.conversation') }}</h3>
                            <a v-if="data.conversation" :href="data.conversation.url" class="crm-btn crm-btn-sm crm-btn-soft">{{ crm.t('leads.open_inbox') }}</a>
                        </div>
                        <p v-if="!data.conversation" class="crm-small crm-muted mb-0">{{ crm.t('contacts.no_conversations') }}</p>
                        <div v-else class="d-flex flex-column" style="max-height:320px;overflow:auto">
                            <div v-for="m in data.conversation.messages" :key="m.id" :class="['crm-msg', m.direction]">{{ m.body || '…' }}<div class="crm-small crm-muted" style="font-size:10.5px">{{ crm.date(m.created_at, true) }}</div></div>
                        </div>
                    </div>

                    <div class="crm-card">
                        <div v-if="crm.canWrite" class="d-flex gap-2 flex-wrap mb-3">
                            <button v-for="t in ['call', 'note', 'task', 'meeting']" :key="t" type="button" class="crm-btn crm-btn-sm" @click="log = t"><i :class="['bx', icons[t]]"></i> {{ crm.t('activity_types.' + t) }}</button>
                        </div>
                        <crm-log-form v-if="log" :type="log" :contact-id="l.contact.id" :lead-id="l.id" @close="log = null" @saved="log = null; load()" />
                        <h3 class="crm-card-title mb-2">{{ crm.t('leads.history') }}</h3>
                        <p v-if="!data.timeline.length" class="crm-small crm-muted">{{ crm.t('contacts.no_timeline') }}</p>
                        <ul class="crm-timeline">
                            <li v-for="a in data.timeline" :key="a.id">
                                <span :class="['crm-tl-icon', a.type]"><i :class="['bx', icons[a.type] || 'bx-git-commit']"></i></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong class="crm-small">{{ a.type === 'event' ? crm.t('events.' + a.subject, { stage: (a.meta && a.meta.stage) || '' }) : (a.subject || crm.t('activity_types.' + a.type)) }}</strong>
                                        <span class="crm-small crm-muted">{{ crm.ago(a.created_at) }}</span>
                                    </div>
                                    <div v-if="a.author" class="crm-small crm-muted">{{ crm.t('common.by', { name: a.author.name }) }}<span v-if="a.outcome"> · {{ crm.opt('outcomes', a.outcome) }}</span></div>
                                    <div v-if="a.body && a.type !== 'event'" class="crm-bubble" dir="auto">{{ a.body }}</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </section>

                <aside class="d-flex flex-column gap-3">
                    <div class="crm-card" style="border-color:var(--crm-purple)">
                        <h3 class="crm-card-title mb-2"><i class="bx bx-bulb"></i> {{ crm.t('leads.next_step') }}</h3>
                        <p class="crm-small mb-0">{{ nextStep }}</p>
                    </div>
                    <div class="crm-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="crm-card-title mb-0">{{ crm.t('leads.score_title') }}</h3>
                            <strong :style="{ color: crm.scoreColor(l.score) }">{{ l.score }}/100</strong>
                        </div>
                        <p v-if="!l.score_breakdown.length" class="crm-small crm-muted mb-0">{{ crm.t('leads.no_score_reasons') }}</p>
                        <div v-for="(r, i) in l.score_breakdown" :key="i" class="d-flex justify-content-between crm-small py-1" style="border-bottom:1px solid var(--crm-border)">
                            <span>{{ ruleLabel(r) }}</span>
                            <strong :style="{ color: r.points < 0 ? 'var(--crm-red)' : 'var(--crm-green)' }" class="crm-ltr">{{ r.points > 0 ? '+' : '' }}{{ r.points }}</strong>
                        </div>
                    </div>
                    <div class="crm-card">
                        <h3 class="crm-card-title mb-2">{{ crm.t('leads.open_tasks') }}</h3>
                        <p v-if="!data.tasks.length" class="crm-small crm-muted mb-0">{{ crm.t('activities.none_open') }}</p>
                        <div v-for="t in data.tasks" :key="t.id" class="crm-check-row">
                            <input type="checkbox" :disabled="!crm.canWrite" :aria-label="t.subject" @change="complete(t)">
                            <div class="min-w-0"><div class="crm-small" style="font-weight:600" dir="auto">{{ t.subject }}</div>
                                <div :class="['crm-small', t.overdue ? '' : 'crm-muted']" :style="t.overdue ? 'color:var(--crm-red)' : ''">{{ crm.date(t.due_at, true) }}<span v-if="t.priority"> · {{ crm.t('priorities.' + t.priority) }}</span></div></div>
                        </div>
                    </div>
                    <crm-ai-card :contact-id="l.contact.id" />
                </aside>
            </div>
        </template>

        <crm-convert-modal v-if="conv" :lead="l" @close="conv = false" @done="conv = false; load()" />
        <crm-disqualify-modal v-if="disq" :lead="l" @close="disq = false" @done="disq = false; load()" />
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';
import CrmAiCard from '../ui/CrmAiCard.vue';
import CrmLogForm from '../ui/CrmLogForm.vue';
import CrmConvertModal from '../ui/CrmConvertModal.vue';
import CrmDisqualifyModal from '../ui/CrmDisqualifyModal.vue';

const CRITERIA = ['need', 'budget', 'timing', 'decision_maker', 'delivery_area'];

export default {
    name: 'CrmLead',
    components: { CrmHeader, CrmAiCard, CrmLogForm, CrmConvertModal, CrmDisqualifyModal },
    inject: ['crm'],
    props: { leadId: { type: Number, required: true } },
    data: () => ({
        data: null, q: {}, dirty: false, busy: false, aiBusy: false, conv: false, disq: false, log: null, criteria: CRITERIA,
        icons: { call: 'bx-phone', meeting: 'bx-calendar', task: 'bx-check-square', note: 'bx-note', email: 'bx-envelope', event: 'bx-git-commit' },
    }),
    computed: {
        l() { return this.data && this.data.lead; },
        open() { return this.l && !['converted', 'unqualified'].includes(this.l.status); },
        metCount() { return CRITERIA.filter((c) => this.q[c] && this.q[c].met).length; },
        nextStep() {
            const l = this.l;
            if (!this.open) return this.crm.t('leads.next.closed');
            if (!l.owner) return this.crm.t('leads.next.assign');
            if (l.first_response_minutes === null) return this.crm.t('leads.next.reply');
            if (this.data.tasks.some((t) => t.overdue)) return this.crm.t('leads.next.overdue');
            if (this.metCount >= 4) return this.crm.t('leads.next.convert');
            if (this.metCount < 2) return this.crm.t('leads.next.qualify', { missing: CRITERIA.filter((c) => !this.q[c].met).slice(0, 2).map((c) => this.crm.t('leads.criteria.' + c)).join(', ') });
            if (!this.data.tasks.length) return this.crm.t('leads.next.follow_up');
            return this.crm.t('leads.next.keep_going');
        },
    },
    created() { this.load(); },
    methods: {
        async load() {
            try {
                this.data = await this.crm.api.get(`leads/${this.leadId}`);
                this.q = JSON.parse(JSON.stringify(this.data.lead.qualification));
                this.dirty = false;
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        passed(s) {
            const order = this.data.statuses;
            return this.l.status === 'converted' ? s !== 'converted' : order.indexOf(s) < order.indexOf(this.l.status);
        },
        ruleLabel(r) {
            const field = this.crm.t('automations.field_' + r.field.replace('.', '_'));
            const op = this.crm.t('automations.op_' + r.operator);
            return r.value === null || r.value === undefined || r.operator === 'not_empty' ? `${field} ${op}` : `${field} ${op} ${r.value}`;
        },
        async patch(payload) {
            this.busy = true;
            try { await this.crm.api.patch(`leads/${this.leadId}`, payload); this.crm.notify(this.crm.t('common.saved')); await this.load(); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.busy = false;
        },
        setStatus(s) { if (s !== this.l.status) this.patch({ status: s }); },
        saveQualification() {
            const payload = {};
            CRITERIA.forEach((c) => { payload[c] = { met: !!this.q[c].met, note: this.q[c].note || null }; });
            this.patch({ qualification: payload });
        },
        async aiQualify() {
            this.aiBusy = true;
            try {
                const res = await window.axios.post(this.crm.withId(this.crm.urls.aiQualify, this.leadId));
                this.q = JSON.parse(JSON.stringify({ ...this.q, ...res.data.qualification }));
                this.crm.notify(this.crm.t('leads.ai_filled'));
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.aiBusy = false;
        },
        async complete(t) { await this.crm.api.patch(`activities/${t.id}`, { completed: true }).catch(() => null); this.load(); },
    },
};
</script>
