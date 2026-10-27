<template>
  <div class="gs-screen">
    <div class="gs-head">
      <div>
        <p class="gs-eyebrow">{{ t('outcomes.advertising') }}</p>
        <h1>{{ t('ads.title') }}</h1>
        <p class="gs-sub">
          <template v-if="account">{{ account.name }} · <span class="gs-mono">{{ account.customer_id }}</span></template>
          <template v-else>{{ t('ads.subtitle') }}</template>
        </p>
      </div>
      <div v-if="account" class="gs-head-actions">
        <gs-range @change="load" />
        <a :href="suite.urls.ads" class="gs-btn gs-btn-primary"><i class="bx bx-bullseye"></i>{{ t('overview.open_ads') }}</a>
      </div>
    </div>

    <!-- No Google Ads account in the Ads Hub yet -->
    <section v-if="!account || (error && error.code === 'ads_not_connected')" class="gs-card gs-pad gs-empty-hero">
      <gs-service-icon service="google_ads" />
      <div>
        <h2>{{ t(account ? 'ads.reconnect_title' : 'ads.connect_title') }}</h2>
        <p>{{ t(account ? 'ads.reconnect_text' : 'ads.connect_text') }}</p>
        <ul class="gs-tips">
          <li v-for="(p, i) in tr('ads.connect_points')" :key="i"><i class="bx bx-check"></i>{{ p }}</li>
        </ul>
      </div>
      <a :href="suite.urls.ads_connect" class="gs-btn gs-btn-primary"><i class="bx bx-link"></i>{{ t(account ? 'ads.reconnect' : 'ads.connect') }}</a>
    </section>

    <template v-else>
      <gs-state v-if="error" kind="error" :error="error" @retry="load" />

      <template v-if="!error || d">
        <section class="gs-kpis" :aria-busy="loading ? 'true' : 'false'">
          <div v-for="k in kpis" :key="k.key" class="gs-kpi">
            <span class="gs-kpi-label">{{ k.label }}</span>
            <strong v-if="d" class="gs-kpi-value">{{ k.value }}</strong>
            <span v-else class="gs-skel lg" aria-hidden="true"></span>
            <gs-delta v-if="d && k.current !== null" :current="k.current" :previous="k.previous" :mode="k.mode || 'pct'" :inverse="k.inverse" />
            <small v-if="d && k.note" class="gs-muted">{{ k.note }}</small>
          </div>
        </section>

        <div v-if="d && d.conversion_tracking === false" class="gs-banner is-warning" role="note">
          <i class="bx bx-info-circle"></i>
          <div><strong>{{ t('ads.no_tracking_title') }}</strong><p>{{ t('ads.no_tracking_text') }}</p></div>
          <a href="https://support.google.com/google-ads/answer/1722022" target="_blank" rel="noopener" class="gs-link">{{ t('common.learn_more') }}</a>
        </div>

        <section class="gs-card gs-pad">
          <div class="gs-card-head">
            <div>
              <p class="gs-eyebrow">{{ t('analytics.trend') }}</p>
              <div class="gs-seg" role="tablist">
                <button v-for="m in ['cost', 'clicks', 'conversions']" :key="m" type="button" role="tab" :aria-selected="metric === m ? 'true' : 'false'" :class="{ active: metric === m }" @click="metric = m">{{ t('ads.kpi.' + m) }}</button>
              </div>
            </div>
            <div class="gs-legend">
              <span><span class="gs-line-key" :style="{ background: COLORS.google_ads }"></span>{{ t('common.this_period') }}</span>
              <span v-if="suite.compare"><span class="gs-line-key dashed"></span>{{ t('common.previous') }}</span>
            </div>
          </div>
          <gs-state v-if="loading && !d" kind="loading" compact />
          <gs-state v-else-if="empty" kind="empty" :title="t('ads.empty_title')" :text="t('ads.empty_text')" compact />
          <gs-line-chart v-else-if="d" :series="trendSeries" :labels="d.trend.dates" area :y-format="metric === 'cost' ? (v) => fmt.money(v, d.currency) : fmt.compact" :aria-label="t('ads.kpi.' + metric)" :height="260" />
        </section>

        <section class="gs-card gs-pad">
          <div class="gs-card-head">
            <h2>{{ t('ads.campaigns') }}</h2>
            <span class="gs-muted sm">{{ t('ads.campaigns_hint') }}</span>
          </div>
          <div v-if="loading && !d" class="gs-skel-block"><span v-for="n in 4" :key="n" class="gs-skel"></span></div>
          <gs-table v-else-if="d && d.campaigns.length" :rows="d.campaigns" row-key="id" initial-sort="cost" :limit="10" :columns="[
            { key: 'name', label: t('ads.columns.campaign') },
            { key: 'cost', label: t('ads.kpi.cost'), numeric: true, format: (v) => fmt.money(v, d.currency) },
            { key: 'clicks', label: t('columns.clicks'), numeric: true, format: fmt.int },
            { key: 'ctr', label: t('columns.ctr'), numeric: true, format: (v) => fmt.pct(v), class: 'gs-hide-sm' },
            { key: 'conversions', label: t('ads.kpi.conversions'), numeric: true, format: (v) => fmt.dec(v, v % 1 ? 1 : 0) },
            { key: 'cost_per_conversion', label: t('ads.kpi.cpa'), numeric: true, format: (v) => v === null ? '—' : fmt.money(v, d.currency), class: 'gs-hide-sm' },
            { key: 'roas', label: t('ads.kpi.roas'), numeric: true, format: (v) => v === null ? '—' : fmt.dec(v, 2) + '×' },
          ]">
            <template #cell-name="{ row }">
              <span class="gs-cell-main">{{ row.name }}</span>
              <small class="gs-cell-sub">{{ statusLabel(row.status) }}<template v-if="row.channel"> · {{ channelLabel(row.channel) }}</template></small>
            </template>
          </gs-table>
          <gs-state v-else-if="d" kind="empty" :text="t('ads.no_campaigns')" compact />
        </section>

        <p class="gs-note"><i class="bx bx-info-circle"></i>{{ t('ads.source_note') }}<template v-if="fetchedAt"> · {{ t('common.last_synced') }} {{ fmt.ago(fetchedAt) }}</template></p>
      </template>
    </template>
  </div>
