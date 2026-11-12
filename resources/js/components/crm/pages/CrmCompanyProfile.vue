<template>
    <div>
        <crm-header :title="crm.t('nav.company')" :crumbs="[{ label: crm.t('nav.companies'), url: crm.pageUrl('companies') }, { label: co ? co.name : '…' }]" />
        <div v-if="!co" class="crm-card crm-muted">{{ crm.t('common.loading') }}</div>
        <template v-else>
            <section class="crm-card d-flex flex-wrap gap-3 align-items-center justify-content-between mb-3">
                <div class="crm-person" style="gap:14px">
                    <span :class="['crm-avatar lg', crm.avatarClass(co.id)]" style="border-radius:14px">{{ crm.initials(co.name) }}</span>
                    <div><h2 style="font-size:21px;font-weight:750;margin:0">{{ co.name }}</h2>
                        <div class="crm-small crm-muted d-flex gap-3 flex-wrap mt-1">
                            <span v-if="co.domain" class="crm-ltr">{{ co.domain }}</span><span v-if="co.industry">{{ co.industry }}</span>
                            <span v-if="co.city">{{ [co.city, co.country].filter(Boolean).join(', ') }}</span>
                        </div></div>
                </div>
                <div v-if="crm.canWrite" class="d-flex gap-2">
                    <button type="button" class="crm-btn" @click="dealModal = true"><i class="bx bx-briefcase"></i> {{ crm.t('contacts.create_deal') }}</button>
                    <button type="button" class="crm-btn" @click="edit = true"><i class="bx bx-edit"></i> {{ crm.t('common.edit') }}</button>
                    <button type="button" class="crm-btn crm-icon-btn" :aria-label="crm.t('common.delete')" @click="remove"><i class="bx bx-trash"></i></button>
                </div>
            </section>
            <div class="crm-grid" style="grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr)">
                <section class="crm-card">
                    <h3 class="crm-card-title mb-2">{{ crm.t('companies.people') }} ({{ data.contacts.length }})</h3>
                    <div v-for="c in data.contacts" :key="c.id" class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid #f3f1f9">
                        <div class="crm-person"><span :class="['crm-avatar', crm.avatarClass(c.id)]">{{ crm.initials(c.name) }}</span>
                            <div class="min-w-0"><a :href="c.url" class="crm-person-name">{{ c.name }}</a><div class="crm-person-sub">{{ c.job_title || c.email }}</div></div></div>
                        <span :class="['crm-chip', c.lifecycle]">{{ crm.t('lifecycle.' + c.lifecycle) }}</span>
                    </div>
                    <p v-if="!data.contacts.length" class="crm-muted crm-small">{{ crm.t('contacts.empty_title') }}</p>
                    <h3 class="crm-card-title mt-4 mb-2">{{ crm.t('nav.deals') }} ({{ data.deals.length }})</h3>
                    <div v-for="d in data.deals" :key="d.id" class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid #f3f1f9">
                        <div><a :href="d.url" style="font-weight:700;color:inherit">{{ d.name }}</a><div class="crm-small crm-muted crm-ltr">{{ crm.money(d.value, d.currency) }}</div></div>
                        <span :class="['crm-chip', d.status === 'open' ? 'qualified' : d.status]">{{ d.stage }}</span>
                    </div>
                    <p v-if="!data.deals.length" class="crm-muted crm-small">{{ crm.t('contacts.no_deals') }}</p>
                </section>
                <aside class="crm-card">
                    <h3 class="crm-card-title mb-3">{{ crm.t('contacts.about') }}</h3>
                    <dl class="crm-dl">
                        <dt>{{ crm.t('common.owner') }}</dt><dd>{{ co.owner ? co.owner.name : crm.t('common.unassigned') }}</dd>
                        <dt>{{ crm.t('common.phone') }}</dt><dd class="crm-ltr">{{ co.phone || '—' }}</dd>
                        <dt>{{ crm.t('common.email') }}</dt><dd class="crm-ltr">{{ co.email || '—' }}</dd>
                        <dt>{{ crm.t('companies.open_value') }}</dt><dd class="crm-ltr">{{ crm.money(co.open_value) }}</dd>
                    </dl>
                    <p v-if="co.notes" class="crm-bubble mt-3">{{ co.notes }}</p>
                </aside>
            </div>
        </template>
        <crm-company-drawer v-if="edit" :company="co" @close="edit = false" @saved="load" />
        <crm-deal-modal v-if="dealModal" :defaults="{ company_id: co.id }" @close="dealModal = false" @saved="load" />
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';
import CrmCompanyDrawer from '../ui/CrmCompanyDrawer.vue';
import CrmDealModal from '../ui/CrmDealModal.vue';

export default {
    name: 'CrmCompanyProfile',
    components: { CrmHeader, CrmCompanyDrawer, CrmDealModal },
    inject: ['crm'],
    props: { companyId: { type: Number, required: true } },
    data: () => ({ data: null, edit: false, dealModal: false }),
    computed: { co() { return this.data && this.data.company; } },
    created() { this.load(); },
    methods: {
        async load() { try { this.data = await this.crm.api.get(`companies/${this.companyId}`); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); } },
        async remove() {
            if (!window.confirm(this.crm.t('common.confirm_delete'))) return;
            try { await this.crm.api.del(`companies/${this.companyId}`); window.location.href = this.crm.pageUrl('companies'); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
    },
};
</script>
