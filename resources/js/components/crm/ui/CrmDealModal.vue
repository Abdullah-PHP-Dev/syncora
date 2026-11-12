<template>
    <crm-modal :title="deal ? crm.t('deals.edit') : crm.t('deals.new')" icon="bx-briefcase" :close-label="crm.t('common.close')" @close="$emit('close')">
        <form @submit.prevent="save">
            <div class="crm-field">
                <label class="crm-label" for="dm-name">{{ crm.t('leads.deal_name') }} *</label>
                <input id="dm-name" v-model.trim="form.name" class="crm-input" required maxlength="160">
            </div>
            <div class="crm-row2">
                <div class="crm-field">
                    <label class="crm-label" for="dm-value">{{ crm.t('common.value') }} ({{ form.currency }}) *</label>
                    <input id="dm-value" v-model.number="form.value" class="crm-input" type="number" min="0" step="0.01" dir="ltr" required>
                </div>
                <div class="crm-field">
                    <label class="crm-label" for="dm-close">{{ crm.t('leads.expected_close') }}</label>
                    <input id="dm-close" v-model="form.expected_close_date" class="crm-input" type="date">
                </div>
            </div>
            <div v-if="!deal" class="crm-row2">
                <div class="crm-field">
                    <label class="crm-label" for="dm-pipe">{{ crm.t('leads.pipeline') }}</label>
                    <select id="dm-pipe" v-model="form.pipeline_id" class="crm-select" @change="form.stage_id = openStages[0] && openStages[0].id">
                        <option v-for="p in crm.pipelines" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <div class="crm-field">
                    <label class="crm-label" for="dm-stage">{{ crm.t('leads.stage') }}</label>
                    <select id="dm-stage" v-model="form.stage_id" class="crm-select">
                        <option v-for="s in openStages" :key="s.id" :value="s.id">{{ s.name }} · {{ s.probability }}%</option>
                    </select>
                </div>
            </div>
            <div class="crm-row2">
                <div class="crm-field">
                    <label class="crm-label" for="dm-contact">{{ crm.t('deals.contact') }}</label>
                    <select id="dm-contact" v-model="form.contact_id" class="crm-select">
                        <option :value="null">{{ crm.t('common.none') }}</option>
                        <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="crm-field">
                    <label class="crm-label" for="dm-owner">{{ crm.t('common.owner') }}</label>
                    <select id="dm-owner" v-model="form.owner_id" class="crm-select">
                        <option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                </div>
            </div>
            <div class="crm-row2">
                <div class="crm-field">
                    <label class="crm-label" for="dm-company">{{ crm.t('common.company') }}</label>
                    <select id="dm-company" v-model="form.company_id" class="crm-select">
                        <option :value="null">{{ crm.t('common.none') }}</option>
                        <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="crm-field">
                    <label class="crm-label" for="dm-source">{{ crm.t('common.source') }}</label>
                    <select id="dm-source" v-model="form.source" class="crm-select">
                        <option :value="null">{{ crm.t('common.none') }}</option>
                        <option v-for="s in crm.options.sources" :key="s" :value="s">{{ crm.sourceLabel(s) }}</option>
                    </select>
                </div>
            </div>
            <div class="crm-field mb-0">
                <label class="crm-label" for="dm-notes">{{ crm.t('deals.notes') }}</label>
                <textarea id="dm-notes" v-model="form.notes" class="crm-textarea" rows="3" dir="auto" maxlength="5000"></textarea>
            </div>
        </form>
        <template #footer>
            <button type="button" class="crm-btn" @click="$emit('close')">{{ crm.t('common.cancel') }}</button>
            <button type="button" class="crm-btn crm-btn-primary" :disabled="saving || !form.name || form.value === ''" @click="save"><i class="bx bx-check"></i> {{ deal ? crm.t('common.save') : crm.t('deals.create') }}</button>
        </template>
    </crm-modal>
</template>

<script>
import CrmModal from './CrmModal.vue';

export default {
    name: 'CrmDealModal',
    components: { CrmModal },
    inject: ['crm'],
    props: { defaults: { type: Object, default: () => ({}) }, deal: { type: Object, default: null } },
    data() {
        const pipeline = this.crm.pipelines.find((p) => p.id === this.defaults.pipeline_id) || this.crm.pipelines[0] || { stages: [] };
        const stage = (pipeline.stages || []).find((s) => s.id === this.defaults.stage_id) || (pipeline.stages || []).find((s) => s.kind === 'open');
        return {
            saving: false, contacts: [], companies: [],
            form: this.deal ? {
                name: this.deal.name, value: this.deal.value, currency: this.deal.currency, expected_close_date: this.deal.expected_close_date || '',
                contact_id: this.deal.contact ? this.deal.contact.id : null, company_id: this.deal.company ? this.deal.company.id : null,
                owner_id: this.deal.owner ? this.deal.owner.id : null, source: this.deal.source || null, notes: this.deal.notes || '',
            } : {
                name: '', value: '', currency: this.crm.currency, expected_close_date: '', contact_id: null, company_id: null, source: null, notes: '',
                owner_id: this.crm.actorId, ...this.defaults, pipeline_id: pipeline.id, stage_id: stage && stage.id,
            },
        };
    },
    computed: {
        openStages() { const p = this.crm.pipelines.find((x) => x.id === this.form.pipeline_id); return p ? p.stages.filter((s) => s.kind === 'open') : []; },
    },
    async created() {
        const [res, cos] = await Promise.all([
            this.crm.api.get('contacts', { sort: 'last_activity_at' }).catch(() => ({ data: [] })),
            this.crm.api.get('companies').catch(() => ({ data: [] })),
        ]);
        this.contacts = res.data;
        this.companies = cos.data;
        if (this.deal) {
            if (this.deal.contact && !this.contacts.some((c) => c.id === this.deal.contact.id)) this.contacts.unshift(this.deal.contact);
            if (this.deal.company && !this.companies.some((c) => c.id === this.deal.company.id)) this.companies.unshift(this.deal.company);
        }
        if (this.form.contact_id && !this.contacts.some((c) => c.id === this.form.contact_id) && this.defaults.contact_name) {
            this.contacts.unshift({ id: this.form.contact_id, name: this.defaults.contact_name });
        }
    },
    methods: {
        async save() {
            this.saving = true;
            const payload = { ...this.form, expected_close_date: this.form.expected_close_date || null, notes: this.form.notes || null };
            delete payload.contact_name;
            try {
                const res = this.deal ? await this.crm.api.put(`deals/${this.deal.id}`, payload) : await this.crm.api.post('deals', payload);
                this.crm.notify(this.crm.t('common.saved'));
                this.$emit('saved', res.data);
                this.$emit('close');
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.saving = false;
        },
    },
};
</script>