</template>

<script>
import { suite, t, raw, fmt, api, rangeParams, COLORS } from '../store';
import GsRange from '../ui/GsRange.vue';
import GsDelta from '../ui/GsDelta.vue';
import GsState from '../ui/GsState.vue';
import GsTable from '../ui/GsTable.vue';
import GsLineChart from '../ui/GsLineChart.vue';
import GsServiceIcon from '../ui/GsServiceIcon.vue';

/* Google Ads results (GAQL via the Ads Hub account). Conversions are whatever the account's own tracking records. */
export default {
  name: 'GsAdvertising',
  components: { GsRange, GsDelta, GsState, GsTable, GsLineChart, GsServiceIcon },
  data: () => ({ suite, COLORS, loading: false, result: null, metric: 'cost' }),
  computed: {
    fmt: () => fmt,
    account() { return suite.extra.ads_account; },
    d() { return this.result && this.result.data; },
    error() { return this.result && this.result.error; },
    fetchedAt() { return this.result && this.result.fetched_at; },
    empty() { return this.d && this.d.totals.current.impressions === 0 && this.d.totals.current.cost === 0; },
    kpis() {
      const c = this.d ? this.d.totals.current : {}; const p = this.d ? this.d.totals.previous : {};
      const cur = this.d && this.d.currency;
      const tracked = this.d && this.d.conversion_tracking !== false;
      return [
        { key: 'cost', label: t('ads.kpi.cost'), value: fmt.money(c.cost, cur), current: c.cost, previous: p.cost, inverse: true },
        { key: 'clicks', label: t('ads.kpi.clicks'), value: fmt.int(c.clicks), current: c.clicks, previous: p.clicks },
        { key: 'ctr', label: t('ads.kpi.ctr'), value: fmt.pct(c.ctr), current: c.ctr, previous: p.ctr, mode: 'pts', note: this.d ? t('ads.of_impressions', { n: fmt.compact(c.impressions) }) : null },
        { key: 'conversions', label: t('ads.kpi.conversions'), value: tracked ? fmt.dec(c.conversions, c.conversions % 1 ? 1 : 0) : t('ads.not_tracked'), current: tracked ? c.conversions : null, previous: p.conversions },
        { key: 'cpa', label: t('ads.kpi.cpa'), value: c.cost_per_conversion === null || c.cost_per_conversion === undefined ? '—' : fmt.money(c.cost_per_conversion, cur), current: c.cost_per_conversion, previous: p.cost_per_conversion, inverse: true },
        { key: 'roas', label: t('ads.kpi.roas'), value: c.roas ? fmt.dec(c.roas, 2) + '×' : '—', current: c.roas, previous: p.roas, note: c.roas ? null : (this.d ? t('ads.roas_unset') : null) },
      ];
    },
    trendSeries() {
      const s = this.d.trend[this.metric];
      const label = t('ads.kpi.' + this.metric);
      const out = [{ key: 'cur', label: `${label} · ${t('common.this_period')}`, color: COLORS.google_ads, values: s.current }];
      if (suite.compare) out.push({ key: 'prev', label: `${label} · ${t('common.previous')}`, color: COLORS.previous, values: s.previous, dashed: true });
      return out;
    },
  },
  mounted() { if (this.account) this.load(); },
  methods: {
    t,
    tr: (key) => raw(key) || [],
    statusLabel(s) { return t('ads.status.' + String(s || '').toLowerCase()) === 'ads.status.' + String(s || '').toLowerCase() ? (s || '') : t('ads.status.' + String(s).toLowerCase()); },
    channelLabel(c) { const k = 'ads.channel.' + String(c).toLowerCase(); return t(k) === k ? String(c).replace(/_/g, ' ').toLowerCase() : t(k); },
    async load() {
      this.loading = true;
      try { this.result = await api('get', suite.urls.api.google_ads, { params: rangeParams() }); } catch (e) { this.result = { error: e }; } finally { this.loading = false; }
    },
  },
};
</script>
