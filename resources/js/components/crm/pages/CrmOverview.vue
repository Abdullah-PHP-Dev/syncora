<template>
    <div>
        <crm-header :title="crm.t('nav.overview')">
            <div class="crm-seg" role="group">
                <button type="button" :class="{ active: scope === 'all' }" @click="setScope('all')">{{ crm.t('common.everyone') }}</button>
                <button type="button" :class="{ active: scope === 'mine' }" @click="setScope('mine')">{{ crm.t('common.mine') }}</button>
            </div>
            <select v-model.number="days" class="crm-select" style="width:auto;height:36px" @change="load">
                <option v-for="d in [7, 30, 90, 365]" :key="d" :value="d">{{ crm.t('overview.period', { days: d }) }}</option>
            </select>
        </crm-header>

        <p class="crm-muted" style="margin:-8px 0 16px"><strong style="color:var(--crm-ink)">{{ crm.t('overview.greeting', { name: firstName }) }}</strong> · {{ crm.t('overview.subtitle') }}</p>

        <div v-if="!data" class="crm-card crm-muted">{{ crm.t('common.loading') }}</div>
        <template v-else>
            <div class="crm-grid crm-kpis">
                <div v-for="k in kpis" :key="k.key" class="crm-card">
                    <div class="crm-kpi-label"><span class="crm-kpi-icon"><i :class="['bx', k.icon]"></i></span>{{ k.label }}</div>
                    <div class="crm-kpi-value crm-ltr">{{ k.value }}</div>
                    <div class="crm-small">
                        <span v-if="k.trend !== null && k.trend !== undefined" :class="['crm-trend', k.trend >= 0 ? 'up' : 'down']">{{ k.trend >= 0 ? '↗' : '↘' }} {{ Math.abs(k.trend) }}%</span>
                        <span class="crm-muted">{{ k.sub }}</span>
                    </div>
                </div>
            </div>

            <div class="crm-grid mt-3" style="grid-template-columns: minmax(0, 2fr) minmax(0, 1fr)">
                <div class="crm-card">
                    <h3 class="crm-card-title">{{ crm.t('overview.sales_performance') }}</h3>
                    <p class="crm-card-sub">{{ crm.t('overview.sales_hint') }} ({{ crm.currency }})</p>
                    <div class="d-flex gap-3 crm-small mb-2">
                        <span><i class="bx bxs-circle" style="color:#6d28d9"></i> {{ crm.t('overview.won_revenue') }}</span>
                        <span><i class="bx bxs-circle" style="color:#2563eb"></i> {{ crm.t('overview.pipeline_added') }}</span>
                    </div>
                    <svg viewBox="0 0 600 220" class="w-100" role="img" :aria-label="crm.t('overview.sales_performance')" style="direction:ltr">
                        <line v-for="i in 4" :key="'g' + i" x1="40" :x2="590" :y1="20 + (i - 1) * 55" :y2="20 + (i - 1) * 55" stroke="#efedf6" />
                        <text v-for="i in 4" :key="'y' + i" x="34" :y="24 + (i - 1) * 55" text-anchor="end" font-size="10" fill="#8a88a3">{{ shortNum(maxPerf * (1 - (i - 1) / 3)) }}</text>
                        <path :d="area('won')" fill="url(#crmWonFill)" />
                        <polyline :points="points('won')" fill="none" stroke="#6d28d9" stroke-width="2.5" stroke-linejoin="round" />
                        <polyline :points="points('added')" fill="none" stroke="#2563eb" stroke-width="2" stroke-dasharray="6 5" />
                        <text v-for="(m, i) in data.performance" :key="'x' + i" :x="x(i)" y="215" text-anchor="middle" font-size="10.5" fill="#8a88a3">{{ m.label }}</text>
                        <defs><linearGradient id="crmWonFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#7c3aed" stop-opacity=".22" /><stop offset="1" stop-color="#7c3aed" stop-opacity="0" /></linearGradient></defs>
                    </svg>
                </div>

                <div class="d-flex flex-column gap-3">
                    <div class="crm-ai">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong class="d-flex gap-2 align-items-center"><span class="crm-ai-icon"><i class="bx bx-bell"></i></span>{{ crm.t('overview.attention') }}</strong>
                        </div>
                        <ul class="list-unstyled mb-0 crm-small" style="line-height:1.9">
                            <li><a :href="crm.pageUrl('leads')"><strong>{{ data.attention.uncontacted_leads }}</strong> {{ crm.t('overview.uncontacted_leads') }}</a></li>
                            <li><a :href="crm.pageUrl('deals')"><strong>{{ data.attention.idle_deals }}</strong> {{ crm.t('overview.idle_deals') }}</a></li>
                            <li><a :href="crm.pageUrl('activities')"><strong>{{ data.attention.overdue_tasks }}</strong> {{ crm.t('overview.overdue_tasks') }}</a></li>
                        </ul>
                    </div>
                    <div class="crm-card">
                        <h3 class="crm-card-title">{{ crm.t('overview.funnel') }}</h3>
                        <p class="crm-card-sub">{{ crm.t('overview.period', { days }) }}</p>
                        <div v-for="f in funnel" :key="f.key" class="mb-2">
                            <div class="d-flex justify-content-between crm-small"><span>{{ f.label }}</span><span><strong>{{ f.value }}</strong> <span class="crm-muted">{{ f.pct }}%</span></span></div>
                            <div class="crm-score-bar" style="width:100%;height:7px"><span :style="{ width: f.pct + '%', background: 'var(--crm-grad)' }"></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="crm-grid mt-3" style="grid-template-columns: repeat(3, minmax(0, 1fr))">
                <div class="crm-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><h3 class="crm-card-title">{{ crm.t('overview.my_tasks') }}</h3></div>
                        <a :href="crm.pageUrl('activities')" class="crm-btn crm-btn-soft crm-btn-sm">{{ crm.t('common.view') }}</a>
                    </div>
                    <p v-if="!data.tasks.length" class="crm-muted crm-small mt-3">{{ crm.t('overview.no_tasks') }}</p>
                    <div v-for="task in data.tasks" :key="task.id" class="d-flex justify-content-between gap-2 py-2" style="border-bottom:1px solid #f3f1f9">
                        <div class="min-w-0">
                            <div style="font-weight:600">{{ task.subject }}</div>
                            <div class="crm-small crm-muted">{{ task.contact ? task.contact.name : '' }} {{ task.due_at ? '· ' + crm.date(task.due_at, true) : '' }}</div>
                        </div>
                        <span v-if="task.overdue" class="crm-chip danger plain">{{ crm.t('common.overdue') }}</span>
                    </div>
                </div>
                <div class="crm-card">
                    <h3 class="crm-card-title mb-2">{{ crm.t('overview.recent_activity') }}</h3>
                    <p v-if="!data.recent.length" class="crm-muted crm-small">{{ crm.t('overview.no_activity') }}</p>
                    <div v-for="a in data.recent" :key="a.id" class="d-flex gap-2 py-2">
                        <span class="crm-tl-icon event" style="width:26px;height:26px"><i :class="['bx', icon(a.type)]"></i></span>
                        <div class="min-w-0 crm-small">
                            <div style="font-weight:600">{{ label(a) }}</div>
                            <div class="crm-muted">{{ a.contact ? a.contact.name : (a.deal ? a.deal.name : '') }} · {{ crm.ago(a.created_at) }}</div>
                        </div>
                    </div>
                </div>
                <div class="crm-card">
                    <h3 class="crm-card-title">{{ crm.t('overview.lead_sources') }}</h3>
                    <p class="crm-card-sub">{{ crm.t('overview.period', { days }) }}</p>
                    <p v-if="!sources.length" class="crm-muted crm-small">{{ crm.t('overview.no_data') }}</p>
                    <div v-else class="d-flex gap-3 align-items-center">
                        <svg viewBox="0 0 42 42" width="120" height="120" role="img" :aria-label="crm.t('overview.lead_sources')">
                            <circle cx="21" cy="21" r="15.9" fill="none" stroke="#f1eff8" stroke-width="6" />
                            <circle v-for="(s, i) in sources" :key="s.key" cx="21" cy="21" r="15.9" fill="none" :stroke="palette[i]" stroke-width="6"
                                :stroke-dasharray="s.pct + ' ' + (100 - s.pct)" :stroke-dashoffset="25 - s.offset" />
                            <text x="21" y="23" text-anchor="middle" font-size="7" font-weight="700" fill="#120f2d">{{ totalSources }}</text>
                        </svg>
                        <ul class="list-unstyled crm-small mb-0 flex-grow-1">
                            <li v-for="(s, i) in sources" :key="s.key" class="d-flex justify-content-between py-1"><span><i class="bx bxs-circle" :style="{ color: palette[i] }"></i> {{ crm.sourceLabel(s.key) }}</span><strong>{{ s.pct }}%</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';

