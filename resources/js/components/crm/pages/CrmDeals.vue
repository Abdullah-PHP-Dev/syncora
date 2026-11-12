<template>
    <div>
        <crm-header :title="crm.t('deals.title')" :crumbs="pipeline ? [{ label: crm.t('nav.deals') }, { label: pipeline.name }] : []">
            <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-primary" @click="dealModal = {}"><i class="bx bx-plus"></i> {{ crm.t('deals.new') }}</button>
        </crm-header>

        <div class="crm-toolbar">
            <select v-model.number="pipelineId" class="crm-select" :aria-label="crm.t('leads.pipeline')" @change="load">
                <option v-for="p in crm.pipelines" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <div class="crm-seg" role="group">
                <button type="button" :class="{ active: view === 'board' }" @click="view = 'board'"><i class="bx bx-columns"></i> {{ crm.t('deals.board') }}</button>
                <button type="button" :class="{ active: view === 'list' }" @click="view = 'list'"><i class="bx bx-list-ul"></i> {{ crm.t('deals.list') }}</button>
            </div>
            <select v-model="owner" class="crm-select" :aria-label="crm.t('common.owner')" @change="load">
                <option value="">{{ crm.t('common.owner') }}: {{ crm.t('common.all') }}</option>
                <option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <span v-if="totals" class="crm-small d-flex gap-3 flex-wrap" style="margin-inline-start:auto">
                <span>{{ crm.t('deals.open_total') }} <strong class="crm-ltr">{{ crm.money(totals.open) }}</strong></span>
                <span>{{ crm.t('deals.weighted') }} <strong class="crm-ltr">{{ crm.money(totals.weighted) }}</strong></span>
                <span>{{ crm.t('deals.won_month') }} <strong class="crm-ltr" style="color:var(--crm-green)">{{ crm.money(totals.won_month) }}</strong></span>
            </span>
        </div>

        <div v-if="loading" class="crm-card crm-muted">{{ crm.t('common.loading') }}</div>
        <div v-else-if="view === 'board'" class="crm-board">
            <section v-for="stage in stages" :key="stage.id" :class="['crm-col', { drop: dropStage === stage.id }]" :style="{ '--col': stage.color || '#94a3b8' }"
                :aria-label="stage.name" @dragover.prevent="dropStage = stage.id" @dragleave="dropStage = dropStage === stage.id ? null : dropStage" @drop.prevent="drop(stage)">
                <div class="crm-col-head">
                    <span>{{ stage.name }} <span class="crm-muted crm-small">{{ byStage(stage.id).length }}</span></span>
                    <span class="crm-small crm-muted crm-ltr">{{ crm.money(sum(stage.id)) }}</span>
                </div>
                <article v-for="d in byStage(stage.id)" :key="d.id" :class="['crm-deal', { dragging: dragId === d.id }]" :draggable="crm.canWrite" @dragstart="dragId = d.id" @dragend="dragId = null; dropStage = null">
                    <div class="d-flex justify-content-between gap-2">
                        <a :href="d.url" class="crm-deal-name" style="font-weight:700;color:inherit" draggable="false">{{ d.name }}</a>
                        <span v-if="crm.canWrite" class="crm-btn crm-btn-ghost crm-btn-sm crm-icon-btn" style="position:relative;height:24px;width:24px;flex:none">
                            <i class="bx bx-dots-horizontal-rounded"></i>
                            <select style="position:absolute;inset:0;opacity:0;cursor:pointer" :aria-label="crm.t('contacts.change_stage')" :value="d.stage_id" @change="moveTo(d, Number($event.target.value))">
                                <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </span>
                    </div>
                    <div v-if="d.contact || d.company" class="crm-small crm-muted mt-1">
                        <a v-if="d.contact" :href="d.contact.url">{{ d.contact.name }}</a><span v-if="d.contact && d.company"> · </span><span v-if="d.company">{{ d.company.name }}</span>
                    </div>
                    <div class="crm-deal-value crm-ltr">{{ crm.money(d.value, d.currency) }}</div>
                    <div class="d-flex justify-content-between align-items-center crm-small crm-muted gap-2">
                        <span>
                            <i class="bx bx-calendar"></i> {{ d.status === 'won' ? crm.date(d.won_at) : (d.expected_close_date ? crm.date(d.expected_close_date) : '—') }}
                            <span v-if="d.idle_days >= 7" class="crm-chip warn plain ms-1"><i class="bx bx-error"></i> {{ crm.t('deals.idle', { days: d.idle_days }) }}</span>
                        </span>
                        <span v-if="d.owner" :class="['crm-avatar sm', crm.avatarClass(d.owner.id)]" :title="d.owner.name">{{ crm.initials(d.owner.name) }}</span>
                    </div>
                </article>
                <button v-if="crm.canWrite && stage.kind === 'open'" type="button" class="crm-btn crm-btn-ghost crm-btn-sm w-100 justify-content-center crm-muted" @click="dealModal = { stage_id: stage.id }"><i class="bx bx-plus"></i> {{ crm.t('deals.add') }}</button>
            </section>
        </div>
        <div v-else class="crm-table-wrap">
            <table class="crm-table">
                <thead><tr>
                    <th>{{ crm.t('deals.deal') }}</th><th>{{ crm.t('deals.contact') }}</th><th>{{ crm.t('deals.stage') }}</th><th>{{ crm.t('common.value') }}</th>
                    <th>{{ crm.t('deals.probability') }}</th><th>{{ crm.t('deals.close_date') }}</th><th>{{ crm.t('common.source') }}</th><th>{{ crm.t('common.owner') }}</th>
                </tr></thead>
                <tbody>
                    <tr v-for="d in deals" :key="d.id">
                        <td><a :href="d.url" style="font-weight:700;color:inherit">{{ d.name }}</a></td>
                        <td><a v-if="d.contact" :href="d.contact.url">{{ d.contact.name }}</a><span v-else>{{ d.company ? d.company.name : '—' }}</span></td>
                        <td><span :class="['crm-chip', d.status === 'open' ? 'qualified' : d.status]">{{ d.stage }}</span></td>
                        <td class="crm-ltr"><strong>{{ crm.money(d.value, d.currency) }}</strong></td>
                        <td>{{ d.probability }}%</td>
                        <td>{{ d.status === 'won' ? crm.date(d.won_at) : (d.expected_close_date ? crm.date(d.expected_close_date) : '—') }}</td>
                        <td>{{ crm.sourceLabel(d.source) }}</td>
                        <td>{{ d.owner ? d.owner.name : '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="crm-pager"><span>{{ deals.length }} · <span class="crm-ltr">{{ crm.money(deals.reduce((s, d) => s + d.value, 0)) }}</span></span></div>
        </div>
        <div v-if="!loading && !deals.length" class="crm-empty">
            <div class="crm-empty-icon"><i class="bx bx-briefcase"></i></div>
            <h4>{{ crm.t('deals.empty_title') }}</h4><p class="crm-muted crm-small">{{ crm.t('deals.empty_body') }}</p>
        </div>

        <crm-deal-modal v-if="dealModal" :defaults="{ pipeline_id: pipelineId, ...dealModal }" @close="dealModal = null" @saved="load" />
        <crm-won-modal v-if="won" :deal="won.deal" @close="won = null" @confirm="finish('won', $event)" />
        <crm-lost-modal v-if="lost" :deal="lost.deal" :required="crm.options.requireLostReason" @close="lost = null" @confirm="finish('lost', $event)" />
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';
import CrmDealModal from '../ui/CrmDealModal.vue';
import CrmWonModal from '../ui/CrmWonModal.vue';
import CrmLostModal from '../ui/CrmLostModal.vue';

export default {
    name: 'CrmDeals',
    components: { CrmHeader, CrmDealModal, CrmWonModal, CrmLostModal },
    inject: ['crm'],
    data() {
        return {
            pipelineId: (this.crm.pipelines.find((p) => p.is_default) || this.crm.pipelines[0] || {}).id,
            pipeline: null, deals: [], totals: null, loading: true, view: 'board', owner: '', dragId: null, dropStage: null, dealModal: null, lost: null, won: null,
        };
    },
    computed: { stages() { return this.pipeline ? this.pipeline.stages : []; } },
    created() { this.load(); },
    methods: {
        async load() {
            this.loading = true;
            try {
                const res = await this.crm.api.get('deals', { pipeline_id: this.pipelineId, owner_id: this.owner });
                Object.assign(this, { pipeline: res.pipeline, deals: res.deals, totals: res.totals });
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.loading = false;
        },
        byStage(id) { return this.deals.filter((d) => d.stage_id === id); },
        sum(id) { return this.byStage(id).reduce((s, d) => s + d.value, 0); },
        drop(stage) {
            const deal = this.deals.find((d) => d.id === this.dragId);
            this.dropStage = null;
            if (deal && deal.stage_id !== stage.id) this.moveTo(deal, stage.id);
        },
        moveTo(deal, stageId) {
            const stage = this.stages.find((s) => s.id === stageId);
            if (!stage || stage.id === deal.stage_id) return;
            if (stage.kind === 'lost') { this.lost = { deal, stage }; return; }
            if (stage.kind === 'won') { this.won = { deal, stage }; return; }
            this.move(deal, stage);
        },
        finish(kind, details) {
            const { deal, stage } = this[kind];
            this[kind] = null;
            this.move(deal, stage, details);
        },
        async move(deal, stage, details = {}, undo = true) {
            const from = deal.stage_id;
            const index = this.deals.indexOf(deal);
            this.$set(this.deals, index, { ...deal, stage_id: stage.id, stage: stage.name, status: stage.kind, probability: stage.probability });
            try {
                const res = await this.crm.api.post(`deals/${deal.id}/move`, { stage_id: stage.id, ...(details || {}) });
                this.$set(this.deals, this.deals.findIndex((d) => d.id === deal.id), res.data);
                const message = stage.kind === 'won' ? this.crm.t('deals.won_ok') : this.crm.t('deals.moved', { name: deal.name, stage: stage.name });
                const fromStage = this.stages.find((s) => s.id === from);
                this.crm.notify(message, 'success', undo && fromStage ? { label: this.crm.t('common.undo'), run: () => this.move(res.data, fromStage, {}, false) } : null);
                this.refreshTotals();
            } catch (e) {
                this.$set(this.deals, index, deal);
                this.crm.notify(this.crm.error(e), 'error');
            }
        },
        async refreshTotals() {
            const res = await this.crm.api.get('deals', { pipeline_id: this.pipelineId, owner_id: this.owner }).catch(() => null);
            if (res) this.totals = res.totals;
        },
    },
};
</script>
