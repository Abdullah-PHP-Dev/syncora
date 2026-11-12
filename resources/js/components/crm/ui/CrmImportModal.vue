<template>
    <crm-modal :title="crm.t('import.title')" :subtitle="crm.t('import.step_' + step)" icon="bx-import" width="720px" :close-label="crm.t('common.close')" @close="$emit('close')">
        <div v-if="step === 'upload'">
            <label class="crm-card d-block text-center" style="border-style:dashed;cursor:pointer;padding:32px">
                <i class="bx bx-cloud-upload" style="font-size:34px;color:var(--crm-purple)"></i>
                <div style="font-weight:700">{{ crm.t('import.choose') }}</div>
                <div class="crm-small crm-muted">{{ crm.t('import.formats') }}</div>
                <input type="file" accept=".csv,text/csv,text/plain" class="d-none" @change="upload">
            </label>
            <p v-if="busy" class="crm-small crm-muted mt-2">{{ crm.t('common.loading') }}</p>
        </div>

        <div v-else-if="step === 'map'">
            <p class="crm-small crm-muted">{{ crm.t('import.rows_found', { n: crm.number(preview.rows) }) }}</p>
            <div class="crm-table-wrap mb-3">
                <table class="crm-table" style="min-width:0">
                    <thead><tr><th>{{ crm.t('import.column') }}</th><th>{{ crm.t('import.sample') }}</th><th>{{ crm.t('import.maps_to') }}</th></tr></thead>
                    <tbody><tr v-for="(h, i) in preview.headers" :key="i">
                        <td><strong dir="auto">{{ h || '#' + (i + 1) }}</strong></td>
                        <td class="crm-small crm-muted" dir="auto">{{ (preview.sample[0] || [])[i] }}</td>
                        <td><select v-model="mapping[i]" class="crm-select" style="height:32px"><option value="">{{ crm.t('import.skip_column') }}</option><option v-for="f in fields" :key="f" :value="f">{{ crm.t('import.field_' + f) }}</option></select></td>
                    </tr></tbody>
                </table>
            </div>
            <div class="crm-row2">
                <div class="crm-field"><label class="crm-label">{{ crm.t('import.duplicates') }}</label>
                    <select v-model="duplicates" class="crm-select"><option value="skip">{{ crm.t('import.dup_skip') }}</option><option value="update">{{ crm.t('import.dup_update') }}</option><option value="create">{{ crm.t('import.dup_create') }}</option></select></div>
                <div class="crm-field"><label class="crm-label">{{ crm.t('common.owner') }}</label>
                    <select v-model="owner" class="crm-select"><option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option></select></div>
            </div>
            <div class="crm-row2">
                <div class="crm-field"><label class="crm-label">{{ crm.t('import.default_lifecycle') }}</label>
                    <select v-model="lifecycle" class="crm-select"><option v-for="l in crm.options.lifecycles" :key="l" :value="l">{{ crm.t('lifecycle.' + l) }}</option></select></div>
                <div class="crm-field"><label class="crm-label">{{ crm.t('import.add_tag') }}</label><input v-model="tag" class="crm-input" :placeholder="crm.t('common.optional')"></div>
            </div>
        </div>

        <div v-else class="text-center py-3">
            <div class="crm-empty-icon" style="background:#ecfdf3;color:#16a34a"><i class="bx bx-check"></i></div>
            <h4 style="font-size:16px;font-weight:700">{{ crm.t('import.done') }}</h4>
            <p class="crm-small">{{ crm.t('import.summary', { created: result.created, updated: result.updated, skipped: result.skipped }) }}</p>
            <div v-if="result.errors.length" class="crm-alert warn text-start"><div><div v-for="e in result.errors" :key="e.row">{{ crm.t('import.row', { n: e.row }) }}: {{ e.message }}</div></div></div>
        </div>

        <template #footer>
            <button type="button" class="crm-btn" @click="$emit('close')">{{ step === 'done' ? crm.t('common.close') : crm.t('common.cancel') }}</button>
            <button v-if="step === 'map'" type="button" class="crm-btn crm-btn-primary" :disabled="busy || !mapsAPerson" @click="run">
                <i :class="['bx', busy ? 'bx-loader-alt bx-spin' : 'bx-import']"></i> {{ crm.t('import.import_n', { n: crm.number(preview.rows) }) }}
            </button>
        </template>
    </crm-modal>
</template>

<script>
import CrmModal from './CrmModal.vue';

export default {
    name: 'CrmImportModal',
    components: { CrmModal },
    inject: ['crm'],
    data() {
        return { step: 'upload', busy: false, preview: null, mapping: {}, duplicates: 'skip', owner: this.crm.actorId, lifecycle: 'lead', tag: '', result: null,
            fields: ['first_name', 'last_name', 'full_name', 'arabic_name', 'phone', 'email', 'company', 'job_title', 'lifecycle', 'source', 'tags'] };
    },
    computed: { mapsAPerson() { return Object.values(this.mapping).some((f) => ['first_name', 'full_name', 'phone', 'email'].includes(f)); } },
    methods: {
        async upload(e) {
            const file = e.target.files[0];
            if (!file) return;
            const body = new FormData(); body.append('file', file);
            this.busy = true;
            try {
                this.preview = (await window.axios.post(this.crm.urls.api + '/contacts/import/preview', body)).data;
                this.mapping = Object.fromEntries(this.preview.headers.map((_, i) => [i, this.preview.mapping[i] || '']));
                this.step = 'map';
            } catch (err) { this.crm.notify(this.crm.error(err), 'error'); }
            this.busy = false;
        },
        async run() {
            this.busy = true;
            try {
                this.result = await this.crm.api.post('contacts/import', {
                    token: this.preview.token, mapping: Object.fromEntries(Object.entries(this.mapping).filter(([, f]) => f)), duplicates: this.duplicates,
                    owner_id: this.owner, lifecycle: this.lifecycle, tags: this.tag ? [this.tag.trim()] : [],
                });
                this.step = 'done';
                this.$emit('imported');
            } catch (err) { this.crm.notify(this.crm.error(err), 'error'); }
            this.busy = false;
        },
    },
};
</script>
