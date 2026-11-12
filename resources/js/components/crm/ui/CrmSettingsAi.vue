<template>
    <section class="crm-card">
        <h3 class="crm-card-title">{{ crm.t('settings.section_ai') }}</h3>
        <p class="crm-card-sub">{{ crm.t('settings.ai_hint') }}</p>
        <label v-for="key in keys" :key="key" class="crm-check-row" style="cursor:pointer">
            <input v-model="ai[key]" type="checkbox">
            <span><strong class="crm-small d-block">{{ crm.t('settings.ai_' + key) }}</strong><span class="crm-small crm-muted">{{ crm.t('settings.ai_' + key + '_hint') }}</span></span>
        </label>
        <div class="d-flex justify-content-end mt-3">
            <button type="button" class="crm-btn crm-btn-primary" :disabled="busy" @click="save"><i class="bx bx-check"></i> {{ crm.t('common.save') }}</button>
        </div>
    </section>
</template>

<script>
/** S-11: switch each CRM AI feature on or off for the workspace. */
export default {
    name: 'CrmSettingsAi',
    inject: ['crm'],
    props: { settings: { type: Object, required: true } },
    emits: ['saved'],
    data() {
        const keys = ['brief', 'ask', 'qualification', 'intent_signal'];
        const ai = {};
        keys.forEach((k) => { ai[k] = (this.settings.ai || {})[k] !== false; });
        return { keys, ai, busy: false };
    },
    methods: {
        async save() {
            this.busy = true;
            try { await this.crm.api.put('settings', { ai: this.ai }); this.crm.notify(this.crm.t('common.saved')); this.$emit('saved'); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.busy = false;
        },
    },
};
</script>
