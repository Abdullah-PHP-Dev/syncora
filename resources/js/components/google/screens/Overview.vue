<template>
  <div class="gs-screen">
    <div class="gs-head">
      <div>
        <p class="gs-eyebrow">{{ t('brand') }} / {{ t('nav.integrations') }} / Google</p>
        <h1>{{ t('overview.title') }}</h1>
        <p class="gs-sub">{{ t('overview.subtitle') }}</p>
      </div>
      <div class="gs-head-actions">
        <a :href="suite.urls.properties" class="gs-btn gs-btn-ghost"><i class="bx bx-lock-alt"></i>{{ t('overview.manage_credentials') }}</a>
        <a :href="suite.urls.integrations" class="gs-btn gs-btn-primary"><i class="bx bx-plus"></i>{{ t('overview.connect_service') }}</a>
      </div>
    </div>

    <!-- First-time setup -->
    <section v-if="!suite.account.connected" class="gs-card gs-hero-connect">
      <gs-service-icon service="google" />
      <div>
        <h2>{{ t('overview.connect_title') }}</h2>
        <p>{{ t('overview.connect_text') }}</p>
      </div>
      <a v-if="suite.canManage" :href="suite.urls.setup" class="gs-btn gs-btn-primary"><i class="bx bxl-google"></i>{{ t('setup.button') }}</a>
      <p v-else class="gs-muted">{{ t('common.owner_only') }}</p>
    </section>

    <!-- Linked account strip -->
    <account-strip v-else />

    <div v-if="suite.account.connected || suite.extra.ads_account" class="gs-toolbar">
      <gs-range @change="load" />
      <div v-if="suite.account.connected" class="gs-toolbar-end">
        <span v-if="fetchedAt" class="gs-muted">{{ t('common.last_synced') }} {{ fmt.ago(fetchedAt) }}</span>
        <button type="button" class="gs-btn gs-btn-ghost" :disabled="refreshing || loading" @click="refresh">
          <i class="bx bx-refresh" :class="{ 'bx-spin': refreshing }"></i>{{ refreshing ? t('common.refreshing') : t('common.refresh') }}
        </button>
      </div>
    </div>

    <!-- Service cards -->
    <section v-if="widget('services')" class="gs-grid-4">
      <article v-for="card in serviceCards" :key="card.service" class="gs-card gs-svc" :class="{ 'is-planned': card.planned }">
        <div class="gs-svc-top">
          <gs-service-icon :service="card.service" />
          <span class="gs-pill" :class="card.pill.tone">{{ card.pill.text }}</span>
        </div>
        <p class="gs-svc-outcome">{{ card.outcome }}</p>
        <h3>{{ serviceName(card.service) }}</h3>
        <p v-if="card.sub" class="gs-mono gs-svc-sub" :title="card.sub">{{ card.sub }}</p>
        <p v-else class="gs-svc-text">{{ card.text }}</p>

        <div v-if="card.metric" class="gs-svc-metric">
          <div>
            <span class="gs-kpi-label">{{ card.metric.label }}</span>
            <strong class="gs-kpi-value">{{ card.metric.value }}</strong>
            <span v-if="card.metric.note" class="gs-svc-note" :class="card.metric.noteTone">{{ card.metric.note }}</span>
            <gs-delta v-else :current="card.metric.current" :previous="card.metric.previous" :inverse="!!card.metric.inverse" :suffix="t('common.vs_prior')" />
          </div>
          <gs-spark v-if="card.metric.spark" :values="card.metric.spark" :color="card.color" />
        </div>
        <gs-state v-else-if="card.state" v-bind="card.state" compact @retry="load" />
        <ul v-else-if="card.points" class="gs-checks">
          <li v-for="p in card.points" :key="p.text" :class="{ done: p.done }"><i class="bx" :class="p.done ? 'bxs-check-circle' : 'bx-circle'"></i>{{ p.text }}</li>
        </ul>
        <div v-else-if="card.loading" class="gs-skel-block"><span class="gs-skel" style="width:40%"></span><span class="gs-skel lg" style="width:65%"></span></div>

        <div class="gs-svc-actions">
          <a :href="card.action.url" class="gs-btn gs-btn-block" :class="card.planned ? 'gs-btn-ghost' : 'gs-btn-primary'">{{ card.action.label }}</a>
          <a v-if="card.settings" :href="card.settings" class="gs-btn gs-btn-ghost gs-btn-icon" :aria-label="t('nav.settings') + ' · ' + serviceName(card.service)"><i class="bx bx-cog"></i></a>
        </div>
      </article>
    </section>

    <div v-if="suite.account.connected" class="gs-split">
      <!-- Unified performance -->
      <section v-if="widget('performance')" class="gs-card gs-pad">
        <div class="gs-card-head">
          <div>
            <p class="gs-eyebrow">{{ t('overview.big_picture') }}</p>
            <h2>{{ t('overview.unified_title') }}</h2>
            <p class="gs-sub sm">{{ t('overview.unified_subtitle') }}</p>
          </div>
        </div>
        <div class="gs-tiles" role="group" :aria-label="t('overview.unified_title')">
          <button v-for="tile in tiles" :key="tile.key" type="button" class="gs-tile" :class="{ off: !tile.on, disabled: tile.value === null }"
                  :aria-pressed="tile.on ? 'true' : 'false'" :disabled="tile.value === null" @click="toggle(tile.key)">
            <span class="gs-tile-label"><span class="gs-sq" :style="{ background: tile.color }"></span>{{ tile.label }}</span>
            <strong>{{ tile.value === null ? '—' : fmt.compact(tile.value) }}</strong>
            <small>{{ tile.source }}</small>
          </button>
        </div>
        <gs-state v-if="loading" kind="loading" compact />
        <gs-state v-else-if="!chartSeries.length" kind="empty" :text="t('states.no_property_text', { service: t('nav.section') })" compact />
        <gs-line-chart v-else :series="chartSeries" :labels="chartDates" area :aria-label="t('overview.unified_title')" :height="280" />
        <p class="gs-note"><i class="bx bx-info-circle"></i>{{ t('overview.separate_note') }}<template v-if="sc && sc.data_through"> · {{ t('overview.data_through', { date: fmt.date(sc.data_through) }) }}</template></p>
      </section>

      <aside class="gs-stack">
        <section v-if="widget('attention')" class="gs-card gs-pad">
          <p class="gs-eyebrow">{{ t('overview.daily_focus') }}</p>
          <h2>{{ t('overview.attention_title') }}</h2>
          <ul v-if="attention.length" class="gs-attn">
            <li v-for="(item, i) in attention" :key="i" :class="'is-' + item.tone">
              <span class="gs-attn-n">{{ item.n || '!' }}</span>
              <div><strong>{{ item.title }}</strong><small>{{ item.text }}</small></div>
              <a :href="item.url" class="gs-link">{{ item.action }}</a>
            </li>
          </ul>
          <p v-else class="gs-all-clear"><i class="bx bx-check-shield"></i>{{ loading ? t('common.loading') : t('overview.all_clear') }}</p>
        </section>

        <section v-if="widget('activity')" class="gs-card gs-pad">
          <p class="gs-eyebrow">{{ t('overview.recent_activity') }}</p>
          <ul v-if="suite.events.length" class="gs-activity">
            <li v-for="e in suite.events.slice(0, 6)" :key="e.id">
              <span class="gs-dot" :class="'is-' + e.status" :style="{ background: eventColor(e) }"></span>
              <div>{{ eventText(e) }}<small>{{ fmt.ago(e.at) }}</small></div>
            </li>
          </ul>
          <p v-else class="gs-muted">{{ t('overview.no_activity') }}</p>
        </section>
      </aside>
    </div>

    <!-- Insights from connected data -->
    <section v-if="widget('insights') && hasAnyData" class="gs-card gs-pad">
      <div class="gs-card-head">
        <div>
          <p class="gs-eyebrow">{{ t('insights.eyebrow') }}</p>
          <h2>{{ t('insights.title') }}</h2>
          <p class="gs-sub sm">{{ t('insights.subtitle') }}</p>
        </div>
        <div class="gs-legend gs-insight-legend">
          <span><span class="gs-chip is-neutral">{{ t('insights.kind.measured') }}</span>{{ t('insights.kind.measured_hint') }}</span>
          <span><span class="gs-chip is-brand">{{ t('insights.kind.suggestion') }}</span>{{ t('insights.kind.suggestion_hint') }}</span>
        </div>
      </div>
      <div v-if="loading" class="gs-skel-block" aria-hidden="true"><span v-for="n in 3" :key="n" class="gs-skel"></span></div>
      <ul v-else-if="insights.length" class="gs-insights">
        <li v-for="item in insights" :key="item.key" :class="'is-' + item.tone">
          <span class="gs-insight-icon" aria-hidden="true"><i class="bx" :class="insightIcon(item)"></i></span>
          <div class="gs-insight-body">
            <span class="gs-chip" :class="item.kind === 'suggestion' ? 'is-brand' : 'is-neutral'">{{ t('insights.kind.' + item.kind) }}</span>
            <strong>{{ item.title }}</strong>
            <p>{{ item.text }}</p>
            <a :href="item.url" class="gs-link sm">{{ item.action }} <i class="bx" :class="suite.dir === 'rtl' ? 'bx-left-arrow-alt' : 'bx-right-arrow-alt'"></i></a>
          </div>
        </li>
      </ul>
      <p v-else class="gs-all-clear"><i class="bx bx-check-shield"></i>{{ t('insights.none') }}</p>
    </section>

    <!-- Breakdowns -->
    <div v-if="suite.account.connected" class="gs-grid-2">
      <section v-if="widget('acquisition')" class="gs-card gs-pad">
        <div class="gs-card-head"><h2>{{ t('overview.acquisition') }}</h2><span class="gs-muted sm">{{ t('overview.acquisition_hint') }}</span></div>
        <section-body :result="results.ga4" :loading="loading" service="ga4" @retry="load">
          <ul v-if="ga && ga.channels.length" class="gs-bars">
            <li v-for="row in ga.channels.slice(0, 6)" :key="row.channel">
              <span class="gs-bars-label">{{ row.channel || t('common.not_set') }}</span>
              <span class="gs-bars-track"><span :style="{ width: share(row.sessions, ga.totals.current.sessions) + '%', background: COLORS.ga4 }"></span></span>
              <b>{{ fmt.int(row.sessions) }}</b>
            </li>
          </ul>
          <gs-state v-else kind="empty" :text="t('analytics.empty_text')" compact />
        </section-body>
        <a :href="suite.urls.analytics" class="gs-link gs-card-foot">{{ t('common.open_dashboard') }} →</a>
      </section>

      <section v-if="widget('queries')" class="gs-card gs-pad">
        <div class="gs-card-head"><h2>{{ t('overview.top_queries') }}</h2><span class="gs-muted sm">{{ t('overview.top_queries_hint') }}</span></div>
        <section-body :result="results.search_console" :loading="loading" service="search_console" @retry="load">
          <gs-table v-if="sc && sc.queries && sc.queries.length" :rows="sc.queries.slice(0, 6)" :columns="[
            { key: 'key', label: t('columns.query'), sortable: false },
            { key: 'clicks', label: t('columns.clicks'), numeric: true, format: fmt.int, sortable: false },
            { key: 'ctr', label: t('columns.ctr'), numeric: true, format: (v) => fmt.pct(v), sortable: false },
            { key: 'position', label: t('columns.position'), numeric: true, format: (v) => fmt.dec(v), sortable: false },
          ]" />
          <gs-state v-else kind="empty" :text="t('search.empty_text')" compact />
        </section-body>
        <a :href="suite.urls.search_console" class="gs-link gs-card-foot">{{ t('common.open_dashboard') }} →</a>
      </section>

      <section v-if="widget('landing_pages')" class="gs-card gs-pad">
        <div class="gs-card-head"><h2>{{ t('overview.landing_pages') }}</h2><span class="gs-muted sm">{{ t('overview.landing_pages_hint') }}</span></div>
        <section-body :result="results.ga4" :loading="loading" service="ga4" @retry="load">
          <gs-table v-if="ga && ga.landing_pages.length" :rows="ga.landing_pages.slice(0, 6)" :columns="[
            { key: 'page', label: t('columns.landingPage'), sortable: false, class: 'gs-mono gs-ellipsis' },
            { key: 'sessions', label: t('columns.sessions'), numeric: true, format: fmt.int, sortable: false },
            { key: 'engagement_rate', label: t('columns.engagement_rate'), numeric: true, format: (v) => fmt.pct(v), sortable: false },
          ]" />
          <gs-state v-else kind="empty" :text="t('analytics.empty_text')" compact />
        </section-body>
      </section>

      <section v-if="widget('audience')" class="gs-card gs-pad">
        <div class="gs-card-head"><h2>{{ t('overview.audience') }}</h2><span class="gs-muted sm">{{ t('overview.audience_hint') }}</span></div>
        <section-body :result="results.ga4" :loading="loading" service="ga4" @retry="load">
          <div v-if="ga && ga.devices.length" class="gs-audience">
            <gs-donut :items="deviceItems" :size="132" :stroke="18" :aria="t('analytics.devices')" />
            <ul class="gs-bars compact">
              <li v-for="row in ga.countries.slice(0, 5)" :key="row.country">
                <span class="gs-bars-label">{{ row.country || t('common.not_set') }}</span>
                <span class="gs-bars-track"><span :style="{ width: share(row.users, ga.countries[0].users) + '%', background: COLORS.brand }"></span></span>
                <b>{{ fmt.int(row.users) }}</b>
              </li>
            </ul>
          </div>
          <gs-state v-else kind="empty" :text="t('analytics.empty_text')" compact />
        </section-body>
      </section>
    </div>
  </div>
