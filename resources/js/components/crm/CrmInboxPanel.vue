<template>
    <div class="crm crm-inbox-panel" :dir="config.locale === 'ar' ? 'rtl' : 'ltr'">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="d-flex gap-2 align-items-center">{{ crm.t('inbox.crm') }}
                <span v-if="state && state.linked" class="crm-chip success">{{ crm.t('inbox.linked') }}</span>
            </strong>
            <a v-if="state && state.linked" :href="state.contact.url" class="crm-small" style="font-weight:700">{{ crm.t('inbox.open_profile') }} ↗</a>
        </div>

        <div v-if="!state" class="crm-small crm-muted">{{ crm.t('common.loading') }}</div>

        <template v-else-if="state.linked">
            <div class="crm-person mb-2">
                <span :class="['crm-avatar', crm.avatarClass(c.id)]">{{ crm.initials(c.name) }}</span>
                <div class="min-w-0">
                    <a :href="c.url" class="crm-person-name">{{ c.name }}</a>
                    <div class="d-flex gap-2 align-items-center">
                        <span :class="['crm-chip', c.lifecycle]">{{ crm.t('lifecycle.' + c.lifecycle) }}</span>
                        <span class="crm-score crm-small">{{ c.score }}<span class="crm-score-bar"><span :style="{ width: c.score + '%', background: crm.scoreColor(c.score) }"></span></span></span>
                    </div>
                </div>
            </div>
            <dl class="crm-dl crm-small mb-2">
                <dt>{{ crm.t('common.owner') }}</dt><dd>{{ c.owner ? c.owner.name : crm.t('common.unassigned') }}</dd>
                <dt>{{ crm.t('common.source') }}</dt><dd>{{ crm.sourceLabel(c.source) }}</dd>
                <dt v-if="c.tags.length">{{ crm.t('common.tags') }}</dt><dd v-if="c.tags.length"><span v-for="tag in c.tags" :key="tag" class="crm-tag">{{ tag }}</span></dd>
                <dt>{{ crm.t('inbox.lifetime') }}</dt><dd class="crm-ltr">{{ crm.money(state.lifetime_value) }}</dd>
            </dl>

            <div v-if="state.deal" class="crm-card mb-2" style="padding:10px 12px">
                <div class="d-flex justify-content-between gap-2"><strong class="crm-small">{{ state.deal.name }}</strong><span class="crm-chip qualified">{{ state.deal.stage }}</span></div>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <span class="crm-small crm-muted crm-ltr">{{ crm.money(state.deal.value, state.deal.currency) }}</span>
                    <select v-if="config.canWrite" class="crm-select" style="width:auto;height:28px;font-size:11.5px" :aria-label="crm.t('inbox.change_stage')" :value="state.deal.stage_id" @change="moveDeal($event.target.value)">
                        <option v-for="s in state.deal.stages" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
            </div>

            <div v-if="state.next_task" class="mb-2">
                <div class="crm-small crm-muted" style="font-weight:700;text-transform:uppercase;letter-spacing:.05em">{{ crm.t('inbox.next_task') }}</div>
                <label class="crm-check crm-small mt-1"><input type="checkbox" :disabled="!config.canWrite" @change="completeTask"> {{ state.next_task.subject }} <span class="crm-muted">· {{ crm.date(state.next_task.due_at, true) }}</span></label>
            </div>

            <form v-if="config.canWrite" class="mt-2" @submit.prevent="addNote">
                <input v-model.trim="note" class="crm-input" style="height:34px" :placeholder="crm.t('inbox.note_placeholder')" :aria-label="crm.t('inbox.add_note')">
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <button type="submit" class="crm-btn crm-btn-sm" :disabled="!note || busy"><i class="bx bx-note"></i> {{ crm.t('inbox.add_note') }}</button>
                    <button type="button" class="crm-btn crm-btn-sm" :disabled="!note || busy" @click="addNote('task')"><i class="bx bx-check-square"></i> {{ crm.t('inbox.add_task') }}</button>
                    <a :href="c.url" class="crm-btn crm-btn-sm"><i class="bx bx-briefcase"></i> {{ crm.t('contacts.create_deal') }}</a>
                </div>
            </form>
            <button v-if="config.canWrite" type="button" class="crm-btn crm-btn-ghost crm-btn-sm crm-muted mt-2" @click="unlink">{{ crm.t('inbox.unlink') }}</button>
        </template>

        <template v-else>
            <p class="crm-small crm-muted mb-2">{{ crm.t('inbox.not_linked') }}</p>
            <template v-if="config.canWrite">
                <div class="crm-seg mb-2 w-100">
                    <button type="button" :class="{ active: mode === 'match' }" @click="mode = 'match'">{{ crm.t('inbox.match_existing') }}</button>
                    <button type="button" :class="{ active: mode === 'create' }" @click="mode = 'create'">{{ crm.t('inbox.create_new') }}</button>
                </div>
                <template v-if="mode === 'match'">
                    <input v-model="q" class="crm-input mb-2" style="height:34px" type="search" :placeholder="crm.t('inbox.search_contacts')" :aria-label="crm.t('inbox.search_contacts')" @input="search">
                    <div class="crm-small crm-muted mb-1" style="font-weight:700">{{ crm.t('inbox.suggested') }}</div>
                    <p v-if="!candidates.length" class="crm-small crm-muted">{{ crm.t('inbox.no_suggestions') }}</p>
                    <label v-for="m in candidates" :key="m.id" class="crm-check crm-small py-1" style="align-items:center">
                        <input v-model="pick" type="radio" :value="m.id">
                        <span class="flex-grow-1"><strong>{{ m.name }}</strong><br><span class="crm-muted crm-ltr">{{ m.phone || m.email || m.sub }}</span></span>
                        <span v-if="m.match" :class="['crm-chip plain', m.match === 'phone' ? 'success' : 'warn']">{{ crm.t('inbox.match_' + m.match) }}</span>
                    </label>
                </template>
                <template v-else>
                    <div class="crm-row2">
                        <input v-model.trim="form.first_name" class="crm-input" style="height:34px" :placeholder="crm.t('contacts.first_name')" :aria-label="crm.t('contacts.first_name')">
                        <input v-model.trim="form.last_name" class="crm-input" style="height:34px" :placeholder="crm.t('contacts.last_name')" :aria-label="crm.t('contacts.last_name')">
                    </div>
                    <input v-model.trim="form.phone" class="crm-input mt-2" style="height:34px" dir="ltr" :placeholder="crm.t('common.phone')" :aria-label="crm.t('common.phone')">
                    <label class="crm-check crm-small mt-2"><input v-model="form.as_lead" type="checkbox"> {{ crm.t('inbox.as_lead') }}</label>
                </template>
                <label class="crm-check crm-small mt-2"><input v-model="remember" type="checkbox"> {{ crm.t('inbox.remember') }}</label>
                <button type="button" class="crm-btn crm-btn-primary crm-btn-sm w-100 justify-content-center mt-2" :disabled="busy || (mode === 'match' ? !pick : !form.first_name)" @click="link">
                    <i class="bx bx-link"></i> {{ crm.t('inbox.link') }}
                </button>
            </template>
        </template>
    </div>
