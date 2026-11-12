<template>
    <crm-drawer :title="contact ? crm.t('contacts.edit') : crm.t('contacts.new')" :close-label="crm.t('common.close')" @close="$emit('close')">
        <form @submit.prevent="save(false)">
            <div class="crm-row2">
                <div class="crm-field">
                    <label class="crm-label" for="cf-first">{{ crm.t('contacts.first_name') }} *</label>
                    <input id="cf-first" v-model.trim="form.first_name" class="crm-input" required maxlength="80">
                </div>
                <div class="crm-field">
                    <label class="crm-label" for="cf-last">{{ crm.t('contacts.last_name') }}</label>
                    <input id="cf-last" v-model.trim="form.last_name" class="crm-input" maxlength="80">
                </div>
            </div>
            <div class="crm-field">
                <label class="crm-label" for="cf-ar">{{ crm.t('contacts.arabic_name') }}</label>
                <input id="cf-ar" v-model.trim="form.arabic_name" class="crm-input" dir="rtl" maxlength="160">
            </div>
            <div class="crm-field">
                <label class="crm-label" for="cf-phone">{{ crm.t('common.phone') }}</label>
                <input id="cf-phone" v-model.trim="form.phone" class="crm-input" dir="ltr" inputmode="tel" placeholder="+966 5X XXX XXXX" @input="checkDuplicates">
                <div class="crm-hint">{{ crm.t('contacts.phone_hint') }}</div>
            </div>
            <div class="crm-field">
                <label class="crm-label" for="cf-email">{{ crm.t('common.email') }}</label>
                <input id="cf-email" v-model.trim="form.email" class="crm-input" type="email" dir="ltr" @input="checkDuplicates">
            </div>
            <div class="crm-row2">
                <div class="crm-field">
                    <label class="crm-label" for="cf-company">{{ crm.t('common.company') }}</label>
                    <select id="cf-company" v-model="form.company_id" class="crm-select">
                        <option :value="null">{{ crm.t('common.none') }}</option>
                        <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="crm-field">
                    <label class="crm-label" for="cf-title">{{ crm.t('contacts.job_title') }}</label>
                    <input id="cf-title" v-model.trim="form.job_title" class="crm-input" maxlength="120">
                </div>
            </div>
            <div class="crm-row2">
                <div class="crm-field">
                    <label class="crm-label" for="cf-life">{{ crm.t('contacts.lifecycle') }}</label>
                    <select id="cf-life" v-model="form.lifecycle" class="crm-select">
                        <option v-for="l in crm.options.lifecycles" :key="l" :value="l">{{ crm.t('lifecycle.' + l) }}</option>
                    </select>
                </div>
                <div class="crm-field">
                    <label class="crm-label" for="cf-owner">{{ crm.t('common.owner') }}</label>
                    <select id="cf-owner" v-model="form.owner_id" class="crm-select">
                        <option :value="null">{{ crm.t('common.unassigned') }}</option>
                        <option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                </div>
            </div>
            <div class="crm-row2">
                <div class="crm-field">
                    <label class="crm-label" for="cf-source">{{ crm.t('common.source') }}</label>
                    <select id="cf-source" v-model="form.source" class="crm-select">
                        <option :value="null">{{ crm.t('common.none') }}</option>
                        <option v-for="s in crm.options.sources" :key="s" :value="s">{{ crm.sourceLabel(s) }}</option>
                    </select>
                </div>
                <div class="crm-field">
                    <label class="crm-label" for="cf-lang">{{ crm.t('contacts.language') }}</label>
                    <select id="cf-lang" v-model="form.language" class="crm-select">
                        <option :value="null">{{ crm.t('common.none') }}</option>
                        <option value="ar">{{ crm.t('contacts.arabic') }}</option>
                        <option value="en">{{ crm.t('contacts.english') }}</option>
                    </select>
                </div>
            </div>
            <div class="crm-field">
                <label class="crm-label" for="cf-tags">{{ crm.t('common.tags') }}</label>
                <input id="cf-tags" v-model="tagsText" class="crm-input" :placeholder="crm.t('common.tags_hint')">
            </div>

            <div v-for="f in crm.fields" :key="f.key" class="crm-field">
                <label class="crm-label" :for="'cf-x-' + f.key">{{ f.label }}</label>
                <select v-if="f.type === 'dropdown'" :id="'cf-x-' + f.key" v-model="form.custom_fields[f.key]" class="crm-select">
                    <option :value="null">{{ crm.t('common.none') }}</option><option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
                </select>
                <input v-else :id="'cf-x-' + f.key" v-model="form.custom_fields[f.key]" class="crm-input" :type="f.type === 'number' ? 'number' : f.type === 'date' ? 'date' : 'text'" :dir="f.type === 'text' ? 'auto' : 'ltr'">
            </div>

            <div v-if="duplicates.length" class="crm-alert warn mb-3" role="alert">
                <i class="bx bx-error"></i>
                <div class="flex-grow-1">
                    {{ crm.t('contacts.possible_duplicate', { name: dupeName(duplicates[0]) }) }}
                    <div class="mt-1"><a :href="crm.pageUrl('contact', duplicates[0].id)" style="font-weight:700">{{ crm.t('contacts.open_existing') }}</a></div>
                </div>
            </div>
            <label class="crm-check"><input v-model="form.whatsapp_consent" type="checkbox"> {{ crm.t('contacts.whatsapp_consent') }}</label>
        </form>

        <template #footer>
            <button type="button" class="crm-btn" @click="$emit('close')">{{ crm.t('common.cancel') }}</button>
            <button v-if="!contact" type="button" class="crm-btn" :disabled="saving" @click="save(true)">{{ crm.t('contacts.save_another') }}</button>
            <button type="button" class="crm-btn crm-btn-primary" :disabled="saving || !form.first_name" @click="save(false)">
                <i class="bx bx-check"></i> {{ contact ? crm.t('common.save') : (duplicates.length ? crm.t('contacts.create_anyway') : crm.t('contacts.create')) }}
            </button>
        </template>
    </crm-drawer>
