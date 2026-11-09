<template>
  <div class="gs-screen">
    <div class="gs-head">
      <div>
        <p class="gs-eyebrow">{{ t('outcomes.performance') }}</p>
        <h1>{{ t('speed.title') }}</h1>
        <p class="gs-sub">{{ t('speed.subtitle') }}</p>
      </div>
    </div>

    <form class="gs-card gs-pad gs-speed-form" @submit.prevent="run(true)">
      <label class="gs-field gs-speed-url">
        <span class="gs-label">{{ t('speed.url_label') }}</span>
        <input v-model.trim="url" type="url" inputmode="url" class="gs-input gs-mono" :placeholder="t('speed.url_placeholder')" required :aria-invalid="fieldError ? 'true' : null" :disabled="running">
        <span v-if="fieldError" class="gs-field-error">{{ fieldError }}</span>
      </label>
      <div class="gs-field">
        <span class="gs-label" id="gs-speed-device">{{ t('speed.device') }}</span>
        <div class="gs-seg" role="radiogroup" aria-labelledby="gs-speed-device">
          <button v-for="s in ['mobile', 'desktop']" :key="s" type="button" role="radio" :aria-checked="strategy === s ? 'true' : 'false'" :class="{ active: strategy === s }" :disabled="running" @click="switchStrategy(s)">
            <i class="bx" :class="s === 'mobile' ? 'bx-mobile' : 'bx-desktop'"></i> {{ t('speed.' + s) }}
          </button>
        </div>
      </div>
      <button type="submit" class="gs-btn gs-btn-primary" :disabled="running || !url">
        <span v-if="running" class="gs-spinner sm light" aria-hidden="true"></span><i v-else class="bx bx-tachometer"></i>{{ running ? t('speed.running') : (d ? t('speed.rerun') : t('speed.run')) }}
      </button>
    </form>

    <gs-state v-if="running" kind="loading" :title="t('speed.running_title')" :text="t('speed.running_text')" />
    <gs-state v-else-if="error" kind="error" :error="error" @retry="run(true)" />
    <p v-if="!running && keyProblem" class="gs-note"><i class="bx bx-key"></i><a href="#speed-api-key" class="gs-link">{{ t('speed_key.jump') }}</a></p>
    <gs-state v-else-if="!d && !checking" kind="empty" icon="bx-tachometer" :title="t('speed.empty_title')" :text="t('speed.empty_text')" />

    <template v-if="d && !running">
      <div class="gs-speed-top">
        <!-- Lab score -->
        <section class="gs-card gs-pad gs-speed-score">
          <p class="gs-eyebrow">{{ t('speed.lab_title') }}</p>
          <div class="gs-gauge" :class="'is-' + band(d.score)" role="img" :aria-label="t('speed.score_aria', { n: d.score })">
            <svg viewBox="0 0 120 120" width="132" height="132" aria-hidden="true">
              <circle cx="60" cy="60" r="52" class="gs-gauge-track" />
              <circle cx="60" cy="60" r="52" class="gs-gauge-arc" :stroke-dasharray="arc" transform="rotate(-90 60 60)" />
            </svg>
            <strong>{{ d.score === null ? '—' : d.score }}</strong>
          </div>
          <p class="gs-speed-band">{{ t('speed.band.' + band(d.score)) }}</p>
          <p class="gs-muted sm">{{ t('speed.lab_note') }}</p>
        </section>

        <!-- Field data -->
        <section class="gs-card gs-pad gs-speed-field">
          <div class="gs-card-head">
            <div>
              <p class="gs-eyebrow">{{ t('speed.field_title') }}</p>
              <h2>{{ d.field.scope ? t('speed.field_heading') : t('speed.field_none_heading') }}</h2>
            </div>
            <span v-if="d.field.overall" class="gs-pill" :class="catTone(d.field.overall)"><span class="gs-dot"></span>{{ t('speed.cwv.' + d.field.overall) }}</span>
          </div>
          <template v-if="d.field.scope">
            <ul class="gs-vitals">
              <li v-for="m in fieldMetrics" :key="m.key">
                <span class="gs-vital-name">{{ t('speed.metric.' + m.key) }} <abbr :title="t('speed.metric_long.' + m.key)">{{ m.key.toUpperCase() }}</abbr></span>
                <strong>{{ m.text }}</strong>
                <span class="gs-chip" :class="catTone(m.category)">{{ t('speed.category.' + (m.category || 'NONE')) }}</span>
              </li>
            </ul>
            <p class="gs-muted sm">{{ t(d.field.scope === 'page' ? 'speed.field_page' : 'speed.field_origin') }}</p>
          </template>
          <p v-else class="gs-muted">{{ t('speed.field_none') }}</p>
        </section>
      </div>

      <!-- Lab metrics -->
      <section class="gs-kpis gs-kpis-5" :aria-label="t('speed.lab_metrics')">
        <div v-for="m in labMetrics" :key="m.key" class="gs-kpi">
          <span class="gs-kpi-label"><span class="gs-dot" :class="'band-' + band(m.score === null ? null : m.score * 100)"></span>{{ t('speed.metric.' + m.key) }}</span>
          <strong class="gs-kpi-value">{{ m.display || '—' }}</strong>
        </div>
      </section>

      <!-- Opportunities -->
      <section class="gs-card gs-pad">
        <div class="gs-card-head">
          <div>
            <p class="gs-eyebrow">{{ t('speed.recommendations') }}</p>
            <h2>{{ t('speed.opportunities') }}</h2>
          </div>
          <span class="gs-chip is-brand">{{ t('insights.kind.suggestion') }}</span>
        </div>
        <ol v-if="d.opportunities.length" class="gs-opps">
          <li v-for="o in d.opportunities" :key="o.id">
            <div class="gs-opp-main">
              <strong>{{ o.title }}</strong>
              <p class="gs-muted sm">{{ o.description }}</p>
            </div>
            <div class="gs-opp-side">
              <span v-if="o.savings_ms >= 50" class="gs-chip is-warning">{{ t('speed.saving', { value: seconds(o.savings_ms) }) }}</span>
              <span v-else-if="o.display" class="gs-chip">{{ o.display }}</span>
              <a v-if="o.learn_url" :href="o.learn_url" target="_blank" rel="noopener" class="gs-link sm">{{ t('common.learn_more') }}</a>
            </div>
          </li>
        </ol>
        <p v-else class="gs-all-clear"><i class="bx bx-check-shield"></i>{{ t('speed.no_opportunities') }}</p>
        <p class="gs-note"><i class="bx bx-info-circle"></i>{{ t('speed.savings_note') }}<template v-if="d.passed"> · {{ t('speed.passed', { n: d.passed }) }}</template></p>
      </section>

      <p class="gs-note">
        <i class="bx bx-info-circle"></i>{{ t('speed.source_note') }}
        <template v-if="d.final_url"> · <span class="gs-mono gs-break">{{ d.final_url }}</span></template>
        <template v-if="fetchedAt"> · {{ t('speed.tested') }} {{ fmt.ago(fetchedAt) }}</template>
      </p>
      <p v-for="(w, i) in d.warnings" :key="i" class="gs-note gs-text-warning"><i class="bx bx-error"></i>{{ w }}</p>
    </template>

    <!-- The workspace's own PageSpeed Insights API key: tests then run on its own Google quota. -->
    <section id="speed-api-key" class="gs-card gs-pad gs-speed-key" :class="{ 'is-attention': keyProblem }">
      <div class="gs-card-head">
        <div>
          <h2>{{ t('speed_key.title') }}</h2>
          <p class="gs-sub sm">{{ t(keyState.saved ? 'speed_key.sub_saved' : (keyState.platform_key ? 'speed_key.sub_optional' : 'speed_key.sub_required')) }}</p>
        </div>
        <span class="gs-chip" :class="keyState.saved ? 'is-success' : 'is-neutral'">{{ t(keyState.saved ? 'speed_key.chip_saved' : 'speed_key.chip_none') }}</span>
      </div>

      <div v-if="keyState.saved && !editingKey" class="gs-speed-key-saved">
        <span class="gs-mono">AIza••••••••••••{{ keyState.last4 }}</span>
        <template v-if="suite.canManage">
          <button type="button" class="gs-btn gs-btn-ghost" :disabled="keySaving" @click="editingKey = true">{{ t('speed_key.replace') }}</button>
          <button type="button" class="gs-btn gs-btn-ghost" :disabled="keySaving" @click="removeKey">{{ t('speed_key.remove') }}</button>
        </template>
      </div>

      <form v-else-if="suite.canManage" class="gs-speed-key-row" autocomplete="off" @submit.prevent="saveKey">
        <label class="gs-field">
          <span class="gs-label">{{ t('speed_key.label') }}</span>
          <input v-model.trim="apiKey" type="password" class="gs-input gs-mono" :placeholder="t('speed_key.placeholder')" autocomplete="off" spellcheck="false" maxlength="100" required :aria-invalid="keyError ? 'true' : null" :disabled="keySaving">
          <span v-if="keyError" class="gs-field-error">{{ keyError }}</span>
        </label>
        <button type="submit" class="gs-btn gs-btn-primary" :disabled="keySaving || !apiKey">
          <span v-if="keySaving" class="gs-spinner sm light" aria-hidden="true"></span>{{ t('speed_key.save') }}
        </button>
        <button v-if="keyState.saved" type="button" class="gs-btn gs-btn-ghost" :disabled="keySaving" @click="editingKey = false; apiKey = ''; keyError = ''">{{ t('common.cancel') }}</button>
      </form>
      <p v-else class="gs-muted sm">{{ t('speed_key.owner_only') }}</p>

      <div>
        <span class="gs-label">{{ t('speed_key.how_title') }}</span>
        <ol>
          <li v-for="(s, i) in tr('speed_key.how_steps')" :key="i">{{ s }}</li>
        </ol>
        <a href="https://developers.google.com/speed/docs/insights/v5/get-started#APIKey" target="_blank" rel="noopener" class="gs-link sm">{{ t('speed_key.docs') }}</a>
      </div>
      <p class="gs-note"><i class="bx bx-lock-alt"></i>{{ t('speed_key.security_note') }}</p>
    </section>
  </div>
