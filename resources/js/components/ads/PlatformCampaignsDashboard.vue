<template>
  <div class="pc-dash" :style="themeVars">

    <a :href="data.urls.dashboard" class="pc-back"><i class="bx bx-arrow-back"></i> Back to Ads Dashboard</a>

    <!-- ================= HERO ================= -->
    <section class="pc-hero">
      <div class="pc-hero-id">
        <span class="pc-mark"><i class="bx" :class="data.icon"></i></span>
        <div class="pc-hero-txt">
          <div class="pc-hero-top">
            <h4>{{ data.label }} campaigns</h4>
            <span v-if="data.connected && data.healthy" class="pc-pill ok"><span class="d"></span>Connected</span>
            <span v-else-if="data.connected" class="pc-pill warn"><span class="d"></span>Needs re-auth</span>
            <span v-else class="pc-pill mut">Not connected</span>
          </div>
          <p class="pc-hero-sub">
            <template v-if="data.account_names.length">
              <i class="bx bx-briefcase-alt-2"></i> {{ data.account_names.join(', ') }}
              <span class="sep">•</span>
              <i class="bx bx-time-five"></i> {{ data.last_synced_at ? 'Synced ' + timeAgo(data.last_synced_at) : 'Never synced' }}
            </template>
            <template v-else-if="data.connected">Connected, no ad account resolved yet</template>
            <template v-else>Connect a {{ data.label }} ad account to manage campaigns here</template>
          </p>
        </div>
      </div>

      <div class="pc-hero-actions">
        <button v-if="data.connected && data.can_sync" type="button" class="pc-btn ghost" :disabled="syncing" @click="syncNow">
          <span v-if="syncing" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-sync"></i>
          {{ syncing ? 'Syncing…' : 'Sync now' }}
        </button>
        <a v-if="data.connected" :href="data.urls.connect" class="pc-btn ghost"><i class="bx bx-refresh"></i> Reconnect</a>
        <a v-if="data.connected" :href="data.urls.create" class="pc-btn primary"><i class="bx bx-plus"></i> Create campaign</a>
      </div>
    </section>

    <div v-if="syncMsg" class="pc-toast" :class="syncOk ? 'ok' : 'bad'" role="status">
      <i class="bx" :class="syncOk ? 'bx-check-circle' : 'bx-error-circle'"></i>
      <span>{{ syncMsg }}</span>
      <button type="button" class="pc-toast-x" aria-label="Dismiss" @click="syncMsg = ''"><i class="bx bx-x"></i></button>
    </div>

    <!-- ================= NOT CONNECTED ================= -->
    <section v-if="!data.connected" class="pc-connect">
      <div class="pc-connect-main">
        <div class="pc-connect-logos" aria-hidden="true">
          <span class="pc-connect-ic"><i class="bx" :class="data.icon"></i></span>
          <span class="pc-connect-link"><i class="bx bx-transfer-alt"></i></span>
          <span class="pc-connect-app"><i class="bx bx-rocket"></i></span>
        </div>
        <span class="pc-eyebrow">Ads Manager · {{ data.label }}</span>
        <h3>Connect {{ data.label }} and launch your first campaign</h3>
        <p>Link your {{ data.label }} ad account once. Then create, sync and manage every campaign here, with full targeting, live status and one-click controls.</p>
        <div class="pc-connect-cta">
          <a :href="data.urls.connect" class="pc-btn primary lg"><i class="bx bx-plug"></i> Connect {{ data.label }}</a>
          <a :href="data.urls.dashboard" class="pc-btn ghost lg"><i class="bx bx-grid-alt"></i> All platforms</a>
        </div>
        <ul class="pc-trust">
          <li><i class="bx bx-lock-alt"></i> Secure sign-in with {{ data.label }}</li>
          <li><i class="bx bx-shield-quarter"></i> We never see your password</li>
          <li><i class="bx bx-reset"></i> Disconnect any time</li>
        </ul>
      </div>

      <div class="pc-connect-side">
        <h6>How it works</h6>
        <ol class="pc-steps">
          <li><span class="n">1</span><div><strong>Sign in to {{ data.label }}</strong><small>Approve access in a secure {{ data.label }} window.</small></div></li>
          <li><span class="n">2</span><div><strong>Choose your ad account</strong><small>Pick the account you want to manage from here.</small></div></li>
          <li><span class="n">3</span><div><strong>Create or sync campaigns</strong><small>Launch new campaigns or pull in the ones you already run.</small></div></li>
        </ol>
        <div class="pc-benefits">
          <div><i class="bx bx-target-lock"></i><strong>Full targeting</strong><small>Audiences, locations, placements</small></div>
          <div><i class="bx bx-sync"></i><strong>Two-way sync</strong><small>Keep campaigns up to date</small></div>
          <div><i class="bx bx-pointer"></i><strong>One-click control</strong><small>Pause, activate and edit</small></div>
        </div>
      </div>
    </section>

    <template v-else>
      <!-- ================= KPI CARDS ================= -->
      <div class="pc-kpis">
        <div class="pc-kpi">
          <div class="pc-kpi-h"><span class="pc-kpi-ic"><i class="bx bxs-megaphone"></i></span>Campaigns</div>
          <div class="pc-kpi-v">{{ int(data.summary.campaigns_total) }}</div>
          <div class="pc-kpi-bar" :aria-label="activeShare + '% active'"><span :style="{ width: activeShare + '%' }"></span></div>
          <div class="pc-kpi-s"><span class="ok-t">{{ int(data.summary.campaigns_active) }} active</span> · {{ int(data.summary.campaigns_paused) }} paused</div>
        </div>
        <div class="pc-kpi">
          <div class="pc-kpi-h"><span class="pc-kpi-ic"><i class="bx bx-wallet"></i></span>Daily budget</div>
          <div class="pc-kpi-v">{{ money(data.summary.daily_budget_total) }}</div>
          <div class="pc-kpi-s">Configured across daily campaigns</div>
        </div>
        <div class="pc-kpi">
          <div class="pc-kpi-h"><span class="pc-kpi-ic"><i class="bx bx-coin-stack"></i></span>Lifetime budget</div>
          <div class="pc-kpi-v">{{ money(data.summary.lifetime_budget_total) }}</div>
          <div class="pc-kpi-s">Configured across lifetime campaigns</div>
        </div>
        <div class="pc-kpi">
          <div class="pc-kpi-h"><span class="pc-kpi-ic"><i class="bx bx-sitemap"></i></span>Structure</div>
          <div class="pc-kpi-split">
            <div><strong>{{ int(data.summary.ad_groups) }}</strong><small>Ad groups</small></div>
            <div><strong>{{ int(data.summary.ads) }}</strong><small>Ads</small></div>
            <div><strong>{{ int(data.summary.creatives) }}</strong><small>Creatives</small></div>
          </div>
        </div>
      </div>

      <div class="pc-grid">
        <div class="pc-main">
          <!-- ================= PERFORMANCE ================= -->
          <section v-if="data.urls.insights" class="pc-card">
            <div class="pc-card-h">
              <span class="t"><i class="bx bx-line-chart"></i> Performance</span>
              <span class="pc-chip">Last {{ insights ? insights.days : 30 }} days</span>
            </div>
            <div class="pc-card-b">
              <div v-if="insightsLoading" class="pc-metrics">
                <div v-for="n in 5" :key="n" class="pc-metric pc-skel"><span></span><strong></strong></div>
              </div>
              <div v-else-if="insightsError" class="pc-note bad">
                <i class="bx bx-error-circle"></i>
                <div><strong>Couldn't load performance</strong><p>{{ insightsError }}</p></div>
              </div>
              <div v-else-if="insights" class="pc-metrics">
                <div v-for="m in metricTiles" :key="m.label" class="pc-metric">
                  <span class="lbl"><i class="bx" :class="m.icon"></i> {{ m.label }}</span>
                  <strong>{{ m.value }}</strong>
                </div>
              </div>
            </div>
          </section>
          <div v-else class="pc-note">
            <i class="bx bx-bar-chart-alt-2"></i>
            <div>
              <strong>Performance metrics aren't available for {{ data.label }} yet</strong>
              <p>Sync now refreshes campaign details and status. Spend, impressions, clicks and conversions need a reporting sync, which isn't built for this platform yet.</p>
            </div>
          </div>

          <!-- ================= CAMPAIGNS ================= -->
          <section class="pc-card">
            <div class="pc-card-h pc-toolbar">
              <span class="t"><i class="bx bx-list-ul"></i> Campaigns <span class="pc-count">{{ data.campaigns.length }}</span></span>
              <div v-if="data.campaigns.length" class="pc-tools">
                <div class="pc-search">
                  <i class="bx bx-search"></i>
                  <input v-model.trim="query" type="search" placeholder="Search campaigns" aria-label="Search campaigns">
                </div>
                <select v-model="sort" class="pc-select" aria-label="Sort campaigns">
                  <option value="newest">Newest first</option>
                  <option value="name">Name A–Z</option>
                  <option value="budget">Highest budget</option>
                </select>
              </div>
            </div>

            <div v-if="data.campaigns.length" class="pc-filters" role="tablist" aria-label="Filter by status">
              <button v-for="f in filters" :key="f.key" type="button" role="tab" :aria-selected="filter === f.key" :class="{ on: filter === f.key }" @click="filter = f.key">
                {{ f.label }} <span>{{ f.count }}</span>
              </button>
            </div>

            <div v-if="!data.campaigns.length" class="pc-empty">
              <span class="pc-empty-ic"><i class="bx bx-rocket"></i></span>
              <h6>No campaigns yet</h6>
              <p>
                <template v-if="data.can_sync">Pull your existing campaigns from {{ data.label }} or create your first one.</template>
                <template v-else>Create your first {{ data.label }} campaign to see it here.</template>
              </p>
              <div class="pc-empty-a">
                <a :href="data.urls.create" class="pc-btn primary"><i class="bx bx-plus"></i> Create campaign</a>
                <button v-if="data.can_sync" type="button" class="pc-btn ghost" :disabled="syncing" @click="syncNow"><i class="bx bx-sync"></i> Sync now</button>
              </div>
            </div>
            <div v-else-if="!visibleCampaigns.length" class="pc-empty sm">
              <span class="pc-empty-ic"><i class="bx bx-search-alt"></i></span>
              <p>No campaigns match your filters.</p>
              <button type="button" class="pc-btn ghost" @click="query = ''; filter = 'all'">Clear filters</button>
            </div>
            <div v-else class="table-responsive">
              <table class="pc-table">
                <thead>
                  <tr>
                    <th>Campaign</th>
                    <th>Status</th>
                    <th class="text-end">Budget</th>
                    <th v-if="insights" class="text-end">Spend</th>
                    <th v-if="insights" class="text-end">Clicks</th>
                    <th>Schedule</th>
                    <th class="text-end"><span class="visually-hidden">Actions</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="c in visibleCampaigns" :key="c.id">
                    <td>
                      <div class="c-cell">
                        <span class="c-av">{{ initials(c.name) }}</span>
                        <div class="c-txt">
                          <span class="c-nm" :title="c.name">{{ c.name }}</span>
                          <span class="c-meta">
                            <span v-if="c.channel_type" class="c-tag">{{ prettyChannel(c.channel_type) }}</span>
                            <span v-if="c.objective" class="c-obj">{{ prettyObjective(c.objective) }}</span>
                            <span class="c-ext">#{{ c.id }}</span>
                          </span>
                        </div>
                      </div>
                    </td>
                    <td><span class="pc-pill" :class="statusClass(c.status)"><span class="d"></span>{{ statusLabel(c.status) }}</span></td>
                    <td class="text-end">
                      <span class="c-money">{{ budgetLabel(c) }}</span>
                      <small v-if="c.daily_budget || c.lifetime_budget" class="c-sub">{{ c.daily_budget ? 'Daily' : 'Lifetime' }}</small>
                    </td>
                    <td v-if="insights" class="text-end c-money">{{ campaignMetric(c, 'spend') }}</td>
                    <td v-if="insights" class="text-end">{{ campaignMetric(c, 'clicks') }}</td>
                    <td>
                      <span class="c-sched">{{ shortDate(c.start_time) }} <i class="bx bx-right-arrow-alt"></i> {{ c.end_time ? shortDate(c.end_time) : 'Ongoing' }}</span>
                    </td>
                    <td class="text-end">
                      <div class="c-actions">
                        <button v-if="data.urls.status && normStatus(c.status) === 'active'" type="button" class="c-act" :disabled="busyId === c.id" title="Pause" aria-label="Pause" @click="setStatus(c, 'PAUSED')">
                          <span v-if="busyId === c.id" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-pause"></i>
                        </button>
                        <button v-else-if="data.urls.status && normStatus(c.status) !== 'archived'" type="button" class="c-act" :disabled="busyId === c.id" title="Activate" aria-label="Activate" @click="setStatus(c, 'ACTIVE')">
                          <span v-if="busyId === c.id" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-play"></i>
                        </button>
                        <a v-if="data.urls.edit" :href="editUrl(c.id)" class="c-act" title="Edit" aria-label="Edit"><i class="bx bx-edit-alt"></i></a>
                        <button type="button" class="c-act danger" title="Delete" aria-label="Delete" @click="destroy(c)"><i class="bx bx-trash"></i></button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- ================= RIGHT RAIL ================= -->
        <aside class="pc-rail">
          <section class="pc-card">
            <div class="pc-card-h">
              <span class="t"><i class="bx bx-user-circle"></i> Ad accounts</span>
              <span class="pc-count">{{ data.accounts.length }}</span>
            </div>
            <div class="pc-card-b">
              <ul class="pc-acc">
                <li v-for="a in data.accounts" :key="a.id">
                  <AccountAvatarBadge :avatar-url="a.avatar_url" :icon="'bx ' + data.icon" :color="data.color" :size="34" />
                  <div class="a-body">
                    <div class="a-top">
                      <span class="a-nm" :title="a.name">{{ a.name }}</span>
                      <span class="pc-pill" :class="a.healthy ? 'ok' : 'warn'"><span class="d"></span>{{ a.healthy ? 'Active' : 'Re-auth' }}</span>
                    </div>
                    <div class="a-meta">
                      <span v-if="a.currency" class="a-cur">{{ a.currency }}</span>
                      <span v-if="a.account_status">{{ a.account_status }}</span>
                      <span class="a-ext" dir="ltr">{{ a.external_id }}</span>
                    </div>
                  </div>
                </li>
              </ul>
              <a :href="data.urls.connect" class="pc-link"><i class="bx bx-refresh"></i> Reconnect or add an account</a>
            </div>
          </section>

          <section class="pc-card">
            <div class="pc-card-h"><span class="t"><i class="bx bx-bolt-circle"></i> Quick actions</span></div>
            <div class="pc-card-b pc-quick">
              <a :href="data.urls.create" class="pc-q">
                <span class="pc-q-ic"><i class="bx bx-plus"></i></span>
                <span><strong>Create campaign</strong><small>Launch a new {{ data.label }} campaign</small></span>
                <i class="bx bx-chevron-right pc-q-go"></i>
              </a>
              <button v-if="data.can_sync" type="button" class="pc-q" :disabled="syncing" @click="syncNow">
                <span class="pc-q-ic"><i class="bx bx-sync" :class="{ 'bx-spin': syncing }"></i></span>
                <span><strong>{{ syncing ? 'Syncing…' : 'Sync campaigns' }}</strong><small>Pull the latest from {{ data.label }}</small></span>
                <i class="bx bx-chevron-right pc-q-go"></i>
              </button>
              <a :href="data.urls.dashboard" class="pc-q">
                <span class="pc-q-ic"><i class="bx bx-grid-alt"></i></span>
                <span><strong>All platforms</strong><small>Back to the ads overview</small></span>
                <i class="bx bx-chevron-right pc-q-go"></i>
              </a>
            </div>
          </section>
        </aside>
      </div>
    </template>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AccountAvatarBadge from '../posts/AccountAvatarBadge.vue';

