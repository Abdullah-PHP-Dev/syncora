<template>
  <div class="pc-dash">

    <a :href="data.urls.dashboard" class="pc-back"><i class="bx bx-arrow-back"></i> Back to Ads Dashboard</a>

    <!-- ================= PLATFORM HEADER ================= -->
    <div class="pc-head">
      <div class="id">
        <span class="mark" :style="{ background: data.color + '18', color: data.color }">
          <i class="bx" :class="data.icon"></i>
        </span>
        <div>
          <h4>
            {{ data.label }}
            <span v-if="data.connected && data.healthy" class="pc-pill ok"><span class="d"></span>Connected</span>
            <span v-else-if="data.connected" class="pc-pill warn"><span class="d"></span>Needs re-auth</span>
            <span v-else class="pc-pill mut">Not connected</span>
          </h4>
          <div class="acc">
            <template v-if="data.account_names.length">
              Ad account{{ data.account_names.length > 1 ? 's' : '' }}: {{ data.account_names.join(', ') }}
              <span v-if="data.last_synced_at"> · Last synced {{ timeAgo(data.last_synced_at) }}</span>
              <span v-else> · Never synced</span>
            </template>
            <template v-else-if="data.connected">Connected, no ad account resolved yet</template>
            <template v-else>Connect a {{ data.label }} ad account to manage campaigns here</template>
          </div>
        </div>
      </div>

      <div class="actions">
        <button
          v-if="data.connected && data.can_sync"
          type="button"
          class="btn btn-outline-secondary btn-sm"
          :disabled="syncing"
          @click="syncNow"
        >
          <span v-if="syncing" class="spinner-border spinner-border-sm me-1"></span>
          <i v-else class="bx bx-sync me-1"></i>
          {{ syncing ? 'Syncing…' : 'Sync Now' }}
        </button>
        <a v-if="data.connected" :href="data.urls.connect" class="btn btn-outline-secondary btn-sm">
          <i class="bx bx-refresh me-1"></i> Reconnect account
        </a>
        <a v-if="data.connected" :href="data.urls.create" class="btn btn-primary btn-sm">
          <i class="bx bx-plus me-1"></i> Create campaign
        </a>
        <a v-else :href="data.urls.connect" class="btn btn-primary btn-sm">
          <i class="bx bx-plug me-1"></i> Connect {{ data.label }}
        </a>
      </div>
    </div>

    <div v-if="syncMsg" class="alert" :class="syncOk ? 'alert-success' : 'alert-danger'" role="alert">
      {{ syncMsg }}
      <button type="button" class="btn-close" @click="syncMsg = ''"></button>
    </div>

    <!-- ================= NOT CONNECTED ================= -->
    <div v-if="!data.connected" class="pc-card">
      <div class="pc-empty">
        <i class="bx bx-plug"></i>
        <p>{{ data.label }} isn't connected. Connect an ad account to create and sync campaigns.</p>
        <a :href="data.urls.connect" class="btn btn-primary">Connect {{ data.label }}</a>
      </div>
    </div>

    <template v-else>
      <!-- ================= ENTITY TILES ================= -->
      <div class="pc-tiles">
        <div class="pc-tile" v-for="t in tiles" :key="t.label" :class="{ entity: t.entity }">
          <div class="lbl"><i class="bx" :class="t.icon"></i> {{ t.label }}</div>
          <div class="val">{{ t.value }}</div>
          <div v-if="t.sub" class="sub">{{ t.sub }}</div>
        </div>
      </div>

      <div class="row">
        <div class="col-xl-8">
          <!-- performance not available -->
          <div class="pc-card mb-4">
            <div class="pc-card-h"><span class="t"><i class="bx bx-line-chart"></i> Performance overview</span></div>
            <div class="pc-card-b">
              <div class="pc-perf-empty">
                <i class="bx bx-bar-chart-alt-2"></i>
                <div>
                  <strong>Spend, impressions, clicks &amp; conversions aren't available yet</strong>
                  <p>Sync Now refreshes campaign details and status from {{ data.label }}. Live performance metrics need a reporting-API sync, which isn't built yet.</p>
                </div>
              </div>
            </div>
          </div>

          <!-- campaigns table -->
          <div class="pc-card">
            <div class="pc-card-h">
              <span class="t"><i class="bx bx-list-ul"></i> Campaigns</span>
              <span class="text-muted small">{{ data.campaigns.length }} total</span>
            </div>
            <div class="pc-card-b p-0">
              <div v-if="!data.campaigns.length" class="pc-empty">
                <i class="bx bx-bullhorn"></i>
                <p>No campaigns yet.
                  <span v-if="data.can_sync">Use <strong>Sync Now</strong> to pull existing campaigns from {{ data.label }}, or</span>
                  <a :href="data.urls.create"> create one</a>.
                </p>
              </div>
              <div v-else class="table-responsive">
                <table class="pc-table">
                  <thead>
                    <tr>
                      <th>Campaign</th>
                      <th>Objective</th>
                      <th>Status</th>
                      <th class="text-end">Budget</th>
                      <th class="text-end">Start</th>
                      <th class="text-end">End</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="c in data.campaigns" :key="c.id">
                      <td>
                        <span class="c-nm">{{ c.name }}</span>
                        <span v-if="c.id" class="c-ext">#{{ c.id }}</span>
                      </td>
                      <td class="text-capitalize">{{ prettyObjective(c.objective) }}</td>
                      <td><span class="pc-pill" :class="statusClass(c.status)">{{ c.status || 'unknown' }}</span></td>
                      <td class="text-end">{{ budgetLabel(c) }}</td>
                      <td class="text-end text-muted">{{ shortDate(c.start_time) }}</td>
                      <td class="text-end text-muted">{{ shortDate(c.end_time) }}</td>
                      <td class="text-end">
                        <div class="dropdown">
                          <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown">
                            <i class="bx bx-dots-vertical-rounded"></i>
                          </button>
                          <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" :href="editUrl(c.id)"><i class="bx bx-edit-alt me-1"></i> Edit</a>
                            <button class="dropdown-item text-danger" type="button" @click="destroy(c)">
                              <i class="bx bx-trash me-1"></i> Delete
                            </button>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- right rail -->
        <div class="col-xl-4">
          <div class="pc-card mb-3">
            <div class="pc-card-h">
              <span class="t"><i class="bx bx-user-circle"></i> Ad accounts</span>
              <span class="pc-pill mut">{{ data.accounts.length }}</span>
            </div>
            <div class="pc-card-b">
              <ul class="pc-acc">
                <li v-for="a in data.accounts" :key="a.id">
                  <div class="a-top">
                    <AccountAvatarBadge :avatar-url="a.avatar_url" :icon="data.icon" :color="data.color" :size="30" />
                    <span class="a-nm">{{ a.name }}</span>
                    <span class="pc-pill" :class="a.healthy ? 'ok' : 'warn'"><span class="d"></span>{{ a.healthy ? 'Active' : 'Needs re-auth' }}</span>
                  </div>
                  <div class="a-meta">
                    <span v-if="a.currency">{{ a.currency }}</span>
                    <span v-if="a.account_status">· {{ a.account_status }}</span>
                    <span class="a-ext">{{ a.external_id }}</span>
                  </div>
                  <a :href="data.urls.connect" class="a-reconnect"><i class="bx bx-refresh"></i> Reconnect</a>
                </li>
              </ul>
            </div>
          </div>

          <div class="pc-card">
            <div class="pc-card-h"><span class="t"><i class="bx bx-bolt-circle"></i> Quick actions</span></div>
            <div class="pc-card-b">
              <div class="pc-actions">
                <a :href="data.urls.create" class="btn btn-primary"><i class="bx bx-plus"></i> Create campaign</a>
                <button v-if="data.can_sync" type="button" class="btn btn-outline-secondary" :disabled="syncing" @click="syncNow">
                  <i class="bx bx-sync"></i> {{ syncing ? 'Syncing…' : 'Sync Now' }}
                </button>
                <a :href="data.urls.dashboard" class="btn btn-outline-secondary"><i class="bx bx-grid-alt"></i> All platforms</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import AccountAvatarBadge from '../posts/AccountAvatarBadge.vue';

