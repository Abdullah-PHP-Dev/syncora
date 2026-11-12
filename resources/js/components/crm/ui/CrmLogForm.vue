<template>
    <form class="crm-card mb-3" style="background:var(--crm-soft, #f8fafc)" @submit.prevent="save">
        <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
            <div class="crm-seg">
                <button v-for="tp in types" :key="tp" type="button" :class="{ active: form.type === tp }" @click="form.type = tp">{{ crm.t('activity_types.' + tp) }}</button>
            </div>
            <button v-if="closable" type="button" class="crm-btn crm-icon-btn crm-btn-sm" style="margin-inline-start:auto" :aria-label="crm.t('common.close')" @click="$emit('close')"><i class="bx bx-x"></i></button>
        </div>
        <input v-if="form.type !== 'note'" v-model.trim="form.subject" class="crm-input mb-2" :placeholder="crm.t('activities.subject')" :aria-label="crm.t('activities.subject')" dir="auto">
        <textarea v-model.trim="form.body" class="crm-textarea" rows="2" :placeholder="crm.t('contacts.write_note')" :aria-label="crm.t('contacts.write_note')" dir="auto"></textarea>
        <div class="d-flex gap-2 mt-2 flex-wrap align-items-center">
            <select v-if="form.type === 'call' || form.type === 'meeting'" v-model="form.outcome" class="crm-select" style="width:auto" :aria-label="crm.t('activities.outcome')">
                <option value="">{{ crm.t('activities.outcome') }}</option>
                <option v-for="o in outcomes" :key="o" :value="o">{{ crm.opt('outcomes', o) }}</option>
            </select>
            <input v-if="form.type === 'call' || form.type === 'meeting'" v-model.number="form.duration_minutes" type="number" min="0" max="1440" class="crm-input" style="width:120px" :placeholder="crm.t('activities.duration')" :aria-label="crm.t('activities.duration')">
            <template v-if="form.type === 'task'">
                <input v-model="form.due_at" type="datetime-local" class="crm-input" style="width:auto" :aria-label="crm.t('activities.due')">
                <select v-model="form.priority" class="crm-select" style="width:auto" :aria-label="crm.t('activities.priority')">
                    <option v-for="p in ['low', 'medium', 'high']" :key="p" :value="p">{{ crm.t('priorities.' + p) }}</option>
                </select>
            </template>
            <select v-if="crm.members.length > 1" v-model="form.owner_id" class="crm-select" style="width:auto" :aria-label="crm.t('common.owner')">
                <option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
        </div>
        <div v-if="contactId && form.type !== 'task'" class="d-flex gap-2 mt-2 flex-wrap align-items-center">
            <label class="crm-small d-flex align-items-center gap-1"><input v-model="followUp" type="checkbox"> {{ crm.t('activities.schedule_follow_up') }}</label>
            <template v-if="followUp">
                <input v-model.trim="fu.subject" class="crm-input" style="width:200px" :placeholder="crm.t('activities.subject')" :aria-label="crm.t('activities.subject')" dir="auto">
                <input v-model="fu.due_at" type="datetime-local" class="crm-input" style="width:auto" :aria-label="crm.t('activities.due')">
                <select v-model.number="fu.remind_minutes" class="crm-select" style="width:auto" :aria-label="crm.t('activities.remind')">
                    <option :value="null">{{ crm.t('activities.no_reminder') }}</option>
                    <option v-for="m in [15, 60, 1440]" :key="m" :value="m">{{ crm.t('activities.remind_before', { n: m >= 60 ? (m / 60) + 'h' : m + 'm' }) }}</option>
                </select>
            </template>
            <select v-if="dealId && stages.length" v-model="moveStage" class="crm-select" style="width:auto;margin-inline-start:auto" :aria-label="crm.t('activities.move_stage')">
                <option :value="null">{{ crm.t('activities.move_stage') }}</option>
                <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
        </div>
        <div class="d-flex justify-content-end mt-2">
            <button type="submit" class="crm-btn crm-btn-primary crm-btn-sm" :disabled="saving || (!form.body && !form.subject)">{{ crm.t('contacts.log') }}</button>
        </div>
    </form>
</template>

<script>
const OUTCOMES = ['connected', 'no_answer', 'voicemail', 'interested', 'not_interested', 'call_back'];

export default {
    name: 'CrmLogForm',
    inject: ['crm'],
    props: {
        type: { type: String, default: 'note' },
        contactId: { type: Number, default: null },
        dealId: { type: Number, default: null },
        leadId: { type: Number, default: null },
        stages: { type: Array, default: () => [] },
        closable: { type: Boolean, default: true },
    },
    emits: ['close', 'saved'],
    data() {
        return {
            types: ['note', 'call', 'meeting', 'email', 'task'], outcomes: OUTCOMES, saving: false, followUp: false, moveStage: null,
            form: { type: this.type, subject: '', body: '', outcome: '', duration_minutes: null, due_at: '', priority: 'medium', owner_id: this.crm.actorId },
            fu: { subject: '', due_at: '', remind_minutes: 15 },
        };
    },
    watch: { type(v) { this.form.type = v; } },
    methods: {
        async save() {
            const f = this.form;
            const payload = {
                type: f.type, subject: f.subject || (f.type === 'note' ? null : f.body.slice(0, 200)), body: f.body || null,
                outcome: f.outcome || null, duration_minutes: f.duration_minutes || null, owner_id: f.owner_id || null,
                due_at: f.type === 'task' ? (f.due_at || null) : null, priority: f.type === 'task' ? f.priority : null,
                deal_id: this.dealId, lead_id: this.leadId,
            };
            if (this.contactId && this.followUp && this.fu.subject && this.fu.due_at) payload.follow_up = { type: 'task', ...this.fu };
            if (this.moveStage) payload.move_stage_id = this.moveStage;
            this.saving = true;
            try {
                if (this.contactId) await this.crm.api.post(`contacts/${this.contactId}/activities`, payload);
                else await this.crm.api.post('activities', { ...payload, subject: payload.subject || f.body.slice(0, 200) });
                this.crm.notify(this.crm.t('common.saved'));
                f.subject = ''; f.body = ''; f.outcome = ''; f.duration_minutes = null; f.due_at = '';
                this.followUp = false; this.moveStage = null; this.fu = { subject: '', due_at: '', remind_minutes: 15 };
                this.$emit('saved');
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.saving = false;
        },
    },
};
</script>
