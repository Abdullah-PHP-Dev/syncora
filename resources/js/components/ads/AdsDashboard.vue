<template>
  <div class="ads-dash">

    <!-- ================= HERO ================= -->
    <section class="ad-hero">
      <div class="ad-hero-main">
        <span class="ad-hero-date"><i class="bx bx-bar-chart-square"></i> {{ todayLabel }}</span>
        <h4>{{ selected === 'all' ? 'Ads Dashboard' : activePlatform.label + ' Ads' }}</h4>
        <p>{{ selected === 'all'
          ? 'Campaigns, ad accounts and budgets across every advertising platform you use.'
          : activePlatform.label + ' campaigns, ad accounts and budgets.' }}</p>
        <div v-if="data.has_connections" class="ad-hero-chips">
          <span class="ad-chip"><i class="bx bx-play-circle"></i><strong>{{ int(scope.campaigns_active) }}</strong> active {{ scope.campaigns_active === 1 ? 'campaign' : 'campaigns' }}</span>
          <span class="ad-chip"><i class="bx bx-wallet"></i><strong>{{ money(scope.daily_budget_total) }}</strong> per day configured</span>
          <span class="ad-chip"><i class="bx bx-user-circle"></i><strong>{{ int(scopeAccounts) }}</strong> ad {{ scopeAccounts === 1 ? 'account' : 'accounts' }}</span>
        </div>
      </div>

      <div v-if="data.has_connections" class="ad-hero-actions">
        <a v-if="selected !== 'all' && activePlatform.campaigns_url" :href="activePlatform.campaigns_url" class="ad-btn ad-btn-ghost">
          <i class="bx bx-list-ul"></i> All {{ activePlatform.label }} campaigns
        </a>
        <a v-if="selected !== 'all' && activePlatform.create_url" :href="activePlatform.create_url" class="ad-btn ad-btn-primary">
          <i class="bx bx-plus"></i> Create campaign
        </a>
        <div v-if="selected === 'all'" ref="createMenu" class="ad-menu">
          <button type="button" class="ad-btn ad-btn-primary" :aria-expanded="createOpen" @click="createOpen = !createOpen">
            <i class="bx bx-plus"></i> Create campaign <i class="bx bx-chevron-down"></i>
          </button>
          <div v-if="createOpen" class="ad-menu-list" role="menu">
            <a v-for="p in connectedPlatforms" :key="p.platform" :href="p.create_url" role="menuitem">
              <span class="ad-menu-ic" :style="{ background: p.color + '1a', color: menuColor(p) }"><i class="bx" :class="p.icon"></i></span>
              {{ p.label }}
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- ================= NOTHING CONNECTED ================= -->
    <section v-if="!data.has_connections" class="ad-card ad-empty">
      <div class="ring"><i class="bx bx-bullhorn"></i></div>
      <h5>No ad accounts yet</h5>
      <p>Connect an advertising account in Connections and its campaigns, ad accounts and budgets show up here.</p>
      <a v-if="data.connections_url" :href="data.connections_url" class="ad-btn ad-btn-primary"><i class="bx bx-link"></i> Go to Connections</a>
    </section>

    <template v-else>

      <!-- ================= PLATFORM SWITCHER ================= -->
      <nav class="ad-platnav" aria-label="Platforms">
        <button type="button" class="ad-pl" :class="{ active: selected === 'all' }" @click="selected = 'all'">
          <span class="ad-pl-ic is-all"><i class="bx bx-grid-alt"></i></span>
          <span>All platforms</span>
          <span class="ad-pl-count">{{ int(data.combined.summary.campaigns_total) }}</span>
        </button>
        <button
          v-for="p in connectedPlatforms"
          :key="p.platform"
          type="button"
          class="ad-pl"
          :class="{ active: selected === p.platform }"
          :title="p.healthy ? p.label : p.label + ' - needs reconnecting'"
          @click="selected = p.platform"
        >
          <span class="ad-pl-ic" :style="{ background: p.color + '1a', color: menuColor(p) }"><i class="bx" :class="p.icon"></i></span>
          <span>{{ p.label }}</span>
          <span class="ad-pl-count">{{ int((p.summary || {}).campaigns_total) }}</span>
          <span v-if="!p.healthy" class="ad-pl-warn" aria-hidden="true"></span>
        </button>
      </nav>

      <!-- ================= KPIs ================= -->
      <div class="ad-kpis">
        <div v-for="k in kpis" :key="k.label" class="ad-kpi">
          <div class="ad-kpi-top">
            <span class="ad-kpi-label">{{ k.label }}</span>
            <span class="ad-kpi-ic" :style="{ background: k.bg, color: k.color }"><i class="bx" :class="k.icon"></i></span>
          </div>
          <div class="ad-kpi-val">{{ k.value }}</div>
          <div class="ad-kpi-sub">{{ k.sub }}</div>
        </div>
      </div>

      <div class="ad-strip">
        <div v-for="s in strip" :key="s.label" class="ad-strip-item">
          <i class="bx" :class="s.icon" :style="{ color: s.color }"></i>
          <strong>{{ s.value }}</strong>
          <span>{{ s.label }}</span>
        </div>
      </div>

      <div class="ad-grid">

        <!-- ================= MAIN ================= -->
        <div class="ad-main">

          <!-- platform comparison (all) -->
          <section v-if="selected === 'all'" class="ad-card">
            <div class="ad-card-h">
              <div><h5>Platform comparison</h5><p>Share of campaigns and configured daily budget</p></div>
            </div>
            <div v-if="!data.combined.platform_breakdown.length" class="ad-none">No campaigns created on any platform yet.</div>
            <ul v-else class="ad-bars">
              <li v-for="p in data.combined.platform_breakdown" :key="p.platform">
                <span class="ad-bars-ic" :style="{ background: p.color + '1a', color: menuColor(p) }"><i class="bx" :class="p.icon"></i></span>
                <div class="ad-bars-body">
                  <div class="ad-bars-head">
                    <span class="nm">{{ p.label }}</span>
                    <span class="ct">{{ p.campaigns_total }} {{ p.campaigns_total === 1 ? 'campaign' : 'campaigns' }} · {{ p.share }}%</span>
                  </div>
                  <div class="ad-bars-track"><span :style="{ width: p.share + '%', background: barColor(p) }"></span></div>
                  <div v-if="p.daily_budget" class="ad-bars-foot">{{ money(p.daily_budget) }}/day configured</div>
                </div>
              </li>
            </ul>
          </section>

          <!-- campaigns table -->
          <section class="ad-card">
            <div class="ad-card-h">
              <div>
                <h5>{{ selected === 'all' ? 'Recent campaigns' : activePlatform.label + ' campaigns' }}</h5>
                <p>{{ selected === 'all' ? 'Latest across all platforms' : 'Every campaign created here' }}</p>
              </div>
              <a v-if="selected !== 'all' && activePlatform.campaigns_url" :href="activePlatform.campaigns_url" class="ad-link">Manage <i class="bx bx-right-arrow-alt"></i></a>
            </div>
            <div v-if="!tableRows.length" class="ad-none">
              <i class="bx bx-rocket"></i>
              No campaigns yet.
              <a v-if="selected !== 'all' && activePlatform.create_url" :href="activePlatform.create_url">Create the first one</a>
            </div>
            <div v-else class="ad-table-wrap">
              <table class="ad-table">
                <thead>
                  <tr>
                    <th>Campaign</th>
                    <th v-if="selected === 'all'">Platform</th>
                    <th>Status</th>
                    <th class="text-end">Budget</th>
                    <th class="text-end">{{ selected === 'all' ? 'Created' : 'Start' }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="c in tableRows" :key="c.id">
                    <td>
                      <span class="c-nm">{{ c.name }}</span>
                      <span v-if="c.objective" class="c-sub">{{ objectiveLabel(c.objective) }}</span>
                    </td>
                    <td v-if="selected === 'all'">
                      <span class="c-plat">
                        <span class="c-plat-ic" :style="{ background: platformMeta(c.platform).color + '1a', color: menuColor(platformMeta(c.platform)) }"><i class="bx" :class="platformMeta(c.platform).icon"></i></span>
                        {{ platformMeta(c.platform).label }}
                      </span>
                    </td>
                    <td><span class="ad-pill" :class="statusClass(c.status)"><span class="d"></span>{{ c.status || 'unknown' }}</span></td>
                    <td class="text-end c-num">{{ budgetLabel(c) }}</td>
                    <td class="text-end c-date">{{ shortDate(selected === 'all' ? c.created_at : (c.start_time || c.created_at)) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- performance note -->
          <div class="ad-note">
            <i class="bx bx-info-circle"></i>
            <span><strong>Spend, impressions, clicks and conversions</strong> will appear here once reporting is synced from each platform. Today this page shows the campaigns, ad accounts and budgets configured in the app.</span>
          </div>
        </div>

        <!-- ================= SIDE ================= -->
        <aside class="ad-side">

          <section class="ad-card">
            <div class="ad-card-h"><div><h5>Campaign status</h5><p>{{ selected === 'all' ? 'All platforms' : activePlatform.label }}</p></div></div>
            <div class="ad-donut-wrap">
              <svg class="ad-donut" viewBox="0 0 120 120" role="img" :aria-label="int(scope.campaigns_total) + ' campaigns'">
                <circle cx="60" cy="60" r="48" class="track"></circle>
                <circle v-for="seg in donut" :key="seg.key" cx="60" cy="60" r="48" class="seg" :stroke="seg.color"
                        :stroke-dasharray="seg.length + ' ' + (circumference - seg.length)" :stroke-dashoffset="-seg.offset"></circle>
              </svg>
              <div class="ad-donut-label"><strong>{{ int(scope.campaigns_total) }}</strong><small>campaigns</small></div>
            </div>
            <ul class="ad-legend">
              <li v-for="s in statusSlices" :key="s.key"><span class="dot" :style="{ background: s.color }"></span> {{ s.label }} <strong>{{ int(s.value) }}</strong></li>
            </ul>
          </section>

          <section v-if="selected === 'all'" class="ad-card">
            <div class="ad-card-h"><div><h5>Quick actions</h5><p>Start a campaign on a platform</p></div></div>
            <div class="ad-quick">
              <a v-for="p in connectedPlatforms" :key="p.platform" :href="p.create_url" class="ad-quick-item">
                <span class="ad-quick-ic" :style="{ background: p.color + '1a', color: menuColor(p) }"><i class="bx" :class="p.icon"></i></span>
                <span><strong>New {{ p.label }} campaign</strong><small>{{ int((p.summary || {}).campaigns_total) }} so far</small></span>
                <i class="bx bx-chevron-right"></i>
              </a>
            </div>
          </section>

          <section v-else class="ad-card">
            <div class="ad-card-h"><div><h5>Ad accounts</h5><p>{{ activePlatform.accounts.length }} on {{ activePlatform.label }}</p></div></div>
            <ul class="ad-acc-list">
              <li v-for="a in activePlatform.accounts" :key="a.id">
                <AccountAvatarBadge :avatar-url="a.avatar_url" :icon="'bx ' + activePlatform.icon" :color="activePlatform.color" :size="34" />
                <div class="a-body">
                  <span class="a-nm">{{ a.name }}</span>
                  <span class="a-meta">{{ [a.currency, a.account_status, a.external_id].filter(Boolean).join(' · ') }}</span>
                </div>
                <span class="ad-pill" :class="a.healthy ? 'ok' : 'warn'"><span class="d"></span>{{ a.healthy ? 'Active' : 'Reconnect' }}</span>
              </li>
            </ul>
          </section>

        </aside>
      </div>
    </template>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import AccountAvatarBadge from '../posts/AccountAvatarBadge.vue';

const props = defineProps({
  data: { type: Object, required: true },
});

const selected = ref('all');
const createOpen = ref(false);
const createMenu = ref(null);

const platforms = computed(() => props.data.platforms || []);
// Only platforms with an ad account - connecting lives in the Connection Hub.
const connectedPlatforms = computed(() => platforms.value.filter((p) => p.connected));

const activePlatform = computed(
  () => platforms.value.find((p) => p.platform === selected.value) || { label: '', campaigns: [], accounts: [], summary: {} }
);

// Numbers for whatever is selected (all platforms or one).
const scope = computed(() => (selected.value === 'all' ? props.data.combined.summary : activePlatform.value.summary) || {});
const scopeAccounts = computed(() => (selected.value === 'all' ? props.data.totals.accounts : activePlatform.value.accounts.length) || 0);
const tableRows = computed(() => (selected.value === 'all' ? props.data.combined.recent_campaigns : activePlatform.value.campaigns) || []);

const todayLabel = new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });

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
// "OUTCOME_SALES" -> "Sales", "LEAD_GENERATION" -> "Lead generation".
function objectiveLabel(v) {
  const s = String(v || '').replace(/^OUTCOME_/i, '').replace(/_/g, ' ').toLowerCase();
  return s.charAt(0).toUpperCase() + s.slice(1);
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
// Snapchat's yellow is unreadable as icon/bar color on white.
function menuColor(p) {
  return (p.platform === 'snapchat' || /^#?fffc00$/i.test(String(p.color).replace('#', ''))) ? '#B59A00' : p.color;
}
function barColor(p) {
  return menuColor(p);
}

const kpis = computed(() => {
  const s = scope.value;
  return [
    { label: 'Total campaigns', value: int(s.campaigns_total), sub: int(s.ad_groups) + ' ad groups · ' + int(s.ads) + ' ads', icon: 'bx-rocket', color: '#6D4AFF', bg: '#F2EEFF' },
    { label: 'Active campaigns', value: int(s.campaigns_active), sub: int(s.campaigns_paused) + ' paused', icon: 'bx-play-circle', color: '#16A34A', bg: '#E7F7EE' },
    { label: 'Daily budget', value: money(s.daily_budget_total), sub: 'Configured across campaigns', icon: 'bx-wallet', color: '#D97706', bg: '#FFF6E5' },
    { label: 'Lifetime budget', value: money(s.lifetime_budget_total), sub: 'Configured across campaigns', icon: 'bx-money', color: '#2563EB', bg: '#EAF2FF' },
  ];
});

const strip = computed(() => {
  const s = scope.value;
  return [
    { label: 'Ad accounts', value: int(scopeAccounts.value), icon: 'bx-user-circle', color: '#1f9bd8' },
    { label: 'Ad groups', value: int(s.ad_groups), icon: 'bx-layer', color: '#e08a12' },
    { label: 'Ads', value: int(s.ads), icon: 'bx-purchase-tag-alt', color: '#5566e6' },
    { label: 'Creatives', value: int(s.creatives), icon: 'bx-image', color: '#0d9488' },
  ];
});

const statusSlices = computed(() => {
  const s = scope.value;
  const total = Number(s.campaigns_total || 0);
  const active = Number(s.campaigns_active || 0);
  const paused = Number(s.campaigns_paused || 0);
  return [
    { key: 'active', label: 'Active', value: active, color: '#16A34A' },
    { key: 'paused', label: 'Paused', value: paused, color: '#F59E0B' },
    { key: 'other', label: 'Draft or ended', value: Math.max(0, total - active - paused), color: '#CBD2E1' },
  ];
});

const circumference = 2 * Math.PI * 48;
const donut = computed(() => {
  const total = statusSlices.value.reduce((n, s) => n + s.value, 0);
  if (!total) return [];
  let offset = 0;
  return statusSlices.value.filter((s) => s.value > 0).map((s) => {
    const length = (s.value / total) * circumference;
    const seg = { ...s, length, offset };
    offset += length;
    return seg;
  });
});

function closeCreate(event) {
  if (createOpen.value && createMenu.value && !createMenu.value.contains(event.target)) createOpen.value = false;
}
onMounted(() => document.addEventListener('click', closeCreate));
onBeforeUnmount(() => document.removeEventListener('click', closeCreate));
</script>

<style scoped>
.ads-dash {
  --ink: #161B2B; --text: #4B5263; --muted: #8A92A3; --line: #E7E9F0; --line-soft: #F1F3F7;
  --brand: #6D4AFF; --brand-2: #8F6BFF; --brand-soft: #F2EEFF;
  display: flex; flex-direction: column; gap: 20px; min-width: 0;
}

/* Hero */
.ad-hero {
  /* No overflow: hidden - the Create campaign menu drops out of the hero.
     z-index keeps that menu above the platform switcher below. */
  position: relative; z-index: 5; display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1.25rem;
  padding: 1.6rem 1.8rem; border-radius: 20px; border: 1px solid #E4E0FB;
  background:
    radial-gradient(120% 140% at 100% 0%, rgba(143,107,255,.16) 0%, rgba(143,107,255,0) 55%),
    radial-gradient(90% 120% at 0% 100%, rgba(21,112,239,.08) 0%, rgba(21,112,239,0) 60%),
    linear-gradient(180deg, #fff 0%, #FBFAFF 100%);
  box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 12px 32px rgba(76,52,190,.06);
}
.ad-hero::after {
  content: ""; position: absolute; inset: 0; border-radius: inherit; pointer-events: none; opacity: .5;
  background-image: radial-gradient(rgba(109,74,255,.14) 1px, transparent 1px); background-size: 18px 18px;
  -webkit-mask-image: linear-gradient(110deg, transparent 45%, #000 100%); mask-image: linear-gradient(110deg, transparent 45%, #000 100%);
}
.ad-hero > * { position: relative; z-index: 1; }
.ad-hero-main { flex: 1 1 360px; min-width: 0; }
.ad-hero-date { display: inline-flex; align-items: center; gap: .35rem; font-size: .74rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #6b5bd6; margin-bottom: .5rem; }
.ad-hero h4 { margin: 0 0 .3rem; font-size: 1.7rem; font-weight: 700; letter-spacing: -.02em; color: var(--ink); }
.ad-hero p { margin: 0; color: #6b7385; font-size: .92rem; max-width: 72ch; }
.ad-hero-chips { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1rem; }
.ad-chip { display: inline-flex; align-items: center; gap: .4rem; height: 34px; padding: 0 .8rem; border-radius: 999px; background: rgba(255,255,255,.85); border: 1px solid #E7E4F7; color: #4b5263; font-size: .8rem; font-variant-numeric: tabular-nums; }
.ad-chip i { font-size: 1rem; color: var(--brand); }
.ad-chip strong { color: var(--ink); font-weight: 700; }
.ad-hero-actions { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: flex-end; }

.ad-btn { display: inline-flex; align-items: center; gap: .4rem; height: 40px; padding: 0 16px; border-radius: 10px; font-weight: 600; font-size: .85rem; text-decoration: none; border: 1px solid transparent; cursor: pointer; white-space: nowrap; transition: transform .15s, box-shadow .15s, border-color .15s, color .15s; }
.ad-btn i { font-size: 1.05rem; }
.ad-btn-primary { background: linear-gradient(135deg, var(--brand), var(--brand-2)); color: #fff; box-shadow: 0 4px 12px rgba(109,74,255,.25); }
.ad-btn-primary:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(109,74,255,.35); }
.ad-btn-ghost { background: #fff; border-color: var(--line); color: #3f4759; }
.ad-btn-ghost:hover { border-color: #CFC4FF; color: var(--brand); }

.ad-menu { position: relative; }
.ad-menu-list { position: absolute; right: 0; top: calc(100% + 6px); z-index: 30; min-width: 230px; padding: 6px; background: #fff; border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 18px 40px rgba(16,24,40,.16); display: flex; flex-direction: column; }
.ad-menu-list a { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; color: var(--text); font-size: .84rem; font-weight: 500; text-decoration: none; }
.ad-menu-list a:hover { background: var(--brand-soft); color: var(--brand); }
.ad-menu-ic { width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; font-size: 15px; flex-shrink: 0; }

/* Platform switcher */
.ad-platnav { display: flex; gap: 6px; padding: 6px; background: #fff; border: 1px solid var(--line); border-radius: 14px; overflow-x: auto; scrollbar-width: none; }
.ad-pl { position: relative; flex-shrink: 0; display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 12px 0 6px; border: 1px solid transparent; border-radius: 10px; background: none; color: var(--text); font-size: .84rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
.ad-pl:hover { background: #F7F8FB; }
.ad-pl.active { background: var(--brand-soft); border-color: #DCD3FF; color: #4F2FD6; }
.ad-pl-ic { width: 30px; height: 30px; border-radius: 8px; display: grid; place-items: center; font-size: 16px; }
.ad-pl-ic.is-all { background: #EEF0F6; color: #4B5263; }
.ad-pl.active .ad-pl-ic.is-all { background: #fff; color: var(--brand); }
.ad-pl-count { min-width: 22px; height: 20px; padding: 0 6px; border-radius: 999px; background: #F1F3F7; color: var(--muted); font-size: .7rem; display: inline-grid; place-items: center; }
.ad-pl.active .ad-pl-count { background: #fff; color: #4F2FD6; }
.ad-pl-warn { position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; border-radius: 50%; background: #F59E0B; box-shadow: 0 0 0 2px #fff; }

/* KPIs */
.ad-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
.ad-kpi { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 1.1rem 1.2rem; box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 8px 24px rgba(16,24,40,.03); transition: transform .2s, box-shadow .2s; min-width: 0; }
.ad-kpi:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(16,24,40,.08); }
.ad-kpi-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
.ad-kpi-label { font-size: .8rem; font-weight: 600; color: var(--muted); }
.ad-kpi-ic { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; font-size: 19px; flex-shrink: 0; }
.ad-kpi-val { margin-top: .35rem; font-size: 1.75rem; font-weight: 700; letter-spacing: -.02em; color: var(--ink); font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ad-kpi-sub { margin-top: .35rem; font-size: .78rem; color: var(--muted); }

.ad-strip { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); background: #fff; border: 1px solid var(--line); border-radius: 14px; }
.ad-strip-item { display: flex; align-items: center; gap: 10px; padding: 12px 16px; font-size: .82rem; color: var(--muted); min-width: 0; }
.ad-strip-item + .ad-strip-item { border-left: 1px solid var(--line-soft); }
.ad-strip-item i { font-size: 18px; }
.ad-strip-item strong { font-size: 1.05rem; color: var(--ink); font-variant-numeric: tabular-nums; }

/* Layout */
.ad-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; align-items: start; }
.ad-main, .ad-side { display: flex; flex-direction: column; gap: 20px; min-width: 0; }

.ad-card { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 1.2rem 1.3rem; box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 8px 24px rgba(16,24,40,.03); min-width: 0; }
.ad-card-h { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 1rem; }
.ad-card-h h5 { margin: 0; font-size: .98rem; font-weight: 700; color: var(--ink); }
.ad-card-h p { margin: 2px 0 0; font-size: .78rem; color: var(--muted); }
.ad-link { display: inline-flex; align-items: center; gap: 2px; font-size: .82rem; font-weight: 600; color: var(--brand); text-decoration: none; white-space: nowrap; }
.ad-none { display: flex; align-items: center; justify-content: center; gap: .5rem; flex-wrap: wrap; padding: 1.6rem 1rem; color: var(--muted); font-size: .86rem; text-align: center; }
.ad-none i { font-size: 1.2rem; color: #B4BBCB; }
.ad-none a { color: var(--brand); font-weight: 600; }

/* Platform comparison */
.ad-bars { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
.ad-bars li { display: flex; align-items: center; gap: 12px; }
.ad-bars-ic { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 18px; flex-shrink: 0; }
.ad-bars-body { flex: 1; min-width: 0; }
.ad-bars-head { display: flex; justify-content: space-between; gap: 8px; font-size: .84rem; margin-bottom: 6px; }
.ad-bars-head .nm { font-weight: 600; color: var(--ink); }
.ad-bars-head .ct { color: var(--muted); font-variant-numeric: tabular-nums; white-space: nowrap; }
.ad-bars-track { height: 8px; border-radius: 8px; background: #EEF1F6; overflow: hidden; }
.ad-bars-track span { display: block; height: 100%; border-radius: 8px; transition: width .4s; }
.ad-bars-foot { margin-top: 4px; font-size: .74rem; color: var(--muted); }

/* Table */
.ad-table-wrap { overflow-x: auto; margin: 0 -1.3rem -1.2rem; }
.ad-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
.ad-table th { padding: .65rem 1.3rem; background: #F8F9FC; color: var(--muted); font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; border-top: 1px solid var(--line-soft); border-bottom: 1px solid var(--line-soft); white-space: nowrap; }
.ad-table td { padding: .8rem 1.3rem; border-bottom: 1px solid var(--line-soft); vertical-align: middle; color: var(--text); }
.ad-table tbody tr:last-child td { border-bottom: none; }
.ad-table tbody tr:hover td { background: #FBFAFF; }
.c-nm { display: block; font-weight: 600; color: var(--ink); max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.c-sub { display: block; font-size: .74rem; color: var(--muted); }
.c-plat { display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; font-weight: 500; }
.c-plat-ic { width: 26px; height: 26px; border-radius: 7px; display: grid; place-items: center; font-size: 14px; }
.c-num, .c-date { font-variant-numeric: tabular-nums; white-space: nowrap; }
.c-date { color: var(--muted); }

.ad-pill { display: inline-flex; align-items: center; gap: 5px; height: 24px; padding: 0 9px; border-radius: 999px; font-size: .72rem; font-weight: 600; text-transform: capitalize; white-space: nowrap; }
.ad-pill .d { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.ad-pill.ok { background: #E7F7EE; color: #15803D; }
.ad-pill.warn { background: #FFF6E5; color: #B45309; }
.ad-pill.bad { background: #FDECEC; color: #DC2626; }
.ad-pill.mut { background: #F1F3F7; color: #64748B; }

.ad-note { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border-radius: 12px; background: #F5F8FF; border: 1px solid #DCE5FB; color: #3F4A63; font-size: .8rem; line-height: 1.5; }
.ad-note i { font-size: 1.05rem; color: #2563EB; margin-top: 1px; }
.ad-note strong { color: var(--ink); }

/* Status donut */
.ad-donut-wrap { position: relative; width: 150px; height: 150px; margin: 4px auto 14px; }
.ad-donut { width: 100%; height: 100%; transform: rotate(-90deg); }
.ad-donut circle { fill: none; stroke-width: 12; }
.ad-donut .track { stroke: #EEF1F6; }
.ad-donut-label { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.ad-donut-label strong { font-size: 1.8rem; font-weight: 700; color: var(--ink); line-height: 1.1; font-variant-numeric: tabular-nums; }
.ad-donut-label small { font-size: .74rem; color: var(--muted); }
.ad-legend { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.ad-legend li { display: flex; align-items: center; gap: 8px; font-size: .84rem; color: var(--text); }
.ad-legend .dot { width: 9px; height: 9px; border-radius: 50%; }
.ad-legend strong { margin-left: auto; color: var(--ink); font-variant-numeric: tabular-nums; }

/* Quick actions */
.ad-quick { display: flex; flex-direction: column; gap: 10px; }
.ad-quick-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 12px; text-decoration: none; color: inherit; transition: border-color .15s, box-shadow .15s; }
.ad-quick-item:hover { border-color: #CFC4FF; box-shadow: 0 6px 16px rgba(16,24,40,.06); color: inherit; }
.ad-quick-item > span:nth-child(2) { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ad-quick-item strong { font-size: .84rem; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ad-quick-item small { font-size: .74rem; color: var(--muted); }
.ad-quick-item > .bx-chevron-right { color: #B4BBCB; font-size: 18px; }
.ad-quick-ic { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; font-size: 19px; flex-shrink: 0; }

/* Ad accounts (per platform) */
.ad-acc-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.ad-acc-list li { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 12px; }
.a-body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.a-nm { font-size: .85rem; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.a-meta { font-size: .74rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Empty */
.ad-empty { text-align: center; padding: 3rem 1.5rem; }
.ad-empty .ring { width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 1rem; font-size: 28px; color: var(--brand); background: var(--brand-soft); }
.ad-empty h5 { margin: 0 0 .4rem; font-weight: 700; color: var(--ink); }
.ad-empty p { margin: 0 auto 1.2rem; color: var(--muted); font-size: .88rem; max-width: 46ch; }

@media (max-width: 1199.98px) {
  .ad-grid { grid-template-columns: minmax(0, 1fr); }
  .ad-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 767.98px) {
  .ad-hero { padding: 1.25rem; }
  .ad-hero-actions { width: 100%; justify-content: stretch; }
  .ad-hero-actions > * { flex: 1; }
  .ad-hero-actions .ad-btn { width: 100%; justify-content: center; }
  .ad-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .ad-strip-item:nth-child(3) { border-left: none; }
  .ad-strip-item:nth-child(n+3) { border-top: 1px solid var(--line-soft); }
}
@media (max-width: 479.98px) {
  .ad-kpis { grid-template-columns: minmax(0, 1fr); }
}
</style>