</template>

<script>
import { suite, t, fmt, api, rangeParams, selected, grants, serviceName, toast, eventText, COLORS, DEVICE_COLORS } from '../store';
import { buildInsights } from '../insights';
import GsRange from '../ui/GsRange.vue';
import GsSpark from '../ui/GsSpark.vue';
import GsDelta from '../ui/GsDelta.vue';
import GsState from '../ui/GsState.vue';
import GsTable from '../ui/GsTable.vue';
import GsDonut from '../ui/GsDonut.vue';
import GsLineChart from '../ui/GsLineChart.vue';
import GsServiceIcon from '../ui/GsServiceIcon.vue';
import AccountStrip from './parts/AccountStrip.vue';
import SectionBody from './parts/SectionBody.vue';

export default {
  name: 'GsOverview',
  components: { GsRange, GsSpark, GsDelta, GsState, GsTable, GsDonut, GsLineChart, GsServiceIcon, AccountStrip, SectionBody },
  data: () => ({ suite, COLORS, loading: false, refreshing: false, results: { ga4: null, search_console: null }, ads: null, speed: null, on: { sessions: true, clicks: true, key_events: true } }),
  computed: {
    fmt: () => fmt,
    ga() { return this.results.ga4 && this.results.ga4.data; },
    sc() { return this.results.search_console && this.results.search_console.data; },
    adsData() { return this.ads && this.ads.data; },
    speedData() { return this.speed && this.speed.data; },
    hasAnyData() { return !!(this.ga || this.sc || this.adsData || this.speedData || this.loading); },
    insights() { return buildInsights({ ga: this.ga, sc: this.sc, ads: this.adsData, speed: this.speedData }); },
    fetchedAt() { return [this.results.ga4, this.results.search_console].map((r) => r && r.fetched_at).filter(Boolean).sort()[0] || null; },
    rangeLabel() { return t('ranges.' + suite.range.key); },
    serviceCards() {
      return [this.dataCard('ga4'), this.dataCard('search_console'), this.adsCard(), this.speedCard()];
    },
    tiles() {
      const g = this.ga; const s = this.sc;
      return [
        { key: 'sessions', label: t('analytics.kpi.sessions'), source: serviceName('ga4'), color: COLORS.ga4, value: g ? g.totals.current.sessions : null, on: this.on.sessions },
        { key: 'clicks', label: t('columns.clicks'), source: serviceName('search_console'), color: COLORS.search_console, value: s ? s.totals.current.clicks : null, on: this.on.clicks },
        { key: 'key_events', label: t('analytics.kpi.key_events'), source: serviceName('ga4'), color: COLORS.conversions, value: g ? g.totals.current.key_events : null, on: this.on.key_events },
      ];
    },
    chartDates() { return (this.ga && this.ga.trend.dates) || (this.sc && this.sc.trend.dates) || []; },
    chartSeries() {
      const out = [];
      // All three are daily counts, so they share one y-axis honestly.
      if (this.ga && this.on.sessions) out.push({ key: 'sessions', label: t('analytics.kpi.sessions'), color: COLORS.ga4, values: this.ga.trend.sessions.current });
      if (this.sc && this.on.clicks) out.push({ key: 'clicks', label: t('columns.clicks'), color: COLORS.search_console, values: this.sc.trend.clicks.current });
      if (this.ga && this.on.key_events) out.push({ key: 'key_events', label: t('analytics.kpi.key_events'), color: COLORS.conversions, values: this.ga.trend.key_events.current });
      return out;
    },
    deviceItems() {
      return (this.ga ? this.ga.devices : []).map((d) => ({ key: d.device, label: d.device ? d.device.charAt(0).toUpperCase() + d.device.slice(1) : t('common.not_set'), value: d.users, color: DEVICE_COLORS[d.device] || COLORS.previous }));
    },
    attention() {
      const items = [];
      const a = suite.account;
      if (a.connected && !a.usable) items.push({ tone: 'danger', title: t('attention.reconnect'), text: t('attention.reconnect_text'), url: suite.urls.connect, action: t('attention.fix') });
      ['ga4', 'search_console'].forEach((service) => {
        const name = serviceName(service);
        const settings = service === 'ga4' ? suite.urls.settings_ga4 : suite.urls.settings_search_console;
        if (a.connected && !grants(service)) items.push({ tone: 'warning', title: t('attention.missing_scope', { service: name }), text: t('attention.missing_scope_text'), url: suite.urls.connect, action: t('attention.fix') });
        else if (a.connected && !selected(service)) items.push({ tone: 'brand', title: t('attention.no_property', { service: name }), text: t('attention.no_property_text'), url: suite.urls.properties, action: t('attention.fix') });
        else {
          const result = this.results[service];
          if (result && result.error && !['no_property', 'missing_scope'].includes(result.error.code)) {
            items.push({ tone: 'danger', title: t('attention.failed', { service: name }), text: result.error.message, url: settings, action: t('attention.review') });
          }
        }
      });
      if (this.sc) {
        this.sc.sitemaps.filter((m) => m.errors > 0).forEach((m) => items.push({ tone: 'danger', n: m.errors, title: t('attention.sitemap_errors', { path: (() => { try { return new URL(m.path).pathname; } catch (e) { return m.path; } })(), n: m.errors }), text: t('attention.sitemap_errors_text'), url: suite.urls.search_console, action: t('attention.inspect') }));
      }
      if (this.ga && this.ga.totals.current.key_events === 0 && this.ga.totals.previous.key_events === 0) {
        items.push({ tone: 'brand', title: t('attention.no_key_events'), text: t('attention.no_key_events_text'), url: 'https://support.google.com/analytics/answer/12844695', action: t('common.learn_more') });
      }
      return items;
    },
  },
  mounted() { this.load(); },
  methods: {
    t, serviceName, eventText,
    widget(key) { return (suite.preferences.widgets || []).includes(key); },
    toggle(key) { this.on = { ...this.on, [key]: !this.on[key] }; },
    share(v, total) { return total ? Math.max(2, Math.round((v / total) * 100)) : 0; },
    eventColor(e) { return e.status === 'error' ? '#C5221F' : e.status === 'warning' ? '#B45309' : COLORS[e.service] || '#188038'; },
    insightIcon(item) { return { up: 'bx-trending-up', down: 'bx-trending-down', warning: 'bx-error', brand: 'bx-bulb' }[item.tone] || 'bx-info-circle'; },
    adsCard() {
      const base = { service: 'google_ads', color: COLORS.google_ads, outcome: t('outcomes.advertising'), settings: null, action: { url: suite.urls.advertising, label: t('common.open_dashboard') } };
      if (!suite.extra.ads_account) return { ...base, text: t('integrations.ads_value'), pill: { tone: 'is-neutral', text: t('status.not_connected') }, action: { url: suite.urls.ads_connect, label: t('ads.connect') } };
      const sub = suite.extra.ads_account.name;
      if (!this.ads) return { ...base, sub, loading: true, pill: { tone: 'is-neutral', text: t('status.syncing') } };
      if (this.ads.error) return { ...base, sub, pill: { tone: this.ads.error.code === 'ads_not_connected' ? 'is-danger' : 'is-warning', text: this.ads.error.code === 'ads_not_connected' ? t('status.reauth') : t('status.error') }, state: { kind: 'error', error: this.ads.error } };
      const d = this.ads.data; const c = d.totals.current; const p = d.totals.previous;
      const conversions = d.conversion_tracking !== false && (c.conversions > 0 || p.conversions > 0);
      const metric = conversions
        ? { label: t('ads.card_conversions', { range: this.rangeLabel }), value: fmt.dec(c.conversions, c.conversions % 1 ? 1 : 0), current: c.conversions, previous: p.conversions, spark: d.trend.conversions.current }
        : { label: t('ads.card_cost', { range: this.rangeLabel }), value: fmt.money(c.cost, d.currency), current: c.cost, previous: p.cost, spark: d.trend.cost.current, inverse: true };
      return { ...base, sub, metric, pill: { tone: 'is-success', text: t('status.connected') } };
    },
    speedCard() {
      const base = { service: 'pagespeed', color: COLORS.pagespeed, outcome: t('outcomes.performance'), settings: null, action: { url: suite.urls.pagespeed, label: t('speed.open') } };
      const d = this.speedData;
      if (!d) return { ...base, text: t('speed.card_text'), pill: { tone: 'is-brand', text: t('speed.no_signin') }, action: { url: suite.urls.pagespeed, label: t('speed.run') } };
      const band = d.score >= 90 ? 'good' : d.score >= 50 ? 'average' : 'poor';
      return {
        ...base, sub: d.final_url || suite.extra.website_url,
        pill: { tone: band === 'good' ? 'is-success' : band === 'average' ? 'is-warning' : 'is-danger', text: t('speed.band.' + band) },
        metric: { label: t('speed.card_metric'), value: `${d.score}/100`, note: t('speed.card_opps', { n: d.opportunities.length }), noteTone: d.opportunities.length ? 'is-warning' : 'is-success' },
      };
    },
    dataCard(service) {
      const prop = selected(service);
      const result = this.results[service];
      const color = COLORS[service];
      const isGa = service === 'ga4';
      const base = { service, color, outcome: t(isGa ? 'outcomes.traffic' : 'outcomes.search'), settings: isGa ? suite.urls.settings_ga4 : suite.urls.settings_search_console, action: { url: isGa ? suite.urls.analytics : suite.urls.search_console, label: t('common.open_dashboard') } };
      if (!suite.account.connected) return { ...base, settings: null, text: t(isGa ? 'integrations.ga4_value' : 'integrations.sc_value'), pill: { tone: 'is-neutral', text: t('status.not_connected') }, action: { url: suite.urls.setup, label: t('common.connect') } };
      if (!grants(service)) return { ...base, text: t('properties.scope_missing', { service: serviceName(service) }), pill: { tone: 'is-warning', text: t('status.reauth') }, action: { url: suite.urls.connect, label: t('common.reconnect') } };
      if (!prop) return { ...base, text: t('overview.service_not_selected', { service: serviceName(service) }), pill: { tone: 'is-warning', text: t('status.needs_setup') }, action: { url: suite.urls.properties, label: t('overview.choose_property') } };
      const sub = isGa ? [prop.measurement_id, prop.external_id.replace('properties/', 'Property ')].filter(Boolean).join(' · ') : prop.external_id;
      if (!result) return { ...base, sub, loading: true, pill: { tone: 'is-neutral', text: t('status.syncing') } };
      if (result.error) return { ...base, sub, pill: { tone: 'is-danger', text: t('status.error') }, state: { kind: 'error', error: result.error } };
      const d = result.data;
      const metric = isGa
        ? { label: t('overview.sessions_28', { range: this.rangeLabel }), value: fmt.int(d.totals.current.sessions), current: d.totals.current.sessions, previous: d.totals.previous.sessions, spark: d.trend.sessions.current }
        : { label: t('overview.clicks_28', { range: this.rangeLabel }), value: fmt.int(d.totals.current.clicks), current: d.totals.current.clicks, previous: d.totals.previous.clicks, spark: d.trend.clicks.current };
      return { ...base, sub, metric, pill: { tone: 'is-success', text: t('status.connected') } };
    },
    async load() {
      this.loading = true;
      // Each source fails on its own; none blocks the others.
      const google = suite.account.connected
        ? api('get', suite.urls.api.overview, { params: rangeParams() }).catch((e) => ({ ga4: { error: e }, search_console: { error: e } }))
        : Promise.resolve({ ga4: null, search_console: null });
      const ads = suite.extra.ads_account
        ? api('get', suite.urls.api.google_ads, { params: rangeParams() }).catch((e) => ({ error: e }))
        : Promise.resolve(null);
      const speed = suite.extra.website_url
        ? api('get', suite.urls.api.pagespeed, { params: { url: suite.extra.website_url, strategy: 'mobile', cached: 1 } }).catch(() => null)
        : Promise.resolve(null);
      try {
        [this.results, this.ads, this.speed] = await Promise.all([google, ads, speed]);
      } finally {
        this.loading = false;
      }
    },
    async refresh() {
      this.refreshing = true;
      try {
        const res = await api('post', suite.urls.api.refresh);
        suite.properties = res.properties;
        const failed = Object.keys(res.results).filter((k) => res.results[k] !== 'ok');
        toast(failed.length ? t('states.sync_failed', { services: failed.map(serviceName).join(', ') }) : t('states.synced'), failed.length ? 'error' : 'success');
        await this.load();
      } catch (e) {
        toast(e.message, 'error');
      } finally {
        this.refreshing = false;
      }
    },
  },
};
</script>
