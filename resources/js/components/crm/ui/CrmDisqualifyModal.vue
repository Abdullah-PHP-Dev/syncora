<template>
    <crm-modal :title="crm.t('leads.disqualify_title')" :subtitle="lead.contact.name" icon="bx-x" tone="danger" width="480px" :close-label="crm.t('common.close')" @close="$emit('close')">
        <label class="crm-label">{{ crm.t('leads.reason') }}</label>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <button v-for="r in crm.options.disqualifyReasons" :key="r" type="button" :class="['crm-btn crm-btn-sm', reason === r ? 'crm-btn-soft' : '']" :aria-pressed="reason === r" @click="reason = r">{{ crm.opt('reasons', r) }}</button>
        </div>
        <label class="crm-label" for="dq-note">{{ crm.t('leads.note') }}</label>
        <textarea id="dq-note" v-model="note" class="crm-textarea" dir="auto"></textarea>
        <template #footer>
            <button type="button" class="crm-btn" @click="$emit('close')">{{ crm.t('common.cancel') }}</button>
            <button type="button" class="crm-btn crm-btn-danger" :disabled="busy || !reason" @click="save">{{ crm.t('leads.disqualify') }}</button>
        </template>
    </crm-modal>
</template>

<script>
import CrmModal from './CrmModal.vue';

/** L-06 disqualify with one of the workspace's configured reasons. */
export default {
    name: 'CrmDisqualifyModal',
    components: { CrmModal },
    inject: ['crm'],
    props: { lead: { type: Object, required: true } },
    emits: ['close', 'done'],
    data() { return { busy: false, note: '', reason: (this.crm.options.disqualifyReasons || [])[0] || '' }; },
    methods: {
        async save() {
            this.busy = true;
            try {
                const res = await this.crm.api.post(`leads/${this.lead.id}/disqualify`, { reason: this.reason, note: this.note });
                this.crm.notify(this.crm.t('common.saved'));
                this.$emit('done', res.data);
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.busy = false;
        },
    },
};
</script>
