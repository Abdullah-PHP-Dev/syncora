<template>
    <div class="crm" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <div v-if="!crm.canWrite" class="crm-alert info mb-3"><i class="bx bx-lock-alt"></i> {{ crm.t('common.read_only') }}</div>
        <component :is="pageComponent" v-bind="pageProps" />
        <div v-if="toast" :class="['crm-toast', toast.type]" role="status">
            <i :class="['bx', toast.type === 'error' ? 'bx-error-circle' : 'bx-check']"></i>
            <span>{{ toast.message }}</span>
            <button v-if="toast.action" type="button" @click="runToastAction">{{ toast.action.label }}</button>
        </div>
    </div>
</template>

<script>
import './crm.css';
import { createContext } from './context';
import CrmOverview from './pages/CrmOverview.vue';
import CrmContacts from './pages/CrmContacts.vue';
import CrmContactProfile from './pages/CrmContactProfile.vue';
import CrmCompanies from './pages/CrmCompanies.vue';
import CrmCompanyProfile from './pages/CrmCompanyProfile.vue';
import CrmLeads from './pages/CrmLeads.vue';
import CrmDeals from './pages/CrmDeals.vue';
import CrmActivities from './pages/CrmActivities.vue';
import CrmSettings from './pages/CrmSettings.vue';
import CrmAutomations from './pages/CrmAutomations.vue';
import CrmReports from './pages/CrmReports.vue';

const PAGES = {
    overview: CrmOverview, contacts: CrmContacts, contact: CrmContactProfile, companies: CrmCompanies,
    company: CrmCompanyProfile, leads: CrmLeads, deals: CrmDeals, activities: CrmActivities, settings: CrmSettings,
    automations: CrmAutomations, reports: CrmReports,
};

export default {
    name: 'CrmApp',
    props: {
        page: { type: String, required: true }, props: { type: Object, default: () => ({}) },
        urls: { type: Object, required: true }, messages: { type: Object, required: true },
        locale: { type: String, default: 'en' }, userName: { type: String, default: '' },
    },
    provide() { return { crm: this.crm }; },
    data() {
        const crm = createContext(this.$props);
        crm.notify = (message, type = 'success', action = null) => this.notify(message, type, action);
        return { crm, toast: null, toastTimer: null };
    },
    computed: {
        pageComponent() { return PAGES[this.page] || CrmOverview; },
        pageProps() { return { contactId: this.props.contactId, companyId: this.props.companyId }; },
    },
    methods: {
        notify(message, type, action) {
            clearTimeout(this.toastTimer);
            this.toast = { message, type, action };
            this.toastTimer = setTimeout(() => { this.toast = null; }, action ? 6000 : 3500);
        },
        runToastAction() { const action = this.toast?.action; this.toast = null; action?.run(); },
    },
};
</script>