export default {
    name: 'CrmOverview',
    components: { CrmHeader },
    inject: ['crm'],
    data: () => ({ data: null, days: 30, scope: 'all', palette: ['#7c3aed', '#16a34a', '#2563eb', '#db2777', '#d97706', '#94a3b8'] }),
    computed: {
        firstName() { return (this.crm.userName || '').split(' ')[0]; },
        kpis() {
            const k = this.data.kpis;
            return [
                { key: 'contacts', icon: 'bx-group', label: this.crm.t('overview.kpi_contacts'), value: this.crm.number(k.contacts.total), trend: k.contacts.trend, sub: this.crm.t('overview.vs_previous') },
                { key: 'leads', icon: 'bx-target-lock', label: this.crm.t('overview.kpi_new_leads'), value: this.crm.number(k.leads.new), trend: k.leads.trend, sub: this.crm.t('overview.vs_previous') },
                { key: 'deals', icon: 'bx-briefcase', label: this.crm.t('overview.kpi_open_deals'), value: this.crm.number(k.deals.open), trend: null, sub: this.crm.money(k.deals.value) },
                { key: 'won', icon: 'bx-trophy', label: this.crm.t('overview.kpi_won_month'), value: this.crm.money(k.won.value), trend: null, sub: k.won.conversion !== null ? this.crm.t('overview.conversion', { value: k.won.conversion }) : '' },
            ];
        },
        funnel() {
            const f = this.data.funnel; const base = f.leads || 1;
            return [['funnel_leads', f.leads], ['qualified', f.qualified], ['deals', f.deals], ['won', f.won]]
                .map(([key, value]) => ({ key, value, label: this.crm.t('overview.' + key), pct: f.leads ? Math.round((value / base) * 100) : 0 }));
        },
        totalSources() { return Object.values(this.data.sources || {}).reduce((a, b) => a + Number(b), 0); },
        sources() {
            let offset = 0;
            return Object.entries(this.data.sources || {}).map(([key, total]) => {
                const pct = Math.round((Number(total) / (this.totalSources || 1)) * 100);
                const row = { key, pct, offset }; offset += pct; return row;
            });
        },
        maxPerf() { return Math.max(1, ...this.data.performance.flatMap((m) => [m.won, m.added])); },
    },
    created() { this.load(); },
    methods: {
        async load() {
            try { this.data = await this.crm.api.get('overview', { days: this.days, scope: this.scope }); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        setScope(scope) { this.scope = scope; this.load(); },
        x(i) { return 60 + i * (520 / Math.max(1, this.data.performance.length - 1)); },
        y(v) { return 185 - (v / this.maxPerf) * 165; },
        points(key) { return this.data.performance.map((m, i) => `${this.x(i)},${this.y(m[key])}`).join(' '); },
        area(key) { const p = this.data.performance; return `M${this.x(0)},185 ` + p.map((m, i) => `L${this.x(i)},${this.y(m[key])}`).join(' ') + ` L${this.x(p.length - 1)},185 Z`; },
        shortNum(n) { return n >= 1e6 ? (n / 1e6).toFixed(1) + 'M' : n >= 1e3 ? Math.round(n / 1e3) + 'K' : Math.round(n); },
        icon(type) { return { task: 'bx-check-square', call: 'bx-phone', meeting: 'bx-calendar', note: 'bx-note', email: 'bx-envelope', event: 'bx-git-commit' }[type] || 'bx-circle'; },
        label(a) { return a.type === 'event' ? this.crm.t('events.' + a.subject, { stage: (a.meta && a.meta.stage) || '' }) : (a.subject || this.crm.t('activity_types.' + a.type)); },
    },
};
</script>
