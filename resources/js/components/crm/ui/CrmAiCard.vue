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
    </div>
</template>

<script>
export default {
    name: 'CrmAiCard',
    inject: ['crm'],
    props: { contactId: { type: Number, required: true } },
    data: () => ({ brief: null, loading: false, copied: false }),
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
        async copy() {
            try { await navigator.clipboard.writeText(this.brief.follow_up); this.copied = true; setTimeout(() => { this.copied = false; }, 2000); } catch (e) { /* clipboard blocked */ }
        },
    },
};
</script>
