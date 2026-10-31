<template>
  <div class="ads-dash" :dir="i18n.dir">

    <!-- ================= HEADER ================= -->
    <header class="ad-head">
      <div class="ad-head-main">
        <p class="ad-eyebrow">{{ t('eyebrow') }}</p>
        <h1>{{ filter === 'all' ? t('title_all') : t('title_platform', { platform: platformName(activePlatform) }) }}</h1>
        <p class="ad-sub">{{ t('subtitle_all') }}</p>
      </div>
      <div v-if="data.has_connections" class="ad-head-side">
        <div class="ad-head-controls">
          <label class="ad-select">
            <i class="bx bx-calendar" aria-hidden="true"></i>
            <span class="ad-sr">{{ t('range_label') }}</span>
            <select v-model.number="days" @change="loadPerformance">
              <option v-for="d in [7, 30, 90]" :key="d" :value="d">{{ t('range_option', { days: d }) }}</option>
            </select>
          </label>
          <label class="ad-select sm">
            <span class="ad-sr">{{ t('platform_filter') }}</span>
            <select v-model="filter">
              <option value="all">{{ t('all_platforms') }}</option>
              <option v-for="p in connectedPlatforms" :key="p.platform" :value="p.platform">{{ platformName(p) }}</option>
            </select>
          </label>
          <a v-if="filter !== 'all' && activePlatform.create_url" :href="activePlatform.create_url" class="ad-btn primary"><i class="bx bx-plus"></i>{{ t('create_campaign') }}</a>
          <div v-else ref="createMenu" class="ad-menu">
            <button type="button" class="ad-btn primary" :aria-expanded="createOpen ? 'true' : 'false'" @click="createOpen = !createOpen">
              <i class="bx bx-plus"></i>{{ t('create_campaign') }}<i class="bx bx-chevron-down"></i>
            </button>
            <div v-if="createOpen" class="ad-menu-list" role="menu">
              <a v-for="p in connectedPlatforms" :key="p.platform" :href="p.create_url" role="menuitem">
                <span class="ad-pf-ic" :style="pfStyle(p)"><i class="bx" :class="p.icon"></i></span>{{ platformName(p) }}
              </a>
            </div>
          </div>
        </div>
        <p class="ad-sync">
          <span class="ad-dot" :class="loading ? 'is-loading' : (reporting.length ? 'is-ok' : 'is-off')"></span>
          <template v-if="loading">{{ t('sync_loading') }}</template>
          <template v-else-if="reporting.length">{{ t('sync_ok', { when: timeAgo(perf && perf.fetched_at) }) }} · {{ reporting.map((r) => platformName(r.meta)).join(', ') }}</template>
          <template v-else>{{ t('sync_none') }}</template>
        </p>
      </div>
    </header>

    <!-- ================= NOTHING CONNECTED ================= -->
    <section v-if="!data.has_connections" class="ad-card ad-empty">
      <div class="ring"><i class="bx bxs-megaphone"></i></div>
      <h5>{{ t('empty_title') }}</h5>
      <p>{{ t('empty_text') }}</p>
      <a v-if="data.connections_url" :href="data.connections_url" class="ad-btn primary"><i class="bx bx-link"></i>{{ t('go_connections') }}</a>
    </section>

    <template v-else>
      <!-- ================= INSIGHTS ================= -->
      <section v-if="insights.length" class="ad-insights" :aria-label="t('insights_title')">
        <p class="ad-insights-h"><i class="bx bx-bulb"></i>{{ t('insights_title') }} <span>· {{ t('insights_note') }}</span></p>
        <strong class="ad-insights-lead">{{ insights[0].text }}</strong>
        <div v-if="insights.length > 1" class="ad-insights-list">
          <span v-for="(item, i) in insights.slice(1, 4)" :key="i" class="ad-insight">
            {{ item.text }}<button v-if="item.action" type="button" class="ad-insight-go" @click="item.action()">{{ t('view') }}</button>
          </span>
        </div>
      </section>

      <!-- No platform in scope reports spend: say so instead of showing zeros. -->
      <div v-if="!loading && !reporting.length" class="ad-note">
        <i class="bx bx-info-circle"></i>
        <div><strong>{{ t('no_reporting_title') }}</strong><p>{{ t('no_reporting_text') }}</p></div>
      </div>
      <div v-if="perfError" class="ad-note bad">
        <i class="bx bx-error-circle"></i>
        <div><strong>{{ t('perf_error') }}</strong><p>{{ perfError }}</p></div>
        <button type="button" class="ad-btn ghost sm" @click="loadPerformance"><i class="bx bx-refresh"></i>{{ t('retry') }}</button>
      </div>
      <div v-for="r in failed" :key="r.key" class="ad-note bad">
        <i class="bx bx-error-circle"></i>
        <div><strong>{{ t('platform_error', { platform: platformName(r.meta) }) }}</strong><p>{{ r.error }}</p></div>
      </div>

      <!-- ================= KPI CARDS ================= -->
      <div class="ad-kpis">
        <div v-for="k in kpis" :key="k.key" class="ad-kpi">
          <div class="ad-kpi-top"><span class="ad-kpi-label">{{ k.label }}</span><i class="bx bx-info-circle ad-kpi-info" :title="k.hint"></i></div>
          <div v-if="loading" class="ad-skel lg"></div>
          <div v-else class="ad-kpi-val">{{ k.value }}</div>
          <div class="ad-kpi-foot">
            <div class="ad-kpi-delta">
              <span v-if="!loading && k.delta" class="ad-delta" :class="k.delta.tone"><i class="bx" :class="k.delta.icon"></i>{{ k.delta.text }}</span>
              <small :title="loading ? null : k.sub">{{ loading ? '' : k.sub }}</small>
            </div>
            <ad-sparkline v-if="!loading && k.spark && k.spark.length > 1" :values="k.spark" :color="k.delta && k.delta.tone === 'down' ? '#16A34A' : '#004CFF'" />
          </div>
        </div>
      </div>

      <div class="ad-strip">
        <div v-for="s in strip" :key="s.key" class="ad-strip-item">
          <span class="ad-strip-label">{{ s.label }}</span>
          <strong>{{ loading ? '—' : s.value }}</strong>
          <span v-if="!loading && s.delta" class="ad-delta sm" :class="s.delta.tone"><i class="bx" :class="s.delta.icon"></i>{{ s.delta.text }} {{ t('vs_previous') }}</span>
          <span v-else class="ad-strip-sub">{{ loading ? '' : s.sub }}</span>
        </div>
      </div>

      <!-- ================= TREND + PACING ================= -->
      <div class="ad-grid">
        <section class="ad-card">
          <div class="ad-card-h">
            <div><h2>{{ t('trend_title') }}</h2><p>{{ t('trend_sub') }}</p></div>
          </div>
          <div class="ad-tabs-row">
            <div class="ad-seg" role="tablist" :aria-label="t('trend_title')">
              <button v-for="m in trendMetrics" :key="m" type="button" role="tab" :aria-selected="metric === m ? 'true' : 'false'" :class="{ on: metric === m }" @click="metric = m">{{ t('metrics.' + m) }}</button>
            </div>
            <div class="ad-seg" role="tablist" :aria-label="t('grouping')">
              <button v-for="g in ['daily', 'weekly']" :key="g" type="button" role="tab" :aria-selected="grouping === g ? 'true' : 'false'" :class="{ on: grouping === g }" @click="grouping = g">{{ t('groupings.' + g) }}</button>
            </div>
          </div>
          <div v-if="loading" class="ad-skel chart"></div>
          <template v-else-if="trend">
            <ad-trend-chart :current="trendSeries.current" :previous="trendSeries.previous" :labels="trendSeries.labels" :format="metric === 'spend' ? moneyAxis : int"
                            :current-label="t('current_period')" :previous-label="t('previous_period')" :aria-label="t('metrics.' + metric)" />
            <div class="ad-legend">
              <span><i class="line"></i>{{ t('current_period') }}</span>
              <span><i class="line dash"></i>{{ t('previous_period') }}</span>
              <span class="ad-legend-src">{{ t('trend_source', { platforms: trendSources }) }}</span>
            </div>
          </template>
          <div v-else class="ad-none"><i class="bx bx-line-chart"></i>{{ t('trend_none') }}</div>
        </section>

        <section class="ad-card">
          <div class="ad-card-h"><div><h2>{{ t('pacing_title') }}</h2><p>{{ t('pacing_sub', { days }) }}</p></div></div>
          <ul v-if="pacing.length" class="ad-pacing">
            <li v-for="row in pacing" :key="row.key">
              <div class="ad-pacing-h">
                <span class="nm">{{ platformName(row.meta) }}</span>
                <span v-if="row.allocated && row.spend !== null" class="ct">{{ moneyShort(row.spend, row.currency) }} / {{ moneyShort(row.allocated, row.currency) }} · {{ row.pct }}%</span>
                <span v-else-if="row.allocated" class="ct">{{ t('pacing_configured', { amount: moneyShort(row.allocated / days) }) }}</span>
                <span v-else class="ct">{{ t('pacing_no_budget') }}</span>
              </div>
              <div class="ad-track"><span :style="{ width: (row.pct === null ? 0 : Math.min(100, row.pct)) + '%', background: barColor(row.meta) }"></span></div>
              <small v-if="row.spend === null" class="ad-pacing-note">{{ t('pacing_no_spend') }}</small>
            </li>
          </ul>
          <div v-else class="ad-none"><i class="bx bx-wallet"></i>{{ t('pacing_none') }}</div>
        </section>
      </div>

      <!-- ================= COMPARISON + FUNNEL ================= -->
      <div class="ad-grid">
        <section class="ad-card">
          <div class="ad-card-h"><div><h2>{{ t('comparison') }}</h2><p>{{ t('comparison_sub') }}</p></div></div>
          <div v-if="comparison.length" class="ad-compare">
            <ul class="ad-bars">
              <li v-for="row in comparison" :key="row.key">
                <div class="ad-bars-head"><span class="nm">{{ platformName(row.meta) }}</span><span class="ct">{{ row.share === null ? t('not_reported') : row.share + '%' }}</span></div>
                <div class="ad-track"><span :style="{ width: (row.share || 0) + '%', background: barColor(row.meta) }"></span></div>
              </li>
            </ul>
            <div class="ad-table-wrap">
              <table class="ad-table compact">
                <thead><tr><th>{{ t('table.platform') }}</th><th class="num">{{ t('metrics.spend') }}</th><th class="num">{{ t('metrics.conversions_short') }}</th><th class="num">{{ t('metrics.cpa') }}</th><th class="num">{{ t('metrics.roas') }}</th></tr></thead>
                <tbody>
                  <tr v-for="row in comparison" :key="row.key">
                    <td>{{ platformName(row.meta) }}</td>
                    <td class="num">{{ row.spend === null ? '—' : moneyShort(row.spend, row.currency) }}</td>
                    <td class="num">{{ row.conversions === null ? '—' : dec(row.conversions) }}</td>
                    <td class="num">{{ row.cpa === null ? '—' : moneyShort(row.cpa, row.currency) }}</td>
                    <td class="num">{{ row.roas === null ? '—' : dec(row.roas, 2) + '×' }}</td>
                  </tr>
                </tbody>
              </table>
              <p class="ad-foot">{{ t('comparison_foot') }}</p>
            </div>
          </div>
          <div v-else class="ad-none"><i class="bx bx-bar-chart-alt-2"></i>{{ t('comparison_none') }}</div>
        </section>

        <section class="ad-card">
          <div class="ad-card-h"><div><h2>{{ t('funnel_title') }}</h2><p>{{ t('funnel_sub') }}</p></div></div>
          <ul v-if="funnel" class="ad-funnel">
            <li v-for="(step, i) in funnel.steps" :key="step.key">
              <span class="nm">{{ t('metrics.' + step.key) }}</span>
              <div class="bar"><span :style="{ width: step.width + '%', opacity: 1 - i * 0.18 }"></span></div>
              <b>{{ int(step.value) }}</b>
              <small v-if="step.rate !== null" class="rate"><i class="bx bx-down-arrow-alt"></i>{{ t('funnel_rates.' + step.key, { rate: pct(step.rate) }) }}</small>
            </li>
          </ul>
          <p v-if="funnel" class="ad-foot">{{ t('funnel_source', { platforms: funnel.sources }) }}</p>
          <div v-else class="ad-none"><i class="bx bx-filter-alt"></i>{{ t('funnel_none') }}</div>
        </section>
      </div>

      <!-- ================= CAMPAIGNS ================= -->
      <section ref="campaignsCard" class="ad-card">
        <div class="ad-card-h"><div><h2>{{ t('campaigns_title') }}</h2><p>{{ t('campaigns_sub') }}</p></div></div>
        <div class="ad-tools">
          <div class="ad-seg" role="tablist" :aria-label="t('campaigns_title')">
            <button v-for="v in ['all', 'top', 'attention']" :key="v" type="button" role="tab" :aria-selected="view === v ? 'true' : 'false'" :class="{ on: view === v }" @click="setView(v)">{{ t('views.' + v) }}</button>
          </div>
          <div class="ad-tools-end">
            <label class="ad-search"><i class="bx bx-search" aria-hidden="true"></i><input v-model.trim="query" type="search" :placeholder="t('search')" :aria-label="t('search')" @input="page = 1"></label>
            <label class="ad-select sm"><span class="ad-sr">{{ t('platform_filter') }}</span>
              <select v-model="tablePlatform" @change="page = 1">
                <option value="all">{{ t('all_platforms') }}</option>
                <option v-for="p in scopePlatforms" :key="p.platform" :value="p.platform">{{ platformName(p) }}</option>
              </select>
            </label>
            <label class="ad-select sm"><span class="ad-sr">{{ t('status_filter') }}</span>
              <select v-model="statusFilter" @change="page = 1">
                <option value="all">{{ t('all_statuses') }}</option>
                <option value="active">{{ t('status_groups.active') }}</option>
                <option value="paused">{{ t('status_groups.paused') }}</option>
                <option value="other">{{ t('status_groups.other') }}</option>
              </select>
            </label>
            <button type="button" class="ad-btn ghost" :disabled="!filteredRows.length" @click="exportCsv"><i class="bx bx-download"></i>{{ t('export') }}</button>
          </div>
        </div>

        <div v-if="!allRows.length" class="ad-none"><i class="bx bx-rocket"></i>{{ t('no_campaigns') }}</div>
        <div v-else-if="!filteredRows.length" class="ad-none"><i class="bx bx-search-alt"></i>{{ view === 'attention' ? t('attention_none') : t('no_matches') }}</div>
        <div v-else class="ad-table-wrap edge">
          <table class="ad-table">
            <thead>
              <tr>
                <th>{{ t('table.campaign') }}</th><th>{{ t('table.status') }}</th><th class="num">{{ t('table.budget') }}</th>
                <th>{{ t('metrics.spend') }}</th><th class="num">{{ t('metrics.conversions') }}</th><th class="num">{{ t('metrics.cpa') }}</th><th class="num">{{ t('metrics.roas') }}</th>
                <th><span class="ad-sr">{{ t('table.actions') }}</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in pageRows" :key="row.platform + row.id">
                <td>
                  <div class="c-cell">
                    <span class="ad-pf-ic sm" :style="pfStyle(row.meta)"><i class="bx" :class="row.meta.icon"></i></span>
                    <div class="c-txt"><span class="c-nm" :title="row.name">{{ row.name }}</span><span class="c-sub">{{ platformName(row.meta) }}</span></div>
                  </div>
                </td>
                <td><span class="ad-pill" :class="statusClass(row.status)"><span class="d"></span>{{ statusLabel(row.status) }}</span></td>
                <td class="num">{{ budgetLabel(row) }}</td>
                <td>
                  <template v-if="row.m && row.m.spend !== null">
                    <span class="c-num">{{ moneyShort(row.m.spend, row.currency) }}</span>
                    <span v-if="row.allocated" class="c-spend"><span :style="{ width: Math.min(100, (row.m.spend / row.allocated) * 100) + '%' }"></span></span>
                  </template>
                  <span v-else class="c-muted" :title="t('not_reported_hint')">{{ t('not_reported') }}</span>
                </td>
                <td class="num">{{ row.m && row.m.conversions !== null ? dec(row.m.conversions) : '—' }}</td>
                <td class="num">{{ row.cpa === null ? '—' : moneyShort(row.cpa, row.currency) }}</td>
                <td class="num"><span v-if="row.roas !== null" class="c-roas" :class="row.roas >= 1 ? 'up' : 'down'"><i class="bx" :class="row.roas >= 1 ? 'bx-trending-up' : 'bx-trending-down'"></i>{{ dec(row.roas, 2) }}</span><template v-else>—</template></td>
                <td class="num"><a v-if="row.meta.campaigns_url" :href="row.meta.campaigns_url" class="c-act" :title="t('manage')" :aria-label="t('manage') + ' · ' + row.name"><i class="bx bx-dots-horizontal-rounded"></i></a></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="filteredRows.length > PAGE" class="ad-pager">
          <span>{{ t('page_range', { from: (page - 1) * PAGE + 1, to: Math.min(page * PAGE, filteredRows.length), total: filteredRows.length }) }}</span>
          <button type="button" class="c-act" :disabled="page === 1" :aria-label="t('prev')" @click="page--"><i class="bx bx-chevron-left"></i></button>
          <button type="button" class="c-act" :disabled="page * PAGE >= filteredRows.length" :aria-label="t('next')" @click="page++"><i class="bx bx-chevron-right"></i></button>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { createTranslator } from './facebook/i18n';
