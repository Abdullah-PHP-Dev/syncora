<template>
    <div>
        <crm-header :title="crm.t('nav.contact')" :crumbs="[{ label: crm.t('nav.contacts'), url: crm.pageUrl('contacts') }, { label: c ? c.name : '…' }]" />

        <div v-if="!c" class="crm-card crm-muted">{{ crm.t('common.loading') }}</div>
        <template v-else>
            <section class="crm-card d-flex flex-wrap gap-3 align-items-center justify-content-between mb-3">
                <div class="crm-person" style="gap:14px">
                    <span :class="['crm-avatar lg', crm.avatarClass(c.id)]">{{ crm.initials(c.name) }}</span>
                    <div class="min-w-0">
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <h2 style="font-size:21px;font-weight:750;margin:0">{{ c.name }}</h2>
                            <span :class="['crm-chip', c.lifecycle]">{{ crm.t('lifecycle.' + c.lifecycle) }}</span>
                            <span v-for="tag in c.tags" :key="tag" class="crm-tag">{{ tag }}</span>
                        </div>
                        <div class="crm-small crm-muted d-flex gap-3 flex-wrap mt-1">
                            <span v-if="c.arabic_name" dir="rtl">{{ c.arabic_name }}</span>
                            <span v-if="c.phone"><i class="bx bx-phone"></i> <span class="crm-ltr">{{ c.phone }}</span></span>
                            <span v-if="c.email"><i class="bx bx-envelope"></i> <span class="crm-ltr">{{ c.email }}</span></span>
                            <span v-if="data.conversations.length"><i class="bx bx-message-rounded-dots"></i> {{ data.conversations.length }} {{ crm.t('contacts.channels').toLowerCase() }}</span>
                        </div>
                    </div>
                </div>
                <div v-if="crm.canWrite" class="d-flex gap-2 flex-wrap">
                    <a v-if="c.phone" class="crm-btn crm-icon-btn" :href="'https://wa.me/' + c.phone.replace(/\D/g, '')" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="bx bxl-whatsapp"></i></a>
                    <a v-if="c.email" class="crm-btn crm-icon-btn" :href="'mailto:' + c.email" :aria-label="crm.t('common.email')"><i class="bx bx-envelope"></i></a>
                    <button type="button" class="crm-btn" @click="dealModal = true"><i class="bx bx-briefcase"></i> {{ crm.t('contacts.create_deal') }}</button>
                    <button type="button" class="crm-btn" @click="edit = true"><i class="bx bx-edit"></i> {{ crm.t('common.edit') }}</button>
                    <button type="button" class="crm-btn crm-icon-btn" :aria-label="crm.t('common.delete')" @click="remove"><i class="bx bx-trash"></i></button>
                </div>
            </section>

            <div class="crm-grid" style="grid-template-columns: minmax(220px, 1fr) minmax(0, 2.2fr) minmax(260px, 1.2fr); align-items:start">
                <aside class="crm-card">
                    <h3 class="crm-card-title mb-3">{{ crm.t('contacts.about') }}</h3>
                    <dl class="crm-dl">
                        <dt>{{ crm.t('common.owner') }}</dt><dd>{{ c.owner ? c.owner.name : crm.t('common.unassigned') }}</dd>
                        <dt>{{ crm.t('contacts.lifecycle') }}</dt><dd>{{ crm.t('lifecycle.' + c.lifecycle) }}</dd>
                        <dt>{{ crm.t('common.score') }}</dt><dd><span class="crm-score">{{ c.score }}<span class="crm-score-bar"><span :style="{ width: c.score + '%', background: crm.scoreColor(c.score) }"></span></span></span></dd>
                        <dt>{{ crm.t('common.source') }}</dt><dd>{{ crm.sourceLabel(c.source) }}</dd>
                        <dt>{{ crm.t('common.company') }}</dt><dd><a v-if="c.company" :href="crm.pageUrl('company', c.company.id)">{{ c.company.name }}</a><span v-else>—</span></dd>
                        <dt>{{ crm.t('contacts.job_title') }}</dt><dd>{{ c.job_title || '—' }}</dd>
                        <dt>{{ crm.t('contacts.language') }}</dt><dd>{{ c.language ? crm.t('contacts.' + (c.language === 'ar' ? 'arabic' : 'english')) : '—' }}</dd>
                        <dt>{{ crm.t('contacts.preferred_channel') }}</dt><dd>{{ crm.sourceLabel(c.preferred_channel) }}</dd>
                        <dt>{{ crm.t('contacts.lifetime_value') }}</dt><dd class="crm-ltr">{{ crm.money(lifetime) }}</dd>
                        <dt>{{ crm.t('common.created') }}</dt><dd>{{ crm.date(c.created_at) }}</dd>
                        <template v-for="f in data.fields">
                            <dt :key="'k' + f.key">{{ f.label }}</dt><dd :key="'v' + f.key" dir="auto">{{ (c.custom_fields || {})[f.key] || '—' }}</dd>
                        </template>
                    </dl>
                    <div v-if="data.email_lists && data.email_lists.length" class="mt-3">
                        <div class="crm-small crm-muted mb-1" style="font-weight:700">{{ crm.t('contacts.email_lists') }}</div>
                        <span v-for="l in data.email_lists" :key="l.id" class="crm-tag">{{ l.name }}</span>
                    </div>
                </aside>

                <section class="crm-card">
                    <nav class="crm-tabs" role="tablist">
                        <button v-for="tb in tabs" :key="tb.key" type="button" role="tab" :class="['crm-tab', { active: tab === tb.key }]" @click="tab = tb.key">
                            {{ crm.t('contacts.tab_' + tb.key) }}<span class="count">{{ tb.count }}</span>
                        </button>
                    </nav>

                    <template v-if="tab === 'activity'">
                        <crm-log-form v-if="crm.canWrite" :contact-id="c.id" :deal-id="openDeals.length === 1 ? openDeals[0].id : null" :stages="openDeals.length === 1 ? stagesOf(openDeals[0]) : []" :closable="false" @saved="load" />

                        <div v-if="upcoming.length" class="mb-3">
                            <div class="crm-small crm-muted mb-2" style="font-weight:700;letter-spacing:.05em;text-transform:uppercase">{{ crm.t('contacts.upcoming') }}</div>
                            <div v-for="task in upcoming" :key="task.id" :class="['crm-alert', task.overdue ? 'danger' : 'warn', 'mb-2', 'justify-content-between', 'align-items-center']">
                                <span><i class="bx bx-time-five"></i> <strong>{{ task.subject }}</strong> <span class="crm-small">· {{ crm.date(task.due_at, true) }} · {{ task.owner ? task.owner.name : '' }}</span></span>
                                <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-sm" @click="complete(task)"><i class="bx bx-check"></i> {{ crm.t('contacts.mark_done') }}</button>
                            </div>
                        </div>

                        <div class="crm-small crm-muted mb-1" style="font-weight:700;letter-spacing:.05em;text-transform:uppercase">{{ crm.t('contacts.history') }}</div>
                        <p v-if="!history.length" class="crm-muted crm-small">{{ crm.t('contacts.no_timeline') }}</p>
                        <ul class="crm-timeline">
                            <li v-for="item in history" :key="item.id">
                                <span :class="['crm-tl-icon', item.kind === 'message' ? 'message' : item.type]"><i :class="['bx', itemIcon(item)]"></i></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong class="crm-small">{{ itemTitle(item) }}</strong>
                                        <span class="crm-small crm-muted">{{ crm.ago(item.created_at) }}</span>
                                    </div>
                                    <div v-if="item.kind === 'activity' && item.author && item.type !== 'event'" class="crm-small crm-muted">{{ crm.t('common.by', { name: item.author.name }) }}</div>
                                    <div v-if="item.body && item.type !== 'event'" class="crm-bubble" dir="auto">{{ item.body }}</div>
                                </div>
                            </li>
                        </ul>
                    </template>

                    <template v-else-if="tab === 'conversations'">
                        <p v-if="!data.conversations.length" class="crm-muted crm-small">{{ crm.t('contacts.no_conversations') }}</p>
                        <div v-for="conv in data.conversations" :key="conv.id" class="d-flex justify-content-between align-items-center gap-2 py-2" style="border-bottom:1px solid #f3f1f9">
                            <div class="min-w-0"><strong>{{ crm.sourceLabel(conv.platform) }}</strong><div class="crm-small crm-muted text-truncate">{{ conv.preview }} · {{ crm.ago(conv.last_message_at) }}</div></div>
                            <a :href="conv.url" class="crm-btn crm-btn-soft crm-btn-sm">{{ crm.t('contacts.open_in_inbox') }}</a>
                        </div>
                    </template>

                    <template v-else-if="tab === 'deals'">
                        <p v-if="!data.deals.length" class="crm-muted crm-small">{{ crm.t('contacts.no_deals') }}</p>
                        <div v-for="d in data.deals" :key="d.id" class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid #f3f1f9">
                            <div><a :href="d.url" style="font-weight:700;color:inherit">{{ d.name }}</a><div class="crm-small crm-muted"><span class="crm-ltr">{{ crm.money(d.value, d.currency) }}</span> · {{ d.expected_close_date || '—' }}</div></div>
                            <span :class="['crm-chip', d.status === 'open' ? 'qualified' : d.status]">{{ d.stage }}</span>
                        </div>
                    </template>

                    <template v-else>
                        <div v-for="l in data.leads" :key="l.id" class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid #f3f1f9">
                            <div><a :href="l.url" style="font-weight:700;color:inherit">{{ crm.sourceLabel(l.source) }}</a><div class="crm-small crm-muted">{{ l.interest || '—' }} · {{ crm.date(l.created_at) }}</div></div>
                            <span :class="['crm-chip', l.status]">{{ crm.opt('lead_status', l.status) }}</span>
                        </div>
                    </template>
                </section>

                <aside class="d-flex flex-column gap-3">
                    <crm-ai-card :contact-id="c.id" />
                    <div class="crm-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="crm-card-title">{{ crm.t('nav.deals') }}</h3>
                            <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-ghost crm-icon-btn crm-btn-sm" :aria-label="crm.t('contacts.create_deal')" @click="dealModal = true"><i class="bx bx-plus"></i></button>
                        </div>
                        <p v-if="!openDeals.length" class="crm-muted crm-small mb-0">{{ crm.t('contacts.no_deals') }}</p>
                        <div v-for="d in openDeals" :key="d.id" class="d-flex justify-content-between align-items-center py-2">
                            <div class="min-w-0"><a :href="d.url" class="crm-small d-block" style="font-weight:700;color:inherit">{{ d.name }}</a><div class="crm-small crm-muted"><span class="crm-ltr">{{ crm.money(d.value, d.currency) }}</span></div></div>
                            <span class="crm-chip qualified">{{ d.stage }}</span>
                        </div>
                    </div>
                    <div class="crm-card">
                        <h3 class="crm-card-title mb-2">{{ crm.t('contacts.tab_conversations') }}</h3>
                        <p v-if="!data.conversations.length" class="crm-muted crm-small mb-0">{{ crm.t('contacts.no_conversations') }}</p>
                        <div v-for="conv in data.conversations.slice(0, 4)" :key="conv.id" class="d-flex justify-content-between align-items-center py-2 gap-2">
                            <div class="min-w-0"><strong class="crm-small">{{ crm.sourceLabel(conv.platform) }}</strong><div class="crm-small crm-muted">{{ crm.ago(conv.last_message_at) }}</div></div>
                            <a :href="conv.url" class="crm-btn crm-btn-soft crm-btn-sm">{{ crm.t('common.open') }}</a>
                        </div>
                    </div>
                </aside>
            </div>
        </template>

        <crm-contact-drawer v-if="edit" :contact="c" @close="edit = false" @saved="load" />
        <crm-deal-modal v-if="dealModal" :defaults="{ contact_id: c.id, contact_name: c.name, company_id: c.company ? c.company.id : null }" @close="dealModal = false" @saved="load" />
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';
import CrmContactDrawer from '../ui/CrmContactDrawer.vue';
import CrmDealModal from '../ui/CrmDealModal.vue';
import CrmAiCard from '../ui/CrmAiCard.vue';
import CrmLogForm from '../ui/CrmLogForm.vue';

