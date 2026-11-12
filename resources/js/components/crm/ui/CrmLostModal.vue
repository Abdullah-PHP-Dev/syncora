<template>
    <crm-modal :title="crm.t('deals.mark_lost')" :subtitle="deal.name + ' · ' + crm.money(deal.value, deal.currency)" icon="bx-x" tone="danger" width="520px" :close-label="crm.t('common.close')" @close="$emit('close')">
        <label class="crm-label">{{ crm.t('deals.lost_reason') }}<span v-if="required"> *</span></label>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <button v-for="r in crm.options.lostReasons" :key="r" type="button" :class="['crm-btn crm-btn-sm', reason === crm.opt('lost_reasons', r) ? 'crm-btn-soft' : '']" @click="reason = crm.opt('lost_reasons', r)">{{ crm.opt('lost_reasons', r) }}</button>
        </div>
        <input v-model.trim="reason" class="crm-input mb-3" maxlength="160" :aria-label="crm.t('deals.lost_reason')" :placeholder="crm.t('deals.lost_reason_other')">
        <div class="crm-field"><label class="crm-label" for="lost-c">{{ crm.t('deals.competitor') }}</label><input id="lost-c" v-model.trim="competitor" class="crm-input" maxlength="160" :placeholder="crm.t('common.optional')"></div>
        <div class="crm-field"><label class="crm-label" for="lost-n">{{ crm.t('companies.notes') }}</label><textarea id="lost-n" v-model="notes" class="crm-textarea" dir="auto"></textarea></div>
        <label class="crm-check"><input v-model="reengage" type="checkbox"> {{ crm.t('deals.reengage', { days: 90 }) }}</label>
        <template #footer>
            <button type="button" class="crm-btn" @click="$emit('close')">{{ crm.t('common.cancel') }}</button>
            <button type="button" class="crm-btn crm-btn-danger" :disabled="required && !reason" @click="$emit('confirm', { lost_reason: reason || null, competitor: competitor || null, notes: notes || null, reengage_days: reengage ? 90 : null })">{{ crm.t('deals.mark_lost') }}</button>
        </template>
    </crm-modal>
</template>

<script>
import CrmModal from './CrmModal.vue';

export default {
    name: 'CrmLostModal',
    components: { CrmModal },
    inject: ['crm'],
    props: { deal: { type: Object, required: true }, required: { type: Boolean, default: false } },
    data: () => ({ reason: '', competitor: '', notes: '', reengage: true }),
};
</script>