</template>

<script>
import { suite, t, raw, fmt, api, toast } from '../store';
import GsState from '../ui/GsState.vue';

const KEY_ERRORS = ['pagespeed_setup', 'pagespeed_key_invalid', 'pagespeed_quota'];

/* PageSpeed Insights: one Lighthouse lab run plus Chrome UX Report field data, labelled separately. */
export default {
  name: 'GsPageSpeed',
  components: { GsState },
  data: () => ({
    suite, url: suite.extra.website_url || '', strategy: 'mobile', running: false, checking: false, result: null, fieldError: '',
    keyState: suite.extra.pagespeed_key || { saved: false, last4: null, platform_key: false },
    apiKey: '', keyError: '', keySaving: false, editingKey: false,
  }),
  computed: {
    fmt: () => fmt,
    d() { return this.result && this.result.data; },
    error() { return this.result && this.result.error; },
    /* The last test failed for a reason the workspace's own key fixes. */
    keyProblem() { return !!(this.error && KEY_ERRORS.includes(this.error.code)); },
    fetchedAt() { return this.result && this.result.fetched_at; },
    arc() { const c = 2 * Math.PI * 52; const v = (this.d.score || 0) / 100; return `${c * v} ${c}`; },
    fieldMetrics() {
      const m = this.d.field.metrics;
      return ['lcp', 'inp', 'cls', 'fcp', 'ttfb'].filter((k) => m[k]).map((k) => ({ key: k, category: m[k].category, text: k === 'cls' ? fmt.dec(m[k].value, 2) : (k === 'inp' ? `${fmt.int(m[k].value)} ms` : `${fmt.dec(m[k].value / 1000, 1)} s`) }));
    },
    labMetrics() {
      const l = this.d.lab;
      return ['lcp', 'tbt', 'cls', 'fcp', 'si'].map((k) => ({ key: k, display: l[k] ? l[k].display : null, score: l[k] ? l[k].score : null }));
    },
  },
  mounted() { if (this.url) this.run(false, true); },
  methods: {
    t,
    tr: (key) => raw(key) || [],
    async saveKey() {
      this.keySaving = true; this.keyError = '';
      try {
        const res = await api('put', suite.urls.api.pagespeed_key, { data: { api_key: this.apiKey } });
        this.keyState = res.pagespeed_key;
        this.apiKey = ''; this.editingKey = false;
        // A failure the old key caused is stale now.
        if (this.keyProblem) this.result = null;
        toast(res.message);
      } catch (e) {
        this.keyError = (e.errors && e.errors.api_key && e.errors.api_key[0]) || e.message;
      } finally { this.keySaving = false; }
    },
    async removeKey() {
      this.keySaving = true;
      try {
        const res = await api('delete', suite.urls.api.pagespeed_key);
        this.keyState = res.pagespeed_key;
        toast(res.message);
      } catch (e) {
        toast(e.message, 'error');
      } finally { this.keySaving = false; }
    },
    band(score) { if (score === null || score === undefined) return 'none'; return score >= 90 ? 'good' : score >= 50 ? 'average' : 'poor'; },
    catTone(c) { return { FAST: 'is-success', AVERAGE: 'is-warning', SLOW: 'is-danger' }[c] || 'is-neutral'; },
    seconds(ms) { return ms >= 1000 ? `${fmt.dec(ms / 1000, 1)} s` : `${fmt.int(ms)} ms`; },
    switchStrategy(s) {
      if (this.strategy === s) return;
      this.strategy = s;
      // Show the stored run for this device if there is one; never start a test by itself.
      if (this.url) this.run(false, true);
    },
    /** cachedOnly: read a stored run without asking Google to test again. */
    async run(fresh, cachedOnly = false) {
      this.fieldError = '';
      if (cachedOnly) this.checking = true; else this.running = true;
      try {
        const res = await api('get', suite.urls.api.pagespeed, { params: { url: this.url, strategy: this.strategy, fresh: fresh ? 1 : 0, cached: cachedOnly ? 1 : 0 } });
        if (cachedOnly && !res.data && !res.error) { this.result = null; return; }
        this.result = res;
        if (!cachedOnly && res.data) toast(t('speed.done'));
      } catch (e) {
        if (e.code === 'validation') { this.fieldError = e.message; if (!cachedOnly) this.result = null; } else if (!cachedOnly) { this.result = { error: e }; }
      } finally {
        this.running = false;
        this.checking = false;
      }
    },
  },
};
</script>