const props = defineProps({
  data: { type: Object, required: true },
  csrf: { type: String, default: '' },
});

const syncing = ref(false);
const syncMsg = ref('');
const syncOk = ref(false);

const currency = computed(() => props.data.currency || '');

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
  if (!o) return '—';
  return String(o).replace(/^OUTCOME_/, '').replace(/_/g, ' ').toLowerCase();
}
function statusClass(status) {
  const s = String(status || '').toLowerCase();
  if (['active', 'enable', 'enabled', 'running', '1'].includes(s)) return 'ok';
  if (['paused', 'pending', 'review', 'in_review', 'disable', 'disabled', 'draft'].includes(s)) return 'warn';
  if (['error', 'rejected', 'failed', 'archived', 'deleted', 'with_issues'].includes(s)) return 'bad';
  return 'mut';
}
function editUrl(id) { return props.data.urls.edit.replace('CAMPAIGN_ID', id); }

const tiles = computed(() => {
  const s = props.data.summary;
  return [
    { label: 'Campaigns', value: int(s.campaigns_total), icon: 'bx-bullhorn', entity: true },
    { label: 'Active', value: int(s.campaigns_active), sub: s.campaigns_paused + ' paused', icon: 'bx-play-circle', entity: true },
    { label: 'Ad groups', value: int(s.ad_groups), icon: 'bx-layer', entity: true },
    { label: 'Ads', value: int(s.ads), icon: 'bx-purchase-tag-alt', entity: true },
    { label: 'Creatives', value: int(s.creatives), icon: 'bx-image', entity: true },
    { label: 'Daily budget (configured)', value: money(s.daily_budget_total), icon: 'bx-wallet' },
    { label: 'Lifetime budget (configured)', value: money(s.lifetime_budget_total), icon: 'bx-money' },
  ];
});

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
.pc-dash { --ln: #e0e2e7; --ln-soft: #eef1f5; --ink: #192126; --ink2: #5b6675; --muted: #7f7f7f; --brand: #6366f1; --radius: 14px; }

.pc-back { font-size: .82rem; font-weight: 600; color: var(--ink2); display: inline-flex; align-items: center; gap: .3rem; margin-bottom: 1rem; text-decoration: none; }
.pc-back:hover { color: var(--brand); }

.pc-head { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; justify-content: space-between; margin-bottom: 1.5rem; }
.pc-head .id { display: flex; align-items: center; gap: .9rem; }
.pc-head .mark { width: 46px; height: 46px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
.pc-head h4 { font-size: 1.35rem; font-weight: 700; margin: 0; color: var(--ink); display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
.pc-head .acc { font-size: .82rem; color: var(--muted); margin-top: .2rem; }
.pc-head .actions { display: flex; gap: .5rem; flex-wrap: wrap; }

.pc-pill { font-size: .68rem; font-weight: 700; padding: .24em .62em; border-radius: 20px; text-transform: capitalize; white-space: nowrap; display: inline-flex; align-items: center; gap: .3rem; }
.pc-pill.ok { background: #e6f6ee; color: #0f7a4f; }
.pc-pill.warn { background: #fef1dc; color: #8a5a12; }
.pc-pill.bad { background: #fde8e7; color: #c0322c; }
.pc-pill.mut { background: #eef1f5; color: #5b6675; }
.pc-pill .d { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

.pc-card { background: #fff; border: 1px solid var(--ln); border-radius: var(--radius); }
.pc-card-h { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .9rem 1.15rem; border-bottom: 1px solid var(--ln-soft); }
.pc-card-h .t { font-size: .95rem; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: .5rem; }
.pc-card-h .t i { color: var(--brand); }
.pc-card-b { padding: 1.15rem; }
.pc-card-b.p-0 { padding: 0; }

.pc-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.pc-tile { background: #fff; border: 1px solid var(--ln); border-radius: var(--radius); padding: 1rem 1.1rem; }
.pc-tile .lbl { font-size: .77rem; font-weight: 600; color: var(--ink2); display: flex; align-items: center; gap: .35rem; }
.pc-tile .lbl i { color: var(--brand); }
.pc-tile .val { font-size: 1.35rem; font-weight: 700; color: var(--ink); margin-top: .45rem; font-variant-numeric: tabular-nums; }
.pc-tile .sub { font-size: .73rem; color: var(--muted); margin-top: .3rem; }
.pc-tile.entity .val { color: var(--brand); }

.pc-perf-empty { display: flex; gap: 1rem; align-items: flex-start; padding: .4rem; }
.pc-perf-empty > i { font-size: 1.9rem; color: #c3c8d0; flex-shrink: 0; }
.pc-perf-empty strong { color: var(--ink); font-size: .88rem; }
.pc-perf-empty p { color: var(--muted); font-size: .8rem; margin: .3rem 0 0; }

.pc-table { width: 100%; margin: 0; min-width: 680px; }
.pc-table thead th { font-size: .66rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; border: 0; border-bottom: 1px solid var(--ln); padding: .6rem 1.15rem; white-space: nowrap; }
.pc-table tbody td { border: 0; border-bottom: 1px solid var(--ln-soft); padding: .65rem 1.15rem; vertical-align: middle; font-size: .82rem; color: var(--ink2); white-space: nowrap; }
.pc-table tbody tr:last-child td { border-bottom: 0; }
.pc-table tbody tr:hover td { background: #fafbfc; }
.pc-table .c-nm { font-weight: 700; color: var(--ink); display: block; }
.pc-table .c-ext { font-size: .71rem; color: var(--muted); }
.pc-table .btn-icon { border: 0; background: transparent; color: var(--ink2); padding: .2rem .4rem; }

.pc-acc { list-style: none; margin: 0; padding: 0; }
.pc-acc li { padding: .6rem 0; border-bottom: 1px solid var(--ln-soft); }
.pc-acc li:last-child { border-bottom: 0; }
.pc-acc .a-top { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
.pc-acc .a-nm { font-weight: 700; color: var(--ink); font-size: .84rem; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pc-acc .a-meta { font-size: .73rem; color: var(--muted); margin-top: .2rem; padding-left: 38px; display: flex; flex-wrap: wrap; gap: .3rem; }
.pc-acc .a-ext { opacity: .7; }
.pc-acc .a-reconnect { display: inline-flex; align-items: center; gap: .25rem; font-size: .74rem; font-weight: 600; color: var(--ink2); text-decoration: none; margin-top: .4rem; }
.pc-acc .a-reconnect:hover { color: var(--brand); }

.pc-actions { display: flex; flex-direction: column; gap: .5rem; }
.pc-actions .btn { text-align: left; font-size: .84rem; font-weight: 600; border-radius: 10px; padding: .55rem .8rem; display: flex; align-items: center; gap: .55rem; }

.pc-empty { text-align: center; padding: 2.5rem 1.5rem; }
.pc-empty > i { font-size: 1.9rem; color: var(--muted); opacity: .5; margin-bottom: .6rem; display: block; }
.pc-empty p { color: var(--muted); font-size: .86rem; margin: 0 0 1rem; }

@media (max-width: 575px) {
  .pc-tiles { grid-template-columns: repeat(2, 1fr); }
  .pc-tile .val { font-size: 1.2rem; }
}
</style>
