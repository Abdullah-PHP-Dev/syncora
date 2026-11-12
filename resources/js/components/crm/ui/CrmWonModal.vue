<template>
    <crm-modal :title="crm.t('deals.won_title')" icon="bx-trophy" width="520px" :close-label="crm.t('common.close')" @close="$emit('close')">
        <div class="text-center mb-3">
            <div class="crm-ltr" style="font-size:28px;font-weight:800">{{ crm.money(form.value, deal.currency) }}</div>
            <div class="crm-small crm-muted">{{ deal.name }}<span v-if="deal.contact"> · {{ deal.contact.name }}</span></div>
        </div>
        <div class="crm-row2">
            <div class="crm-field"><label class="crm-label" for="won-v">{{ crm.t('deals.final_amount') }}</label><input id="won-v" v-model.number="form.value" type="number" min="0" class="crm-input" dir="ltr"></div>
            <div class="crm-field"><label class="crm-label" for="won-d">{{ crm.t('deals.won_date') }}</label><input id="won-d" v-model="form.won_at" type="date" class="crm-input"></div>
        </div>
        <label class="crm-label">{{ crm.t('deals.why_won') }}</label>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <button v-for="r in crm.options.winReasons" :key="r" type="button" :class="['crm-btn crm-btn-sm', form.win_reasons.includes(r) ? 'crm-btn-soft' : '']" :aria-pressed="form.win_reasons.includes(r)" @click="toggle(r)">{{ crm.opt('win_reasons', r) }}</button>
        </div>
        <label v-if="deal.contact" class="crm-check mb-2"><input v-model="form.mark_customer" type="checkbox"> {{ crm.t('deals.mark_customer', { name: deal.contact.name }) }}</label>
        <label class="crm-check mb-2"><input v-model="onboarding" type="checkbox"> {{ crm.t('deals.onboarding_task') }}</label>
        <input v-if="onboarding" v-model.trim="form.onboarding_task" class="crm-input mb-2" :aria-label="crm.t('deals.onboarding_task')">
        <p class="crm-hint mb-0"><i class="bx bx-line-chart"></i> {{ crm.t('deals.won_roas_hint') }}</p>
        <template #footer>
            <button type="button" class="crm-btn" @click="$emit('close')">{{ crm.t('common.cancel') }}</button>
            <button type="button" class="crm-btn crm-btn-success" @click="confirm"><i class="bx bx-trophy"></i> {{ crm.t('deals.confirm_win') }}</button>
        </template>
    </crm-modal>
</template>

<script>
import CrmModal from './CrmModal.vue';

export default {
    name: 'CrmWonModal',
    components: { CrmModal },
    inject: ['crm'],
    props: { deal: { type: Object, required: true } },
    data() {
        return {
            onboarding: true,
            form: { value: this.deal.value, won_at: new Date().toISOString().slice(0, 10), win_reasons: [], mark_customer: true, onboarding_task: this.crm.t('deals.onboarding_default') },
        };
    },
    methods: {
        toggle(r) { const i = this.form.win_reasons.indexOf(r); if (i >= 0) this.form.win_reasons.splice(i, 1); else this.form.win_reasons.push(r); },
        confirm() { this.$emit('confirm', { ...this.form, onboarding_task: this.onboarding ? this.form.onboarding_task : null }); },
    },
};
</script>
