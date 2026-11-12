<template>
    <crm-drawer :title="company ? crm.t('common.edit') + ' · ' + company.name : crm.t('companies.new')" :close-label="crm.t('common.close')" @close="$emit('close')">
        <form @submit.prevent="save">
            <div class="crm-field">
                <label class="crm-label" for="co-name">{{ crm.t('common.name') }} *</label>
                <input id="co-name" v-model.trim="form.name" class="crm-input" required maxlength="160">
            </div>
            <div class="crm-row2">
                <div class="crm-field"><label class="crm-label" for="co-domain">{{ crm.t('companies.domain') }}</label><input id="co-domain" v-model.trim="form.domain" class="crm-input" dir="ltr"></div>
                <div class="crm-field"><label class="crm-label" for="co-ind">{{ crm.t('companies.industry') }}</label><input id="co-ind" v-model.trim="form.industry" class="crm-input"></div>
            </div>
            <div class="crm-row2">
                <div class="crm-field"><label class="crm-label" for="co-phone">{{ crm.t('common.phone') }}</label><input id="co-phone" v-model.trim="form.phone" class="crm-input" dir="ltr"></div>
                <div class="crm-field"><label class="crm-label" for="co-email">{{ crm.t('common.email') }}</label><input id="co-email" v-model.trim="form.email" type="email" class="crm-input" dir="ltr"></div>
            </div>
            <div class="crm-row2">
                <div class="crm-field"><label class="crm-label" for="co-city">{{ crm.t('companies.city') }}</label><input id="co-city" v-model.trim="form.city" class="crm-input"></div>
                <div class="crm-field"><label class="crm-label" for="co-country">{{ crm.t('companies.country') }}</label><input id="co-country" v-model.trim="form.country" class="crm-input"></div>
            </div>
            <div class="crm-field">
                <label class="crm-label" for="co-owner">{{ crm.t('common.owner') }}</label>
                <select id="co-owner" v-model="form.owner_id" class="crm-select">
                    <option :value="null">{{ crm.t('common.unassigned') }}</option>
                    <option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
            </div>
            <div class="crm-field"><label class="crm-label" for="co-notes">{{ crm.t('companies.notes') }}</label><textarea id="co-notes" v-model="form.notes" class="crm-textarea"></textarea></div>
        </form>
        <template #footer>
            <button type="button" class="crm-btn" @click="$emit('close')">{{ crm.t('common.cancel') }}</button>
            <button type="button" class="crm-btn crm-btn-primary" :disabled="saving || !form.name" @click="save"><i class="bx bx-check"></i> {{ company ? crm.t('common.save') : crm.t('companies.create') }}</button>
        </template>
    </crm-drawer>
</template>

<script>
import CrmDrawer from './CrmDrawer.vue';

export default {
    name: 'CrmCompanyDrawer',
    components: { CrmDrawer },
    inject: ['crm'],
    props: { company: { type: Object, default: null } },
    data() {
        const c = this.company || {};
        return {
            saving: false,
            form: { name: c.name || '', domain: c.domain || '', industry: c.industry || '', phone: c.phone || '', email: c.email || '',
                city: c.city || '', country: c.country || '', notes: c.notes || '', owner_id: c.owner ? c.owner.id : (this.company ? null : this.crm.actorId) },
        };
    },
    methods: {
        async save() {
            this.saving = true;
            try {
                const payload = Object.fromEntries(Object.entries(this.form).map(([k, v]) => [k, v === '' ? null : v]));
                const res = this.company ? await this.crm.api.put(`companies/${this.company.id}`, payload) : await this.crm.api.post('companies', payload);
                this.crm.notify(this.crm.t('common.saved'));
                this.$emit('saved', res.data);
                this.$emit('close');
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.saving = false;
        },
    },
};
</script>
