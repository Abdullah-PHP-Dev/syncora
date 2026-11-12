<template>
    <div>
        <crm-header :title="crm.t('insights.title')" />
        <div v-if="!data" class="crm-card crm-muted">{{ crm.t('common.loading') }}</div>
        <template v-else>
            <div class="crm-grid crm-kpis mb-3">
                <div class="crm-card crm-kpi"><div class="crm-small crm-muted">{{ crm.t('insights.leads_30') }}</div><div class="crm-kpi-value">{{ crm.number(f.leads.total) }}</div><div class="crm-small crm-muted">{{ crm.t('insights.hot_open', { n: f.leads.hot_open }) }}</div></div>
                <div class="crm-card crm-kpi"><div class="crm-small crm-muted">{{ crm.t('insights.open_pipeline') }}</div><div class="crm-kpi-value crm-ltr">{{ crm.money(f.pipeline.open_value) }}</div><div class="crm-small crm-muted">{{ crm.t('insights.open_deals', { n: f.pipeline.open_deals }) }}</div></div>
                <div class="crm-card crm-kpi"><div class="crm-small crm-muted">{{ crm.t('insights.won_30') }}</div><div class="crm-kpi-value crm-ltr">{{ crm.money(f.results.won_value) }}</div><div class="crm-small crm-muted">{{ crm.t('insights.win_rate', { n: f.results.win_rate === null ? '—' : f.results.win_rate + '%' }) }}</div></div>
                <div class="crm-card crm-kpi"><div class="crm-small crm-muted">{{ crm.t('insights.first_response') }}</div><div class="crm-kpi-value">{{ f.leads.avg_first_response_minutes === null ? '—' : crm.t('leads.minutes_short', { n: f.leads.avg_first_response_minutes }) }}</div><div class="crm-small crm-muted">{{ crm.t('insights.overdue', { n: f.tasks.overdue }) }}</div></div>
            </div>

            <div class="crm-grid" style="grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);align-items:start">
                <section class="crm-card">
                    <h3 class="crm-card-title mb-3"><i class="bx bx-bulb"></i> {{ crm.t('insights.findings') }}</h3>
                    <p v-if="!data.cards.length" class="crm-small crm-muted mb-0">{{ crm.t('insights.all_good') }}</p>
                    <a v-for="(c, i) in data.cards" :key="i" :href="c.url" :class="['crm-alert', c.tone, 'mb-2']" style="text-decoration:none;display:flex">
                        <i :class="['bx', icons[c.key] || 'bx-info-circle']"></i>
                        <span>{{ crm.t('insights.card_' + c.key, cardParams(c)) }}</span>
                    </a>
                    <div v-if="f.pipeline.top_idle.length" class="mt-3">
                        <div class="crm-small crm-muted mb-1" style="font-weight:700">{{ crm.t('insights.top_idle') }}</div>
                        <div v-for="d in f.pipeline.top_idle" :key="d.name" class="d-flex justify-content-between crm-small py-1" style="border-bottom:1px solid var(--crm-border)">
                            <span dir="auto">{{ d.name }}</span><span class="crm-muted"><span class="crm-ltr">{{ crm.money(d.value) }}</span> · {{ crm.t('insights.days', { n: d.days }) }}</span>
                        </div>
                    </div>
                </section>

                <section class="crm-ai">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="d-flex gap-2 align-items-center"><span class="crm-ai-icon"><i class="bx bxs-magic-wand"></i></span>{{ crm.t('insights.ask_title') }}</strong>
                        <span class="crm-ai-badge">AI</span>
                    </div>
                    <p v-if="!data.ai" class="crm-small crm-muted mb-0">{{ crm.t('ai.disabled') }}</p>
                    <template v-else>
                        <p class="crm-small crm-muted">{{ crm.t('insights.ask_hint') }}</p>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <button v-for="s in samples" :key="s" type="button" class="crm-btn crm-btn-sm" @click="question = s; ask()">{{ s }}</button>
                        </div>
                        <form class="d-flex gap-2" @submit.prevent="ask">
                            <input v-model.trim="question" class="crm-input" maxlength="500" :placeholder="crm.t('insights.ask_placeholder')" :aria-label="crm.t('insights.ask_title')" dir="auto">
                            <button type="submit" class="crm-btn crm-btn-primary crm-btn-sm" :disabled="asking || !question || !crm.canWrite"><i :class="['bx', asking ? 'bx-loader-alt bx-spin' : 'bx-send bx-flip-horizontal-rtl']"></i></button>
                        </form>
                        <template v-if="answer">
                            <div class="crm-bubble mt-2" style="background:#fff" dir="auto">{{ answer.answer }}</div>
                            <ul v-if="answer.suggestions && answer.suggestions.length" class="crm-small mt-2 mb-0" style="padding-inline-start:18px">
                                <li v-for="(s, i) in answer.suggestions" :key="i" dir="auto">{{ s }}</li>
                            </ul>
                        </template>
                        <p class="crm-hint mt-2 mb-0"><i class="bx bx-lock-alt"></i> {{ crm.t('insights.ask_privacy') }}</p>
                    </template>
                </section>
            </div>
        </template>
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';

/** AI-01: findings from the workspace's own numbers (free) and "Ask CRM AI" (one AI credit per question). */
export default {
    name: 'CrmInsights',
    components: { CrmHeader },
    inject: ['crm'],
    data: () => ({
        data: null, question: '', answer: null, asking: false,
        icons: { hot_no_reply: 'bx-error-circle', unassigned: 'bx-user-x', idle_deals: 'bx-time-five', overdue: 'bx-alarm-exclamation', slow_reply: 'bx-timer', best_source: 'bx-trophy', lost_reason: 'bx-trending-down' },
    }),
    computed: {
        f() { return this.data.facts; },
        samples() { return [this.crm.t('insights.sample_1'), this.crm.t('insights.sample_2'), this.crm.t('insights.sample_3')]; },
    },
    async created() {
        try { this.data = await this.crm.api.get('insights'); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
    },
    methods: {
        cardParams(c) {
            const p = { ...c.params };
            if (p.source) p.source = this.crm.sourceLabel(p.source);
            return p;
        },
        async ask() {
            if (!this.question) return;
            this.asking = true;
            try { this.answer = (await window.axios.post(this.crm.urls.aiAsk, { question: this.question })).data.data; } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.asking = false;
        },
    },
};
</script>
