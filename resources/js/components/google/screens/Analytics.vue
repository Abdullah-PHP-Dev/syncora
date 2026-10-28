<template>
  <div class="gs-screen">
    <section class="gs-hero">
      <div class="gs-hero-main">
        <p class="gs-eyebrow"><i class="bx bx-bar-chart-alt-2"></i>{{ t('services.ga4') }}</p>
        <h1>{{ t('analytics.title') }}</h1>
        <p class="gs-sub">{{ t('analytics.subtitle') }}</p>
        <div v-if="prop" class="gs-hero-chips">
          <span class="gs-hero-chip" :title="prop.website_url || prop.name"><i class="bx bx-globe"></i><strong>{{ prop.website_url || prop.name }}</strong></span>
          <span class="gs-hero-chip"><i class="bx bx-purchase-tag-alt"></i><strong class="gs-mono">{{ prop.measurement_id || prop.external_id }}</strong></span>
          <span v-if="fetchedAt" class="gs-hero-chip"><i class="bx bx-refresh"></i>{{ t('common.last_synced') }} <strong>{{ fmt.ago(fetchedAt) }}</strong></span>
        </div>
      </div>
      <div v-if="ready" class="gs-hero-actions">
        <gs-range @change="load" />
        <a :href="exportUrl" class="gs-btn gs-btn-primary"><i class="bx bx-download"></i>{{ t('analytics.export') }}</a>
      </div>
    </section>

    <gs-state v-if="blocker" kind="error" :error="blocker" />

    <template v-else>
      <gs-state v-if="error" kind="error" :error="error" @retry="load" />
      <template v-if="!error || d">

      <section class="gs-kpis" :aria-busy="loading ? 'true' : 'false'">
        <div v-for="k in kpis" :key="k.key" class="gs-kpi">
          <div class="gs-kpi-top">
            <span class="gs-kpi-label">{{ k.label }}</span>
            <span class="gs-kpi-ic" :style="{ background: k.tint[1], color: k.tint[0] }" aria-hidden="true"><i class="bx" :class="k.icon"></i></span>
          </div>
          <strong v-if="d" class="gs-kpi-value">{{ k.value }}</strong>
          <span v-else class="gs-skel lg" aria-hidden="true"></span>
          <gs-delta v-if="d && k.current !== null" :current="k.current" :previous="k.previous" :mode="k.mode || 'pct'" />
          <small v-if="d && k.note" class="gs-muted">{{ k.note }}</small>
        </div>
      </section>

      <div class="gs-split">
        <section class="gs-card gs-pad">
          <div class="gs-card-head">
            <div>
              <h2>{{ t('analytics.trend_title') }}</h2>
              <p class="gs-sub sm">{{ t('analytics.trend_sub') }}</p>
            </div>
            <div class="gs-legend">
              <span><span class="gs-line-key" :style="{ background: COLORS.brand }"></span>{{ t('common.this_period') }}</span>
              <span v-if="suite.compare"><span class="gs-line-key dashed"></span>{{ t('common.previous') }}</span>
            </div>
          </div>
          <div class="gs-seg" role="tablist" :aria-label="t('analytics.trend_title')">
            <button v-for="m in ['active_users', 'sessions', 'key_events']" :key="m" type="button" role="tab" :aria-selected="metric === m ? 'true' : 'false'" :class="{ active: metric === m }" @click="metric = m">{{ t('analytics.trend_metric.' + m) }}</button>
          </div>
          <gs-state v-if="loading && !d" kind="loading" compact />
          <gs-state v-else-if="d && empty" kind="empty" :title="t('analytics.empty_title')" :text="t('analytics.empty_text')" />
          <gs-line-chart v-else-if="d" :series="trendSeries" :labels="d.trend.dates" area :height="280" :aria-label="t('analytics.trend_metric.' + metric)" />
        </section>

        <section class="gs-card gs-realtime" aria-live="polite">
          <p class="gs-eyebrow light"><span class="gs-live" aria-hidden="true"></span>{{ t('analytics.realtime') }}</p>
          <template v-if="realtime">
            <strong class="gs-rt-value">{{ fmt.int(realtime.active_users) }}</strong>
            <p>{{ realtime.active_users ? t('analytics.realtime_users') : t('analytics.realtime_none') }}</p>
            <div class="gs-rt-bars" aria-hidden="true">
              <span v-for="(v, i) in realtime.per_minute" :key="i" :style="{ height: barHeight(v) + '%' }" :title="v"></span>
            </div>
            <ul class="gs-rt-countries">
              <li v-for="c in realtime.countries" :key="c.country">
                <span>{{ c.country || t('common.not_set') }}</span>
                <span class="gs-rt-track"><span :style="{ width: (realtime.countries[0].users ? c.users / realtime.countries[0].users * 100 : 0) + '%' }"></span></span>
                <b>{{ fmt.int(c.users) }}</b>
              </li>
            </ul>
          </template>
          <p v-else-if="realtimeError" class="gs-rt-error">{{ realtimeError.message }}</p>
          <span v-else class="gs-spinner light" aria-hidden="true"></span>
        </section>
      </div>

      <div class="gs-split">
        <section class="gs-card gs-pad">
          <div class="gs-card-head"><div><h2>{{ t('analytics.acquisition') }}</h2><p class="gs-sub sm">{{ t('analytics.acquisition_hint') }}</p></div></div>
          <gs-state v-if="loading && !d" kind="loading" compact />
          <gs-table v-else-if="d && d.channels.length" :rows="d.channels" initial-sort="sessions" row-key="channel" :caption="t('analytics.acquisition')" :columns="[
            { key: 'channel', label: t('columns.channel'), format: (v) => v || t('common.not_set') },
            { key: 'share', label: t('analytics.share'), sortable: false, class: 'gs-hide-sm' },
            { key: 'sessions', label: t('columns.sessions'), numeric: true, format: fmt.int },
            { key: 'engaged', label: t('columns.engaged'), numeric: true, format: fmt.int },
            { key: 'key_events', label: t('columns.key_events'), numeric: true, format: fmt.int },
            ...(showRevenue ? [{ key: 'revenue', label: t('columns.revenue'), numeric: true, format: (v) => fmt.money(v, d.currency) }] : []),
          ]">
            <template #cell-share="{ row }">
              <span class="gs-share"><span :style="{ width: share(row.sessions) + '%', background: shareColor(row.channel) }"></span></span>
            </template>
          </gs-table>
          <gs-state v-else-if="d" kind="empty" :text="t('analytics.empty_text')" compact />
        </section>

        <section class="gs-card gs-pad">
          <div class="gs-card-head"><div><h2>{{ t('analytics.devices') }}</h2><p class="gs-sub sm">{{ t('analytics.devices_sub') }}</p></div></div>
          <gs-state v-if="loading && !d" kind="loading" compact />
          <gs-donut v-else-if="d && d.devices.length" :items="deviceItems" :aria="t('analytics.devices')" />
          <gs-state v-else-if="d" kind="empty" :text="t('analytics.empty_text')" compact />
          <hr class="gs-hr">
          <div class="gs-card-head"><div><h2>{{ t('analytics.key_events') }}</h2><p class="gs-sub sm">{{ t('analytics.key_events_sub') }}</p></div></div>
          <ul v-if="d && d.key_events.length" class="gs-kv">
            <li v-for="e in d.key_events" :key="e.event"><span class="gs-mono">{{ e.event }}</span><b>{{ fmt.int(e.count) }}</b></li>
          </ul>
          <p v-else-if="d" class="gs-muted sm">{{ t('analytics.key_events_empty') }}</p>
        </section>
      </div>

      <section class="gs-card gs-pad">
        <div class="gs-card-head">
          <div><h2>{{ t('analytics.top_pages') }}</h2><p class="gs-sub sm">{{ t('analytics.top_pages_sub') }}</p></div>
          <a :href="exportUrl + '&type=pages'" class="gs-link sm">{{ t('common.view_all') }} <i class="bx" :class="suite.dir === 'rtl' ? 'bx-left-arrow-alt' : 'bx-right-arrow-alt'"></i></a>
        </div>
        <gs-state v-if="loading && !d" kind="loading" compact />
        <gs-table v-else-if="d && d.pages.length" :rows="d.pages" initial-sort="views" row-key="page" :caption="t('analytics.top_pages')" :columns="[
          { key: 'page', label: t('columns.page_path'), class: 'gs-mono gs-ellipsis' },
          { key: 'views', label: t('columns.views'), numeric: true, format: fmt.int },
          { key: 'users', label: t('columns.users'), numeric: true, format: fmt.int },
          { key: 'avg_engagement', label: t('columns.avg_engagement'), numeric: true, format: fmt.duration },
          { key: 'key_events', label: t('columns.key_events'), numeric: true, format: fmt.int },
        ]" />
        <gs-state v-else-if="d" kind="empty" :text="t('analytics.empty_text')" compact />
      </section>

      <section class="gs-card gs-pad">
        <div class="gs-card-head">
          <div><h2>{{ t('analytics.breakdown_title') }}</h2><p class="gs-sub sm">{{ t('analytics.breakdown_sub') }}</p></div>
          <div class="gs-seg" role="tablist" :aria-label="t('analytics.breakdown_title')">
            <button v-for="tab in ['landing_pages', 'sources', 'countries']" :key="tab" type="button" role="tab" :aria-selected="breakdown === tab ? 'true' : 'false'" :class="{ active: breakdown === tab }" @click="breakdown = tab">{{ t('analytics.' + tab) }}</button>
          </div>
        </div>
        <gs-state v-if="loading && !d" kind="loading" compact />
        <template v-else-if="d">
          <gs-table v-if="breakdown === 'landing_pages' && d.landing_pages.length" :key="'lp'" :rows="d.landing_pages" initial-sort="sessions" :columns="[
            { key: 'page', label: t('columns.landingPage'), class: 'gs-mono gs-ellipsis' },
            { key: 'sessions', label: t('columns.sessions'), numeric: true, format: fmt.int },
            { key: 'users', label: t('columns.users'), numeric: true, format: fmt.int },
            { key: 'engagement_rate', label: t('columns.engagement_rate'), numeric: true, format: (v) => fmt.pct(v) },
            { key: 'key_events', label: t('columns.key_events'), numeric: true, format: fmt.int },
          ]" />
          <gs-table v-else-if="breakdown === 'sources' && d.sources.length" :key="'src'" :rows="d.sources" initial-sort="sessions" :columns="[
            { key: 'source', label: t('columns.source') },
            { key: 'sessions', label: t('columns.sessions'), numeric: true, format: fmt.int },
            { key: 'users', label: t('columns.users'), numeric: true, format: fmt.int },
          ]" />
          <gs-table v-else-if="breakdown === 'countries' && d.countries.length" :key="'ctry'" :rows="d.countries" initial-sort="users" :columns="[
            { key: 'country', label: t('columns.country'), format: (v) => v || t('common.not_set') },
            { key: 'users', label: t('columns.users'), numeric: true, format: fmt.int },
            { key: 'sessions', label: t('columns.sessions'), numeric: true, format: fmt.int },
          ]" />
          <gs-state v-else kind="empty" :text="t('analytics.empty_text')" compact />
        </template>
      </section>

      </template>

      <p class="gs-note"><i class="bx bx-info-circle"></i>{{ t('states.sample_note') }}<template v-if="fetchedAt"> · {{ t('common.last_synced') }} {{ fmt.ago(fetchedAt) }}</template><template v-if="d && d.time_zone"> · {{ d.time_zone }}</template></p>
    </template>
  </div>
