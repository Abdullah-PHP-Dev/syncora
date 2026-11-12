<template>
    <section class="crm-card">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
            <div><h3 class="crm-card-title">{{ crm.t('settings.section_scoring') }}</h3><p class="crm-card-sub mb-0">{{ crm.t('settings.scoring_hint') }}</p></div>
            <label class="crm-check"><input v-model="cfg.enabled" type="checkbox"> {{ crm.t('settings.scoring_on') }}</label>
        </div>
        <div v-if="!cfg.rules" class="crm-muted">{{ crm.t('common.loading') }}</div>
        <template v-else>
            <div class="crm-row2 mb-3">
                <div class="crm-field mb-0"><label class="crm-label" for="sc-hot">{{ crm.t('settings.band_hot') }}</label><input id="sc-hot" v-model.number="cfg.bands.hot" type="number" min="1" max="100" class="crm-input" dir="ltr"></div>
                <div class="crm-field mb-0"><label class="crm-label" for="sc-warm">{{ crm.t('settings.band_warm') }}</label><input id="sc-warm" v-model.number="cfg.bands.warm" type="number" min="0" max="99" class="crm-input" dir="ltr"></div>
            </div>
            <label class="crm-check mb-3"><input v-model="cfg.intent_signal" type="checkbox"> {{ crm.t('settings.intent_signal') }}</label>

            <div v-for="g in groups" :key="g" class="mb-3">
                <div class="crm-small mb-1" style="font-weight:700;text-transform:uppercase;letter-spacing:.04em" :style="{ color: g === 'negative' ? 'var(--crm-red)' : 'var(--crm-muted)' }">{{ crm.t('settings.group_' + g) }}</div>
                <div v-for="r in rulesOf(g)" :key="r._k" class="d-flex gap-2 align-items-center flex-wrap py-1">
                    <input v-model="r.on" type="checkbox" :aria-label="crm.t('automations.on')">
                    <select v-model="r.field" class="crm-select" style="width:auto;max-width:240px" :aria-label="crm.t('automations.field')">
                        <option v-for="f in fields" :key="f" :value="f">{{ crm.t('automations.field_' + f.replace('.', '_')) }}</option>
                    </select>
                    <select v-model="r.operator" class="crm-select" style="width:auto" :aria-label="crm.t('automations.operator')">
                        <option v-for="o in operators" :key="o" :value="o">{{ crm.t('automations.op_' + o) }}</option>
                    </select>
                    <input v-if="!['is_empty', 'not_empty'].includes(r.operator)" v-model="r.value" class="crm-input" style="width:140px" :aria-label="crm.t('settings.rule_value')" dir="auto">
                    <input v-model.number="r.points" type="number" min="-100" max="100" class="crm-input" style="width:80px" :aria-label="crm.t('settings.points')" dir="ltr">
                    <span class="crm-small crm-muted">{{ crm.t('settings.points') }}</span>
                    <button type="button" class="crm-btn crm-btn-ghost crm-icon-btn crm-btn-sm" :aria-label="crm.t('common.delete')" @click="cfg.rules.splice(cfg.rules.indexOf(r), 1)"><i class="bx bx-trash"></i></button>
                </div>
                <button type="button" class="crm-btn crm-btn-ghost crm-btn-sm" @click="addRule(g)"><i class="bx bx-plus"></i> {{ crm.t('settings.add_rule') }}</button>
            </div>

            <div class="crm-card mb-3" style="background:var(--crm-canvas)">
                <strong class="crm-small">{{ crm.t('settings.backtest') }}</strong>
                <p class="crm-hint mb-2">{{ crm.t('settings.backtest_hint') }}</p>
                <div class="d-flex gap-3 flex-wrap">
                    <div v-for="b in ['hot', 'warm', 'cold']" :key="b" class="crm-small">
                        <span :class="['crm-chip', 'band-' + b]">{{ crm.t('bands.' + b) }}</span>
                        {{ crm.t('settings.backtest_row', { leads: backtest[b] ? backtest[b].leads : 0, rate: backtest[b] && backtest[b].rate !== null ? backtest[b].rate + '%' : '—' }) }}
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between gap-2 flex-wrap">
                <div class="d-flex gap-2">
                    <button type="button" class="crm-btn" :disabled="busy" @click="resetDefaults">{{ crm.t('settings.reset_default') }}</button>
                    <button type="button" class="crm-btn" :disabled="busy" @click="rescore"><i class="bx bx-refresh"></i> {{ crm.t('settings.rescore') }}</button>
                </div>
                <button type="button" class="crm-btn crm-btn-primary" :disabled="busy || cfg.bands.warm >= cfg.bands.hot" @click="save"><i class="bx bx-check"></i> {{ crm.t('common.save') }}</button>
            </div>
        </template>
    </section>
</template>

<script>
let seq = 0;
const keyed = (rules) => rules.map((r) => ({ ...r, value: r.value === null || r.value === undefined ? '' : r.value, _k: ++seq }));

/** S-06: transparent scoring rules (fit, engagement, intent, negative), bands, and a 90-day back-test. */
export default {
    name: 'CrmSettingsScoring',
    inject: ['crm'],
    data: () => ({ cfg: { enabled: true, intent_signal: true, bands: { hot: 75, warm: 50 }, rules: null }, defaults: null, backtest: {}, fields: [], operators: [], busy: false,
        groups: ['fit', 'engagement', 'intent', 'negative'] }),
    created() { this.load(); },
    methods: {
        async load() {
            try {
                const res = await this.crm.api.get('settings/scoring');
                Object.assign(this, { defaults: res.defaults, backtest: res.backtest, fields: res.fields, operators: res.operators });
                this.cfg = { ...res.scoring, bands: { ...res.scoring.bands }, rules: keyed(res.scoring.rules) };
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
        },
        rulesOf(g) { return this.cfg.rules.filter((r) => r.group === g); },
        addRule(group) { this.cfg.rules.push({ group, field: 'signal.messages', operator: 'gte', value: '1', points: group === 'negative' ? -10 : 10, on: true, _k: ++seq }); },
        resetDefaults() { this.cfg = { ...this.defaults, bands: { ...this.defaults.bands }, rules: keyed(this.defaults.rules) }; },
        payload() {
            return { ...this.cfg, rules: this.cfg.rules.map(({ _k, ...r }) => ({ ...r, value: ['is_empty', 'not_empty'].includes(r.operator) || r.value === '' ? null : r.value })) };
        },
        async save() {
            this.busy = true;
            try { await this.crm.api.put('settings', { scoring: this.payload() }); this.crm.notify(this.crm.t('settings.scoring_saved')); } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.busy = false;
        },
        async rescore() {
            this.busy = true;
            try {
                await this.crm.api.put('settings', { scoring: this.payload() });
                const res = await this.crm.api.post('settings/scoring/rescore');
                this.backtest = res.backtest;
                this.crm.notify(this.crm.t('settings.rescored', { n: res.rescored }));
            } catch (e) { this.crm.notify(this.crm.error(e), 'error'); }
            this.busy = false;
        },
    },
};
</script>
