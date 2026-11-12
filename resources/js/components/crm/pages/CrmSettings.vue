<template>
    <div>
        <crm-header :title="crm.t('settings.title')" />
        <div class="crm-grid" style="grid-template-columns: 220px minmax(0, 1fr); align-items:start">
            <nav class="crm-card" style="padding:10px" :aria-label="crm.t('settings.title')">
                <button v-for="s in sections" :key="s.key" type="button" :class="['crm-btn crm-btn-ghost w-100 justify-content-start mb-1', { 'crm-btn-soft': section === s.key }]" @click="section = s.key">
                    <i :class="['bx', s.icon]"></i> {{ crm.t('settings.section_' + s.key) }}
                </button>
            </nav>

            <div v-if="!info" class="crm-card crm-muted">{{ crm.t('common.loading') }}</div>

            <crm-settings-lists v-else-if="section === 'lists'" :settings="info.settings" :defaults="info.defaults || {}" @saved="load" />
            <crm-settings-scoring v-else-if="section === 'scoring'" />
            <crm-settings-notifications v-else-if="section === 'notifications'" />
            <crm-settings-ai v-else-if="section === 'ai'" :settings="info.settings" @saved="load" />

            <!-- S-01 / S-07 / S-08 -->
            <section v-else-if="section === 'general'" class="crm-card">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div><h3 class="crm-card-title">{{ crm.t('settings.section_general') }}</h3><p class="crm-card-sub mb-0">{{ crm.t('settings.general_hint') }}</p></div>
                    <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-primary" :disabled="busy" @click="saveGeneral">{{ crm.t('common.save') }}</button>
                </div>
                <div class="crm-field d-flex justify-content-between gap-3 align-items-center flex-wrap">
                    <div><strong>{{ crm.t('settings.visibility') }}</strong><div class="crm-small crm-muted">{{ crm.t('settings.visibility_hint') }}</div></div>
                    <select v-model="general.member_visibility" class="crm-select" style="width:auto" :disabled="!crm.canWrite"><option value="all">{{ crm.t('settings.vis_all') }}</option><option value="own">{{ crm.t('settings.vis_own') }}</option></select>
                </div>
                <div v-if="info.team.length" class="crm-card mb-3" style="padding:12px">
                    <div class="crm-small" style="font-weight:700">{{ crm.t('settings.per_member') }}</div>
                    <div v-for="m in info.team" :key="m.id" class="d-flex justify-content-between align-items-center py-2 gap-2 flex-wrap" style="border-bottom:1px solid #f3f1f9">
                        <span><strong>{{ m.name }}</strong> <span class="crm-small crm-muted">· {{ m.role === 'admin' ? crm.t('settings.role_admin') : crm.t('settings.role_member') }} · CRM: {{ crm.t('settings.perm_' + m.crm) }}</span></span>
                        <select v-if="m.role !== 'admin'" class="crm-select" style="width:auto;height:32px" :value="general.member_overrides[m.id] || ''" :disabled="!crm.canWrite" @change="setOverride(m.id, $event.target.value)">
                            <option value="">{{ crm.t('settings.vis_default') }}</option><option value="all">{{ crm.t('settings.vis_all') }}</option><option value="own">{{ crm.t('settings.vis_own') }}</option>
                        </select>
                        <span v-else class="crm-small crm-muted">{{ crm.t('settings.admin_sees_all') }}</span>
                    </div>
                    <a :href="crm.pageUrl('team')" class="crm-small d-inline-block mt-2">{{ crm.t('settings.manage_team') }} →</a>
                </div>
                <div class="crm-field d-flex justify-content-between gap-3 align-items-center flex-wrap">
                    <div><strong>{{ crm.t('settings.idle_days') }}</strong><div class="crm-small crm-muted">{{ crm.t('settings.idle_days_hint') }}</div></div>
                    <input v-model.number="general.idle_days" type="number" min="1" max="90" class="crm-input" style="width:90px" dir="ltr" :disabled="!crm.canWrite">
                </div>
                <label class="crm-field d-flex justify-content-between gap-3 align-items-center" style="cursor:pointer">
                    <span><strong>{{ crm.t('settings.require_lost_reason') }}</strong><span class="crm-small crm-muted d-block">{{ crm.t('settings.require_lost_reason_hint') }}</span></span>
                    <input v-model="general.require_lost_reason" type="checkbox" style="width:18px;height:18px;accent-color:var(--crm-purple)" :disabled="!crm.canWrite">
                </label>
                <label class="crm-field d-flex justify-content-between gap-3 align-items-center" style="cursor:pointer">
                    <span><strong>{{ crm.t('settings.auto_create') }}</strong><span class="crm-small crm-muted d-block">{{ crm.t('settings.auto_create_hint') }}</span></span>
                    <input v-model="general.auto_create_contacts" type="checkbox" style="width:18px;height:18px;accent-color:var(--crm-purple)" :disabled="!crm.canWrite">
                </label>
                <div class="crm-alert info"><i class="bx bx-id-card"></i>
                    {{ info.limits.limit === -1 ? crm.t('settings.limit_unlimited', { used: crm.number(info.limits.used) }) : crm.t('settings.limit_of', { used: crm.number(info.limits.used), limit: crm.number(info.limits.limit) }) }}
                </div>
            </section>

            <!-- S-02 -->
            <section v-else-if="section === 'pipelines'" class="crm-card">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div><h3 class="crm-card-title">{{ crm.t('settings.pipelines') }}</h3><p class="crm-card-sub mb-0">{{ crm.t('settings.pipelines_hint') }}</p></div>
                    <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-soft crm-btn-sm" @click="addPipeline"><i class="bx bx-plus"></i> {{ crm.t('settings.new_pipeline') }}</button>
                </div>
                <div class="crm-seg mb-3" role="tablist">
                    <button v-for="p in pipelines" :key="p.id" type="button" :class="{ active: current && current.id === p.id }" @click="select(p)">{{ p.name }}<span v-if="p.is_default" class="crm-muted"> · {{ crm.t('settings.default') }}</span></button>
                </div>
                <form v-if="draft" @submit.prevent="savePipeline">
                    <div class="crm-row2">
                        <div class="crm-field"><label class="crm-label" for="pl-name">{{ crm.t('settings.pipeline_name') }}</label><input id="pl-name" v-model.trim="draft.name" class="crm-input" :disabled="!crm.canWrite"></div>
                        <div class="crm-field d-flex align-items-end"><label class="crm-check"><input v-model="draft.is_default" type="checkbox" :disabled="!crm.canWrite || current.is_default"> {{ crm.t('settings.make_default') }}</label></div>
                    </div>
                    <label class="crm-label">{{ crm.t('settings.stages') }}</label>
                    <div v-for="(s, i) in draft.stages" :key="s.key" class="d-flex gap-2 align-items-center mb-2 flex-wrap">
                        <span class="d-flex flex-column">
                            <button type="button" class="crm-btn crm-btn-ghost crm-btn-sm crm-icon-btn" style="height:16px" :disabled="i === 0 || !crm.canWrite" aria-label="↑" @click="moveStage(i, -1)"><i class="bx bx-chevron-up"></i></button>
                            <button type="button" class="crm-btn crm-btn-ghost crm-btn-sm crm-icon-btn" style="height:16px" :disabled="i === draft.stages.length - 1 || !crm.canWrite" aria-label="↓" @click="moveStage(i, 1)"><i class="bx bx-chevron-down"></i></button>
                        </span>
                        <input v-model="s.color" type="color" :disabled="!crm.canWrite" aria-label="color" style="width:32px;height:32px;border:0;background:none;padding:0">
                        <input v-model.trim="s.name" class="crm-input" style="flex:1;min-width:140px" :disabled="!crm.canWrite" :aria-label="crm.t('common.name')">
                        <select v-model="s.kind" class="crm-select" style="width:110px" :disabled="!crm.canWrite" :aria-label="crm.t('common.status')"><option v-for="k in ['open', 'won', 'lost']" :key="k" :value="k">{{ crm.t('settings.kind_' + k) }}</option></select>
                        <input v-model.number="s.probability" type="number" min="0" max="100" class="crm-input" style="width:84px" dir="ltr" :disabled="!crm.canWrite" :aria-label="crm.t('settings.probability')">
                        <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-ghost crm-icon-btn crm-btn-sm" :aria-label="crm.t('common.delete')" @click="draft.stages.splice(i, 1)"><i class="bx bx-trash"></i></button>
                    </div>
                    <div v-if="crm.canWrite" class="d-flex justify-content-between mt-3 flex-wrap gap-2">
                        <button type="button" class="crm-btn crm-btn-sm" @click="draft.stages.splice(lastOpen + 1, 0, { key: Math.random(), name: '', kind: 'open', probability: 50, color: '#7c3aed' })"><i class="bx bx-plus"></i> {{ crm.t('settings.add_stage') }}</button>
                        <span class="d-flex gap-2">
                            <button v-if="!current.is_default" type="button" class="crm-btn crm-btn-sm" @click="removePipeline"><i class="bx bx-trash"></i> {{ crm.t('common.delete') }}</button>
                            <button type="submit" class="crm-btn crm-btn-primary" :disabled="busy"><i class="bx bx-check"></i> {{ crm.t('common.save') }}</button>
                        </span>
                    </div>
                </form>
            </section>

            <!-- S-04 -->
            <section v-else-if="section === 'fields'" class="crm-card">
                <h3 class="crm-card-title">{{ crm.t('settings.section_fields') }}</h3><p class="crm-card-sub">{{ crm.t('settings.fields_hint') }}</p>
                <div v-for="f in info.fields" :key="f.id" class="d-flex justify-content-between align-items-center py-2 gap-2" style="border-bottom:1px solid #f3f1f9">
                    <span><strong>{{ f.label }}</strong> <span class="crm-small crm-muted">· {{ crm.t('settings.type_' + f.type) }}<span v-if="f.options && f.options.length"> · {{ f.options.join(', ') }}</span></span></span>
                    <button v-if="crm.canWrite" type="button" class="crm-btn crm-btn-ghost crm-icon-btn crm-btn-sm" :aria-label="crm.t('common.delete')" @click="removeField(f)"><i class="bx bx-trash"></i></button>
                </div>
                <p v-if="!info.fields.length" class="crm-small crm-muted">{{ crm.t('settings.no_fields') }}</p>
                <form v-if="crm.canWrite" class="d-flex gap-2 flex-wrap mt-3 align-items-end" @submit.prevent="addField">
                    <label class="crm-small">{{ crm.t('settings.field_label') }}<input v-model.trim="field.label" class="crm-input mt-1" maxlength="80" required></label>
                    <label class="crm-small">{{ crm.t('settings.field_type') }}<select v-model="field.type" class="crm-select mt-1"><option v-for="t in ['text', 'number', 'date', 'dropdown']" :key="t" :value="t">{{ crm.t('settings.type_' + t) }}</option></select></label>
                    <label v-if="field.type === 'dropdown'" class="crm-small" style="flex:1;min-width:200px">{{ crm.t('settings.field_options') }}<input v-model="field.options" class="crm-input mt-1" :placeholder="crm.t('common.tags_hint')"></label>
                    <button type="submit" class="crm-btn crm-btn-primary" :disabled="busy || !field.label"><i class="bx bx-plus"></i> {{ crm.t('settings.add_field') }}</button>
                </form>
            </section>

            <!-- S-05 -->
            <section v-else-if="section === 'tags'" class="crm-card">
                <h3 class="crm-card-title">{{ crm.t('settings.section_tags') }}</h3><p class="crm-card-sub">{{ crm.t('settings.tags_hint') }}</p>
                <p v-if="!info.tags.length" class="crm-small crm-muted">{{ crm.t('settings.no_tags') }}</p>
                <div v-for="t in info.tags" :key="t.tag" class="d-flex justify-content-between align-items-center py-2 gap-2" style="border-bottom:1px solid #f3f1f9">
                    <span><span class="crm-tag">{{ t.tag }}</span> <span class="crm-small crm-muted">{{ crm.t('settings.tag_count', { n: t.count }) }}</span></span>
                    <span v-if="crm.canWrite" class="d-flex gap-1">
                        <button type="button" class="crm-btn crm-btn-sm" @click="renameTag(t.tag)">{{ crm.t('settings.rename') }}</button>
                        <button type="button" class="crm-btn crm-btn-ghost crm-icon-btn crm-btn-sm" :aria-label="crm.t('common.delete')" @click="deleteTag(t.tag)"><i class="bx bx-trash"></i></button>
                    </span>
                </div>
            </section>

            <!-- S-10 -->
            <section v-else class="crm-card">
                <h3 class="crm-card-title">{{ crm.t('settings.section_capture') }}</h3><p class="crm-card-sub">{{ crm.t('settings.capture_hint') }}</p>
                <div class="crm-card mb-3" style="padding:14px">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                        <strong><i class="bx bxl-meta"></i> {{ crm.t('settings.meta_lead_ads') }}</strong>
                        <a :href="info.capture.hub_url" class="crm-btn crm-btn-soft crm-btn-sm"><i class="bx bx-link"></i> {{ crm.t('settings.leads_connect') }}</a>
                    </div>
                    <p class="crm-small crm-muted mb-2">{{ crm.t('settings.meta_lead_ads_hint') }}</p>
                    <p v-if="!info.capture.meta_pages.length" class="crm-small mb-0">{{ crm.t('settings.no_meta_pages') }}</p>
                    <div v-for="p in info.capture.meta_pages" :key="p.id" class="d-flex justify-content-between align-items-center gap-2 py-2 flex-wrap" style="border-bottom:1px solid #f3f1f9">
                        <span class="crm-small"><strong>{{ p.name }}</strong>
                            <span :class="['crm-chip plain ms-1', leadState(p).tone]">{{ crm.t('settings.lead_state_' + leadState(p).key) }}</span>
                            <span v-if="p.synced_at" class="crm-muted"> · {{ crm.t('settings.last_sync', { when: crm.ago(p.synced_at * 1000) }) }}</span></span>
                        <span v-if="crm.canWrite && p.has_token && p.granted" class="d-flex gap-1">
                            <button type="button" class="crm-btn crm-btn-sm" :disabled="busy" @click="leadAction(p, 'subscribe')">{{ crm.t('settings.subscribe') }}</button>
                            <button type="button" class="crm-btn crm-btn-sm" :disabled="busy" @click="leadAction(p, 'sync')"><i class="bx bx-sync"></i> {{ crm.t('settings.sync_now') }}</button>
                        </span>
                    </div>
                </div>
                <div class="crm-card mb-3" style="padding:14px">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                        <strong><i class="bx bxl-tiktok"></i> {{ crm.t('settings.tiktok_lead_ads') }}</strong>
                        <a :href="info.capture.hub_url" class="crm-btn crm-btn-soft crm-btn-sm"><i class="bx bx-link"></i> {{ crm.t('settings.leads_connect') }}</a>
                    </div>
                    <p class="crm-small crm-muted mb-2">{{ crm.t('settings.tiktok_lead_ads_hint') }}</p>
                    <p v-if="!info.capture.tiktok_advertisers.length" class="crm-small mb-0">{{ crm.t('settings.no_tiktok_advertisers') }}</p>
                    <div v-for="a in info.capture.tiktok_advertisers" :key="a.id" class="d-flex justify-content-between align-items-center gap-2 py-2 flex-wrap" style="border-bottom:1px solid #f3f1f9">
                        <span class="crm-small"><strong>{{ a.name }}</strong>
                            <span :class="['crm-chip plain ms-1', a.subscribed ? 'success' : 'warn']">{{ crm.t(a.subscribed ? 'settings.lead_state_live' : 'settings.lead_state_not_subscribed') }}</span>
                            <span v-if="a.synced_at" class="crm-muted"> · {{ crm.t('settings.last_sync', { when: crm.ago(a.synced_at * 1000) }) }}</span>
                            <span v-if="!a.subscribed && a.error" class="d-block crm-muted" dir="ltr">{{ a.error }}</span></span>
                        <span v-if="crm.canWrite" class="d-flex gap-1">
                            <button type="button" class="crm-btn crm-btn-sm" :disabled="busy" @click="leadAction(a, 'subscribe')">{{ crm.t('settings.subscribe') }}</button>
                            <button v-if="a.subscribed" type="button" class="crm-btn crm-btn-sm" :disabled="busy" @click="leadAction(a, 'sync')"><i class="bx bx-sync"></i> {{ crm.t('settings.sync_now') }}</button>
                        </span>
                    </div>
                </div>
                <div class="crm-card" style="padding:14px">
                    <strong><i class="bx bx-globe"></i> {{ crm.t('settings.website_forms') }}</strong>
                    <p class="crm-small crm-muted mb-2">{{ crm.t('settings.website_forms_hint') }}</p>
                    <div class="d-flex gap-2">
                        <input class="crm-input" readonly dir="ltr" :value="info.capture.url" :aria-label="crm.t('settings.website_forms')" @focus="$event.target.select()">
                        <button type="button" class="crm-btn" @click="copy(info.capture.url)"><i class="bx bx-copy"></i></button>
                        <button v-if="crm.canWrite" type="button" class="crm-btn" @click="rotate">{{ crm.t('settings.rotate') }}</button>
                    </div>
                    <pre class="crm-bubble mt-2" dir="ltr" style="white-space:pre-wrap;font-size:11.5px">POST {{ info.capture.url }}
