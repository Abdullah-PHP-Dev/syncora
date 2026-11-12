<template>
    <crm-modal :title="crm.t('leads.convert_title')" :subtitle="lead.contact.name" icon="bx-check-circle" :close-label="crm.t('common.close')" @close="$emit('close')">
        <div class="crm-alert info mb-3"><i class="bx bx-user"></i> {{ crm.t('leads.keep_person', { name: lead.contact.name }) }}</div>
        <div class="crm-field"><label class="crm-label" for="cv-co">{{ crm.t('leads.company_optional') }}</label><input id="cv-co" v-model.trim="f.company_name" class="crm-input"></div>
        <label class="crm-check mb-2"><input v-model="f.create_deal" type="checkbox"> <strong>{{ crm.t('leads.create_deal') }}</strong></label>
        <div v-if="f.create_deal" class="crm-card mb-3" style="padding:14px">
            <div class="crm-row2">
                <div class="crm-field"><label class="crm-label" for="cv-dn">{{ crm.t('leads.deal_name') }}</label><input id="cv-dn" v-model.trim="f.deal_name" class="crm-input"></div>
                <div class="crm-field"><label class="crm-label" for="cv-dv">{{ crm.t('common.value') }} ({{ crm.currency }})</label><input id="cv-dv" v-model.number="f.value" type="number" min="0" class="crm-input" dir="ltr"></div>
            </div>
            <div class="crm-row2">
                <div class="crm-field"><label class="crm-label" for="cv-p">{{ crm.t('leads.pipeline') }}</label>
                    <select id="cv-p" v-model="f.pipeline_id" class="crm-select" @change="f.stage_id = (stagesFor(f.pipeline_id)[0] || {}).id"><option v-for="p in crm.pipelines" :key="p.id" :value="p.id">{{ p.name }}</option></select></div>
                <div class="crm-field"><label class="crm-label" for="cv-s">{{ crm.t('leads.stage') }}</label>
                    <select id="cv-s" v-model="f.stage_id" class="crm-select"><option v-for="s in stagesFor(f.pipeline_id)" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
            </div>
            <div class="crm-row2">
                <div class="crm-field mb-0"><label class="crm-label" for="cv-c">{{ crm.t('leads.expected_close') }}</label><input id="cv-c" v-model="f.expected_close_date" type="date" class="crm-input"></div>
                <div class="crm-field mb-0"><label class="crm-label" for="cv-o">{{ crm.t('common.owner') }}</label>
                    <select id="cv-o" v-model="f.owner_id" class="crm-select"><option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option></select></div>
            </div>
        </div>
        <div class="crm-field mb-0"><label class="crm-label" for="cv-t">{{ crm.t('leads.follow_up') }}</label><input id="cv-t" v-model.trim="f.task_subject" class="crm-input" :placeholder="crm.t('common.optional')"></div>
        <template #footer>
            <button type="button" class="crm-btn" @click="$emit('close')">{{ crm.t('common.cancel') }}</button>
            <button type="button" class="crm-btn crm-btn-primary" :disabled="busy || (f.create_deal && !f.deal_name)" @click="save"><i class="bx bx-check"></i> {{ crm.t('leads.convert') }}</button>
        </template>
    </crm-modal>
</template>

<script>
import CrmModal from './CrmModal.vue';

/** L-05 convert a lead: keeps the person, optionally creates a company and a deal. */
export default {
    name: 'CrmConvertModal',
    components: { CrmModal },
    inject: ['crm'],
    props: { lead: { type: Object, required: true } },
    emits: ['close', 'done'],
    data() {
        const pipeline = this.crm.pipelines[0] || { stages: [] };
        const stage = pipeline.stages.find((s) => s.kind === 'open' && s.sort_order > 0) || pipeline.stages[0];
        const l = this.lead;
        return {
            busy: false,
            f: {
                company_name: l.contact.company ? l.contact.company.name : '', create_deal: true,
                deal_name: l.interest ? l.interest.slice(0, 60) : l.contact.name, value: l.estimated_value || 0,
                pipeline_id: pipeline.id, stage_id: stage && stage.id, expected_close_date: '', owner_id: l.owner ? l.owner.id : this.crm.actorId, task_subject: '',
            },
        };
    },
    methods: {
        stagesFor(id) { const p = this.crm.pipelines.find((x) => x.id === id); return p ? p.stages.filter((s) => s.kind === 'open') : []; },
        async save() {
            const f = this.f;
            this.busy = true;
            try {
                const res = await this.crm.api.post(`leads/${this.lead.id}/convert`, { ...f, currency: this.crm.currency, expected_close_date: f.expected_close_date || null, company_name: f.company_name || null, task_subject: f.task_subject || null });
                this.crm.notify(this.crm.t('leads.converted_ok'));
                this.$emit('done', res.data);
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.busy = false;
        },
    },
};
</script>
