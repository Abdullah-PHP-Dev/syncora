<template>
    <div class="crm-ai">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="d-flex gap-2 align-items-center"><span class="crm-ai-icon"><i class="bx bxs-magic-wand"></i></span>{{ crm.t('ai.title') }}</strong>
            <span class="crm-ai-badge">AI</span>
        </div>
        <template v-if="brief">
            <p class="crm-small mb-2" style="line-height:1.6">{{ brief.summary }}
                <span :class="['crm-chip', 'plain', sentimentClass]">{{ crm.t('ai.sentiment_' + brief.sentiment) }}</span>
            </p>
            <div v-if="brief.next_steps && brief.next_steps.length" class="mb-2">
                <div class="crm-small" style="font-weight:700">{{ crm.t('ai.next_steps') }}</div>
                <ul class="crm-small mb-0" style="padding-inline-start:18px">
                    <li v-for="(step, i) in brief.next_steps" :key="i">{{ step }}</li>
                </ul>
            </div>
            <div class="crm-small" style="font-weight:700">{{ crm.t('ai.follow_up') }}</div>
            <div class="crm-bubble" style="background:#fff" dir="auto">{{ brief.follow_up }}</div>
            <div class="d-flex gap-2 mt-2">
                <button type="button" class="crm-btn crm-btn-primary crm-btn-sm" @click="copy"><i class="bx bx-copy"></i> {{ copied ? crm.t('ai.copied') : crm.t('ai.copy') }}</button>
                <button type="button" class="crm-btn crm-btn-sm" :disabled="loading" @click="generate">{{ crm.t('ai.regenerate') }}</button>
            </div>
        </template>
        <template v-else>
            <p class="crm-small crm-muted mb-2">{{ crm.t('ai.hint') }}</p>
            <button type="button" class="crm-btn crm-btn-primary crm-btn-sm" :disabled="loading || !crm.canWrite" @click="generate">
                <i :class="['bx', loading ? 'bx-loader-alt bx-spin' : 'bxs-magic-wand']"></i> {{ crm.t('ai.generate') }}
            </button>
        </template>
        <form v-if="crm.canWrite" class="mt-3 pt-2" style="border-top:1px solid var(--crm-border)" @submit.prevent="ask">
            <label class="crm-small" style="font-weight:700" for="ai-ask">{{ crm.t('ai.ask_title') }}</label>
            <div class="d-flex gap-2 mt-1">
                <input id="ai-ask" v-model.trim="question" class="crm-input" maxlength="500" :placeholder="crm.t('ai.ask_placeholder')" dir="auto">
                <button type="submit" class="crm-btn crm-btn-sm" :disabled="asking || !question" :aria-label="crm.t('ai.ask')"><i :class="['bx', asking ? 'bx-loader-alt bx-spin' : 'bx-send bx-flip-horizontal-rtl']"></i></button>
            </div>
            <div v-if="answer" class="crm-bubble" style="background:#fff" dir="auto">{{ answer }}</div>
        </form>
    </div>
</template>

<script>
export default {
    name: 'CrmAiCard',
    inject: ['crm'],
    props: { contactId: { type: Number, required: true } },
    data: () => ({ brief: null, loading: false, copied: false, question: '', answer: '', asking: false }),
    computed: {
        sentimentClass() { return { positive: 'success', neutral: 'info', negative: 'danger' }[this.brief.sentiment] || 'info'; },
    },
    methods: {
        async generate() {
            this.loading = true;
            try {
                const res = await window.axios.post(this.crm.withId(this.crm.urls.ai, this.contactId));
                this.brief = res.data.data;
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.loading = false;
        },
        async ask() {
            this.asking = true;
            try {
                const res = await window.axios.post(this.crm.withId(this.crm.urls.aiAskContact, this.contactId), { question: this.question });
                this.answer = res.data.data.answer;
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.asking = false;
        },
        async copy() {
            try { await navigator.clipboard.writeText(this.brief.follow_up); this.copied = true; setTimeout(() => { this.copied = false; }, 2000); } catch (e) { /* clipboard blocked */ }
        },
    },
};
</script>
