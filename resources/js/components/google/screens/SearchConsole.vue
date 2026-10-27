<template>
  <div class="gs-screen">
    <div class="gs-head">
      <div>
        <h1>{{ t('search.title') }}</h1>
        <p v-if="prop" class="gs-sub">{{ t('search.subtitle', { type: t('search.types.' + filters.type), property: '' }) }}<span class="gs-mono">{{ prop.external_id }}</span></p>
      </div>
      <div v-if="ready" class="gs-head-actions">
        <span v-if="d && d.data_through" class="gs-pill is-success"><span class="gs-dot"></span>{{ t('search.data_through', { date: fmt.date(d.data_through) }) }}</span>
        <gs-range :presets="['7d', '28d', '3m']" @change="load" />
        <div ref="typeMenu" class="gs-range">
          <button type="button" class="gs-btn gs-btn-ghost" :aria-expanded="typeOpen ? 'true' : 'false'" @click="typeOpen = !typeOpen">{{ t('search.search_type', { type: t('search.types.' + filters.type) }) }}<i class="bx bx-chevron-down"></i></button>
          <div v-if="typeOpen" class="gs-pop" role="menu">
            <button v-for="type in types" :key="type" type="button" role="menuitemradio" :aria-checked="filters.type === type ? 'true' : 'false'" class="gs-pop-item" :class="{ active: filters.type === type }" @click="setType(type)">{{ t('search.types.' + type) }}</button>
          </div>
        </div>
        <div ref="filterMenu" class="gs-range">
          <button type="button" class="gs-btn gs-btn-ghost" :aria-expanded="filterOpen ? 'true' : 'false'" @click="filterOpen = !filterOpen">
            <i class="bx bx-filter-alt"></i>{{ t('search.filter') }}<span v-if="activeFilters" class="gs-count">{{ activeFilters }}</span>
          </button>
          <form v-if="filterOpen" class="gs-pop gs-pop-form" @submit.prevent="applyFilters">
            <label>{{ t('search.filter_query') }}<input v-model.trim="draft.query" type="text" maxlength="200" :disabled="!supportsQueries"></label>
            <label>{{ t('search.filter_page') }}<input v-model.trim="draft.page" type="text" maxlength="200"></label>
            <div class="gs-row">
              <button type="button" class="gs-btn gs-btn-ghost gs-btn-sm" @click="clearFilters">{{ t('search.clear_filters') }}</button>
              <button type="submit" class="gs-btn gs-btn-primary gs-btn-sm">{{ t('ranges.apply') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <gs-state v-if="blocker" kind="error" :error="blocker" />

    <template v-else>
      <gs-state v-if="error" kind="error" :error="error" @retry="load" />
      <template v-if="!error || d">

      <section class="gs-sc-tiles" role="group" :aria-label="t('search.chart_toggle')">
        <button v-for="k in tiles" :key="k.key" type="button" class="gs-sc-tile" :class="{ on: chart.includes(k.key) }"
                :style="chart.includes(k.key) ? { background: k.color, borderColor: k.color } : null" :aria-pressed="chart.includes(k.key) ? 'true' : 'false'" @click="toggle(k.key)">
          <span class="gs-sc-tile-label"><i class="bx" :class="chart.includes(k.key) ? 'bxs-checkbox-checked' : 'bx-checkbox'" aria-hidden="true"></i>{{ k.label }}</span>
          <strong v-if="d">{{ k.value }}</strong><span v-else class="gs-skel lg"></span>
          <gs-delta v-if="d" :current="k.current" :previous="k.previous" :mode="k.mode" :inverse="k.inverse" :suffix="k.mode === 'pct' ? t('common.vs_previous') : ''" />
        </button>
      </section>

      <section class="gs-card gs-pad">
        <div class="gs-legend">
          <span v-for="s in chartSeries" :key="'lg' + s.key"><span class="gs-line-key" :style="{ background: s.color }"></span>{{ s.label }}</span>
          <span class="gs-muted sm">{{ t('search.chart_toggle') }}</span>
        </div>
        <gs-state v-if="loading && !d" kind="loading" compact />
        <gs-state v-else-if="d && empty" kind="empty" :title="t('search.empty_title')" :text="t('search.empty_text')" />
        <gs-line-chart v-else-if="d" :series="chartSeries" :labels="d.trend.dates" :height="300" :y-format="yFormat" :invert="chart[0] === 'position'" :aria-label="t('search.title')" />
        <p class="gs-note"><i class="bx bx-time-five"></i>{{ t('search.delay_note') }}</p>
      </section>

      <div class="gs-split">
        <section class="gs-card gs-pad">
          <div class="gs-seg" role="tablist">
            <button v-for="tab in tabs" :key="tab" type="button" role="tab" :aria-selected="tab === activeTab ? 'true' : 'false'" :class="{ active: tab === activeTab }" @click="activeTab = tab">{{ t('search.tabs.' + tab) }}</button>
          </div>
          <gs-state v-if="loading && !d" kind="loading" compact />
          <template v-else-if="d">
            <gs-state v-if="activeTab === 'queries' && !d.supports_queries" kind="empty" :text="t('search.no_queries_type', { type: t('search.types.' + filters.type) })" compact />
            <gs-table v-else-if="tabRows.length" :key="activeTab" :rows="tabRows" initial-sort="clicks" row-key="key" :columns="tabColumns" :limit="15">
              <template #cell-key="{ value }"><span :dir="'auto'" :class="{ 'gs-mono': activeTab === 'pages' }">{{ label(value) }}</span></template>
              <template #cell-trend="{ row }">
                <span v-if="row.previous_clicks === null" class="gs-chip is-brand">{{ t('search.new') }}</span>
                <span v-else class="gs-chip" :class="trendTone(row)">{{ trendText(row) }}</span>
              </template>
            </gs-table>
            <gs-state v-else kind="empty" :text="t('search.empty_text')" compact />
          </template>
        </section>

        <aside class="gs-stack">
          <section class="gs-card gs-pad">
            <p class="gs-eyebrow">{{ t('search.indexing') }}</p>
            <p class="gs-muted sm">{{ t('search.indexing_note') }}</p>
          </section>

          <section class="gs-card gs-pad">
            <p class="gs-eyebrow">{{ t('search.inspect_title') }}</p>
            <form class="gs-inspect" @submit.prevent="inspect">
              <label class="gs-label" for="gs-inspect-url">{{ t('search.inspect_hint') }}</label>
              <div class="gs-row">
                <input id="gs-inspect-url" v-model.trim="inspectUrl" type="url" required class="gs-input gs-mono" :placeholder="inspectPlaceholder" maxlength="2048">
                <button type="submit" class="gs-btn gs-btn-primary" :disabled="inspecting || !inspectUrl">{{ inspecting ? t('search.inspecting') : t('search.inspect') }}</button>
              </div>
            </form>
            <p v-if="inspectError" class="gs-field-error" role="alert">{{ inspectError }}</p>
            <div v-if="inspection" class="gs-inspection" :class="'is-' + inspection.verdict">
              <strong><i class="bx" :class="inspection.verdict === 'PASS' ? 'bxs-check-circle' : inspection.verdict === 'FAIL' ? 'bxs-x-circle' : 'bxs-info-circle'"></i>{{ t('search.verdict.' + inspection.verdict) }}</strong>
              <dl>
                <div v-if="inspection.coverage"><dt>{{ t('search.coverage') }}</dt><dd>{{ inspection.coverage }}</dd></div>
                <div v-if="inspection.last_crawl"><dt>{{ t('search.last_crawl') }}</dt><dd>{{ fmt.datetime(inspection.last_crawl) }}</dd></div>
                <div v-if="inspection.crawled_as"><dt>{{ t('search.crawled_as') }}</dt><dd>{{ inspection.crawled_as }}</dd></div>
                <div v-if="inspection.google_canonical"><dt>{{ t('search.google_canonical') }}</dt><dd class="gs-mono gs-break">{{ inspection.google_canonical }}</dd></div>
                <div v-if="inspection.robots"><dt>{{ t('search.robots') }}</dt><dd>{{ inspection.robots }}</dd></div>
              </dl>
              <a v-if="inspection.link" :href="inspection.link" target="_blank" rel="noopener" class="gs-link">{{ t('search.open_in_sc') }} ↗</a>
            </div>
          </section>

          <section class="gs-card gs-pad">
            <p class="gs-eyebrow">{{ t('search.sitemaps') }}</p>
            <gs-state v-if="loading && !d" kind="loading" compact />
            <ul v-else-if="d && d.sitemaps.length" class="gs-kv">
              <li v-for="m in d.sitemaps" :key="m.path">
                <span class="gs-mono gs-ellipsis" :title="m.path">{{ shortPath(m.path) }}</span>
                <span v-if="m.errors" class="gs-chip is-danger">{{ t('search.sitemap_errors', { n: m.errors }) }}</span>
                <span v-else-if="m.is_pending" class="gs-chip is-warning">{{ t('search.sitemap_pending') }}</span>
                <span v-else class="gs-chip is-success">{{ t('search.sitemap_ok', { n: fmt.int(m.submitted) }) }}</span>
              </li>
            </ul>
            <p v-else-if="d" class="gs-muted sm">{{ t('search.sitemaps_empty') }}</p>
          </section>
        </aside>
      </div>

      </template>

      <p class="gs-note"><i class="bx bx-info-circle"></i>{{ t('states.sample_note') }}<template v-if="fetchedAt"> · {{ t('common.last_synced') }} {{ fmt.ago(fetchedAt) }}</template></p>
    </template>
  </div>
</template>

<script>
import { suite, t, fmt, api, rangeParams, selected, grants, change } from '../store';
import GsRange from '../ui/GsRange.vue';
import GsDelta from '../ui/GsDelta.vue';
import GsState from '../ui/GsState.vue';
import GsTable from '../ui/GsTable.vue';
import GsLineChart from '../ui/GsLineChart.vue';

// Validated categorical slots (dataviz validator, light surface).
const METRIC_COLORS = { clicks: '#1A73E8', impressions: '#0B1F66', ctr: '#188038', position: '#E8710A' };
const COUNTS = ['clicks', 'impressions'];

export default {
  name: 'GsSearchConsole',
  components: { GsRange, GsDelta, GsState, GsTable, GsLineChart },
  data() {
    const q = new URLSearchParams(window.location.search);
    const filters = { type: q.get('type') || 'web', query: q.get('query') || '', page: q.get('page') || '' };
    return {
      suite, loading: false, result: null, chart: ['clicks', 'impressions'], activeTab: 'queries', typeOpen: false, filterOpen: false,
      types: ['web', 'image', 'video', 'news', 'discover', 'googleNews'], filters, draft: { ...filters },
      inspectUrl: '', inspecting: false, inspection: null, inspectError: '',
    };
  },
  computed: {
    fmt: () => fmt,
    prop() { return selected('search_console'); },
    blocker() {
      if (!suite.account.connected) return { code: 'not_connected', message: t('errors.not_connected'), retryable: false };
      if (!grants('search_console')) return { code: 'missing_scope', message: t('errors.missing_scope'), reconnect: true };
      if (!this.prop) return { code: 'no_property', message: t('states.no_property_text', { service: t('services.search_console') }), retryable: false };
      return null;
    },
    ready() { return !this.blocker; },
    d() { return this.result && this.result.data; },
    error() { return this.result && this.result.error; },
    fetchedAt() { return this.result && this.result.fetched_at; },
    supportsQueries() { return !['discover', 'googleNews'].includes(this.filters.type); },
    activeFilters() { return [this.filters.query, this.filters.page].filter(Boolean).length; },
    empty() { return this.d && this.d.totals.current.impressions === 0; },
    tabs() { return ['queries', 'pages', 'countries', 'devices', 'appearance']; },
    tiles() {
      const c = this.d ? this.d.totals.current : {}; const p = this.d ? this.d.totals.previous : {};
      return [
        { key: 'clicks', label: t('search.kpi.clicks'), color: METRIC_COLORS.clicks, value: fmt.int(c.clicks), current: c.clicks, previous: p.clicks, mode: 'pct' },
        { key: 'impressions', label: t('search.kpi.impressions'), color: METRIC_COLORS.impressions, value: fmt.compact(c.impressions), current: c.impressions, previous: p.impressions, mode: 'pct' },
        { key: 'ctr', label: t('search.kpi.ctr'), color: METRIC_COLORS.ctr, value: fmt.pct(c.ctr), current: c.ctr, previous: p.ctr, mode: 'pts' },
        { key: 'position', label: t('search.kpi.position'), color: METRIC_COLORS.position, value: fmt.dec(c.position), current: c.position, previous: p.position, mode: 'places' },
      ];
    },
    chartSeries() {
      if (!this.d) return [];
      return this.chart.map((key) => ({ key, label: t('search.kpi.' + key), color: METRIC_COLORS[key], values: this.d.trend[key].current, format: this.formatter(key) }));
    },
    yFormat() { return this.formatter(this.chart[0]); },
    tabRows() { return (this.d && this.d[this.activeTab]) || []; },
    tabColumns() {
      const dim = { queries: 'top_query', pages: 'page', countries: 'country', devices: 'device', appearance: 'searchAppearance' }[this.activeTab];
      const cols = [
        { key: 'key', label: t('columns.' + dim), class: this.activeTab === 'pages' ? 'gs-ellipsis' : '' },
        { key: 'clicks', label: t('columns.clicks'), numeric: true, format: fmt.int },
        { key: 'impressions', label: t('columns.impressions'), numeric: true, format: fmt.int },
        { key: 'ctr', label: t('columns.ctr'), numeric: true, format: (v) => fmt.pct(v) },
        { key: 'position', label: t('columns.position'), numeric: true, format: (v) => fmt.dec(v) },
      ];
      if (['queries', 'pages'].includes(this.activeTab) && suite.compare) cols.push({ key: 'trend', label: t('columns.trend'), sortable: false, align: 'end' });
      return cols;
    },
    inspectPlaceholder() {
      if (!this.prop) return '';
      return this.prop.external_id.startsWith('sc-domain:') ? 'https://' + this.prop.external_id.slice(10) + '/' : this.prop.external_id;
    },
  },
  mounted() {
    if (this.ready) this.load();
    document.addEventListener('click', this.outside);
  },
  beforeDestroy() { document.removeEventListener('click', this.outside); },
  methods: {
    t,
    formatter(key) { return key === 'ctr' ? (v) => fmt.pct(v) : key === 'position' ? (v) => fmt.dec(v) : (v) => fmt.compact(v); },
    outside(e) {
      if (this.typeOpen && !this.$refs.typeMenu.contains(e.target)) this.typeOpen = false;
      if (this.filterOpen && !this.$refs.filterMenu.contains(e.target)) this.filterOpen = false;
    },
    /* Counts share one axis; a rate or a position is charted on its own (never two y-scales). */
    toggle(key) {
      if (!COUNTS.includes(key)) { this.chart = [key]; return; }
      const counts = this.chart.filter((k) => COUNTS.includes(k));
      if (counts.includes(key)) { if (counts.length > 1) this.chart = counts.filter((k) => k !== key); } else this.chart = [...counts, key];
    },
    label(value) {
      if (this.activeTab === 'countries') return fmt.country(value);
      if (this.activeTab === 'devices' || this.activeTab === 'appearance') return String(value).replace(/_/g, ' ').toLowerCase().replace(/^\w/, (c) => c.toUpperCase());
      return value;
    },
    trendTone(row) { const c = change(row.clicks, row.previous_clicks); return c === null || Math.abs(c) < 0.005 ? 'is-neutral' : c > 0 ? 'is-success' : 'is-danger'; },
    trendText(row) {
      const c = change(row.clicks, row.previous_clicks);
      if (c === null || Math.abs(c) < 0.005) return '— 0%';
      return (c > 0 ? '▲ ' : '▼ ') + fmt.pct(Math.abs(c), 0);
    },
    shortPath(url) { try { return new URL(url).pathname; } catch (e) { return url; } },
    syncQuery() {
      const url = new URL(window.location.href);
      ['type', 'query', 'page'].forEach((k) => (this.filters[k] && !(k === 'type' && this.filters[k] === 'web') ? url.searchParams.set(k, this.filters[k]) : url.searchParams.delete(k)));
      window.history.replaceState({}, '', url);
    },
    setType(type) {
      this.filters = { ...this.filters, type, query: ['discover', 'googleNews'].includes(type) ? '' : this.filters.query };
      this.typeOpen = false;
      if (!this.supportsQueries && this.activeTab === 'queries') this.activeTab = 'pages';
      this.syncQuery(); this.load();
    },
    applyFilters() { this.filters = { ...this.filters, query: this.draft.query, page: this.draft.page }; this.filterOpen = false; this.syncQuery(); this.load(); },
    clearFilters() { this.draft = { ...this.draft, query: '', page: '' }; this.applyFilters(); },
    async load() {
      this.loading = true;
      try {
        this.result = await api('get', suite.urls.api.search_console, { params: { ...rangeParams(), type: this.filters.type, query: this.filters.query || undefined, page: this.filters.page || undefined } });
      } catch (e) { this.result = { error: e }; } finally { this.loading = false; }
    },
    async inspect() {
      this.inspecting = true; this.inspectError = ''; this.inspection = null;
      try {
        const res = await api('post', suite.urls.api.inspect, { data: { url: this.inspectUrl } });
        if (res.error) this.inspectError = res.error.message; else this.inspection = res.data;
      } catch (e) { this.inspectError = e.message; } finally { this.inspecting = false; }
    },
  },
};
</script>