export default {
    name: 'CrmContactProfile',
    components: { CrmHeader, CrmContactDrawer, CrmDealModal, CrmAiCard, CrmLogForm },
    inject: ['crm'],
    props: { contactId: { type: Number, required: true } },
    data: () => ({ data: null, tab: 'activity', edit: false, dealModal: false }),
    computed: {
        c() { return this.data && this.data.contact; },
        tabs() {
            return [
                { key: 'activity', count: this.history.length }, { key: 'conversations', count: this.data.conversations.length },
                { key: 'deals', count: this.data.deals.length }, { key: 'leads', count: this.data.leads.length },
            ];
        },
        upcoming() { return this.data.timeline.filter((i) => i.kind === 'activity' && i.type === 'task' && !i.completed_at); },
        history() { return this.data.timeline.filter((i) => !(i.kind === 'activity' && i.type === 'task' && !i.completed_at)); },
        openDeals() { return this.data.deals.filter((d) => d.status === 'open'); },
        lifetime() { return this.data.deals.filter((d) => d.status === 'won').reduce((sum, d) => sum + d.value, 0); },
    },
    created() { this.load(); },
    methods: {
        async load() {
            try { this.data = await this.crm.api.get(`contacts/${this.contactId}`); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        stagesOf(deal) { const p = this.crm.pipelines.find((x) => x.id === deal.pipeline_id); return p ? p.stages.filter((st) => st.kind === 'open') : []; },
        async complete(task) {
            try { await this.crm.api.patch(`activities/${task.id}`, { completed: true }); this.load(); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        async remove() {
            if (!window.confirm(this.crm.t('common.confirm_delete'))) return;
            try { await this.crm.api.del(`contacts/${this.contactId}`); window.location.href = this.crm.pageUrl('contacts'); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        itemIcon(item) {
            if (item.kind === 'message') return 'bx-message-rounded-dots';
            return { task: 'bx-check-square', call: 'bx-phone', meeting: 'bx-calendar', note: 'bx-note', email: 'bx-envelope', event: 'bx-git-commit' }[item.type] || 'bx-circle';
        },
        itemTitle(item) {
            if (item.kind === 'message') return `${item.direction === 'inbound' ? this.crm.t('contacts.customer_said') : this.crm.t('contacts.business_said')} · ${this.crm.t('contacts.channel_message')}`;
            if (item.type === 'event') return this.crm.t('events.' + item.subject, { stage: (item.meta && item.meta.stage) || '' });
            return item.subject || this.crm.t('activity_types.' + item.type);
        },
    },
};
</script>
