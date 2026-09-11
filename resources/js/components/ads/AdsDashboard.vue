<template>
  <div class="ads-dash">

    <!-- ================= HEADER ================= -->
    <div class="ad-head">
      <div>
        <h4>Ads Dashboard</h4>
        <p>{{ selected === 'all'
          ? 'Combined overview across every connected advertising platform.'
          : activePlatform.label + ' campaigns, accounts and budgets.' }}</p>
      </div>
      <a :href="connectModalTrigger" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#socialConnectModal">
        <i class="bx bx-link"></i> Connect account
      </a>
    </div>

    <!-- ================= PLATFORM SELECTOR ================= -->
    <div class="ad-platnav">
      <button type="button" class="ad-pl" :class="{ active: selected === 'all' }" @click="selected = 'all'">
        <i class="bx bx-grid-alt"></i>
        <span>All platforms</span>
      </button>
      <button
        v-for="p in platforms"
        :key="p.platform"
        type="button"
        class="ad-pl"
        :class="{ active: selected === p.platform, disconnected: !p.connected }"
        @click="selected = p.platform"
      >
        <i class="bx" :class="p.icon" :style="{ color: selected === p.platform ? '#fff' : p.color }"></i>
        <span>{{ p.label }}</span>
        <span v-if="p.connected" class="ad-dot" :class="p.healthy ? 'ok' : 'warn'"></span>
      </button>
    </div>

    <!-- ================= COMBINED VIEW ================= -->
    <div v-if="selected === 'all'">

      <div v-if="!data.has_connections" class="ad-card">
        <div class="ad-empty">
          <div class="ring"><i class="bx bx-bullhorn"></i></div>
          <h5>No ad accounts connected yet</h5>
          <p>Connect a platform to launch campaigns and see them here. Nothing to show until at least one account is linked.</p>
          <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#socialConnectModal">
            <i class="bx bx-plug"></i> Connect a platform
          </a>
        </div>
      </div>

      <template v-else>
        <!-- metric tiles -->
        <div class="ad-tiles">
          <div class="ad-tile" v-for="t in combinedTiles" :key="t.label">
            <div class="r1">
              <span class="ico" :style="{ background: t.bg, color: t.color }"><i class="bx" :class="t.icon"></i></span>
              <span class="lbl">{{ t.label }}</span>
            </div>
            <div class="val">{{ t.value }}</div>
            <div v-if="t.sub" class="sub">{{ t.sub }}</div>
          </div>
        </div>

        <div class="row">
          <div class="col-xl-8">

            <!-- performance-not-available panel -->
            <div class="ad-card mb-4">
              <div class="ad-card-h">
                <span class="t"><i class="bx bx-line-chart"></i> Performance overview</span>
              </div>
              <div class="ad-card-b">
                <div class="ad-perf-empty">
                  <i class="bx bx-bar-chart-alt-2"></i>
                  <div>
                    <strong>Spend, impressions, clicks &amp; conversions aren't available yet</strong>
                    <p>This dashboard shows what the app stores today - accounts, campaigns and their configured budgets. Live performance metrics need a reporting sync from each platform's API, which isn't built yet.</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- platform comparison -->
            <div class="ad-card mb-4">
              <div class="ad-card-h">
                <span class="t"><i class="bx bx-pie-chart-alt-2"></i> Platform comparison</span>
                <span class="text-muted small">by campaign count</span>
              </div>
              <div class="ad-card-b">
                <div v-if="!data.combined.platform_breakdown.length" class="text-muted small py-3 text-center">
                  No campaigns created on any platform yet.
                </div>
                <ul v-else class="ad-bars">
                  <li v-for="p in data.combined.platform_breakdown" :key="p.platform">
                    <div class="bar-head">
                      <i class="bx" :class="p.icon" :style="{ color: p.color }"></i>
                      <span class="nm">{{ p.label }}</span>
                      <span class="ct">{{ p.campaigns_total }} {{ p.campaigns_total === 1 ? 'campaign' : 'campaigns' }}</span>
                    </div>
                    <div class="bar-track">
                      <div class="bar-fill" :style="{ width: p.share + '%', background: p.color }"></div>
                    </div>
                    <div class="bar-foot">
                      <span>{{ p.share }}%</span>
                      <span v-if="p.daily_budget">{{ money(p.daily_budget) }}/day configured</span>
                    </div>
                  </li>
                </ul>
              </div>
            </div>

            <!-- recent campaigns -->
            <div class="ad-card">
              <div class="ad-card-h"><span class="t"><i class="bx bx-rocket"></i> Recent campaigns</span></div>
              <div class="ad-card-b p-0">
                <div v-if="!data.combined.recent_campaigns.length" class="text-muted small py-4 text-center">
                  No campaigns yet. Connect a platform and create one to get started.
                </div>
                <div v-else class="table-responsive">
                  <table class="ad-table">
                    <thead>
                      <tr>
                        <th>Campaign</th>
                        <th>Platform</th>
                        <th>Status</th>
                        <th class="text-end">Budget</th>
                        <th class="text-end">Created</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="c in data.combined.recent_campaigns" :key="c.id">
                        <td>
                          <span class="c-nm">{{ c.name }}</span>
                          <span v-if="c.objective" class="c-sub">{{ c.objective }}</span>
                        </td>
                        <td>
                          <span class="c-plat">
                            <i class="bx" :class="platformMeta(c.platform).icon" :style="{ color: platformMeta(c.platform).color }"></i>
                            {{ platformMeta(c.platform).label }}
                          </span>
                        </td>
                        <td><span class="ad-pill" :class="statusClass(c.status)">{{ c.status || 'unknown' }}</span></td>
                        <td class="text-end">{{ budgetLabel(c) }}</td>
                        <td class="text-end text-muted">{{ shortDate(c.created_at) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

          </div>

          <!-- right rail -->
          <div class="col-xl-4">
            <div class="ad-card mb-3">
              <div class="ad-card-h">
                <span class="t"><i class="bx bx-plug"></i> Connected platforms</span>
                <span class="ad-pill mut">{{ data.totals.platforms_connected }}/{{ data.totals.platforms_total }}</span>
              </div>
              <div class="ad-card-b">
                <ul class="ad-conn">
                  <li v-for="p in platforms" :key="p.platform">
                    <div class="ad-conn-head">
                      <i class="bx" :class="p.icon" :style="{ color: p.color }"></i>
                      <span class="nm">{{ p.label }}</span>
                      <template v-if="p.connected">
                        <span class="ad-pill" :class="p.healthy ? 'ok' : 'warn'">
                          <span class="d"></span>{{ p.healthy ? 'Connected' : 'Needs re-auth' }}
                        </span>
                        <a :href="p.reconnect_url" class="ad-reconnect" title="Reconnect / refresh token">
                          <i class="bx bx-refresh"></i>
                        </a>
                      </template>
                      <a v-else :href="p.connect_url" class="ad-pill mut">Connect</a>
                    </div>

                    <!-- The actual connected accounts, not just the
                         platform - avatar (real photo when the platform
                         has one, eg. Instagram; a tinted platform-icon
                         fallback otherwise, since most ad-account entities
                         genuinely have no photo of their own) + name. -->
                    <ul v-if="p.connected && p.accounts.length" class="ad-conn-accounts">
                      <li v-for="a in p.accounts.slice(0, 3)" :key="a.id">
                        <AccountAvatarBadge :avatar-url="a.avatar_url" :icon="p.icon" :color="p.color" :size="26" />
                        <span class="an">{{ a.name }}</span>
                        <i v-if="!a.healthy" class="bx bx-error-circle warn-ic" title="Needs re-auth"></i>
                      </li>
                      <li v-if="p.accounts.length > 3" class="ad-conn-more">
                        +{{ p.accounts.length - 3 }} more account{{ p.accounts.length - 3 === 1 ? '' : 's' }}
                      </li>
                    </ul>
                  </li>
                </ul>
              </div>
            </div>

            <div class="ad-card">
              <div class="ad-card-h"><span class="t"><i class="bx bx-bolt-circle"></i> Quick actions</span></div>
              <div class="ad-card-b">
                <div class="ad-actions">
                  <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#socialConnectModal">
                    <i class="bx bx-plus"></i> Connect new account
                  </a>
                  <a
                    v-for="p in connectedPlatforms"
                    :key="p.platform"
                    :href="p.connect_url"
                    class="btn btn-outline-secondary"
                  >
                    <i class="bx" :class="p.icon"></i> {{ p.label }} campaigns
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- ================= PER-PLATFORM VIEW ================= -->
    <div v-else>
      <div v-if="!activePlatform.connected" class="ad-card">
        <div class="ad-empty">
          <div class="ring" :style="{ background: activePlatform.color + '22', color: activePlatform.color }">
            <i class="bx" :class="activePlatform.icon"></i>
          </div>
          <h5>{{ activePlatform.label }} isn't connected</h5>
          <p>Connect a {{ activePlatform.label }} ad account to create campaigns and track them here.</p>
          <a :href="activePlatform.connect_url" class="btn btn-primary">
            <i class="bx bx-plug"></i> Connect {{ activePlatform.label }}
          </a>
        </div>
      </div>

      <template v-else>
        <div class="ad-subbar">
          <span class="ad-pill" :class="activePlatform.healthy ? 'ok' : 'warn'">
            <span class="d"></span>{{ activePlatform.healthy ? 'Account connected' : 'Token needs re-auth' }}
          </span>
          <div class="ms-auto d-flex gap-2 flex-wrap">
            <a :href="activePlatform.reconnect_url" class="btn btn-outline-secondary btn-sm">
              <i class="bx bx-refresh me-1"></i> Reconnect account
            </a>
            <a :href="activePlatform.connect_url" class="btn btn-primary btn-sm">
              <i class="bx bx-list-ul me-1"></i> Manage campaigns
            </a>
          </div>
        </div>

        <div class="ad-tiles">
          <div class="ad-tile" v-for="t in platformTiles" :key="t.label">
            <div class="r1">
              <span class="ico" :style="{ background: t.bg, color: t.color }"><i class="bx" :class="t.icon"></i></span>
              <span class="lbl">{{ t.label }}</span>
            </div>
            <div class="val">{{ t.value }}</div>
            <div v-if="t.sub" class="sub">{{ t.sub }}</div>
          </div>
        </div>

        <div class="row">
          <div class="col-xl-8">
            <div class="ad-card mb-4">
              <div class="ad-card-h"><span class="t"><i class="bx bx-list-ul"></i> {{ activePlatform.label }} campaigns</span></div>
              <div class="ad-card-b p-0">
                <div v-if="!activePlatform.campaigns.length" class="text-muted small py-4 text-center">
                  No campaigns on {{ activePlatform.label }} yet.
                  <a :href="activePlatform.connect_url">Create one</a>.
                </div>
                <div v-else class="table-responsive">
                  <table class="ad-table">
                    <thead>
                      <tr>
                        <th>Campaign</th>
                        <th>Status</th>
                        <th class="text-end">Budget</th>
                        <th class="text-end">Start</th>
                        <th class="text-end">Created</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="c in activePlatform.campaigns" :key="c.id">
                        <td>
                          <span class="c-nm">{{ c.name }}</span>
                          <span v-if="c.objective" class="c-sub">{{ c.objective }}</span>
                        </td>
                        <td><span class="ad-pill" :class="statusClass(c.status)">{{ c.status || 'unknown' }}</span></td>
                        <td class="text-end">{{ budgetLabel(c) }}</td>
                        <td class="text-end text-muted">{{ shortDate(c.start_time) }}</td>
                        <td class="text-end text-muted">{{ shortDate(c.created_at) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="ad-card">
              <div class="ad-card-h"><span class="t"><i class="bx bx-line-chart"></i> Performance</span></div>
              <div class="ad-card-b">
                <div class="ad-perf-empty">
                  <i class="bx bx-bar-chart-alt-2"></i>
                  <div>
                    <strong>No performance data for {{ activePlatform.label }} yet</strong>
                    <p>Spend, impressions, clicks and conversions need a reporting sync from the {{ activePlatform.label }} API, which isn't built yet.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-4">
            <div class="ad-card">
              <div class="ad-card-h">
                <span class="t"><i class="bx bx-user-circle"></i> Ad accounts</span>
                <span class="ad-pill mut">{{ activePlatform.accounts.length }}</span>
              </div>
              <div class="ad-card-b">
                <ul class="ad-acc-list">
                  <li v-for="a in activePlatform.accounts" :key="a.id">
                    <div class="a-top">
                      <AccountAvatarBadge :avatar-url="a.avatar_url" :icon="activePlatform.icon" :color="activePlatform.color" :size="30" />
                      <span class="a-nm">{{ a.name }}</span>
                      <span class="ad-pill" :class="a.healthy ? 'ok' : 'warn'">
                        <span class="d"></span>{{ a.healthy ? 'Active' : 'Needs re-auth' }}
                      </span>
                    </div>
                    <div class="a-meta">
                      <span v-if="a.currency">{{ a.currency }}</span>
                      <span v-if="a.account_status">· {{ a.account_status }}</span>
                      <span class="a-ext">{{ a.external_id }}</span>
                    </div>
                    <a :href="activePlatform.reconnect_url" class="a-reconnect">
                      <i class="bx bx-refresh"></i> Reconnect
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import AccountAvatarBadge from '../posts/AccountAvatarBadge.vue';

const props = defineProps({
  data: { type: Object, required: true },
});

const selected = ref('all');
const platforms = computed(() => props.data.platforms || []);
const connectedPlatforms = computed(() => platforms.value.filter((p) => p.connected));

const activePlatform = computed(
  () => platforms.value.find((p) => p.platform === selected.value) || { label: '', campaigns: [], accounts: [] }
);

// Any platform's connect entry point for the header button's fallback href.
const connectModalTrigger = '#';

const currency = computed(() => props.data.currency || '');

function money(v) {
  const n = Number(v || 0);
  return (currency.value ? currency.value + ' ' : '') + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function int(v) {
  return Number(v || 0).toLocaleString();
}
function shortDate(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}
function budgetLabel(c) {
  if (c.daily_budget) return money(c.daily_budget) + '/day';
  if (c.lifetime_budget) return money(c.lifetime_budget);
  return '—';
}
function statusClass(status) {
  const s = String(status || '').toLowerCase();
  if (['active', 'enable', 'enabled', 'running'].includes(s)) return 'ok';
  if (['paused', 'pending', 'review', 'disable', 'disabled'].includes(s)) return 'warn';
  if (['error', 'rejected', 'failed', 'archived', 'deleted'].includes(s)) return 'bad';
  return 'mut';
}

const META = {
  facebook:  { label: 'Meta / Facebook', icon: 'bxl-facebook-circle', color: '#1877F2' },
  instagram: { label: 'Instagram',       icon: 'bxl-instagram',       color: '#E1306C' },
  google:    { label: 'Google Ads',      icon: 'bxl-google',          color: '#4285F4' },
  youtube:   { label: 'YouTube',         icon: 'bxl-youtube',         color: '#FF0000' },
  tiktok:    { label: 'TikTok',          icon: 'bxl-tiktok',          color: '#111827' },
  snapchat:  { label: 'Snapchat',        icon: 'bxl-snapchat',        color: '#e0b400' },
  x:         { label: 'X',               icon: 'bxl-twitter',         color: '#111827' },
  linkedin:  { label: 'LinkedIn',        icon: 'bxl-linkedin',        color: '#0A66C2' },
};
function platformMeta(key) {
  return META[key] || { label: key, icon: 'bx-globe', color: '#6b7280' };
}

const combinedTiles = computed(() => {
  const s = props.data.combined.summary;
  return [
    { label: 'Connected platforms', value: props.data.totals.platforms_connected + ' / ' + props.data.totals.platforms_total, icon: 'bx-plug', color: '#6f57e8', bg: '#ece9fc' },
    { label: 'Ad accounts', value: int(props.data.totals.accounts), icon: 'bx-user-circle', color: '#1f9bd8', bg: '#e2f3fb' },
    { label: 'Total campaigns', value: int(s.campaigns_total), icon: 'bx-rocket', color: '#e0556f', bg: '#fbe4e9' },
    { label: 'Active campaigns', value: int(s.campaigns_active), sub: s.campaigns_paused + ' paused', icon: 'bx-play-circle', color: '#12a06a', bg: '#dcf3e8' },
    { label: 'Ad groups', value: int(s.ad_groups), icon: 'bx-layer', color: '#e08a12', bg: '#fbeed7' },
    { label: 'Ads', value: int(s.ads), icon: 'bx-purchase-tag-alt', color: '#5566e6', bg: '#e5e8fc' },
    { label: 'Creatives', value: int(s.creatives), icon: 'bx-image', color: '#0d9488', bg: '#d5f2ef' },
    { label: 'Daily budget (configured)', value: money(s.daily_budget_total), icon: 'bx-wallet', color: '#e6a013', bg: '#fff3d6' },
    { label: 'Lifetime budget (configured)', value: money(s.lifetime_budget_total), icon: 'bx-money', color: '#17a558', bg: '#dbf4e6' },
  ];
});

const platformTiles = computed(() => {
  const s = activePlatform.value.summary || {};
  return [
    { label: 'Ad accounts', value: int(activePlatform.value.accounts.length), icon: 'bx-user-circle', color: '#1f9bd8', bg: '#e2f3fb' },
    { label: 'Campaigns', value: int(s.campaigns_total), icon: 'bx-rocket', color: '#e0556f', bg: '#fbe4e9' },
    { label: 'Active', value: int(s.campaigns_active), sub: s.campaigns_paused + ' paused', icon: 'bx-play-circle', color: '#12a06a', bg: '#dcf3e8' },
    { label: 'Ad groups', value: int(s.ad_groups), icon: 'bx-layer', color: '#e08a12', bg: '#fbeed7' },
    { label: 'Ads', value: int(s.ads), icon: 'bx-purchase-tag-alt', color: '#5566e6', bg: '#e5e8fc' },
    { label: 'Creatives', value: int(s.creatives), icon: 'bx-image', color: '#0d9488', bg: '#d5f2ef' },
    { label: 'Daily budget (configured)', value: money(s.daily_budget_total), icon: 'bx-wallet', color: '#e6a013', bg: '#fff3d6' },
    { label: 'Lifetime budget (configured)', value: money(s.lifetime_budget_total), icon: 'bx-money', color: '#17a558', bg: '#dbf4e6' },
  ];
});
</script>

<style scoped>
.ads-dash { --ln: #e0e2e7; --ln-soft: #eef1f5; --ink: #192126; --ink2: #5b6675; --muted: #7f7f7f; --brand: #6366f1; --radius: 14px; }

.ad-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.1rem; }
.ad-head h4 { font-weight: 700; margin: 0 0 .2rem; color: var(--ink); }
.ad-head p { color: var(--muted); font-size: .88rem; margin: 0; }

.ad-platnav { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.4rem; }
.ad-pl { display: inline-flex; align-items: center; gap: .45rem; border: 1px solid var(--ln); background: #fff; color: var(--ink2); font-size: .83rem; font-weight: 600; border-radius: 999px; padding: .44rem .9rem; cursor: pointer; transition: all .15s ease; position: relative; }
.ad-pl:hover { border-color: var(--brand); color: var(--ink); }
.ad-pl.active { background: var(--brand); border-color: var(--brand); color: #fff; }
.ad-pl.disconnected:not(.active) { opacity: .55; }
.ad-pl i { font-size: 1rem; }
.ad-dot { width: 6px; height: 6px; border-radius: 50%; }
.ad-dot.ok { background: #10b981; }
.ad-dot.warn { background: #f59e0b; }

.ad-card { background: #fff; border: 1px solid var(--ln); border-radius: var(--radius); }
.ad-card-h { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .9rem 1.15rem; border-bottom: 1px solid var(--ln-soft); }
.ad-card-h .t { font-size: .95rem; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: .5rem; }
.ad-card-h .t i { color: var(--brand); }
.ad-card-b { padding: 1.15rem; }
.ad-card-b.p-0 { padding: 0; }

.ad-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.ad-tile { background: #fff; border: 1px solid var(--ln); border-radius: var(--radius); padding: 1.05rem 1.1rem; transition: box-shadow .18s ease, transform .18s ease; }
.ad-tile:hover { box-shadow: 0 10px 26px -14px rgba(20, 25, 40, .22); transform: translateY(-2px); }
.ad-tile .r1 { display: flex; align-items: center; gap: .55rem; }
.ad-tile .ico { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.ad-tile .lbl { font-size: .8rem; font-weight: 600; color: var(--ink2); }
.ad-tile .val { font-size: 1.5rem; font-weight: 700; color: var(--ink); line-height: 1.2; margin-top: .65rem; font-variant-numeric: tabular-nums; }
.ad-tile .sub { font-size: .75rem; margin-top: .35rem; color: var(--muted); }

.ad-perf-empty { display: flex; gap: 1rem; align-items: flex-start; padding: .5rem; }
.ad-perf-empty > i { font-size: 2rem; color: #c3c8d0; flex-shrink: 0; }
.ad-perf-empty strong { color: var(--ink); font-size: .9rem; }
.ad-perf-empty p { color: var(--muted); font-size: .82rem; margin: .3rem 0 0; }

.ad-bars { list-style: none; margin: 0; padding: 0; }
.ad-bars li { padding: .6rem 0; border-bottom: 1px solid var(--ln-soft); }
.ad-bars li:last-child { border-bottom: 0; }
.ad-bars .bar-head { display: flex; align-items: center; gap: .5rem; font-size: .84rem; }
.ad-bars .bar-head i { font-size: 1rem; }
.ad-bars .bar-head .nm { font-weight: 600; color: var(--ink); flex: 1; }
.ad-bars .bar-head .ct { color: var(--muted); font-size: .78rem; }
.ad-bars .bar-track { height: 7px; border-radius: 6px; background: var(--ln-soft); margin: .45rem 0 .3rem; overflow: hidden; }
.ad-bars .bar-fill { height: 100%; border-radius: 6px; }
.ad-bars .bar-foot { display: flex; justify-content: space-between; font-size: .74rem; color: var(--muted); font-variant-numeric: tabular-nums; }

.ad-table { width: 100%; margin: 0; min-width: 620px; }
.ad-table thead th { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; border: 0; border-bottom: 1px solid var(--ln); padding: .65rem 1.15rem; white-space: nowrap; }
.ad-table tbody td { border: 0; border-bottom: 1px solid var(--ln-soft); padding: .7rem 1.15rem; vertical-align: middle; font-size: .83rem; color: var(--ink2); white-space: nowrap; }
.ad-table tbody tr:last-child td { border-bottom: 0; }
.ad-table tbody tr:hover td { background: #fafbfc; }
.ad-table .c-nm { font-weight: 700; color: var(--ink); display: block; }
.ad-table .c-sub { font-size: .73rem; color: var(--muted); text-transform: capitalize; }
.ad-table .c-plat { display: inline-flex; align-items: center; gap: .4rem; font-weight: 600; color: var(--ink); }
.ad-table .c-plat i { font-size: 1rem; }

.ad-pill { font-size: .68rem; font-weight: 700; padding: .24em .62em; border-radius: 20px; text-transform: capitalize; white-space: nowrap; display: inline-flex; align-items: center; gap: .3rem; text-decoration: none; }
.ad-pill.ok { background: #e6f6ee; color: #0f7a4f; }
.ad-pill.warn { background: #fef1dc; color: #8a5a12; }
.ad-pill.bad { background: #fde8e7; color: #c0322c; }
.ad-pill.mut { background: #eef1f5; color: #5b6675; }
.ad-pill .d { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

.ad-subbar { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; margin-bottom: 1.25rem; }

.ad-reconnect { color: var(--muted); font-size: 1rem; line-height: 1; display: inline-flex; text-decoration: none; padding: .15rem; border-radius: 6px; }
.ad-reconnect:hover { color: var(--brand); background: var(--ln-soft); }

.a-reconnect { display: inline-flex; align-items: center; gap: .25rem; font-size: .74rem; font-weight: 600; color: var(--ink2); text-decoration: none; margin-top: .4rem; }
.a-reconnect:hover { color: var(--brand); }

.ad-conn { list-style: none; margin: 0; padding: 0; }
.ad-conn > li { padding: .5rem 0; border-bottom: 1px solid var(--ln-soft); }
.ad-conn > li:last-child { border-bottom: 0; }
.ad-conn-head { display: flex; align-items: center; gap: .6rem; }
.ad-conn-head > i { font-size: 1.15rem; }
.ad-conn-head .nm { flex: 1; font-size: .84rem; font-weight: 600; color: var(--ink); }

.ad-conn-accounts { list-style: none; margin: .5rem 0 0; padding: 0 0 0 1.75rem; display: flex; flex-direction: column; gap: .4rem; }
.ad-conn-accounts li { display: flex; align-items: center; gap: .5rem; }
.ad-conn-accounts .an { font-size: .78rem; color: var(--ink2); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ad-conn-accounts .warn-ic { color: #d97706; font-size: .85rem; flex-shrink: 0; }
.ad-conn-more { font-size: .72rem; color: var(--muted); padding-left: 2px; }

.ad-actions { display: flex; flex-direction: column; gap: .5rem; }
.ad-actions .btn { text-align: left; font-size: .84rem; font-weight: 600; border-radius: 10px; padding: .55rem .8rem; display: flex; align-items: center; gap: .55rem; }

.ad-acc-list { list-style: none; margin: 0; padding: 0; }
.ad-acc-list li { padding: .6rem 0; border-bottom: 1px solid var(--ln-soft); }
.ad-acc-list li:last-child { border-bottom: 0; }
.ad-acc-list .a-top { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
.ad-acc-list .a-nm { font-weight: 700; color: var(--ink); font-size: .85rem; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ad-acc-list .a-meta { font-size: .74rem; color: var(--muted); margin-top: .2rem; padding-left: 38px; display: flex; flex-wrap: wrap; gap: .3rem; }
.ad-acc-list .a-ext { opacity: .7; }

.ad-empty { text-align: center; padding: 2.75rem 2rem; }
.ad-empty .ring { width: 60px; height: 60px; border-radius: 16px; background: #ede9fe; color: var(--brand); display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem; }
.ad-empty h5 { font-weight: 700; color: var(--ink); margin-bottom: .35rem; }
.ad-empty p { color: var(--muted); max-width: 420px; margin: 0 auto 1.3rem; font-size: .86rem; }

@media (max-width: 575px) {
  .ad-tiles { grid-template-columns: repeat(2, 1fr); }
  .ad-tile .val { font-size: 1.25rem; }
}
</style>
