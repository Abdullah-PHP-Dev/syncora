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
  </div>
</template>

<script>
import { suite, t, fmt, api, toast } from '../store';
import GsState from '../ui/GsState.vue';

/* PageSpeed Insights: one Lighthouse lab run plus Chrome UX Report field data, labelled separately. */
export default {
  name: 'GsPageSpeed',
  components: { GsState },
  data: () => ({ suite, url: suite.extra.website_url || '', strategy: 'mobile', running: false, checking: false, result: null, fieldError: '' }),
  computed: {
    fmt: () => fmt,
    d() { return this.result && this.result.data; },
    error() { return this.result && this.result.error; },
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
