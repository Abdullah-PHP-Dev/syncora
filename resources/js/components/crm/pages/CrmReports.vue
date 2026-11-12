<template>
    <div>
        <crm-header :title="crm.t('reports.title')">
            <a v-if="tab === 'sales'" class="crm-btn" :href="crm.urls.reportsExport + '?months=' + months"><i class="bx bx-export"></i> {{ crm.t('common.export') }}</a>
        </crm-header>
        <nav class="crm-tabs" role="tablist">
            <button v-for="tb in ['sales', 'sources']" :key="tb" type="button" role="tab" :class="['crm-tab', { active: tab === tb }]" @click="tab = tb; load()">{{ crm.t('reports.tab_' + tb) }}</button>
        </nav>

        <template v-if="tab === 'sales'">
            <div class="crm-toolbar">
                <div class="crm-seg"><button v-for="m in [3, 6, 12]" :key="m" type="button" :class="{ active: months === m }" @click="months = m; load()">{{ crm.t('reports.months', { n: m }) }}</button></div>
                <select v-model.number="pipelineId" class="crm-select" :aria-label="crm.t('leads.pipeline')" @change="load"><option v-for="p in crm.pipelines" :key="p.id" :value="p.id">{{ p.name }}</option></select>
            </div>
            <div v-if="!sales" class="crm-card crm-muted">{{ crm.t('common.loading') }}</div>
            <template v-else>
                <div class="crm-grid crm-kpis">
                    <div v-for="k in salesKpis" :key="k.key" class="crm-card">
                        <div class="crm-kpi-label"><span class="crm-kpi-icon"><i :class="['bx', k.icon]"></i></span>{{ k.label }}</div>
                        <div class="crm-kpi-value crm-ltr">{{ k.value }}</div>
                        <div class="crm-small"><span v-if="k.trend !== null && k.trend !== undefined" :class="['crm-trend', k.good ? 'up' : 'down']">{{ k.trend >= 0 ? '↗' : '↘' }} {{ Math.abs(k.trend) }}{{ k.unit || '%' }}</span> <span class="crm-muted">{{ k.sub }}</span></div>
                    </div>
                </div>
                <div class="crm-grid mt-3" style="grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr)">
                    <div class="crm-card">
                        <h3 class="crm-card-title">{{ crm.t('reports.revenue_by_month') }}</h3><p class="crm-card-sub">{{ crm.currency }}</p>
                        <div class="d-flex align-items-end gap-2" style="height:200px;direction:ltr">
                            <div v-for="(m, i) in sales.by_month" :key="i" class="flex-grow-1 d-flex flex-column align-items-center justify-content-end" style="height:100%">
                                <span class="crm-small crm-muted" style="font-size:10px">{{ m.won ? crm.money(m.won).replace(crm.currency + ' ', '') : '' }}</span>
                                <div :style="{ width: '70%', maxWidth: '36px', height: Math.max(2, (m.won / maxMonth) * 160) + 'px', background: 'var(--crm-grad)', borderRadius: '6px 6px 2px 2px' }" :title="crm.money(m.won)"></div>
                                <span class="crm-small crm-muted mt-1">{{ m.label }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="crm-card">
                        <h3 class="crm-card-title mb-2">{{ crm.t('reports.pipeline_by_stage') }} · {{ sales.pipeline.name }}</h3>
                        <table class="crm-table" style="min-width:0">
                            <thead><tr><th>{{ crm.t('deals.stage') }}</th><th>{{ crm.t('nav.deals') }}</th><th>{{ crm.t('common.value') }}</th><th :title="crm.t('reports.conversion_hint')">{{ crm.t('reports.conversion') }}</th></tr></thead>
                            <tbody><tr v-for="s in sales.stages" :key="s.id">
                                <td><span class="crm-chip" :style="{ color: s.color }">{{ s.name }}</span></td><td>{{ s.deals }}</td><td class="crm-ltr">{{ crm.money(s.value) }}</td><td>{{ s.conversion === null ? '—' : s.conversion + '%' }}</td>
                            </tr></tbody>
                        </table>
                    </div>
                </div>
                <div class="crm-grid mt-3" style="grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr)">
                    <div class="crm-table-wrap">
                        <div class="crm-card-title" style="padding:16px 16px 0">{{ crm.t('reports.by_owner') }}</div>
                        <table class="crm-table" style="min-width:0">
                            <thead><tr><th>{{ crm.t('common.owner') }}</th><th>{{ crm.t('reports.won') }}</th><th>{{ crm.t('reports.revenue') }}</th><th>{{ crm.t('reports.win_rate') }}</th><th>{{ crm.t('reports.cycle') }}</th></tr></thead>
                            <tbody>
                                <tr v-if="!sales.owners.length"><td colspan="5" class="crm-muted crm-small">{{ crm.t('overview.no_data') }}</td></tr>
                                <tr v-for="o in sales.owners" :key="o.member.id">
                                    <td><span class="crm-person"><span :class="['crm-avatar sm', crm.avatarClass(o.member.id)]">{{ crm.initials(o.member.name) }}</span><strong>{{ o.member.name }}</strong></span></td>
                                    <td>{{ o.won }}</td><td class="crm-ltr">{{ crm.money(o.revenue) }}</td><td>{{ o.win_rate === null ? '—' : o.win_rate + '%' }}</td><td>{{ o.cycle === null ? '—' : crm.t('reports.days', { n: o.cycle }) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="crm-card">
                        <h3 class="crm-card-title mb-2">{{ crm.t('reports.lost_reasons') }}</h3>
                        <p v-if="!sales.lost_total" class="crm-muted crm-small">{{ crm.t('overview.no_data') }}</p>
                        <div v-for="(n, reason) in sales.lost_reasons" :key="reason" class="mb-2">
                            <div class="d-flex justify-content-between crm-small"><span dir="auto">{{ reason === '-' ? crm.t('reports.no_reason') : reason }}</span><strong>{{ Math.round(n / sales.lost_total * 100) }}%</strong></div>
                            <div class="crm-score-bar" style="width:100%;height:6px"><span :style="{ width: (n / sales.lost_total * 100) + '%', background: '#dc2626' }"></span></div>
                        </div>
                    </div>
                </div>
            </template>
        </template>

        <template v-else>
            <div class="crm-toolbar">
                <div class="crm-seg"><button v-for="d in [30, 90, 180, 365]" :key="d" type="button" :class="{ active: days === d }" @click="days = d; spend = null; load()">{{ crm.t('overview.period', { days: d }) }}</button></div>
                <button type="button" class="crm-btn crm-btn-soft crm-btn-sm" :disabled="spendLoading" style="margin-inline-start:auto" @click="loadSpend">
                    <i :class="['bx', spendLoading ? 'bx-loader-alt bx-spin' : 'bx-dollar-circle']"></i> {{ spend ? crm.t('reports.refresh_spend') : crm.t('reports.load_spend') }}
                </button>
            </div>
            <div v-if="!sources" class="crm-card crm-muted">{{ crm.t('common.loading') }}</div>
            <template v-else>
                <div class="crm-grid crm-kpis">
                    <div class="crm-card"><div class="crm-kpi-label"><span class="crm-kpi-icon"><i class="bx bx-target-lock"></i></span>{{ crm.t('reports.leads') }}</div><div class="crm-kpi-value">{{ crm.number(sources.totals.leads) }}</div></div>
                    <div class="crm-card"><div class="crm-kpi-label"><span class="crm-kpi-icon"><i class="bx bx-check-shield"></i></span>{{ crm.t('reports.qualified_rate') }}</div><div class="crm-kpi-value">{{ sources.totals.qualified }}%</div></div>
                    <div class="crm-card"><div class="crm-kpi-label"><span class="crm-kpi-icon"><i class="bx bx-trophy"></i></span>{{ crm.t('reports.revenue_from_leads') }}</div><div class="crm-kpi-value crm-ltr">{{ crm.money(sources.totals.revenue) }}</div></div>
                    <div class="crm-card"><div class="crm-kpi-label"><span class="crm-kpi-icon"><i class="bx bx-trending-up"></i></span>{{ crm.t('reports.roas') }}</div><div class="crm-kpi-value crm-ltr">{{ totalRoas }}</div><div class="crm-small crm-muted">{{ crm.t('reports.roas_hint') }}</div></div>
                </div>

                <div v-if="spend" class="crm-table-wrap mt-3">
                    <div class="crm-card-title" style="padding:16px 16px 0">{{ crm.t('reports.spend_by_platform') }}</div>
                    <table class="crm-table" style="min-width:0">
                        <thead><tr><th>{{ crm.t('reports.platform') }}</th><th>{{ crm.t('reports.spend') }}</th><th>{{ crm.t('reports.leads') }}</th><th>{{ crm.t('reports.cost_per_lead') }}</th><th>{{ crm.t('reports.revenue') }}</th><th>{{ crm.t('reports.roas') }}</th></tr></thead>
                        <tbody>
                            <tr v-if="!spend.length"><td colspan="6" class="crm-muted crm-small">{{ crm.t('reports.no_spend') }}</td></tr>
                            <tr v-for="r in spend" :key="r.platform">
                                <td><strong>{{ crm.t('reports.platform_' + r.platform) }}</strong></td>
                                <td class="crm-ltr">{{ r.spend === null ? crm.t('reports.no_reporting') : crm.money(r.spend) }}</td><td>{{ r.leads }}</td>
                                <td class="crm-ltr">{{ r.cost_per_lead === null ? '—' : crm.money(r.cost_per_lead) }}</td><td class="crm-ltr">{{ crm.money(r.revenue) }}</td>
                                <td><strong :style="{ color: r.roas >= 1 ? 'var(--crm-green)' : 'var(--crm-amber)' }">{{ r.roas === null ? '—' : r.roas + '×' }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="crm-grid mt-3" style="grid-template-columns: minmax(0, 1fr) minmax(0, 1fr)">
                    <div class="crm-table-wrap">
                        <div class="crm-card-title" style="padding:16px 16px 0">{{ crm.t('reports.by_source') }}</div>
                        <table class="crm-table" style="min-width:0">
                            <thead><tr><th>{{ crm.t('common.source') }}</th><th>{{ crm.t('reports.leads') }}</th><th>{{ crm.t('reports.qualified') }}</th><th>{{ crm.t('reports.won') }}</th><th>{{ crm.t('reports.revenue') }}</th></tr></thead>
                            <tbody>
                                <tr v-if="!Object.keys(sources.by_source).length"><td colspan="5" class="crm-muted crm-small">{{ crm.t('overview.no_data') }}</td></tr>
                                <tr v-for="(r, s) in sources.by_source" :key="s"><td>{{ crm.sourceLabel(s) }}</td><td>{{ r.leads }}</td><td>{{ r.qualified }}%</td><td>{{ r.won }}</td><td class="crm-ltr">{{ crm.money(r.revenue) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="crm-table-wrap">
                        <div class="crm-card-title" style="padding:16px 16px 0">{{ crm.t('reports.by_campaign') }}</div>
                        <table class="crm-table" style="min-width:0">
                            <thead><tr><th>{{ crm.t('leads.campaign') }}</th><th>{{ crm.t('reports.leads') }}</th><th>{{ crm.t('reports.qualified') }}</th><th>{{ crm.t('reports.won') }}</th><th>{{ crm.t('reports.revenue') }}</th></tr></thead>
                            <tbody>
                                <tr v-if="!sources.by_campaign.length"><td colspan="5" class="crm-muted crm-small">{{ crm.t('reports.no_campaigns') }}</td></tr>
                                <tr v-for="r in sources.by_campaign" :key="r.campaign"><td dir="auto"><strong>{{ r.campaign }}</strong><div class="crm-small crm-muted">{{ crm.sourceLabel(r.source) }}</div></td><td>{{ r.leads }}</td><td>{{ r.qualified }}%</td><td>{{ r.won }}</td><td class="crm-ltr">{{ crm.money(r.revenue) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="crm-alert info mt-3"><i class="bx bx-info-circle"></i> {{ crm.t('reports.roi_note') }}</div>
            </template>
        </template>
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';

export default {
    name: 'CrmReports',
    components: { CrmHeader },
    inject: ['crm'],
    data() {
        return { tab: 'sales', months: 6, days: 90, pipelineId: (this.crm.pipelines.find((p) => p.is_default) || this.crm.pipelines[0] || {}).id, sales: null, sources: null, spend: null, spendLoading: false };
    },
    computed: {
        maxMonth() { return Math.max(1, ...this.sales.by_month.map((m) => m.won)); },
        salesKpis() {
            const k = this.sales.kpis;
            const cycleDelta = k.cycle.value !== null && k.cycle.previous !== null ? k.cycle.value - k.cycle.previous : null;
            return [
                { key: 'revenue', icon: 'bx-trophy', label: this.crm.t('reports.revenue_won'), value: this.crm.money(k.revenue.value), trend: k.revenue.trend, good: k.revenue.trend >= 0, sub: this.crm.t('overview.vs_previous') },
                { key: 'won', icon: 'bx-check-circle', label: this.crm.t('reports.deals_won'), value: this.crm.number(k.won.value), trend: k.won.trend, good: k.won.trend >= 0, sub: this.crm.t('overview.vs_previous') },
                { key: 'avg', icon: 'bx-receipt', label: this.crm.t('reports.avg_deal'), value: this.crm.money(k.avg_deal.value), trend: k.avg_deal.trend, good: k.avg_deal.trend >= 0, sub: this.crm.t('overview.vs_previous') },
                { key: 'cycle', icon: 'bx-time-five', label: this.crm.t('reports.avg_cycle'), value: k.cycle.value === null ? '—' : this.crm.t('reports.days', { n: k.cycle.value }), trend: cycleDelta, unit: ' ' + this.crm.t('reports.d'), good: cycleDelta <= 0, sub: this.crm.t('overview.vs_previous') },
            ];
        },
        totalRoas() {
            if (!this.spend) return '—';
            const spend = this.spend.reduce((s, r) => s + (r.spend || 0), 0);
            const revenue = this.spend.reduce((s, r) => s + (r.spend !== null ? r.revenue : 0), 0);
            return spend ? (revenue / spend).toFixed(1) + '×' : '—';
        },
    },
    created() { this.load(); },
    methods: {
        async load() {
            try {
                if (this.tab === 'sales') this.sales = await this.crm.api.get('reports/sales', { months: this.months, pipeline_id: this.pipelineId });
                else this.sources = await this.crm.api.get('reports/sources', { days: this.days });
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        async loadSpend() {
            this.spendLoading = true;
            try { this.spend = (await this.crm.api.get('reports/spend', { days: this.days })).data; } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.spendLoading = false;
        },
    },
};
</script>