</template>

<script>
import CrmDrawer from './CrmDrawer.vue';

const blank = (crm) => ({
    first_name: '', last_name: '', arabic_name: '', phone: '', email: '', company_id: null, job_title: '',
    lifecycle: 'lead', owner_id: crm.actorId, source: null, language: null, whatsapp_consent: false,
    custom_fields: Object.fromEntries(crm.fields.map((f) => [f.key, null])),
});

export default {
    name: 'CrmContactDrawer',
    components: { CrmDrawer },
    inject: ['crm'],
    props: { contact: { type: Object, default: null }, defaults: { type: Object, default: () => ({}) } },
    data() {
        const form = this.contact
            ? Object.assign(blank(this.crm), { ...this.contact, company_id: this.contact.company ? this.contact.company.id : null, owner_id: this.contact.owner ? this.contact.owner.id : null,
                custom_fields: { ...Object.fromEntries(this.crm.fields.map((f) => [f.key, null])), ...(Array.isArray(this.contact.custom_fields) ? {} : this.contact.custom_fields || {}) } })
            : Object.assign(blank(this.crm), this.defaults);
        return { form, tagsText: (this.contact && this.contact.tags || []).join(', '), duplicates: [], companies: [], saving: false, timer: null };
    },
    async created() {
        const res = await this.crm.api.get('companies', { per_page: 100 }).catch(() => ({ data: [] }));
        this.companies = res.data;
    },
    methods: {
        dupeName(d) { return [d.first_name, d.last_name].filter(Boolean).join(' '); },
        checkDuplicates() {
            clearTimeout(this.timer);
            this.timer = setTimeout(async () => {
                if (!this.form.phone && !this.form.email) { this.duplicates = []; return; }
                const res = await this.crm.api.get('contacts/duplicates', { phone: this.form.phone, email: this.form.email, except: this.contact ? this.contact.id : '' }).catch(() => ({ data: [] }));
                this.duplicates = res.data;
            }, 350);
        },
        async save(another) {
            if (!this.form.first_name || this.saving) return;
            this.saving = true;
            const payload = { ...this.form, tags: this.tagsText.split(',').map((t) => t.trim()).filter(Boolean), force: this.duplicates.length > 0 };
            ['company', 'owner', 'url', 'name', 'identities', 'created_at', 'last_activity_at', 'score'].forEach((k) => delete payload[k]);
            try {
                const res = this.contact
                    ? await this.crm.api.put(`contacts/${this.contact.id}`, payload)
                    : await this.crm.api.post('contacts', payload);
                this.crm.notify(this.crm.t('common.saved'));
                this.$emit('saved', res.data);
                if (another) { this.form = blank(this.crm); this.tagsText = ''; this.duplicates = []; } else { this.$emit('close'); }
            } catch (e) {
                if (e.response && e.response.status === 409) { this.duplicates = e.response.data.duplicates; } else { this.crm.notify(this.crm.error(e), 'error'); }
            } finally { this.saving = false; }
        },
    },
};
</script>
