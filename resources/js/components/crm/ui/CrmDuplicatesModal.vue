<template>
    <crm-modal :title="crm.t('duplicates.title')" :subtitle="crm.t('duplicates.hint')" icon="bx-git-merge" width="760px" :close-label="crm.t('common.close')" @close="$emit('close')">
        <p v-if="loading" class="crm-muted">{{ crm.t('common.loading') }}</p>
        <div v-else-if="!groups.length" class="crm-empty"><div class="crm-empty-icon"><i class="bx bx-check-double"></i></div><h4>{{ crm.t('duplicates.none') }}</h4></div>
        <div v-for="(g, gi) in groups" :key="gi" class="crm-card mb-3" style="padding:14px">
            <div class="crm-small crm-muted mb-2">{{ crm.t('duplicates.same_' + (g.key === 'email' ? 'email' : 'phone')) }}: <strong class="crm-ltr">{{ g.value }}</strong></div>
            <label v-for="c in g.contacts" :key="c.id" class="crm-check py-1" style="align-items:center">
                <input v-model="g.primary" type="radio" :value="c.id" :name="'g' + gi">
                <span class="flex-grow-1"><strong>{{ c.name }}</strong> <span class="crm-small crm-muted crm-ltr">{{ c.phone }} {{ c.email }}</span>
                    <span class="crm-small crm-muted"> · {{ crm.t('lifecycle.' + c.lifecycle) }} · {{ crm.t('duplicates.counts', { deals: c.deals_count, leads: c.leads_count }) }} · {{ crm.date(c.created_at) }}</span></span>
            </label>
            <div class="d-flex justify-content-between align-items-center mt-2 gap-2 flex-wrap">
                <span class="crm-small crm-muted">{{ crm.t('duplicates.keep_hint') }}</span>
                <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-primary crm-btn-sm" :disabled="!g.primary || busy" @click="merge(g, gi)"><i class="bx bx-git-merge"></i> {{ crm.t('duplicates.merge', { n: g.contacts.length }) }}</button>
            </div>
        </div>
        <template #footer><button type="button" class="crm-btn" @click="$emit('close')">{{ crm.t('common.close') }}</button></template>
    </crm-modal>
</template>

<script>
import CrmModal from './CrmModal.vue';

export default {
    name: 'CrmDuplicatesModal',
    components: { CrmModal },
    inject: ['crm'],
    data: () => ({ groups: [], loading: true, busy: false }),
    async created() {
        try {
            const res = await this.crm.api.get('contacts/duplicate-groups');
            // Default: keep the oldest record (it has the longest history).
            this.groups = res.data.map((g) => ({ ...g, primary: g.contacts[0] && g.contacts[0].id }));
        } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        this.loading = false;
    },
    methods: {
        async merge(group, index) {
            if (!window.confirm(this.crm.t('duplicates.confirm'))) return;
            this.busy = true;
            try {
                await this.crm.api.post('contacts/merge', { primary_id: group.primary, ids: group.contacts.map((c) => c.id).filter((id) => id !== group.primary) });
                this.groups.splice(index, 1);
                this.crm.notify(this.crm.t('duplicates.merged'));
                this.$emit('merged');
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.busy = false;
        },
    },
};
</script>
