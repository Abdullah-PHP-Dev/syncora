<template>
    <div>
        <crm-header :title="crm.t('activities.title')">
            <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-primary" @click="openTask"><i class="bx bx-plus"></i> {{ crm.t('activities.new_task') }}</button>
        </crm-header>

        <nav class="crm-tabs" role="tablist">
            <button v-for="tb in ['tasks', 'calendar', 'log', 'team']" :key="tb" type="button" role="tab" :class="['crm-tab', { active: tab === tb }]" @click="setTab(tb)">{{ crm.t('activities.tab_' + tb) }}</button>
        </nav>

        <template v-if="tab === 'tasks'">
            <div class="crm-toolbar">
                <div class="crm-seg" role="group">
                    <button v-for="f in ['open', 'today', 'overdue', 'done']" :key="f" type="button" :class="{ active: filter === f }" @click="filter = f; load()">
                        {{ crm.t('activities.filter_' + f) }}<span v-if="counts[f] !== undefined" class="crm-muted"> {{ counts[f] }}</span>
                    </button>
                </div>
                <div class="crm-seg" role="group" :aria-label="crm.t('common.owner')">
                    <button v-for="o in ['', 'me', 'unassigned']" :key="o" type="button" :class="{ active: who === o }" @click="who = o; load()">{{ crm.t('activities.who_' + (o || 'team')) }}</button>
                </div>
                <select v-model="priority" class="crm-select" style="width:auto" :aria-label="crm.t('activities.priority')" @change="load">
                    <option value="">{{ crm.t('activities.priority') }}: {{ crm.t('common.any') }}</option>
                    <option v-for="p in ['high', 'medium', 'low']" :key="p" :value="p">{{ crm.t('priorities.' + p) }}</option>
                </select>
            </div>
            <div class="crm-small crm-muted d-flex gap-3 flex-wrap mb-2">
                <span><i class="bx bx-calendar-week"></i> {{ crm.t('activities.sum_week', { n: counts.week || 0 }) }}</span>
                <span><i class="bx bx-check-double"></i> {{ crm.t('activities.sum_completed', { n: counts.completed_week || 0 }) }}</span>
                <span><i class="bx bx-bot"></i> {{ crm.t('activities.sum_auto', { n: counts.auto_created || 0 }) }}</span>
            </div>
            <div class="crm-card" style="padding:6px 14px">
                <p v-if="!loading && !items.length" class="crm-muted crm-small py-3 mb-0">{{ crm.t('activities.empty_tasks') }}</p>
                <div v-for="a in items" :key="a.id" class="d-flex gap-3 align-items-center py-2" style="border-bottom:1px solid #f3f1f9">
                    <input type="checkbox" :checked="!!a.completed_at" :disabled="!crm.canWrite" :aria-label="a.subject" style="accent-color:var(--crm-purple);width:16px;height:16px" @change="toggle(a)">
                    <div class="flex-grow-1 min-w-0">
                        <div :style="a.completed_at ? 'text-decoration:line-through;color:var(--crm-muted)' : 'font-weight:600'">{{ a.subject }}</div>
                        <div class="crm-small crm-muted">
                            <a v-if="a.contact" :href="a.contact.url">{{ a.contact.name }}</a><span v-if="a.deal"> · <a :href="crm.pageUrl('deal', a.deal.id)">{{ a.deal.name }}</a></span>
                            <span v-if="a.owner"> · {{ a.owner.name }}</span>
                        </div>
                    </div>
                    <span v-if="a.priority" :class="['crm-chip plain', a.priority === 'high' ? 'danger' : a.priority === 'medium' ? 'warn' : '']">{{ crm.t('priorities.' + a.priority) }}</span>
                    <span v-if="a.due_at" :class="['crm-chip plain', a.overdue ? 'danger' : isToday(a.due_at) ? 'warn' : '']">{{ a.overdue ? crm.t('common.overdue') + ' · ' : '' }}{{ crm.date(a.due_at, true) }}</span>
                    <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-ghost crm-icon-btn crm-btn-sm" :aria-label="crm.t('common.delete')" @click="remove(a)"><i class="bx bx-trash"></i></button>
                </div>
            </div>
        </template>

        <template v-else-if="tab === 'calendar'">
            <div class="crm-toolbar">
                <button type="button" class="crm-btn crm-btn-sm crm-icon-btn" :aria-label="crm.t('common.prev')" @click="shiftMonth(-1)"><i class="bx bx-chevron-left bx-flip-horizontal-rtl"></i></button>
                <strong style="min-width:150px;text-align:center">{{ monthLabel }}</strong>
                <button type="button" class="crm-btn crm-btn-sm crm-icon-btn" :aria-label="crm.t('common.next')" @click="shiftMonth(1)"><i class="bx bx-chevron-right bx-flip-horizontal-rtl"></i></button>
                <button type="button" class="crm-btn crm-btn-sm" @click="month = new Date(); load()">{{ crm.t('common.today') }}</button>
            </div>
            <div class="crm-card" style="padding:0;overflow-x:auto">
                <div style="display:grid;grid-template-columns:repeat(7,minmax(110px,1fr));min-width:770px">
                    <div v-for="d in weekdays" :key="d" class="crm-small crm-muted" style="padding:8px 10px;font-weight:700;border-bottom:1px solid var(--crm-border)">{{ d }}</div>
                    <div v-for="(cell, i) in calendar" :key="i" :style="{ minHeight: '104px', padding: '6px', borderBottom: '1px solid #f3f1f9', borderInlineEnd: '1px solid #f3f1f9', background: cell.inMonth ? '#fff' : '#fafafd' }">
                        <div :class="['crm-small', cell.today ? '' : 'crm-muted']" :style="cell.today ? 'font-weight:800;color:var(--crm-purple)' : ''">{{ cell.day }}</div>
                        <div v-for="a in cell.items" :key="a.id" class="crm-small" :title="a.subject"
                            :style="{ background: a.type === 'meeting' ? '#eff4ff' : a.overdue ? '#fef2f2' : '#f3effe', borderRadius: '6px', padding: '2px 6px', marginTop: '3px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', textDecoration: a.completed_at ? 'line-through' : 'none' }">
                            {{ timeOf(a.due_at) }} {{ a.subject }}
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template v-else-if="tab === 'log'">
            <div class="crm-card">
                <p v-if="!loading && !items.length" class="crm-muted crm-small mb-0">{{ crm.t('activities.empty_log') }}</p>
                <ul class="crm-timeline">
                    <li v-for="a in items" :key="a.id">
                        <span class="crm-tl-icon"><i :class="['bx', { call: 'bx-phone', meeting: 'bx-calendar', note: 'bx-note', email: 'bx-envelope' }[a.type]]"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between gap-2"><strong class="crm-small">{{ crm.t('activity_types.' + a.type) }}<span v-if="a.contact"> · <a :href="a.contact.url">{{ a.contact.name }}</a></span></strong><span class="crm-small crm-muted">{{ crm.ago(a.created_at) }}</span></div>
                            <div v-if="a.author" class="crm-small crm-muted">{{ crm.t('common.by', { name: a.author.name }) }}<span v-if="a.outcome"> · {{ crm.opt('outcomes', a.outcome) }}</span><span v-if="a.duration_minutes"> · {{ crm.t('activities.minutes', { n: a.duration_minutes }) }}</span></div>
                            <div v-if="a.body || a.subject" class="crm-bubble" dir="auto">{{ a.body || a.subject }}</div>
                        </div>
                    </li>
                </ul>
            </div>
        </template>

        <template v-else>
            <div class="crm-table-wrap">
                <table class="crm-table">
                    <thead><tr>
                        <th>{{ crm.t('activities.member') }}</th><th>{{ crm.t('activities.calls') }}</th><th>{{ crm.t('activities.meetings') }}</th><th>{{ crm.t('activities.notes') }}</th>
                        <th>{{ crm.t('activities.emails') }}</th><th>{{ crm.t('activities.won') }}</th><th>{{ crm.t('activities.on_time') }}</th><th>{{ crm.t('activities.last_active') }}</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="r in team" :key="r.member.id">
                            <td><span class="crm-person"><span :class="['crm-avatar sm', crm.avatarClass(r.member.id)]">{{ crm.initials(r.member.name) }}</span><strong>{{ r.member.name }}</strong></span></td>
                            <td>{{ r.calls }}</td><td>{{ r.meetings }}</td><td>{{ r.notes }}</td><td>{{ r.emails }}</td><td>{{ r.won }}</td>
                            <td><span v-if="r.on_time !== null" class="crm-score">{{ r.on_time }}%<span class="crm-score-bar"><span :style="{ width: r.on_time + '%', background: 'var(--crm-grad)' }"></span></span></span><span v-else class="crm-muted">—</span></td>
                            <td class="crm-small crm-muted">{{ r.last_active ? crm.ago(r.last_active) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="crm-pager"><span>{{ crm.t('activities.this_week') }}</span></div>
            </div>
        </template>

        <crm-drawer v-if="task" :title="crm.t('activities.new_task')" :close-label="crm.t('common.close')" @close="task = null">
            <div class="crm-field"><label class="crm-label" for="tk-type">{{ crm.t('activities.type') }}</label>
                <select id="tk-type" v-model="task.type" class="crm-select"><option v-for="t in crm.options.activityTypes" :key="t" :value="t">{{ crm.t('activity_types.' + t) }}</option></select></div>
            <div class="crm-field"><label class="crm-label" for="tk-sub">{{ crm.t('activities.subject') }} *</label><input id="tk-sub" v-model.trim="task.subject" class="crm-input" maxlength="200"></div>
            <div class="crm-field"><label class="crm-label" for="tk-body">{{ crm.t('activities.details') }}</label><textarea id="tk-body" v-model="task.body" class="crm-textarea"></textarea></div>
            <div class="crm-row2">
                <div class="crm-field"><label class="crm-label" for="tk-due">{{ crm.t('activities.due') }}</label><input id="tk-due" v-model="task.due_at" type="datetime-local" class="crm-input"></div>
                <div class="crm-field"><label class="crm-label" for="tk-pri">{{ crm.t('activities.priority') }}</label>
                    <select id="tk-pri" v-model="task.priority" class="crm-select"><option v-for="p in ['low', 'medium', 'high']" :key="p" :value="p">{{ crm.t('priorities.' + p) }}</option></select></div>
            </div>
            <div class="crm-row2">
                <div class="crm-field"><label class="crm-label" for="tk-own">{{ crm.t('activities.assignee') }}</label>
                    <select id="tk-own" v-model="task.owner_id" class="crm-select"><option v-for="m in crm.members" :key="m.id" :value="m.id">{{ m.name }}</option></select></div>
            </div>
            <div class="crm-field"><label class="crm-label" for="tk-ct">{{ crm.t('activities.related_to') }}</label>
                <select id="tk-ct" v-model="task.contact_id" class="crm-select"><option :value="null">{{ crm.t('common.none') }}</option><option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
            <template #footer>
                <button type="button" class="crm-btn" @click="task = null">{{ crm.t('common.cancel') }}</button>
                <button type="button" class="crm-btn crm-btn-primary" :disabled="!task.subject || busy" @click="saveTask"><i class="bx bx-check"></i> {{ crm.t('common.save') }}</button>
            </template>
        </crm-drawer>
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';
import CrmDrawer from '../ui/CrmDrawer.vue';

export default {
    name: 'CrmActivities',
    components: { CrmHeader, CrmDrawer },
    inject: ['crm'],
    data: () => ({ tab: 'tasks', filter: 'open', who: '', priority: '', items: [], counts: {}, team: [], loading: true, month: new Date(), task: null, contacts: [], busy: false }),
    computed: {
        monthLabel() { return this.month.toLocaleDateString(this.crm.locale === 'ar' ? 'ar-SA-u-nu-latn-ca-gregory' : 'en-GB', { month: 'long', year: 'numeric' }); },
        weekdays() {
            const base = new Date(2024, 0, 7); // a Sunday
            return [...Array(7)].map((_, i) => new Date(base.getFullYear(), 0, 7 + i).toLocaleDateString(this.crm.locale === 'ar' ? 'ar' : 'en', { weekday: 'short' }));
        },
        calendar() {
            const first = new Date(this.month.getFullYear(), this.month.getMonth(), 1);
            const start = new Date(first); start.setDate(1 - first.getDay());
            const today = new Date().toDateString();
            return [...Array(42)].map((_, i) => {
                const d = new Date(start); d.setDate(start.getDate() + i);
                return {
                    day: d.getDate(), inMonth: d.getMonth() === this.month.getMonth(), today: d.toDateString() === today,
                    items: this.items.filter((a) => a.due_at && new Date(a.due_at).toDateString() === d.toDateString()),
                };
            });
        },
    },
    created() { this.load(); },
    methods: {
        setTab(tab) { this.tab = tab; this.load(); },
        async load() {
            this.loading = true;
            try {
                if (this.tab === 'team') { this.team = (await this.crm.api.get('activities/team')).data; }
                else {
                    const params = { view: this.tab === 'tasks' ? this.filter : this.tab, owner_id: this.who, priority: this.tab === 'tasks' ? this.priority : '' };
                    if (this.tab === 'calendar') {
                        const y = this.month.getFullYear(); const m = this.month.getMonth();
                        Object.assign(params, { from: new Date(y, m, -6).toISOString(), to: new Date(y, m + 1, 7).toISOString() });
                    }
                    const res = await this.crm.api.get('activities', params);
                    this.items = res.data; this.counts = res.counts;
                }
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.loading = false;
        },
        shiftMonth(step) { this.month = new Date(this.month.getFullYear(), this.month.getMonth() + step, 1); this.load(); },
        isToday(value) { return new Date(value).toDateString() === new Date().toDateString(); },
        timeOf(value) { return value ? new Date(value).toLocaleTimeString(this.crm.locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', { hour: '2-digit', minute: '2-digit' }) : ''; },
        async toggle(a) {
            try { await this.crm.api.patch(`activities/${a.id}`, { completed: !a.completed_at }); this.load(); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        async remove(a) {
            if (!window.confirm(this.crm.t('common.confirm_delete'))) return;
            try { await this.crm.api.del(`activities/${a.id}`); this.load(); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        async openTask() {
            this.task = { type: 'task', subject: '', body: '', due_at: '', priority: 'medium', owner_id: this.crm.actorId, contact_id: null };
            if (!this.contacts.length) this.contacts = (await this.crm.api.get('contacts', { sort: 'last_activity_at' }).catch(() => ({ data: [] }))).data;
        },
        async saveTask() {
            this.busy = true;
            try {
                await this.crm.api.post('activities', { ...this.task, due_at: this.task.due_at || null });
                this.task = null; this.crm.notify(this.crm.t('common.saved')); this.load();
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.busy = false;
        },
    },
};
</script>