import AdSparkline from './dashboard/AdSparkline.vue';
import AdTrendChart from './dashboard/AdTrendChart.vue';

const props = defineProps({
  data: { type: Object, required: true },
  // __('ads_dashboard') for the current locale (lang/{en,ar}/ads_dashboard.php).
  messages: { type: Object, default: () => ({}) },
  locale: { type: String, default: 'en' },
});

const i18n = createTranslator(props.messages, props.locale);
const { t, tc } = i18n;
const known = (key, fallback) => { const v = t(key); return v === key ? fallback : v; };
const PAGE = 8;

/* ---------------- state ---------------- */
const days = ref(30);
const filter = ref('all');
const metric = ref('spend');
const grouping = ref('daily');
const view = ref('all');
const query = ref('');
const tablePlatform = ref('all');
const statusFilter = ref('all');
const page = ref(1);
const perf = ref(null);
const perfError = ref('');
const loading = ref(false);
const createOpen = ref(false);
const createMenu = ref(null);
const campaignsCard = ref(null);

/* ---------------- formatting ---------------- */
function int(v) { return Number(v || 0).toLocaleString(i18n.intl, { maximumFractionDigits: 0 }); }
function dec(v, digits = 1) { const n = Number(v || 0); return n.toLocaleString(i18n.intl, { maximumFractionDigits: Number.isInteger(n) && digits === 1 ? 0 : digits, minimumFractionDigits: Number.isInteger(n) && digits === 1 ? 0 : digits }); }
function pct(v, digits = 2) { return (Number(v || 0) * 100).toLocaleString(i18n.intl, { maximumFractionDigits: digits, minimumFractionDigits: digits }) + '%'; }
function moneyShort(v, cur) {
  const c = cur === undefined ? baseCurrency.value : cur;
  const n = Number(v || 0);
  const s = n.toLocaleString(i18n.intl, { maximumFractionDigits: Math.abs(n) >= 1000 ? 0 : 2, minimumFractionDigits: Math.abs(n) >= 1000 ? 0 : 2 });
  return (c ? c + ' ' : '') + s;
}
function moneyAxis(v) { return (baseCurrency.value ? baseCurrency.value + ' ' : '') + Number(v || 0).toLocaleString(i18n.intl, { maximumFractionDigits: 0 }); }
function timeAgo(iso) {
  if (!iso) return t('just_now');
  const m = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
  if (m < 1) return t('just_now');
  return m < 60 ? t('minutes_ago', { n: m }) : t('hours_ago', { n: Math.round(m / 60) });
}
function delta(cur, prev, { inverse = false } = {}) {
  if (cur === null || prev === null || prev === undefined || !prev) return null;
  const c = (cur - prev) / Math.abs(prev);
  if (Math.abs(c) < 0.0005) return { tone: 'flat', icon: 'bx-minus', text: t('no_change') };
  const good = inverse ? c < 0 : c > 0;
  return { tone: good ? 'up' : 'down', icon: c > 0 ? 'bx-trending-up' : 'bx-trending-down', text: (c > 0 ? '+' : '−') + (Math.abs(c) * 100).toLocaleString(i18n.intl, { maximumFractionDigits: 1 }) + '%' };
}