const props = defineProps({
  data: { type: Object, required: true },
  csrf: { type: String, default: '' },
});

const syncing = ref(false);
const insights = ref(null);
const insightsLoading = ref(false);
const insightsError = ref('');
const busyId = ref(null);
const syncMsg = ref('');
const syncOk = ref(false);
const query = ref('');
const filter = ref('all');
const sort = ref('newest');

const currency = computed(() => props.data.currency || '');

// Platform colour as theme variables. Light brand colours (Snapchat yellow)
// keep their tint for backgrounds but use dark ink for text and icons.
const themeVars = computed(() => {
  const hex = /^#[0-9a-f]{6}$/i.test(props.data.color || '') ? props.data.color : '#6366f1';
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
  const light = (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.7;
  return { '--pc': hex, '--pc-soft': hex + (light ? '33' : '14'), '--pc-line': hex + (light ? '99' : '33'), '--pc-ink': light ? '#3f3a00' : hex, '--pc-on': light ? '#111827' : '#fff' };
});

function money(v) {
  const n = Number(v || 0);
  return (currency.value ? currency.value + ' ' : '') + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function int(v) { return Number(v || 0).toLocaleString(); }
function shortDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}
function timeAgo(iso) {
  if (!iso) return 'never';
  const diff = (Date.now() - new Date(iso).getTime()) / 1000;
  if (diff < 60) return 'just now';
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  return Math.floor(diff / 86400) + 'd ago';
}
function budgetLabel(c) {
  if (c.daily_budget) return money(c.daily_budget) + '/day';
  if (c.lifetime_budget) return money(c.lifetime_budget);
  return '—';
}
function prettyObjective(o) {
  if (!o) return '';
  const s = String(o).replace(/^OUTCOME_/, '').replace(/_/g, ' ').toLowerCase();
  return s.charAt(0).toUpperCase() + s.slice(1);
}
function prettyChannel(t) {
  return ({ SEARCH: 'Search', DISPLAY: 'Display', PERFORMANCE_MAX: 'Performance Max', DEMAND_GEN: 'YouTube · Demand Gen', VIDEO: 'Video', SHOPPING: 'Shopping' })[t] || prettyObjective(t);
}
function initials(name) {
  return String(name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w.charAt(0)).join('').toUpperCase() || '?';
}
// Local rows store status as a boolean (true = running) or a platform string.
function normStatus(status) {
  if (status === true || status === 1) return 'active';
  if (status === false || status === 0 || status === null || status === undefined || status === '') return 'paused';
  const s = String(status).toLowerCase();
  if (['active', 'enable', 'enabled', 'running', '1', 'true'].includes(s)) return 'active';
  if (['paused', 'disable', 'disabled', '0', 'false', 'campaign_status_disable'].includes(s)) return 'paused';
  if (['pending', 'review', 'in_review', 'draft', 'scheduled'].includes(s)) return 'pending';
  if (['archived', 'deleted', 'removed'].includes(s)) return 'archived';
  if (['error', 'rejected', 'failed', 'with_issues'].includes(s)) return 'issue';
  return s;
}
function statusLabel(status) {
  const n = normStatus(status);
  return ({ active: 'Active', paused: 'Paused', pending: 'In review', archived: 'Archived', issue: 'Needs attention' })[n] || prettyObjective(n);
}
function statusClass(status) {
  return ({ active: 'ok', paused: 'warn', pending: 'info', archived: 'mut', issue: 'bad' })[normStatus(status)] || 'mut';
}
function editUrl(id) { return props.data.urls.edit.replace('CAMPAIGN_ID', id); }

const activeShare = computed(() => {
  const s = props.data.summary;
  return s.campaigns_total ? Math.round((s.campaigns_active / s.campaigns_total) * 100) : 0;
});

const filters = computed(() => {
  const count = (key) => props.data.campaigns.filter((c) => normStatus(c.status) === key).length;
  const all = props.data.campaigns.length;
  const active = count('active');
  const paused = count('paused');
  return [
    { key: 'all', label: 'All', count: all },
    { key: 'active', label: 'Active', count: active },
    { key: 'paused', label: 'Paused', count: paused },
    { key: 'other', label: 'Other', count: all - active - paused },
  ].filter((f) => f.key === 'all' || f.count > 0);
});

const visibleCampaigns = computed(() => {
  const q = query.value.toLowerCase();
  const rows = props.data.campaigns.filter((c) => {
    const st = normStatus(c.status);
    if (filter.value === 'other' ? ['active', 'paused'].includes(st) : filter.value !== 'all' && st !== filter.value) return false;
    return !q || [c.name, c.objective, c.channel_type, String(c.id)].some((v) => String(v || '').toLowerCase().includes(q));
  });
  const budget = (c) => Number(c.daily_budget || c.lifetime_budget || 0);
  return [...rows].sort((a, b) => {
    if (sort.value === 'name') return String(a.name).localeCompare(String(b.name));
    if (sort.value === 'budget') return budget(b) - budget(a);
    return String(b.created_at || '').localeCompare(String(a.created_at || '')) || Number(b.id) - Number(a.id);
  });
});

const metricTiles = computed(() => {
  const t = insights.value ? insights.value.totals : {};
  const cur = (insights.value && insights.value.currency) || currency.value;
  const m = (v) => (cur ? cur + ' ' : '') + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return [
    { label: 'Spend', value: m(t.spend), icon: 'bx-wallet' },
    { label: 'Impressions', value: int(t.impressions), icon: 'bx-show' },
    { label: 'Clicks', value: int(t.clicks), icon: 'bx-pointer' },
    { label: 'CTR', value: t.ctr == null ? '—' : (t.ctr * 100).toFixed(2) + '%', icon: 'bx-trending-up' },
    { label: 'CPC', value: t.cpc == null ? '—' : m(t.cpc), icon: 'bx-coin' },
  ];
});

function campaignMetric(c, key) {
  const row = insights.value && insights.value.campaigns ? insights.value.campaigns[String(c.id)] : null;
  if (!row) return '—';
  return key === 'spend' ? money(row.spend) : int(row[key]);
}

async function loadInsights() {
  if (!props.data.urls.insights || !props.data.connected) return;
  insightsLoading.value = true;
  try {
    const { data } = await window.axios.get(props.data.urls.insights);
    insights.value = data;
  } catch (e) {
    insightsError.value = e?.response?.data?.error || 'Please try again in a moment.';
  } finally {
    insightsLoading.value = false;
  }
}

async function setStatus(c, status) {
  busyId.value = c.id;
  try {
    await window.axios.patch(props.data.urls.status.replace('CAMPAIGN_ID', c.id), { status }, { headers: { 'X-CSRF-TOKEN': props.csrf, Accept: 'application/json' } });
    c.status = status;
  } catch (e) {
    window.alert(e?.response?.data?.message || 'Could not change the campaign status.');
  } finally {
    busyId.value = null;
  }
}

onMounted(loadInsights);

async function syncNow() {
  if (syncing.value) return;
  syncing.value = true;
  syncMsg.value = '';
  try {
    const { data } = await window.axios.post(props.data.urls.sync, {}, {
      headers: { 'X-CSRF-TOKEN': props.csrf },
    });
    syncOk.value = true;
    const n = data.synced ?? 0;
    syncMsg.value = n
      ? `Synced ${n} campaign${n === 1 ? '' : 's'} from ${props.data.label} (${data.created} new, ${data.updated} updated). Reloading…`
      : `No campaigns found on ${props.data.label}.`;
    setTimeout(() => window.location.reload(), 1200);
  } catch (e) {
    syncOk.value = false;
    syncMsg.value = e?.response?.data?.error || 'Sync failed. Please try again.';
    syncing.value = false;
  }
}

function destroy(c) {
  if (!window.confirm(`Delete campaign "${c.name}"? This also removes it on ${props.data.label}.`)) return;
  const url = props.data.urls.destroy.replace('CAMPAIGN_ID', c.id);
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = url;
  form.innerHTML = `<input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="${props.csrf}">`;
  document.body.appendChild(form);
  form.submit();
}
</script>

<style scoped>
.pc-dash { --ln: #e7e9f0; --ln-soft: #f1f3f7; --ink: #161b2b; --ink2: #4b5263; --muted: #8a92a3; --brand: #004cff; --brand-grad: linear-gradient(105deg, #004cff, #5235ff); --radius: 16px; --shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 8px 24px rgba(16, 24, 40, .03); --shadow-hover: 0 12px 28px rgba(16, 24, 40, .08); }

.pc-back { font-size: .78rem; font-weight: 600; color: var(--ink2); display: inline-flex; align-items: center; gap: .3rem; margin-bottom: .9rem; padding: .3rem .7rem .3rem .55rem; border: 1px solid var(--ln); border-radius: 999px; background: #fff; text-decoration: none; transition: border-color .15s, color .15s; }
.pc-back:hover { border-color: var(--pc-line); }
.pc-back:hover { color: var(--pc-ink); }
[dir="rtl"] .pc-back .bx, [dir="rtl"] .pc-q-go, [dir="rtl"] .c-sched .bx { transform: scaleX(-1); }

/* hero */
.pc-hero { position: relative; overflow: hidden; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem 1.5rem; padding: 1.4rem 1.5rem; margin-bottom: 1.25rem; border: 1px solid var(--pc-line); border-radius: 20px; background: radial-gradient(120% 140% at 0% 0%, var(--pc-soft) 0%, transparent 55%), linear-gradient(135deg, #fff 0%, #fafbff 100%); box-shadow: var(--shadow); }
.pc-hero::after { content: ''; position: absolute; inset-inline-end: -60px; top: -60px; width: 220px; height: 220px; border-radius: 50%; background: var(--pc-soft); pointer-events: none; }
.pc-hero::before { content: ''; position: absolute; inset: 0; pointer-events: none; opacity: .55; background-image: radial-gradient(var(--pc-line) 1px, transparent 1px); background-size: 18px 18px; -webkit-mask-image: linear-gradient(110deg, transparent 45%, #000 100%); mask-image: linear-gradient(110deg, transparent 45%, #000 100%); }
.pc-hero-id { display: flex; align-items: center; gap: 1rem; min-width: 0; position: relative; z-index: 1; }
.pc-mark { width: 56px; height: 56px; border-radius: 16px; display: grid; place-items: center; font-size: 1.75rem; flex-shrink: 0; color: var(--pc-on); background: linear-gradient(145deg, var(--pc) 0%, color-mix(in srgb, var(--pc) 72%, #000) 100%); box-shadow: inset 0 1px 0 rgba(255, 255, 255, .22), 0 10px 24px var(--pc-line); }
.pc-hero-top { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
.pc-hero h4 { margin: 0; font-size: 1.7rem; font-weight: 700; letter-spacing: -.02em; color: var(--ink); }
.pc-hero-sub { margin: .35rem 0 0; font-size: .82rem; color: var(--ink2); display: flex; align-items: center; flex-wrap: wrap; gap: .3rem; }
.pc-hero-sub .bx { color: var(--muted); }
.pc-hero-sub .sep { color: #c5cad4; margin: 0 .2rem; }
.pc-hero-actions { display: flex; gap: .5rem; flex-wrap: wrap; position: relative; z-index: 1; }

/* buttons */
.pc-btn { display: inline-flex; align-items: center; gap: .45rem; padding: .55rem .95rem; border-radius: 11px; font-size: .84rem; font-weight: 600; border: 1px solid transparent; text-decoration: none; cursor: pointer; transition: background .15s, border-color .15s, box-shadow .15s, transform .15s; white-space: nowrap; }
.pc-btn.primary { background: var(--brand) var(--brand-grad); color: #fff; box-shadow: 0 6px 16px rgba(0, 76, 255, .22); }
.pc-btn.primary:hover { background: #0043e0 linear-gradient(105deg, #0043e0, #4429f0); color: #fff; transform: translateY(-1px); box-shadow: 0 8px 20px rgba(0, 76, 255, .28); }
.pc-btn.ghost { background: #fff; color: var(--ink); border-color: var(--ln); }
.pc-btn.ghost:hover { border-color: #b9ccff; background: #fff; color: var(--brand); }
.pc-btn.lg { padding: .7rem 1.25rem; font-size: .9rem; }
.pc-btn:disabled { opacity: .6; cursor: wait; transform: none; }

/* toast */
.pc-toast { display: flex; align-items: center; gap: .6rem; padding: .75rem 1rem; border-radius: 12px; margin-bottom: 1.1rem; font-size: .85rem; font-weight: 500; }
.pc-toast.ok { background: #ecfdf3; color: #067647; border: 1px solid #abefc6; }
.pc-toast.bad { background: #fef3f2; color: #b42318; border: 1px solid #fecdca; }
.pc-toast > .bx { font-size: 1.15rem; }
.pc-toast-x { margin-inline-start: auto; border: 0; background: transparent; color: inherit; font-size: 1.1rem; padding: 0; line-height: 1; }

/* pills & chips */
.pc-pill { font-size: .72rem; font-weight: 600; padding: .25em .65em; border-radius: 999px; white-space: nowrap; display: inline-flex; align-items: center; gap: .35rem; }
.pc-pill.ok { background: #ecfdf3; color: #067647; }
.pc-pill.warn { background: #fffaeb; color: #b54708; }
.pc-pill.bad { background: #fef3f2; color: #b42318; }
.pc-pill.info { background: #eff8ff; color: #175cd3; }
.pc-pill.mut { background: #f2f4f7; color: #475467; }
.pc-pill .d { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.pc-chip { font-size: .72rem; font-weight: 600; color: var(--ink2); background: var(--ln-soft); padding: .25rem .6rem; border-radius: 999px; }
.pc-count { font-size: .72rem; font-weight: 700; color: var(--pc-ink); background: var(--pc-soft); padding: .1rem .5rem; border-radius: 999px; margin-inline-start: .15rem; }

/* cards */
.pc-card { background: #fff; border: 1px solid var(--ln); border-radius: 18px; box-shadow: var(--shadow); margin-bottom: 1.25rem; overflow: hidden; }
.pc-card-h { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .95rem 1.2rem; border-bottom: 1px solid var(--ln-soft); }
.pc-card-h .t { font-size: .98rem; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: .5rem; }
.pc-card-h .t > .bx { color: var(--pc-ink); font-size: 1.1rem; }
.pc-card-b { padding: 1.15rem 1.2rem; }

/* KPIs */
.pc-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.pc-kpi { background: #fff; border: 1px solid var(--ln); border-radius: var(--radius); padding: 1.05rem 1.15rem; box-shadow: var(--shadow); display: flex; flex-direction: column; min-width: 0; transition: border-color .2s, transform .2s, box-shadow .2s; position: relative; overflow: hidden; }
.pc-kpi::before { content: ''; position: absolute; inset: 0 0 auto; height: 3px; background: linear-gradient(90deg, var(--pc), transparent); opacity: .55; }
.pc-kpi:hover { border-color: var(--pc-line); transform: translateY(-2px); box-shadow: var(--shadow-hover); }
.pc-kpi-h { display: flex; align-items: center; gap: .6rem; font-size: .8rem; font-weight: 600; color: var(--muted); }
.pc-kpi-ic { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; background: var(--pc-soft); color: var(--pc-ink); font-size: 1.2rem; }
.pc-kpi-v { font-size: 1.75rem; font-weight: 700; letter-spacing: -.02em; color: var(--ink); margin-top: .65rem; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pc-kpi-s { font-size: .78rem; color: var(--muted); margin-top: .35rem; }
.pc-kpi-s .ok-t { color: #067647; font-weight: 600; }
.pc-kpi-bar { height: 6px; border-radius: 999px; background: var(--ln-soft); margin-top: .6rem; overflow: hidden; }
.pc-kpi-bar span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #12b76a, #32d583); transition: width .4s ease; }
.pc-kpi-split { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; margin-top: .65rem; }
.pc-kpi-split > div { display: flex; flex-direction: column; padding: .45rem .5rem; border-radius: 10px; background: var(--ln-soft); min-width: 0; }
.pc-kpi-split strong { font-size: 1.05rem; color: var(--ink); font-variant-numeric: tabular-nums; }
.pc-kpi-split small { font-size: .68rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* layout */
.pc-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 1.25rem; align-items: start; }
.pc-main, .pc-rail { min-width: 0; }

/* performance */
.pc-metrics { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .75rem; }
.pc-metric { border: 1px solid var(--ln); border-radius: 14px; padding: .9rem 1rem; display: flex; flex-direction: column; gap: .45rem; min-width: 0; background: radial-gradient(120% 120% at 100% 0%, var(--pc-soft) 0%, transparent 60%), #fff; transition: border-color .15s, transform .15s; }
.pc-metric:hover { border-color: var(--pc-line); transform: translateY(-1px); }
.pc-metric .lbl { font-size: .74rem; color: var(--muted); font-weight: 600; display: flex; align-items: center; gap: .45rem; }
.pc-metric .lbl .bx { width: 26px; height: 26px; border-radius: 8px; display: inline-grid; place-items: center; background: var(--pc-soft); color: var(--pc-ink); font-size: .95rem; }
.pc-metric strong { font-size: 1.3rem; font-weight: 700; letter-spacing: -.01em; color: var(--ink); font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pc-skel span, .pc-skel strong { display: block; border-radius: 6px; background: linear-gradient(90deg, #eef1f5 25%, #f6f8fb 50%, #eef1f5 75%); background-size: 200% 100%; animation: pc-shimmer 1.2s infinite; }
.pc-skel span { height: 10px; width: 60%; }
.pc-skel strong { height: 18px; width: 80%; }
@keyframes pc-shimmer { to { background-position: -200% 0; } }
.pc-note { display: flex; gap: .85rem; align-items: flex-start; padding: .95rem 1.1rem; border: 1px dashed var(--pc-line); border-radius: var(--radius); background: var(--pc-soft); margin-bottom: 1.25rem; }
.pc-note > .bx { font-size: 1.5rem; color: var(--pc-ink); flex-shrink: 0; margin-top: .05rem; }
.pc-note strong { color: var(--ink); font-size: .86rem; }
.pc-note p { color: var(--ink2); font-size: .79rem; margin: .25rem 0 0; line-height: 1.5; }
.pc-card .pc-note { margin: 0; }
.pc-note.bad { border-color: #fecdca; background: #fef3f2; }
.pc-note.bad > .bx { color: #b42318; }

/* toolbar & filters */
.pc-toolbar { flex-wrap: wrap; }
.pc-tools { display: flex; gap: .5rem; flex-wrap: wrap; }
.pc-search { position: relative; }
.pc-search .bx { position: absolute; inset-inline-start: .65rem; top: 50%; transform: translateY(-50%); color: var(--muted); }
.pc-search input { height: 36px; width: 220px; max-width: 100%; border: 1px solid var(--ln); border-radius: 10px; padding-inline: 2rem .75rem; font-size: .82rem; color: var(--ink); background: #fff; }
.pc-search input:focus, .pc-select:focus { outline: none; border-color: var(--pc-ink); box-shadow: 0 0 0 3px var(--pc-soft); }
.pc-select { height: 36px; border: 1px solid var(--ln); border-radius: 10px; padding: 0 .7rem; font-size: .82rem; color: var(--ink); background: #fff; }
.pc-filters { display: flex; gap: .35rem; padding: .7rem 1.2rem 0; flex-wrap: wrap; }
.pc-filters button { border: 1px solid var(--ln); background: #fff; color: var(--ink2); font-size: .78rem; font-weight: 600; padding: .3rem .7rem; border-radius: 999px; display: inline-flex; align-items: center; gap: .4rem; }
.pc-filters button span { font-size: .7rem; color: var(--muted); background: var(--ln-soft); border-radius: 999px; padding: 0 .4rem; }
.pc-filters button { transition: border-color .15s, background .15s, color .15s; }
.pc-filters button:hover { border-color: var(--pc-line); }
.pc-filters button.on { background: var(--pc-soft); color: var(--pc-ink); border-color: var(--pc-line); }
.pc-filters button.on span { background: #fff; color: var(--pc-ink); }

/* table */
.pc-table { width: 100%; margin: .5rem 0 0; min-width: 720px; border-collapse: separate; border-spacing: 0; }
.pc-table thead th { font-size: .66rem; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); font-weight: 700; border-bottom: 1px solid var(--ln); padding: .65rem 1.2rem; white-space: nowrap; background: #fbfcfe; }
.pc-table tbody td { border-bottom: 1px solid var(--ln-soft); padding: .8rem 1.2rem; vertical-align: middle; font-size: .82rem; color: var(--ink2); white-space: nowrap; }
.pc-table tbody tr:last-child td { border-bottom: 0; }
.pc-table tbody tr { transition: background .12s; }
.pc-table tbody tr:hover td { background: #fafbff; }
.c-cell { display: flex; align-items: center; gap: .7rem; min-width: 0; }
.c-av { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0; background: var(--pc-soft); color: var(--pc-ink); font-size: .74rem; font-weight: 700; box-shadow: inset 0 0 0 1px var(--pc-line); }
.c-txt { min-width: 0; display: flex; flex-direction: column; gap: .2rem; }
.c-nm { font-weight: 700; color: var(--ink); max-width: 280px; overflow: hidden; text-overflow: ellipsis; }
.c-meta { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; font-size: .71rem; color: var(--muted); }
.c-tag { font-weight: 700; color: var(--pc-ink); background: var(--pc-soft); padding: .05rem .45rem; border-radius: 6px; }
.c-ext { opacity: .75; }
.c-money { font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }
.c-sub { display: block; font-size: .68rem; color: var(--muted); margin-top: .1rem; }
.c-sched { display: inline-flex; align-items: center; gap: .25rem; color: var(--ink2); }
.c-sched .bx { color: var(--muted); }
.c-actions { display: inline-flex; gap: .3rem; }
.c-act { width: 32px; height: 32px; border-radius: 9px; border: 1px solid var(--ln); background: #fff; color: var(--ink2); display: inline-grid; place-items: center; font-size: 1rem; text-decoration: none; transition: all .15s; }
.c-act:hover { border-color: var(--pc-ink); color: var(--pc-ink); background: var(--pc-soft); }
.c-act.danger:hover { border-color: #fda29b; color: #b42318; background: #fef3f2; }
.c-act:disabled { opacity: .6; }

/* empty */
.pc-empty { text-align: center; padding: 2.75rem 1.5rem; }
.pc-empty.sm { padding: 2rem 1.5rem; }
.pc-empty-ic { width: 56px; height: 56px; border-radius: 16px; margin: 0 auto .8rem; display: grid; place-items: center; background: var(--pc-soft); color: var(--pc-ink); font-size: 1.6rem; }
.pc-empty h6 { font-weight: 700; color: var(--ink); margin-bottom: .3rem; }
.pc-empty p { color: var(--muted); font-size: .85rem; margin: 0 0 1rem; }
.pc-empty-a { display: flex; gap: .5rem; justify-content: center; flex-wrap: wrap; }

/* connect (no account yet) */
.pc-connect { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: 0; border: 1px solid var(--ln); border-radius: 20px; overflow: hidden; background: #fff; box-shadow: var(--shadow); }
.pc-connect-main { padding: 2.5rem 2.25rem; background: radial-gradient(120% 120% at 0% 0%, var(--pc-soft) 0%, transparent 60%), #fff; }
.pc-connect-logos { display: flex; align-items: center; gap: .6rem; margin-bottom: 1.4rem; }
.pc-connect-ic, .pc-connect-app { width: 56px; height: 56px; border-radius: 16px; display: grid; place-items: center; font-size: 1.7rem; }
.pc-connect-ic { color: var(--pc-on); background: linear-gradient(145deg, var(--pc) 0%, color-mix(in srgb, var(--pc) 72%, #000) 100%); box-shadow: inset 0 1px 0 rgba(255, 255, 255, .22), 0 10px 24px var(--pc-line); }
.pc-connect-app { color: #fff; background: var(--brand-grad); box-shadow: 0 10px 24px rgba(0, 76, 255, .22); }
.pc-connect-link { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; color: var(--muted); background: var(--ln-soft); border: 1px dashed #cfd4dd; font-size: 1.1rem; }
.pc-eyebrow { display: inline-block; font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--pc-ink); background: var(--pc-soft); padding: .25rem .6rem; border-radius: 999px; }
.pc-connect h3 { font-size: 1.6rem; font-weight: 700; letter-spacing: -.02em; line-height: 1.25; color: var(--ink); margin: .8rem 0 .6rem; max-width: 520px; }
.pc-connect-main > p { color: var(--ink2); font-size: .92rem; line-height: 1.6; max-width: 500px; margin: 0 0 1.5rem; }
.pc-connect-cta { display: flex; flex-wrap: wrap; gap: .6rem; }
.pc-trust { list-style: none; padding: 0; margin: 1.4rem 0 0; display: flex; flex-wrap: wrap; gap: .5rem 1.2rem; font-size: .78rem; color: var(--muted); }
.pc-trust li { display: inline-flex; align-items: center; gap: .35rem; }
.pc-trust .bx { color: #12b76a; font-size: 1rem; }
.pc-connect-side { padding: 2.25rem 2rem; background: #fafbfe; border-inline-start: 1px solid var(--ln-soft); display: flex; flex-direction: column; gap: 1.4rem; }
.pc-connect-side h6 { margin: 0; font-size: .74rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
.pc-steps { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0; }
.pc-steps li { position: relative; display: flex; gap: .85rem; padding-bottom: 1.1rem; }
.pc-steps li:last-child { padding-bottom: 0; }
.pc-steps li:not(:last-child)::before { content: ''; position: absolute; inset-inline-start: 15px; top: 32px; bottom: 2px; width: 2px; background: linear-gradient(var(--pc-line), var(--ln)); }
.pc-steps .n { width: 32px; height: 32px; flex-shrink: 0; border-radius: 50%; display: grid; place-items: center; font-size: .8rem; font-weight: 700; color: var(--pc-ink); background: #fff; border: 2px solid var(--pc-line); }
.pc-steps strong { display: block; font-size: .88rem; color: var(--ink); margin-top: .3rem; }
.pc-steps small { display: block; font-size: .76rem; color: var(--muted); margin-top: .15rem; line-height: 1.45; }
.pc-benefits { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .6rem; }
.pc-benefits > div { display: flex; flex-direction: column; gap: .2rem; padding: .8rem .75rem; border-radius: 12px; background: #fff; border: 1px solid var(--ln); min-width: 0; }
.pc-benefits .bx { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: var(--pc-soft); color: var(--pc-ink); font-size: 1rem; margin-bottom: .3rem; }
.pc-benefits strong { font-size: .8rem; color: var(--ink); }
.pc-benefits small { font-size: .7rem; color: var(--muted); line-height: 1.35; }
@media (max-width: 991.98px) {
  .pc-connect { grid-template-columns: minmax(0, 1fr); }
  .pc-connect-side { border-inline-start: 0; border-top: 1px solid var(--ln-soft); }
}
@media (max-width: 575.98px) {
  .pc-connect-main, .pc-connect-side { padding: 1.5rem 1.15rem; }
  .pc-connect h3 { font-size: 1.3rem; }
  .pc-connect-cta .pc-btn { flex: 1; justify-content: center; }
  .pc-benefits { grid-template-columns: minmax(0, 1fr); }
}
/* accounts */
.pc-acc { list-style: none; margin: 0; padding: 0; }
.pc-acc li { display: flex; gap: .7rem; align-items: flex-start; padding: .7rem 0; border-bottom: 1px solid var(--ln-soft); }
.pc-acc li:first-child { padding-top: 0; }
.pc-acc li:last-child { border-bottom: 0; }
.pc-acc .a-body { flex: 1; min-width: 0; }
.pc-acc .a-top { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
.pc-acc .a-nm { font-weight: 700; color: var(--ink); font-size: .85rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pc-acc .a-meta { font-size: .72rem; color: var(--muted); margin-top: .25rem; display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }
.pc-acc .a-cur { font-weight: 700; color: var(--ink2); background: var(--ln-soft); padding: 0 .4rem; border-radius: 5px; }
.pc-acc .a-ext { opacity: .75; }
.pc-link { display: inline-flex; align-items: center; gap: .3rem; font-size: .78rem; font-weight: 600; color: var(--pc-ink); text-decoration: none; margin-top: .6rem; }
.pc-link:hover { text-decoration: underline; }

/* quick actions */
.pc-quick { display: flex; flex-direction: column; gap: .5rem; }
.pc-q { display: flex; align-items: center; gap: .75rem; padding: .7rem .8rem; border: 1px solid var(--ln); border-radius: 12px; background: #fff; text-decoration: none; color: inherit; text-align: start; width: 100%; transition: border-color .15s, background .15s, transform .15s; }
.pc-q:hover { border-color: var(--pc-line); background: var(--pc-soft); transform: translateX(2px); }
[dir="rtl"] .pc-q:hover { transform: translateX(-2px); }
.pc-q:disabled { opacity: .6; cursor: wait; }
.pc-q-ic { width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0; display: grid; place-items: center; background: var(--pc-soft); color: var(--pc-ink); font-size: 1.15rem; }
.pc-q > span:nth-child(2) { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.pc-q strong { font-size: .84rem; color: var(--ink); }
.pc-q small { font-size: .72rem; color: var(--muted); }
.pc-q-go { color: var(--muted); font-size: 1.1rem; }

/* responsive */
@media (max-width: 1199.98px) {
  .pc-grid { grid-template-columns: minmax(0, 1fr); }
  .pc-rail { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 1.25rem; }
}
@media (max-width: 991.98px) {
  .pc-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .pc-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 767.98px) {
  .pc-rail { grid-template-columns: minmax(0, 1fr); }
  .pc-hero { padding: 1.1rem; }
  .pc-hero-actions { width: 100%; }
  .pc-hero-actions .pc-btn { flex: 1; justify-content: center; }
  .pc-tools, .pc-search, .pc-search input { width: 100%; }
}
@media (max-width: 575.98px) {
  .pc-kpis { gap: .65rem; }
  .pc-kpi { padding: .85rem; }
  .pc-kpi-v { font-size: 1.25rem; }
  .pc-mark { width: 46px; height: 46px; font-size: 1.4rem; }
  .pc-hero h4 { font-size: 1.35rem; }
}
</style>
