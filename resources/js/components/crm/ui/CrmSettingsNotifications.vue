<template>
    <section class="crm-card">
        <h3 class="crm-card-title">{{ crm.t('settings.section_notifications') }}</h3>
        <p class="crm-card-sub">{{ crm.t('settings.notifications_hint') }}</p>
        <div v-if="!events" class="crm-muted">{{ crm.t('common.loading') }}</div>
        <table v-else class="crm-table" style="max-width:620px">
            <thead><tr><th>{{ crm.t('settings.notify_event') }}</th><th class="text-center">{{ crm.t('settings.notify_bell') }}</th><th class="text-center">{{ crm.t('common.email') }}</th></tr></thead>
            <tbody>
                <tr v-for="(v, key) in events" :key="key">
                    <td>{{ crm.t('settings.notify_' + key) }}</td>
                    <td class="text-center"><input v-model="v.in_app" type="checkbox" :aria-label="crm.t('settings.notify_' + key) + ' · ' + crm.t('settings.notify_bell')"></td>
                    <td class="text-center"><input v-model="v.email" type="checkbox" :aria-label="crm.t('settings.notify_' + key) + ' · ' + crm.t('common.email')"></td>
                </tr>
            </tbody>
        </table>
        <div v-if="events" class="d-flex justify-content-end mt-3">
            <button type="button" class="crm-btn crm-btn-primary" :disabled="busy" @click="save"><i class="bx bx-check"></i> {{ crm.t('common.save') }}</button>
        </div>
    </section>
</template>

<script>
/** S-12: each person chooses which CRM alerts reach the bell and their email. */
export default {
    name: 'CrmSettingsNotifications',
    inject: ['crm'],
    data: () => ({ events: null, busy: false }),
    async created() {
        try { this.events = (await this.crm.api.get('settings/notifications')).events; } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
    },
    methods: {
        async save() {
            this.busy = true;
            try { this.events = (await this.crm.api.put('settings/notifications', { events: this.events })).events; this.crm.notify(this.crm.t('common.saved')); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.busy = false;
        },
    },
};
</script>