/* ---------------- platforms ---------------- */
const platforms = computed(() => props.data.platforms || []);
const connectedPlatforms = computed(() => platforms.value.filter((p) => p.connected));
const activePlatform = computed(() => platforms.value.find((p) => p.platform === filter.value) || { label: '', campaigns: [] });
const scopePlatforms = computed(() => (filter.value === 'all' ? connectedPlatforms.value : connectedPlatforms.value.filter((p) => p.platform === filter.value)));
function platformName(p) { return known('platforms.' + p.platform, p.label); }
function metaFor(key) { return platforms.value.find((p) => p.platform === key) || { platform: key, label: key, icon: 'bx-globe', color: '#6b7280' }; }
// Brand colours that are unreadable on white (Snapchat yellow) get a darker shade.
function barColor(p) { return /^#?(fffc00|e0b400)$/i.test(String(p.color || '')) ? '#C9A400' : (p.color || '#6b7280'); }
function pfStyle(p) { return { background: barColor(p) + '1a', color: barColor(p) }; }

/* ---------------- reporting (live, from /ads/dashboard/performance) ---------------- */
// YouTube is reported inside the Google Ads customer, so its own row stays "in Google".
const reporting = computed(() => {
  if (!perf.value) return [];
  const keys = scopePlatforms.value.map((p) => (p.platform === 'youtube' ? 'google' : p.platform));
  return [...new Set(keys)].map((key) => ({ key, meta: metaFor(key), ...(perf.value.platforms[key] || {}) })).filter((r) => r.status === 'ok');
});
const failed = computed(() => {
  if (!perf.value) return [];
  return scopePlatforms.value.map((p) => ({ key: p.platform, meta: p, ...(perf.value.platforms[p.platform] || {}) })).filter((r) => r.status === 'error');
});
// Money is only added up within one currency; others are shown per platform.
const baseCurrency = computed(() => {
  const counts = {};
  reporting.value.forEach((r) => { if (r.currency) counts[r.currency] = (counts[r.currency] || 0) + 1; });
  return Object.keys(counts).sort((a, b) => counts[b] - counts[a])[0] || props.data.currency || '';
});
const included = computed(() => reporting.value.filter((r) => !r.currency || r.currency === baseCurrency.value));
const withConversions = computed(() => included.value.filter((r) => r.has_conversions));
// Changes compare like with like: only platforms that report a previous period (ChatGPT Ads doesn't).
const comparableList = computed(() => included.value.filter((r) => r.totals.previous));
const comparable = computed(() => comparableList.value.length > 0);
const partialCompare = computed(() => comparable.value && comparableList.value.length < included.value.length);

function sum(list, period, field) {
  return list.reduce((n, r) => n + Number((r.totals[period] || {})[field] || 0), 0);
}
const totals = computed(() => {
  const build = (period, inc, conv) => ({
    spend: sum(inc, period, 'spend'), clicks: sum(inc, period, 'clicks'), impressions: sum(inc, period, 'impressions'),
    conversions: conv.length ? sum(conv, period, 'conversions') : null,
    convSpend: sum(conv, period, 'spend'), convClicks: sum(conv, period, 'clicks'), value: sum(conv, period, 'value'),
  });
  const cmp = comparableList.value;
  return {
    current: build('current', included.value, withConversions.value),
    // Same-platform current/previous pair for the change figures.
    compareCurrent: comparable.value ? build('current', cmp, cmp.filter((r) => r.has_conversions)) : null,
    previous: comparable.value ? build('previous', cmp, cmp.filter((r) => r.has_conversions)) : null,
  };
});
const ratio = (a, b) => (b ? a / b : null);

const compareNote = computed(() => (partialCompare.value ? t('vs_previous_some', { days: days.value, platforms: comparableList.value.map((r) => platformName(r.meta)).join(', ') }) : t('vs_previous_days', { days: days.value })));
const kpis = computed(() => {
  const c = totals.value.current; const p = totals.value.previous; const cc = totals.value.compareCurrent;
  const cpa = c.conversions ? ratio(c.convSpend, c.conversions) : null;
  const roas = c.value > 0 ? ratio(c.value, c.convSpend) : null;
  const pCpa = p && p.conversions ? ratio(p.convSpend, p.conversions) : null;
  const pRoas = p && p.value > 0 ? ratio(p.value, p.convSpend) : null;
  const cCpa = cc && cc.conversions ? ratio(cc.convSpend, cc.conversions) : null;
  const cRoas = cc && cc.value > 0 ? ratio(cc.value, cc.convSpend) : null;
  const tr = trend.value;
  const none = !reporting.value.length;
  const convSources = withConversions.value.map((r) => platformName(r.meta)).join(', ');
  return [
    { key: 'spend', label: t('metrics.spend'), hint: t('hint_spend'), value: none ? '—' : moneyShort(c.spend), delta: p ? delta(cc.spend, p.spend) : null, sub: p ? compareNote.value : t('in_period', { days: days.value }), spark: tr ? tr.spend.current : null },
    { key: 'conversions', label: t('metrics.conversions'), hint: t('hint_conversions'), value: c.conversions === null ? '—' : dec(c.conversions), delta: p ? delta(cc.conversions, p.conversions) : null, sub: c.conversions === null ? t('conv_not_reported') : (p ? compareNote.value : convSources), spark: tr ? tr.conversions.current : null },
    { key: 'cpa', label: t('metrics.cpa_long'), hint: t('hint_cpa'), value: cpa === null ? '—' : moneyShort(cpa), delta: p ? delta(cCpa, pCpa, { inverse: true }) : null, sub: cpa === null ? t('needs_conversions') : (p ? compareNote.value : convSources), spark: tr ? tr.spend.current.map((s, i) => (tr.conversions.current[i] ? s / tr.conversions.current[i] : 0)) : null },
    { key: 'roas', label: t('metrics.roas'), hint: t('hint_roas'), value: roas === null ? '—' : dec(roas, 2) + '×', delta: p ? delta(cRoas, pRoas) : null, sub: roas === null ? t('needs_values') : (p ? compareNote.value : convSources), spark: null },
  ];
});

const strip = computed(() => {
  const c = totals.value.current; const p = totals.value.previous; const cc = totals.value.compareCurrent;
  const ctr = ratio(c.clicks, c.impressions); const cpc = ratio(c.spend, c.clicks); const cpm = c.impressions ? (c.spend / c.impressions) * 1000 : null;
  const convRate = c.conversions !== null ? ratio(c.conversions, c.convClicks) : null;
  const none = !reporting.value.length;
  const dash = (v, f) => (none || v === null ? '—' : f(v));
  return [
    { key: 'impressions', label: t('metrics.impressions'), value: dash(c.impressions, int), delta: p ? delta(cc.impressions, p.impressions) : null, sub: '' },
    { key: 'clicks', label: t('metrics.clicks'), value: dash(c.clicks, int), delta: p ? delta(cc.clicks, p.clicks) : null, sub: '' },
    { key: 'ctr', label: t('metrics.ctr'), value: dash(ctr, (v) => pct(v)), delta: p ? delta(ratio(cc.clicks, cc.impressions), ratio(p.clicks, p.impressions)) : null, sub: '' },
    { key: 'cpc', label: t('metrics.cpc'), value: dash(cpc, (v) => moneyShort(v)), delta: p ? delta(ratio(cc.spend, cc.clicks), ratio(p.spend, p.clicks), { inverse: true }) : null, sub: '' },
    { key: 'cpm', label: t('metrics.cpm'), value: dash(cpm, (v) => moneyShort(v)), delta: p && p.impressions && cc.impressions ? delta((cc.spend / cc.impressions) * 1000, (p.spend / p.impressions) * 1000, { inverse: true }) : null, sub: '' },
    { key: 'conv_rate', label: t('metrics.conv_rate'), value: dash(convRate, (v) => pct(v)), delta: p && p.conversions !== null ? delta(ratio(cc.conversions, cc.convClicks), ratio(p.conversions, p.convClicks)) : null, sub: c.conversions === null ? t('conv_not_reported') : '' },
  ];
});

/* ---------------- trend ---------------- */
// Only platforms that return a daily series (Google Ads) feed the chart.
const trendSources = computed(() => included.value.filter((r) => r.trend).map((r) => platformName(r.meta)).join(', '));
const trend = computed(() => {
  const list = included.value.filter((r) => r.trend);
  if (!list.length) return null;
  const n = list[0].trend.dates.length;
  const add = (field, period) => Array.from({ length: n }, (_, i) => list.reduce((s, r) => s + Number((r.trend[field][period] || [])[i] || 0), 0));
  const series = (field) => ({ current: add(field, 'current'), previous: add(field, 'previous') });
  return { dates: list[0].trend.dates, spend: series('spend'), clicks: series('clicks'), conversions: series('conversions') };
});
const trendMetrics = computed(() => (withConversions.value.some((r) => r.trend) ? ['spend', 'clicks', 'conversions'] : ['spend', 'clicks']));
const trendSeries = computed(() => {
  const tr = trend.value; const s = tr[metric.value] || tr.spend;
  const fmt = (iso) => new Date(iso + 'T00:00:00').toLocaleDateString(i18n.intl, { day: 'numeric', month: 'short' });
  if (grouping.value === 'daily') return { current: s.current, previous: s.previous, labels: tr.dates.map(fmt) };
  const chunk = (arr) => arr.reduce((out, v, i) => { if (i % 7 === 0) out.push(0); out[out.length - 1] += v; return out; }, []);
  return { current: chunk(s.current), previous: chunk(s.previous), labels: chunk(tr.dates.map(() => 0)).map((_, w) => t('week_n', { n: w + 1 })) };
});

/* ---------------- budget pacing ---------------- */
// Allocated = active campaigns' daily budgets × days in the period (daily budgets only).
const allCampaigns = computed(() => scopePlatforms.value.flatMap((p) => (p.campaigns || []).map((c) => ({ ...c, meta: p }))));
function isActive(status) { return ['active', 'enable', 'enabled', 'running', '1', 'true'].includes(String(status).toLowerCase()) || status === true; }
const pacing = computed(() => scopePlatforms.value.filter((p) => p.platform !== 'youtube').map((p) => {
  const key = p.platform;
  const daily = allCampaigns.value.filter((c) => (c.meta.platform === key || (key === 'google' && c.meta.platform === 'youtube')) && isActive(c.status))
    .reduce((n, c) => n + Number(c.daily_budget || 0), 0);
  const allocated = daily * days.value;
  const r = reporting.value.find((x) => x.key === key);
  const spend = r ? Number(r.totals.current.spend || 0) : null;
  return { key, meta: p, allocated, spend, currency: r ? r.currency : baseCurrency.value, pct: allocated && spend !== null ? Math.round((spend / allocated) * 100) : null };
}).filter((row) => row.allocated || row.spend !== null));

/* ---------------- comparison + funnel ---------------- */
const comparison = computed(() => {
  const total = included.value.reduce((n, r) => n + Number(r.totals.current.spend || 0), 0);
  return scopePlatforms.value.filter((p) => p.platform !== 'youtube').map((p) => {
    const r = reporting.value.find((x) => x.key === p.platform);
    if (!r) return { key: p.platform, meta: p, spend: null, share: null, conversions: null, cpa: null, roas: null };
    const c = r.totals.current; const same = !r.currency || r.currency === baseCurrency.value;
    return {
      key: p.platform, meta: p, currency: r.currency, spend: Number(c.spend || 0),
      share: same && total ? Math.round((Number(c.spend || 0) / total) * 100) : null,
      conversions: r.has_conversions ? Number(c.conversions || 0) : null,
      cpa: r.has_conversions && c.conversions ? c.spend / c.conversions : null,
      roas: r.has_conversions && c.value > 0 && c.spend ? c.value / c.spend : null,
    };
  }).sort((a, b) => (b.spend || -1) - (a.spend || -1));
});
const funnel = computed(() => {
  const conv = withConversions.value;
  if (!conv.length) return null;
  const imp = sum(conv, 'current', 'impressions'); const clk = sum(conv, 'current', 'clicks'); const cv = sum(conv, 'current', 'conversions');
  if (!imp) return null;
  return {
    sources: conv.map((r) => platformName(r.meta)).join(', '),
    steps: [
      { key: 'impressions', value: imp, width: 100, rate: ratio(clk, imp) },
      { key: 'clicks', value: clk, width: Math.max(3, Math.sqrt(clk / imp) * 100), rate: ratio(cv, clk) },
      { key: 'conversions', value: cv, width: Math.max(2, Math.sqrt(cv / imp) * 100), rate: null },
    ].map((s) => ({ ...s, width: Math.min(100, s.width) })),
  };
});

/* ---------------- campaigns table ---------------- */
const allRows = computed(() => allCampaigns.value.map((c) => {
  const m = perf.value && perf.value.campaign_metrics ? perf.value.campaign_metrics[c.id] || null : null;
  const r = reporting.value.find((x) => x.key === (c.meta.platform === 'youtube' ? 'google' : c.meta.platform));
  return {
    ...c, m, currency: r ? r.currency : baseCurrency.value,
    allocated: c.daily_budget ? Number(c.daily_budget) * days.value : (c.lifetime_budget ? Number(c.lifetime_budget) : 0),
    cpa: m && m.conversions ? m.spend / m.conversions : null,
    roas: m && m.value > 0 && m.spend ? m.value / m.spend : null,
  };
}));
function statusGroup(status) {
  if (isActive(status)) return 'active';
  return ['paused', 'disable', 'disabled', 'false', '0'].includes(String(status).toLowerCase()) || status === false ? 'paused' : 'other';
}
function needsAttention(row) {
  const s = String(row.status || '').toLowerCase();
  if (['error', 'rejected', 'failed', 'with_issues'].includes(s)) return true;
  if (row.m && row.m.conversions === 0 && row.m.spend > 0) return true;
  return !!(row.m && row.allocated && row.m.spend >= row.allocated * 0.9);
}
const filteredRows = computed(() => {
  const q = query.value.toLowerCase();
  let rows = allRows.value.filter((r) => (tablePlatform.value === 'all' || r.meta.platform === tablePlatform.value)
    && (statusFilter.value === 'all' || statusGroup(r.status) === statusFilter.value)
    && (!q || [r.name, r.objective, platformName(r.meta)].some((v) => String(v || '').toLowerCase().includes(q))));
  if (view.value === 'top') rows = rows.filter((r) => r.m && r.m.spend > 0).sort((a, b) => (b.roas || 0) - (a.roas || 0) || b.m.spend - a.m.spend);
  else if (view.value === 'attention') rows = rows.filter(needsAttention);
  else rows = [...rows].sort((a, b) => ((b.m ? b.m.spend : -1) - (a.m ? a.m.spend : -1)) || String(b.created_at || '').localeCompare(String(a.created_at || '')));
  return rows;
});
const pageRows = computed(() => filteredRows.value.slice((page.value - 1) * PAGE, page.value * PAGE));
function setView(v) { view.value = v; page.value = 1; }
function budgetLabel(c) {
  if (c.daily_budget) return t('per_day', { amount: moneyShort(c.daily_budget, c.currency) });
  if (c.lifetime_budget) return moneyShort(c.lifetime_budget, c.currency);
  return '—';
}
function statusLabel(status) {
  if (status === true || status === false) return t('status_groups.' + (status ? 'active' : 'paused'));
  if (!status) return t('unknown');
  return known('statuses.' + String(status).toLowerCase(), String(status));
}
function statusClass(status) {
  const g = statusGroup(status);
  if (g === 'active') return 'ok';
  if (g === 'paused') return 'warn';
  return ['error', 'rejected', 'failed'].includes(String(status).toLowerCase()) ? 'bad' : 'mut';
}

/* CSV of the rows currently listed (formula-safe). */
function exportCsv() {
  const cell = (v) => { let s = v === null || v === undefined ? '' : String(v); if (/^[=+\-@\t\r]/.test(s)) s = "'" + s; return '"' + s.replace(/"/g, '""') + '"'; };
  const head = [t('table.campaign'), t('table.platform'), t('table.status'), t('table.daily_budget'), t('table.lifetime_budget'), t('metrics.spend'), t('metrics.clicks'), t('metrics.impressions'), t('metrics.conversions'), t('metrics.cpa'), t('metrics.roas'), t('table.currency')];
  const lines = filteredRows.value.map((r) => [r.name, platformName(r.meta), statusLabel(r.status), r.daily_budget ?? '', r.lifetime_budget ?? '',
    r.m ? r.m.spend : '', r.m ? r.m.clicks : '', r.m ? r.m.impressions : '', r.m && r.m.conversions !== null ? r.m.conversions : '',
    r.cpa === null ? '' : r.cpa.toFixed(2), r.roas === null ? '' : r.roas.toFixed(2), r.currency || ''].map(cell).join(','));
  const blob = new Blob(['﻿' + [head.map(cell).join(','), ...lines].join('\n')], { type: 'text/csv;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `ads-campaigns-${days.value}d-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

/* ---------------- insights (only from reported numbers) ---------------- */
function focusCampaigns(v) { setView(v); if (campaignsCard.value) campaignsCard.value.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
const insights = computed(() => {
  if (loading.value || !reporting.value.length) return [];
  const out = [];
  const c = totals.value.current; const p = totals.value.previous;
  const kRoas = kpis.value[3];
  if (kRoas.delta && kRoas.value !== '—') out.push({ text: t('insight_roas', { change: kRoas.delta.text, value: kRoas.value }) });
  else if (p && p.spend) out.push({ text: t('insight_spend', { change: delta(totals.value.compareCurrent.spend, p.spend).text, value: moneyShort(c.spend) }) });
  else out.push({ text: t('insight_total', { value: moneyShort(c.spend), days: days.value }) });
  const top = comparison.value.filter((r) => r.share !== null)[0];
  if (top && comparison.value.filter((r) => r.share !== null).length > 1) out.push({ text: t('insight_top', { platform: platformName(top.meta), share: top.share }) });
  const best = allRows.value.filter((r) => r.roas !== null).sort((a, b) => b.roas - a.roas)[0];
  if (best) out.push({ text: t('insight_best', { name: best.name, roas: dec(best.roas, 2) }), action: () => focusCampaigns('top') });
  const attention = allRows.value.filter(needsAttention).length;
  if (attention) out.push({ text: tc('insight_attention', attention, { count: attention }), action: () => focusCampaigns('attention') });
  const google = reporting.value.find((r) => r.key === 'google');
  if (google && google.conversion_tracking === false) out.push({ text: t('insight_tracking') });
  return out;
});

/* ---------------- loading ---------------- */
async function loadPerformance() {
  if (!props.data.performance_url || !props.data.has_connections) return;
  loading.value = true; perfError.value = '';
  try {
    const { data } = await window.axios.get(props.data.performance_url, { params: { days: days.value } });
    perf.value = data;
  } catch (e) {
    perfError.value = (e && e.response && e.response.data && e.response.data.message) || t('perf_error_text');
  } finally {
    loading.value = false;
  }
}

function closeCreate(event) {
  if (createOpen.value && createMenu.value && !createMenu.value.contains(event.target)) createOpen.value = false;
}
onMounted(() => { document.addEventListener('click', closeCreate); loadPerformance(); });
onBeforeUnmount(() => document.removeEventListener('click', closeCreate));
</script>

<style scoped>
/* Socialeaz Ads palette (shared with the Google Suite and campaign pages). */
.ads-dash {
  --ink: #161B2B; --text: #4B5263; --muted: #8A92A3; --line: #E7E9F0; --line-soft: #F1F3F7; --wash: #F8F9FC;
  --brand: #004CFF; --brand-2: #5235FF; --brand-soft: #EEF3FF; --brand-line: #CFDCFF;
  --shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 8px 24px rgba(16, 24, 40, .03);
  display: flex; flex-direction: column; gap: 18px; min-width: 0; color: var(--text);
  container-type: inline-size; container-name: adsdash;
}
.ad-sr { position: absolute !important; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

/* header */
.ad-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem 1.5rem; }
.ad-head-main { flex: 1 1 360px; min-width: 0; }
.ad-eyebrow { margin: 0 0 .45rem; font-size: .76rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--brand); }
.ad-head h1 { margin: 0; font-size: 1.85rem; font-weight: 700; letter-spacing: -.02em; color: var(--ink); line-height: 1.2; }
.ad-sub { margin: .4rem 0 0; font-size: .92rem; color: #6B7385; }
.ad-head-side { display: flex; flex-direction: column; align-items: flex-end; gap: .55rem; }
.ad-head-controls { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: flex-end; }
.ad-sync { margin: 0; display: flex; align-items: center; gap: .4rem; font-size: .8rem; color: var(--muted); text-align: end; }
.ad-dot { width: 7px; height: 7px; border-radius: 50%; background: #C3C9D6; flex-shrink: 0; }
.ad-dot.is-ok { background: #16A34A; }
.ad-dot.is-loading { background: var(--brand); animation: ad-pulse 1s infinite alternate; }

/* controls */
.ad-select { position: relative; display: inline-flex; align-items: center; gap: .45rem; height: 38px; padding: 0 .75rem; border: 1px solid var(--line); border-radius: 10px; background: #fff; color: var(--ink); font-size: .84rem; }
.ad-select > .bx { color: var(--muted); font-size: 1rem; }
.ad-select select { border: 0; background: transparent; color: var(--ink); font: inherit; font-weight: 500; padding-inline-end: .2rem; cursor: pointer; outline: none; }
.ad-select.sm { font-size: .8rem; }
.ad-select:focus-within { border-color: var(--brand-line); box-shadow: 0 0 0 3px var(--brand-soft); }
.ad-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; height: 38px; padding: 0 .95rem; border-radius: 10px; border: 1px solid transparent; font-size: .84rem; font-weight: 600; text-decoration: none; cursor: pointer; white-space: nowrap; transition: background .15s, border-color .15s, color .15s, box-shadow .15s; }
.ad-btn.primary { background: var(--brand) linear-gradient(105deg, #004CFF, #5235FF); color: #fff; box-shadow: 0 6px 16px rgba(0, 76, 255, .2); }
.ad-btn.primary:hover { background: #0043E0 linear-gradient(105deg, #0043E0, #4429F0); color: #fff; }
.ad-btn.ghost { background: #fff; border-color: var(--line); color: var(--ink); }
.ad-btn.ghost:hover:not(:disabled) { border-color: #B9CCFF; color: var(--brand); }
.ad-btn.sm { height: 32px; padding: 0 .7rem; font-size: .78rem; }
.ad-btn:disabled { opacity: .55; cursor: not-allowed; }
.ad-menu { position: relative; }
.ad-menu-list { position: absolute; inset-inline-end: 0; top: calc(100% + 6px); z-index: 30; min-width: 230px; padding: 6px; background: #fff; border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 18px 40px rgba(16, 24, 40, .16); display: flex; flex-direction: column; }
.ad-menu-list a { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; color: var(--text); font-size: .84rem; font-weight: 500; text-decoration: none; }
.ad-menu-list a:hover { background: var(--brand-soft); color: var(--brand); }
.ad-pf-ic { width: 28px; height: 28px; border-radius: 8px; display: inline-grid; place-items: center; font-size: 15px; flex-shrink: 0; }
.ad-pf-ic.sm { width: 26px; height: 26px; border-radius: 7px; font-size: 14px; }

/* insights */
.ad-insights { display: flex; flex-direction: column; gap: .6rem; padding: 1rem 1.2rem; border-radius: 16px; border: 1px solid #DCE5FB; background: radial-gradient(100% 140% at 0% 0%, rgba(82, 53, 255, .07), transparent 60%), #F7F9FF; }
.ad-insights-h { margin: 0; display: flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 700; color: var(--brand-2); }
.ad-insights-h span { font-weight: 500; color: var(--muted); }
.ad-insights-lead { color: var(--ink); font-size: .92rem; }
.ad-insights-list { display: flex; flex-wrap: wrap; gap: .5rem; }
.ad-insight { display: inline-flex; align-items: center; gap: .5rem; padding: .45rem .75rem; border-radius: 10px; background: #fff; border: 1px solid var(--line); font-size: .8rem; color: var(--text); }
.ad-insight-go { border: 0; background: none; padding: 0; color: var(--brand); font-weight: 600; font-size: .8rem; cursor: pointer; }

/* notes */
.ad-note { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border-radius: 12px; background: #F5F8FF; border: 1px solid #DCE5FB; color: #3F4A63; font-size: .8rem; line-height: 1.5; }
.ad-note > .bx { font-size: 1.1rem; color: var(--brand); margin-top: 1px; }
.ad-note > div { flex: 1; min-width: 0; }
.ad-note strong { color: var(--ink); display: block; }
.ad-note p { margin: 2px 0 0; }
.ad-note.bad { background: #FFF8F7; border-color: #F6D3CF; }
.ad-note.bad > .bx { color: #DC2626; }

/* KPI cards */
.ad-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.ad-kpi { display: flex; flex-direction: column; gap: .3rem; padding: 1rem 1.1rem; background: #fff; border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow); min-width: 0; transition: transform .2s, box-shadow .2s; }
.ad-kpi:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(16, 24, 40, .08); }
.ad-kpi-top { display: flex; justify-content: space-between; align-items: center; }
.ad-kpi-label { font-size: .84rem; font-weight: 500; color: var(--text); }
.ad-kpi-info { color: #B4BBCB; font-size: .95rem; cursor: help; }
.ad-kpi-val { font-size: 1.75rem; font-weight: 700; letter-spacing: -.02em; color: var(--ink); font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.25; }
.ad-kpi-foot { display: flex; justify-content: space-between; align-items: flex-end; gap: 8px; }
.ad-kpi-delta { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.ad-kpi-delta small { font-size: .76rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ad-delta { display: inline-flex; align-items: center; gap: 3px; font-size: .8rem; font-weight: 600; font-variant-numeric: tabular-nums; }
.ad-delta.sm { font-size: .76rem; font-weight: 500; }
.ad-delta.up { color: #16A34A; }
.ad-delta.down { color: #DC2626; }
.ad-delta.flat { color: var(--muted); }
.ad-skel { height: 12px; border-radius: 6px; background: linear-gradient(90deg, #EEF1F5 25%, #F6F8FB 50%, #EEF1F5 75%); background-size: 200% 100%; animation: ad-shimmer 1.2s infinite; }
.ad-skel.lg { height: 30px; width: 60%; margin: 3px 0; }
.ad-skel.chart { height: 240px; border-radius: 12px; }

/* metrics strip */
.ad-strip { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); background: #fff; border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow); }
.ad-strip-item { display: flex; flex-direction: column; gap: 3px; padding: .9rem 1.1rem; min-width: 0; }
.ad-strip-item + .ad-strip-item { border-inline-start: 1px solid var(--line-soft); }
.ad-strip-label { font-size: .8rem; color: var(--muted); }
.ad-strip-item strong { font-size: 1.05rem; font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }
.ad-strip-sub { font-size: .74rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* cards */
.ad-grid { display: grid; grid-template-columns: minmax(0, 1.75fr) minmax(300px, 1fr); gap: 18px; align-items: stretch; }
.ad-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 1.2rem 1.3rem; box-shadow: var(--shadow); min-width: 0; }
.ad-card-h { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 1rem; }
.ad-card-h h2 { margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--ink); letter-spacing: -.01em; }
.ad-card-h p { margin: 3px 0 0; font-size: .82rem; color: var(--muted); }
.ad-tabs-row { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .9rem; }
.ad-seg { display: inline-flex; padding: 3px; border: 1px solid var(--line); border-radius: 10px; background: var(--wash); gap: 2px; }
.ad-seg button { border: 0; background: none; height: 28px; padding: 0 .7rem; border-radius: 7px; font-size: .76rem; font-weight: 600; color: var(--text); cursor: pointer; }
.ad-seg button.on { background: #fff; color: var(--brand); box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
.ad-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; margin-top: .6rem; font-size: .8rem; color: var(--text); }
.ad-legend span { display: inline-flex; align-items: center; gap: .4rem; }
.ad-legend i.line { width: 18px; height: 2px; background: var(--brand); }
.ad-legend i.line.dash { background: repeating-linear-gradient(90deg, #A3ABBE 0 4px, transparent 4px 7px); }
.ad-legend-src { margin-inline-start: auto; color: var(--muted); font-size: .76rem; }
.ad-none { display: flex; align-items: center; justify-content: center; gap: .5rem; flex-wrap: wrap; padding: 2rem 1rem; color: var(--muted); font-size: .84rem; text-align: center; }
.ad-none .bx { font-size: 1.2rem; color: #B4BBCB; }
.ad-foot { margin: .7rem 0 0; font-size: .74rem; color: var(--muted); }

/* pacing + bars */
.ad-pacing, .ad-bars { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 1rem; }
.ad-pacing-h, .ad-bars-head { display: flex; justify-content: space-between; gap: 8px; font-size: .8rem; margin-bottom: 6px; }
.ad-pacing-h .nm, .ad-bars-head .nm { font-weight: 600; color: var(--ink); }
.ad-pacing-h .ct, .ad-bars-head .ct { color: var(--muted); font-variant-numeric: tabular-nums; white-space: nowrap; font-size: .76rem; }
.ad-track { height: 6px; border-radius: 6px; background: #EEF1F6; overflow: hidden; }
.ad-track span { display: block; height: 100%; border-radius: 6px; transition: width .4s; }
.ad-pacing-note { display: block; margin-top: 4px; font-size: .72rem; color: var(--muted); }
.ad-compare { display: grid; grid-template-columns: minmax(140px, .75fr) minmax(0, 1.7fr); gap: 1.25rem; align-items: start; }

/* funnel */
.ad-funnel { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 1.1rem; }
.ad-funnel li { display: grid; grid-template-columns: 96px minmax(0, 1fr) auto; gap: .5rem 1rem; align-items: center; }
.ad-funnel .nm { font-size: .82rem; font-weight: 600; color: var(--ink); }
.ad-funnel .bar { height: 26px; border-radius: 6px; background: #F1F3F9; overflow: hidden; }
.ad-funnel .bar span { display: block; height: 100%; border-radius: 6px; background: linear-gradient(90deg, #5B7CFF, #5235FF); }
.ad-funnel b { font-size: .84rem; color: var(--ink); font-variant-numeric: tabular-nums; }
.ad-funnel .rate { grid-column: 2 / 4; display: inline-flex; align-items: center; gap: 2px; margin-top: -.6rem; font-size: .72rem; color: var(--muted); }

/* tables */
.ad-table-wrap { overflow-x: auto; }
.ad-table-wrap.edge { margin: 0 -1.3rem; }
.ad-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.ad-table th { padding: .6rem .8rem; background: var(--wash); color: var(--muted); font-size: .72rem; font-weight: 600; text-align: start; border-top: 1px solid var(--line-soft); border-bottom: 1px solid var(--line-soft); white-space: nowrap; }
.ad-table td { padding: .75rem .8rem; border-bottom: 1px solid var(--line-soft); color: var(--text); vertical-align: middle; white-space: nowrap; }
.ad-table tbody tr:hover td { background: #F7F9FF; }
.ad-table tbody tr:last-child td { border-bottom: 0; }
.ad-table .num { text-align: end; font-variant-numeric: tabular-nums; }
.ad-table-wrap.edge .ad-table th:first-child, .ad-table-wrap.edge .ad-table td:first-child { padding-inline-start: 1.3rem; }
.ad-table-wrap.edge .ad-table th:last-child, .ad-table-wrap.edge .ad-table td:last-child { padding-inline-end: 1.3rem; }
.ad-table.compact th { background: none; border-top: 0; }
.ad-table.compact th, .ad-table.compact td { padding: .6rem .5rem; }
.ad-table.compact td:first-child { font-weight: 600; color: var(--ink); }
.c-cell { display: flex; align-items: center; gap: .65rem; min-width: 0; }
.c-txt { display: flex; flex-direction: column; min-width: 0; }
.c-nm { font-weight: 600; color: var(--ink); max-width: 260px; overflow: hidden; text-overflow: ellipsis; }
.c-sub { font-size: .72rem; color: var(--muted); }
.c-num { font-variant-numeric: tabular-nums; color: var(--ink); }
.c-spend { display: block; width: 72px; height: 4px; margin-top: 5px; border-radius: 4px; background: #EEF1F6; overflow: hidden; }
.c-spend span { display: block; height: 100%; background: var(--brand); border-radius: 4px; }
.c-muted { color: var(--muted); font-size: .76rem; }
.c-roas { display: inline-flex; align-items: center; gap: 2px; font-weight: 700; }
.c-roas.up { color: #16A34A; }
.c-roas.down { color: #DC2626; }
.c-act { width: 32px; height: 32px; border-radius: 9px; border: 1px solid var(--line); background: #fff; color: var(--text); display: inline-grid; place-items: center; font-size: 1.05rem; text-decoration: none; cursor: pointer; }
.c-act:hover:not(:disabled) { border-color: #B9CCFF; color: var(--brand); }
.c-act:disabled { opacity: .45; cursor: not-allowed; }
.ad-pill { display: inline-flex; align-items: center; gap: 5px; height: 24px; padding: 0 9px; border-radius: 999px; font-size: .72rem; font-weight: 600; text-transform: capitalize; white-space: nowrap; }
.ad-pill .d { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.ad-pill.ok { background: #E7F7EE; color: #15803D; }
.ad-pill.warn { background: #FFF6E5; color: #B45309; }
.ad-pill.bad { background: #FDECEC; color: #DC2626; }
.ad-pill.mut { background: #F1F3F7; color: #64748B; }
.ad-tools { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; margin-bottom: 1rem; }
.ad-tools-end { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
.ad-search { position: relative; display: inline-flex; }
.ad-search .bx { position: absolute; inset-inline-start: .65rem; top: 50%; transform: translateY(-50%); color: var(--muted); }
.ad-search input { height: 38px; width: 220px; border: 1px solid var(--line); border-radius: 10px; padding-inline: 2rem .75rem; font-size: .82rem; color: var(--ink); background: #fff; }
.ad-search input:focus { outline: none; border-color: var(--brand-line); box-shadow: 0 0 0 3px var(--brand-soft); }
.ad-pager { display: flex; justify-content: flex-end; align-items: center; gap: .5rem; padding-top: 1rem; font-size: .78rem; color: var(--muted); }

/* empty */
.ad-empty { text-align: center; padding: 3rem 1.5rem; }
.ad-empty .ring { width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 1rem; font-size: 28px; color: var(--brand); background: var(--brand-soft); }
.ad-empty h5 { margin: 0 0 .4rem; font-weight: 700; color: var(--ink); }
.ad-empty p { margin: 0 auto 1.2rem; color: var(--muted); font-size: .88rem; max-width: 46ch; }

.ads-dash[dir="rtl"] .bx-chevron-right, .ads-dash[dir="rtl"] .bx-chevron-left { transform: scaleX(-1); }
@keyframes ad-shimmer { to { background-position: -200% 0; } }
@keyframes ad-pulse { to { opacity: .35; } }
@media (prefers-reduced-motion: reduce) { .ads-dash * { animation: none !important; transition: none !important; } }

@container adsdash (max-width: 900px) {
  .ad-grid { grid-template-columns: minmax(0, 1fr); }
  .ad-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .ad-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .ad-strip-item:nth-child(4) { border-inline-start: 0; }
  .ad-strip-item:nth-child(n+4) { border-top: 1px solid var(--line-soft); }
}
@container adsdash (max-width: 700px) {
  .ad-head-side { align-items: stretch; width: 100%; }
  .ad-head-controls { justify-content: stretch; }
  .ad-head-controls > * { flex: 1 1 auto; }
  .ad-sync { justify-content: flex-start; text-align: start; }
  .ad-compare { grid-template-columns: minmax(0, 1fr); }
  .ad-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .ad-strip-item:nth-child(n) { border-inline-start: 0; border-top: 1px solid var(--line-soft); }
  .ad-strip-item:nth-child(-n+2) { border-top: 0; }
  .ad-strip-item:nth-child(even) { border-inline-start: 1px solid var(--line-soft); }
  .ad-tools-end, .ad-search, .ad-search input { width: 100%; }
  .ad-tools-end .ad-select { flex: 1; }
}
@container adsdash (max-width: 460px) { .ad-kpis { grid-template-columns: minmax(0, 1fr); } }
</style>