</template>

<script>
import './crm.css';
import { createContext, } from './context';

export default {
    name: 'CrmInboxPanel',
    props: { conversationId: { type: Number, required: true }, config: { type: Object, required: true } },
    data() {
        const crm = createContext({ props: { canWrite: this.config.canWrite, currency: this.config.currency }, urls: { api: this.config.api, pages: {} }, messages: this.config.messages, locale: this.config.locale });
        return { crm, state: null, mode: 'match', q: '', results: [], pick: null, remember: true, busy: false, note: '', form: { first_name: '', last_name: '', phone: '', as_lead: true }, timer: null };
    },
    computed: {
        c() { return this.state && this.state.contact; },
        candidates() { return this.q.trim().length > 1 ? this.results : (this.state.suggestions || []); },
    },
    created() { this.load(); },
    methods: {
        async load() {
            try {
                this.state = await this.crm.api.get(`inbox/${this.conversationId}`);
                if (!this.state.linked) {
                    const [first, ...rest] = (this.state.customer.name || '').split(' ');
                    this.form = { first_name: first || '', last_name: rest.join(' '), phone: this.state.customer.platform === 'whatsapp' ? this.state.customer.external_id : '', as_lead: true };
                    if (!(this.state.suggestions || []).length) this.mode = 'create';
                }
            } catch (e) { this.state = { linked: false, suggestions: [], customer: {} }; }
        },
        search() {
            clearTimeout(this.timer);
            this.timer = setTimeout(async () => {
                const res = await this.crm.api.get('search', { q: this.q }).catch(() => ({ contacts: [] }));
                this.results = res.contacts;
            }, 250);
        },
        async call(fn) {
            this.busy = true;
            try { this.state = await fn(); this.note = ''; } catch (e) { this.crm.notify ? this.crm.notify(this.crm.error(e), 'error') : window.alert(this.crm.error(e)); }
            this.busy = false;
        },
        link() {
            const payload = this.mode === 'match' ? { contact_id: this.pick, remember: this.remember } : { ...this.form, remember: this.remember };
            return this.call(() => this.crm.api.post(`inbox/${this.conversationId}/link`, payload));
        },
        unlink() { return this.call(() => this.crm.api.post(`inbox/${this.conversationId}/unlink`)); },
        addNote(type) { return this.call(() => this.crm.api.post(`inbox/${this.conversationId}/activities`, { type: type === 'task' ? 'task' : 'note', subject: this.note })); },
        async completeTask() { await this.crm.api.patch(`activities/${this.state.next_task.id}`, { completed: true }).catch(() => null); this.load(); },
        async moveDeal(stageId) { await this.crm.api.post(`deals/${this.state.deal.id}/move`, { stage_id: Number(stageId) }).catch((e) => window.alert(this.crm.error(e))); this.load(); },
    },
};
</script>
