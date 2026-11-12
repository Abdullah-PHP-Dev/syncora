<template>
    <div>
        <crm-header :title="crm.t('deals.deal')" :crumbs="[{ label: crm.t('nav.deals'), url: crm.pageUrl('deals') }, { label: d ? d.name : '…' }]" />
        <div v-if="!d" class="crm-card crm-muted">{{ crm.t('common.loading') }}</div>
        <template v-else>
            <section class="crm-card mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                    <div class="crm-person" style="gap:14px">
                        <span class="crm-kpi-icon" style="width:48px;height:48px;font-size:22px"><i class="bx bx-briefcase"></i></span>
                        <div class="min-w-0">
                            <h2 style="font-size:21px;font-weight:750;margin:0" dir="auto">{{ d.name }}</h2>
                            <div class="crm-small crm-muted d-flex gap-3 flex-wrap mt-1">
                                <a v-if="d.company" :href="crm.pageUrl('company', d.company.id)"><i class="bx bx-buildings"></i> {{ d.company.name }}</a>
                                <a v-if="d.contact" :href="d.contact.url"><i class="bx bx-user"></i> {{ d.contact.name }}</a>
                                <span><i class="bx bx-git-branch"></i> {{ data.pipeline.name }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <div class="text-end me-2">
                            <div class="crm-ltr" style="font-size:22px;font-weight:800">{{ crm.money(d.value, d.currency) }}</div>
                            <div class="crm-small crm-muted">{{ d.probability }}% · {{ crm.t('deals.weighted') }} <span class="crm-ltr">{{ crm.money(d.weighted, d.currency) }}</span></div>
                        </div>
                        <template v-if="crm.canWrite && d.status === 'open'">
                            <button type="button" class="crm-btn" style="color:var(--crm-red)" @click="lost = true"><i class="bx bx-x"></i> {{ crm.t('deals.lost') }}</button>
                            <button type="button" class="crm-btn crm-btn-success" @click="won = true"><i class="bx bx-trophy"></i> {{ crm.t('deals.won') }}</button>
                        </template>
                        <span v-else :class="['crm-chip', d.status]">{{ crm.t('deals.status_' + d.status) }}</span>
                        <button v-if="crm.canWrite" type="button" class="crm-btn crm-icon-btn" :aria-label="crm.t('common.edit')" @click="editing = true"><i class="bx bx-edit"></i></button>
                    </div>
                </div>
                <!-- Stage path -->
                <div class="crm-path mt-3" role="list">
                    <button v-for="s in openStages" :key="s.id" type="button" role="listitem" :disabled="!crm.canWrite || d.status !== 'open'"
                        :class="['crm-btn crm-btn-sm', s.id === d.stage_id ? 'crm-btn-primary' : passed(s) ? 'crm-btn-soft' : '']"
                        @click="moveTo(s)"><i v-if="passed(s) && s.id !== d.stage_id" class="bx bx-check"></i> {{ s.name }}</button>
                    <span :class="['crm-btn crm-btn-sm', d.status !== 'open' ? (d.status === 'won' ? 'crm-btn-success' : 'crm-btn-danger') : '']" style="cursor:default">{{ crm.t('deals.won_lost') }}</span>
                </div>
            </section>

            <div class="crm-detail-grid">
                <aside class="d-flex flex-column gap-3">
                    <div class="crm-card">
                        <h3 class="crm-card-title mb-3">{{ crm.t('deals.details') }}</h3>
                        <dl class="crm-dl">
                            <dt>{{ crm.t('common.owner') }}</dt><dd>{{ d.owner ? d.owner.name : crm.t('common.unassigned') }}</dd>
                            <dt>{{ crm.t('deals.close_date') }}</dt><dd>{{ d.expected_close_date ? crm.date(d.expected_close_date) : '—' }}</dd>
                            <dt>{{ crm.t('common.created') }}</dt><dd>{{ crm.date(d.created_at) }}</dd>
                            <dt>{{ crm.t('deals.time_in_stage') }}</dt>
                            <dd :style="d.avg_stage_days !== null && d.stage_days > d.avg_stage_days ? 'color:var(--crm-red)' : ''">{{ crm.t('deals.days_avg', { days: d.stage_days, avg: d.avg_stage_days === null ? '—' : d.avg_stage_days }) }}</dd>
                            <dt>{{ crm.t('common.source') }}</dt><dd>{{ crm.opt('sources', d.source || '') || '—' }}</dd>
                            <dt>{{ crm.t('leads.campaign') }}</dt><dd dir="auto">{{ d.campaign || '—' }}</dd>
                            <template v-if="d.status === 'won' && d.win_reasons.length"><dt>{{ crm.t('deals.why_won') }}</dt><dd>{{ d.win_reasons.map(r => crm.opt('win_reasons', r)).join(', ') }}</dd></template>
                            <template v-if="d.status === 'lost'"><dt>{{ crm.t('deals.lost_reason') }}</dt><dd dir="auto">{{ d.lost_reason || '—' }}</dd><dt>{{ crm.t('deals.competitor') }}</dt><dd dir="auto">{{ d.competitor || '—' }}</dd></template>
                        </dl>
                        <p v-if="d.notes" class="crm-bubble mt-3" dir="auto">{{ d.notes }}</p>
                    </div>
                    <div class="crm-card">
                        <h3 class="crm-card-title mb-2">{{ crm.t('deals.people') }}</h3>
                        <p v-if="!data.people.length" class="crm-small crm-muted mb-0">—</p>
                        <a v-for="p in data.people" :key="p.id" :href="p.url" class="crm-person py-2" style="text-decoration:none">
                            <span :class="['crm-avatar', crm.avatarClass(p.id)]">{{ crm.initials(p.name) }}</span>
                            <span class="min-w-0"><span class="crm-person-name">{{ p.name }}</span><span class="crm-person-sub">{{ p.job_title || '' }}{{ p.primary ? ' · ' + crm.t('deals.primary') : '' }}</span></span>
                        </a>
                    </div>
                </aside>

                <section class="crm-card">
                    <div v-if="crm.canWrite" class="d-flex gap-2 flex-wrap mb-3">
                        <button v-for="t in ['call', 'meeting', 'task', 'note', 'email']" :key="t" type="button" class="crm-btn crm-btn-sm" @click="log = t"><i :class="['bx', icons[t]]"></i> {{ crm.t('deals.log_' + t) }}</button>
                    </div>
                    <crm-log-form v-if="log" :type="log" :contact-id="d.contact ? d.contact.id : null" :deal-id="d.id" :stages="openStages" :closable="true" @close="log = null" @saved="log = null; load()" />
                    <nav class="crm-tabs" role="tablist">
                        <button v-for="tb in ['activity', 'notes', 'tasks', 'history']" :key="tb" type="button" role="tab" :class="['crm-tab', { active: tab === tb }]" @click="tab = tb">{{ crm.t('deals.tab_' + tb) }}</button>
                    </nav>
                    <p v-if="!items.length" class="crm-small crm-muted">{{ crm.t('contacts.no_timeline') }}</p>
                    <ul class="crm-timeline">
                        <li v-for="a in items" :key="a.id">
                            <span :class="['crm-tl-icon', a.type]"><i :class="['bx', icons[a.type] || 'bx-git-commit']"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between gap-2">
                                    <strong class="crm-small">
                                        <input v-if="a.type === 'task' && crm.canWrite" type="checkbox" :checked="!!a.completed_at" class="me-1" :aria-label="a.subject" @change="toggleTask(a)">
                                        {{ a.type === 'event' ? crm.t('events.' + a.subject, { stage: (a.meta && a.meta.stage) || '' }) : (a.subject || crm.t('activity_types.' + a.type)) }}
                                    </strong>
                                    <span class="crm-small crm-muted">{{ crm.ago(a.created_at) }}</span>
                                </div>
                                <div class="crm-small crm-muted">
                                    <span v-if="a.author">{{ crm.t('common.by', { name: a.author.name }) }}</span>
                                    <span v-if="a.outcome"> · {{ crm.opt('outcomes', a.outcome) }}</span><span v-if="a.duration_minutes"> · {{ crm.t('activities.minutes', { n: a.duration_minutes }) }}</span>
                                    <span v-if="a.due_at && !a.completed_at"> · {{ crm.t('activities.due') }} {{ crm.date(a.due_at, true) }}</span>
                                </div>
                                <div v-if="a.body && a.type !== 'event'" class="crm-bubble" dir="auto">{{ a.body }}</div>
                            </div>
                        </li>
                    </ul>
                </section>

                <aside class="d-flex flex-column gap-3">
                    <crm-ai-card v-if="d.contact" :contact-id="d.contact.id" />
                    <div v-if="d.lead_id" class="crm-card">
                        <a :href="crm.pageUrl('lead', d.lead_id)" class="crm-btn crm-btn-soft crm-btn-sm"><i class="bx bx-target-lock"></i> {{ crm.t('deals.open_lead') }}</a>
                    </div>
                </aside>
            </div>
        </template>

        <crm-won-modal v-if="won" :deal="d" @close="won = false" @confirm="finish('won', $event)" />
        <crm-lost-modal v-if="lost" :deal="d" :required="crm.options.requireLostReason" @close="lost = false" @confirm="finish('lost', $event)" />
        <crm-deal-modal v-if="editing" :deal="d" @close="editing = false" @saved="load" />
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';
import CrmAiCard from '../ui/CrmAiCard.vue';
import CrmWonModal from '../ui/CrmWonModal.vue';
import CrmLostModal from '../ui/CrmLostModal.vue';
import CrmDealModal from '../ui/CrmDealModal.vue';
import CrmLogForm from '../ui/CrmLogForm.vue';

export default {
    name: 'CrmDeal',
    components: { CrmHeader, CrmAiCard, CrmWonModal, CrmLostModal, CrmDealModal, CrmLogForm },
    inject: ['crm'],
    props: { dealId: { type: Number, required: true } },
    data: () => ({
        data: null, tab: 'activity', won: false, lost: false, editing: false, log: null,
        icons: { call: 'bx-phone', meeting: 'bx-calendar', task: 'bx-check-square', note: 'bx-note', email: 'bx-envelope', event: 'bx-git-commit' },
    }),
    computed: {
        d() { return this.data && this.data.deal; },
        openStages() { return this.data ? this.data.stages.filter((s) => s.kind === 'open') : []; },
        items() {
            const all = this.data.timeline;
            return {
                activity: all.filter((a) => a.type !== 'event'), notes: all.filter((a) => a.type === 'note'),
                tasks: all.filter((a) => a.type === 'task'), history: all.filter((a) => a.type === 'event'),
            }[this.tab];
        },
    },
    created() { this.load(); },
    methods: {
        async load() { try { this.data = await this.crm.api.get(`deals/${this.dealId}`); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); } },
        passed(s) { const cur = this.data.stages.find((x) => x.id === this.d.stage_id); return cur && this.data.stages.indexOf(s) < this.data.stages.indexOf(cur) || this.d.status === 'won'; },
        async move(stageId, extra = {}) {
            try { await this.crm.api.post(`deals/${this.dealId}/move`, { stage_id: stageId, ...extra }); await this.load(); return true; } catch (e) { this.crm.notify(this.crm.error(e), 'error'); return false; }
        },
        moveTo(s) { if (s.id !== this.d.stage_id) this.move(s.id); },
        async finish(kind, details) {
            const stage = this.data.stages.find((s) => s.kind === kind);
            if (stage && await this.move(stage.id, details)) {
                this.won = false; this.lost = false;
                this.crm.notify(kind === 'won' ? this.crm.t('deals.won_ok') : this.crm.t('common.saved'));
            }
        },
        async toggleTask(a) { await this.crm.api.patch(`activities/${a.id}`, { completed: !a.completed_at }).catch(() => null); this.load(); },
    },
};
</script>