</template>

<script>
import { suite, t, fmt, api, rangeParams, selected, grants, COLORS, DEVICE_COLORS } from '../store';
import GsRange from '../ui/GsRange.vue';
import GsDelta from '../ui/GsDelta.vue';
import GsState from '../ui/GsState.vue';
import GsTable from '../ui/GsTable.vue';
import GsDonut from '../ui/GsDonut.vue';
import GsLineChart from '../ui/GsLineChart.vue';

const CHANNEL_COLORS = ['#1A73E8', '#0B1F66', '#5B6478', '#8FB0FF', '#E8710A', '#188038'];

export default {
  name: 'GsAnalytics',
  components: { GsRange, GsDelta, GsState, GsTable, GsDonut, GsLineChart },
  data: () => ({ suite, COLORS, loading: false, result: null, metric: 'active_users', breakdown: 'landing_pages', realtime: null, realtimeError: null, timer: null }),
  computed: {
    fmt: () => fmt,
    prop() { return selected('ga4'); },
    blocker() {
      if (!suite.account.connected) return { code: 'not_connected', message: t('errors.not_connected'), retryable: false };
      if (!grants('ga4')) return { code: 'missing_scope', message: t('errors.missing_scope'), reconnect: true };
      if (!this.prop) return { code: 'no_property', message: t('states.no_property_text', { service: t('services.ga4') }), retryable: false };
      return null;
    },
    ready() { return !this.blocker; },
    d() { return this.result && this.result.data; },
    error() { return this.result && this.result.error; },
    fetchedAt() { return this.result && this.result.fetched_at; },
    empty() { return this.d && this.d.totals.current.sessions === 0 && this.d.totals.current.active_users === 0; },
    showRevenue() { return this.d && (this.d.totals.current.revenue > 0 || this.d.totals.previous.revenue > 0); },
    exportUrl() { return suite.urls.reports + '?service=ga4&range=' + encodeURIComponent(suite.range.key) + (suite.range.key === 'custom' ? `&start=${suite.range.start}&end=${suite.range.end}` : ''); },
    kpis() {
      const c = this.d ? this.d.totals.current : {}; const p = this.d ? this.d.totals.previous : {};
      return [
        { key: 'active_users', icon: 'bx-user', tint: ['#004CFF', '#EEF3FF'], label: t('analytics.kpi.active_users'), value: fmt.int(c.active_users), current: c.active_users, previous: p.active_users },
        { key: 'sessions', icon: 'bx-pulse', tint: ['#E8710A', '#FEF0E3'], label: t('analytics.kpi.sessions'), value: fmt.int(c.sessions), current: c.sessions, previous: p.sessions },
        { key: 'engagement_rate', icon: 'bx-like', tint: ['#188038', '#E6F4EA'], label: t('analytics.kpi.engagement_rate'), value: fmt.pct(c.engagement_rate), current: c.engagement_rate, previous: p.engagement_rate, mode: 'pts' },
        { key: 'avg_engagement', icon: 'bx-time-five', tint: ['#5235FF', '#F0EEFF'], label: t('analytics.kpi.avg_engagement'), value: fmt.duration(c.avg_engagement), current: c.avg_engagement, previous: p.avg_engagement },
        { key: 'key_events', icon: 'bx-target-lock', tint: ['#0B1F66', '#E8ECF8'], label: t('analytics.kpi.key_events'), value: fmt.int(c.key_events), current: c.key_events, previous: p.key_events },
        this.showRevenue
          ? { key: 'revenue', icon: 'bx-wallet', tint: ['#B06000', '#FEF7E0'], label: t('analytics.kpi.revenue'), value: fmt.money(c.revenue, this.d.currency), current: c.revenue, previous: p.revenue }
          : { key: 'revenue', icon: 'bx-wallet', tint: ['#B06000', '#FEF7E0'], label: t('analytics.kpi.revenue'), value: t('analytics.revenue_unset'), current: null, previous: null },
      ];
    },
    trendSeries() {
      const s = this.d.trend[this.metric];
      const label = t('analytics.trend_metric.' + this.metric);
      const out = [{ key: 'cur', label: `${label} · ${t('common.this_period')}`, color: COLORS.brand, values: s.current }];
      if (suite.compare) out.push({ key: 'prev', label: `${label} · ${t('common.previous')}`, color: COLORS.previous, values: s.previous, dashed: true });
      return out;
    },
    deviceItems() {
      return this.d.devices.map((x) => ({ key: x.device, label: x.device ? x.device.charAt(0).toUpperCase() + x.device.slice(1) : t('common.not_set'), value: x.users, color: DEVICE_COLORS[x.device] || COLORS.previous }));
    },
  },
  mounted() {
    if (!this.ready) return;
    this.load();
    this.loadRealtime();
    this.timer = window.setInterval(() => { if (!document.hidden) this.loadRealtime(); }, 60000);
  },
  beforeDestroy() { window.clearInterval(this.timer); },
  methods: {
    t,
    share(v) { const total = this.d.totals.current.sessions; return total ? Math.max(1, (v / total) * 100) : 0; },
    shareColor(channel) { const i = this.d.channels.findIndex((c) => c.channel === channel); return CHANNEL_COLORS[i] || COLORS.previous; },
    barHeight(v) { const max = Math.max(1, ...this.realtime.per_minute); return Math.max(6, (v / max) * 100); },
    async load() {
      this.loading = true;
      try { this.result = await api('get', suite.urls.api.ga4, { params: rangeParams() }); } catch (e) { this.result = { error: e }; } finally { this.loading = false; }
    },
    async loadRealtime() {
      try {
        const res = await api('get', suite.urls.api.realtime);
        if (res.error) { this.realtimeError = res.error; this.realtime = null; } else { this.realtime = res.data; this.realtimeError = null; }
      } catch (e) { this.realtimeError = e; }
    },
  },
};
</script>