Content-Type: application/json

{ "name": "Noura Al-Qahtani", "phone": "+966552148830", "email": "noura@example.com",
  "message": "9-seat majlis", "source": "website", "campaign": "Ramadan", "submission_id": "abc-123" }</pre>
                </div>
            </section>
        </div>
    </div>
</template>

<script>
import CrmHeader from '../ui/CrmHeader.vue';
import CrmSettingsLists from '../ui/CrmSettingsLists.vue';
import CrmSettingsScoring from '../ui/CrmSettingsScoring.vue';
import CrmSettingsNotifications from '../ui/CrmSettingsNotifications.vue';
import CrmSettingsAi from '../ui/CrmSettingsAi.vue';

export default {
    name: 'CrmSettings',
    components: { CrmHeader, CrmSettingsLists, CrmSettingsScoring, CrmSettingsNotifications, CrmSettingsAi },
    inject: ['crm'],
    data() {
        return {
            section: 'general', info: null, general: null, busy: false, field: { label: '', type: 'text', options: '' },
            pipelines: this.crm.pipelines, current: null, draft: null,
            sections: [{ key: 'general', icon: 'bx-cog' }, { key: 'pipelines', icon: 'bx-git-branch' }, { key: 'fields', icon: 'bx-list-plus' }, { key: 'tags', icon: 'bx-purchase-tag' }, { key: 'lists', icon: 'bx-list-ul' }, { key: 'scoring', icon: 'bx-trending-up' }, { key: 'notifications', icon: 'bx-bell' }, { key: 'ai', icon: 'bxs-magic-wand' }, { key: 'capture', icon: 'bx-broadcast' }],
        };
    },
    computed: { lastOpen() { return this.draft.stages.map((s) => s.kind).lastIndexOf('open'); } },
    created() {
        if (this.pipelines.length) this.select(this.pipelines[0]);
        this.load();
    },
    methods: {
        async load() {
            try {
                this.info = await this.crm.api.get('settings');
                this.general = { ...this.info.settings, member_overrides: Array.isArray(this.info.settings.member_overrides) ? {} : { ...this.info.settings.member_overrides } };
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        async run(fn) {
            this.busy = true;
            try { const r = await fn(); this.crm.notify(this.crm.t('common.saved')); return r; } catch (e) { this.crm.notify(this.crm.error(e), 'error'); return null; } finally { this.busy = false; }
        },
        setOverride(id, value) { if (value) this.$set(this.general.member_overrides, id, value); else this.$delete(this.general.member_overrides, id); },
        async saveGeneral() {
            const g = this.general;
            const payload = { member_visibility: g.member_visibility, member_overrides: g.member_overrides, idle_days: g.idle_days, require_lost_reason: !!g.require_lost_reason, auto_create_contacts: !!g.auto_create_contacts };
            await this.run(() => this.crm.api.put('settings', payload)); this.load();
        },
        async rotate() {
            if (!window.confirm(this.crm.t('settings.rotate_confirm'))) return;
            const r = await this.run(() => this.crm.api.post('settings/capture/rotate'));
            if (r) this.info.capture.url = r.url;
        },
        leadState(p) {
            if (!p.has_token) return { key: 'reconnect', tone: 'danger' };
            if (!p.granted) return { key: 'no_permission', tone: 'warn' };
            return p.subscribed ? { key: 'live', tone: 'success' } : { key: 'not_subscribed', tone: 'warn' };
        },
        async leadAction(account, action) {
            this.busy = true;
            try {
                const { result } = await this.crm.api.post('settings/leads', { action, account_id: account.id });
                const failed = action === 'subscribe' ? !result.ok : !!result.error;
                this.crm.notify(failed ? (result.error || this.crm.t('common.error'))
                    : (action === 'sync' ? this.crm.t('settings.sync_done', { n: result.created }) : this.crm.t('settings.subscribed_ok')), failed ? 'error' : 'success');
                await this.load();
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.busy = false;
        },
        copy(text) { navigator.clipboard && navigator.clipboard.writeText(text).then(() => this.crm.notify(this.crm.t('ai.copied'))); },
        async addField() {
            const options = this.field.type === 'dropdown' ? this.field.options.split(',').map((x) => x.trim()).filter(Boolean) : null;
            if (await this.run(() => this.crm.api.post('settings/fields', { label: this.field.label, type: this.field.type, options }))) { this.field = { label: '', type: 'text', options: '' }; this.load(); }
        },
        async removeField(f) { if (window.confirm(this.crm.t('settings.field_delete_confirm')) && await this.run(() => this.crm.api.del(`settings/fields/${f.id}`))) this.load(); },
        async renameTag(tag) {
            const to = window.prompt(this.crm.t('settings.rename'), tag);
            if (to && to !== tag) { const r = await this.run(() => this.crm.api.post('settings/tags', { from: tag, to })); if (r) this.info.tags = r.tags; }
        },
        async deleteTag(tag) {
            if (!window.confirm(this.crm.t('settings.tag_delete_confirm', { tag }))) return;
            const r = await this.run(() => this.crm.api.post('settings/tags', { from: tag, to: null }));
            if (r) this.info.tags = r.tags;
        },
        select(p) { this.current = p; this.draft = { name: p.name, is_default: p.is_default, stages: p.stages.map((s) => ({ ...s, key: s.id, color: s.color || '#94a3b8' })) }; },
        moveStage(i, step) { const list = this.draft.stages; list.splice(i + step, 0, list.splice(i, 1)[0]); },
        async refresh(selectId) {
            this.pipelines = (await this.crm.api.get('pipelines')).data;
            this.crm.pipelines.splice(0, this.crm.pipelines.length, ...this.pipelines);
            this.select(this.pipelines.find((p) => p.id === selectId) || this.pipelines[0]);
        },
        async savePipeline() {
            const ok = await this.run(() => this.crm.api.put(`pipelines/${this.current.id}`, {
                name: this.draft.name, is_default: this.draft.is_default,
                stages: this.draft.stages.map(({ id, name, kind, probability, color }) => ({ id, name, kind, probability, color })),
            }));
            if (ok) await this.refresh(this.current.id);
        },
        async addPipeline() {
            const name = window.prompt(this.crm.t('settings.pipeline_name'));
            if (!name) return;
            const r = await this.run(() => this.crm.api.post('pipelines', { name }));
            if (r) await this.refresh(r.data.id);
        },
        async removePipeline() {
            if (window.confirm(this.crm.t('common.confirm_delete')) && await this.run(() => this.crm.api.del(`pipelines/${this.current.id}`))) await this.refresh(null);
        },
    },
};
</script>
