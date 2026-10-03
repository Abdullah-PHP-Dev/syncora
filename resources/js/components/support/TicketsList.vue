<template>

  <div class="tk">

    <!-- Header -->
    <div class="tk-head">
      <div class="tk-head-title">
        <div class="tk-head-icon"><i class="bx bx-support"></i></div>
        <div>
          <div class="tk-eyebrow">Support</div>
          <h4>{{ isAdmin ? 'Support Tickets' : 'My Support Tickets' }}</h4>
          <p>{{ isAdmin ? "Every ticket raised across Socialeaz's sellers, newest activity first." : 'Track your support requests, or search the Help Center for a quick answer.' }}</p>
        </div>
      </div>
      <div class="tk-head-actions">
        <a v-if="!isAdmin" :href="helpCenterUrl" class="tk-btn tk-btn-outline"><i class="bx bx-help-circle"></i> Help Center</a>
        <a :href="createUrl" class="tk-btn tk-btn-brand"><i class="bx bx-plus"></i> New Ticket</a>
      </div>
    </div>

    <!-- Summary -->
    <div class="tk-stats">
      <button v-for="card in statCards" :key="card.key" type="button" class="tk-stat" :class="{ 'is-active': filters.status === card.status }" @click="setStatus(card.status)">
        <span class="tk-stat-icon" :style="{ background: card.soft, color: card.color }"><i class="bx" :class="card.icon"></i></span>
        <span>
          <span class="tk-stat-label">{{ card.label }}</span>
          <span class="tk-stat-value">{{ card.value }}</span>
        </span>
      </button>
    </div>

    <div class="tk-card">
      <!-- Toolbar -->
      <div class="tk-toolbar">
        <div class="tk-tabs" role="tablist">
          <button v-for="tab in statusTabs" :key="tab.value" type="button" class="tk-tab" :class="{ 'is-active': filters.status === tab.value }" @click="setStatus(tab.value)">
            {{ tab.label }} <span v-if="tab.count !== null">{{ tab.count }}</span>
          </button>
        </div>
        <div class="tk-filters">
          <label class="tk-search">
            <i class="bx bx-search"></i>
            <input type="search" v-model="filters.search" @input="onSearch" placeholder="Search by subject" aria-label="Search tickets">
          </label>
          <select v-model="filters.priority" @change="fetchTickets(1)" class="tk-select" aria-label="Priority">
            <option value="">All priorities</option>
            <option v-for="p in priorities" :key="p" :value="p">{{ label(p) }}</option>
          </select>
          <select v-if="isAdmin" v-model="filters.assignment" @change="fetchTickets(1)" class="tk-select" aria-label="Assignment">
            <option value="">Anyone</option>
            <option value="mine">Assigned to me</option>
            <option value="unassigned">Unassigned</option>
          </select>
          <button v-if="hasFilters" type="button" class="tk-clear" @click="clearFilters"><i class="bx bx-x"></i> Clear</button>
        </div>
      </div>

      <!-- List -->
      <div class="tk-table-wrap" :class="{ 'is-loading': loading }">
        <table class="tk-table">
          <thead>
            <tr>
              <th>Ticket</th>
              <th v-if="isAdmin">From</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Assignee</th>
              <th class="text-end">Last activity</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ticket in tickets" :key="ticket.id" @click="goTo(ticket)" @keydown.enter="goTo(ticket)" tabindex="0">
              <td>
                <div class="tk-subject">
                  <span class="tk-subject-icon" :class="'is-' + ticket.status"><i class="bx" :class="statusMeta(ticket.status).icon"></i></span>
                  <span class="min-w-0">
                    <span class="tk-subject-text">{{ ticket.subject }}</span>
                    <span class="tk-subject-sub">
                      <span class="tk-number">{{ ticket.ticket_number }}</span>
                      <span v-if="ticket.category" class="tk-category">{{ label(ticket.category) }}</span>
                    </span>
                  </span>
                </div>
              </td>
              <td v-if="isAdmin">
                <span class="tk-person" v-if="ticket.user">
                  <span class="tk-initials">{{ initials(ticket.user.name) }}</span>{{ ticket.user.name }}
                </span>
                <span v-else class="tk-muted">—</span>
              </td>
              <td><span class="tk-priority" :class="'is-' + ticket.priority">{{ label(ticket.priority) }}</span></td>
              <td><span class="tk-status" :class="'is-' + ticket.status">{{ statusMeta(ticket.status).label }}</span></td>
              <td>
                <span class="tk-person" v-if="ticket.assignee">
                  <span class="tk-initials is-staff">{{ initials(ticket.assignee.name) }}</span>{{ ticket.assignee.name }}
                </span>
                <span v-else class="tk-muted">Unassigned</span>
              </td>
              <td class="text-end">
                <span class="tk-time" :title="fullDate(ticket.last_activity_at || ticket.created_at)">{{ timeAgo(ticket.last_activity_at || ticket.created_at) }}</span>
                <i class="bx bx-chevron-right tk-chevron"></i>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="!tickets.length && !loading" class="tk-empty">
          <div class="tk-empty-icon"><i class="bx" :class="hasFilters ? 'bx-search-alt' : 'bx-support'"></i></div>
          <h6>{{ hasFilters ? 'No tickets match these filters' : (isAdmin ? 'No tickets yet' : 'You have no support tickets yet') }}</h6>
          <p>{{ hasFilters ? 'Try a different status, priority or search term.' : (isAdmin ? 'New tickets from sellers will show up here.' : 'Need help? Open a ticket and our team will get back to you.') }}</p>
          <button v-if="hasFilters" type="button" class="tk-btn tk-btn-outline" @click="clearFilters"><i class="bx bx-reset"></i> Clear filters</button>
          <a v-else-if="!isAdmin" :href="createUrl" class="tk-btn tk-btn-brand"><i class="bx bx-plus"></i> New Ticket</a>
        </div>

        <div v-if="loading" class="tk-loading"><span class="spinner-border spinner-border-sm"></span></div>
      </div>

      <!-- Pagination -->
      <div class="tk-foot" v-if="meta.total">
        <span class="tk-muted">Showing <strong>{{ rangeStart }}–{{ rangeEnd }}</strong> of <strong>{{ meta.total }}</strong></span>
        <div class="tk-pager" v-if="meta.last_page > 1">
          <button type="button" class="tk-page" :disabled="meta.current_page <= 1" @click="fetchTickets(meta.current_page - 1)" aria-label="Previous page"><i class="bx bx-chevron-left"></i></button>
          <button v-for="p in pageNumbers" :key="'p' + p" type="button" class="tk-page" :class="{ 'is-active': p === meta.current_page, 'is-gap': p === '…' }" :disabled="p === '…'" @click="p !== '…' && fetchTickets(p)">{{ p }}</button>
          <button type="button" class="tk-page" :disabled="meta.current_page >= meta.last_page" @click="fetchTickets(meta.current_page + 1)" aria-label="Next page"><i class="bx bx-chevron-right"></i></button>
        </div>
      </div>
    </div>

  </div>

</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  initialTickets: { type: Object, required: true },
  isAdmin: { type: Boolean, default: false },
  // { status: count } for this user's scope, unfiltered (TicketController@index).
  statusCounts: { type: [Object, Array], default: () => ({}) },
  fetchUrl: { type: String, required: true },
  createUrl: { type: String, required: true },
  helpCenterUrl: { type: String, default: '#' },
  // route()-generated URL with the literal token TICKET_ID standing in for
  // the real id - see FaqManager.vue's updateUrlTemplate for why this is
  // a server-built template rather than a hand-concatenated base + id
  // (LaravelLocalization's locale prefix, which a plain url() base drops).
  showUrlTemplate: { type: String, required: true },
  // Seeded from the query string when this page is reached via the admin
  // navbar's ticket search or a dashboard "Unassigned"/"Assigned to me"
  // shortcut (see TeamDashboardService/team/ticket-summary.blade.php) -
  // both are plain GETs to this same route, not AJAX.
  initialSearch: { type: String, default: '' },
  initialAssignment: { type: String, default: '' },
});

const tickets = ref(props.initialTickets.data || []);
const meta = ref({
  current_page: props.initialTickets.current_page || 1,
  last_page: props.initialTickets.last_page || 1,
  total: props.initialTickets.total || 0,
  per_page: props.initialTickets.per_page || 15,
});
const loading = ref(false);
const filters = ref({ status: '', priority: '', search: props.initialSearch, assignment: props.initialAssignment });
const priorities = ['urgent', 'high', 'medium', 'low'];

const STATUS = {
  open: { label: 'Open', icon: 'bx-envelope-open' },
  in_progress: { label: 'In progress', icon: 'bx-loader-circle' },
  waiting_customer: { label: 'Awaiting reply', icon: 'bx-time-five' },
  resolved: { label: 'Resolved', icon: 'bx-check-circle' },
  closed: { label: 'Closed', icon: 'bx-lock-alt' },
};

const counts = computed(() => (Array.isArray(props.statusCounts) ? {} : props.statusCounts) || {});
const count = (s) => Number(counts.value[s] || 0);
const totalCount = computed(() => Object.values(counts.value).reduce((a, b) => a + Number(b), 0));

const statCards = computed(() => [
  { key: 'all', status: '', label: 'All tickets', value: totalCount.value, icon: 'bx-collection', color: '#6d4aff', soft: '#f2eeff' },
  { key: 'open', status: 'open', label: 'Open', value: count('open'), icon: 'bx-envelope-open', color: '#1570ef', soft: '#eff8ff' },
  { key: 'progress', status: 'in_progress', label: 'In progress', value: count('in_progress'), icon: 'bx-loader-circle', color: '#b54708', soft: '#fffaeb' },
  { key: 'waiting', status: 'waiting_customer', label: 'Awaiting reply', value: count('waiting_customer'), icon: 'bx-time-five', color: '#c11574', soft: '#fdf2fa' },
  { key: 'resolved', status: 'resolved', label: 'Resolved', value: count('resolved'), icon: 'bx-check-circle', color: '#067647', soft: '#ecfdf3' },
]);

const statusTabs = computed(() => [
  { value: '', label: 'All', count: totalCount.value },
  ...Object.entries(STATUS).map(([value, s]) => ({ value, label: s.label, count: count(value) })),
]);

const hasFilters = computed(() => !!(filters.value.status || filters.value.priority || filters.value.search || filters.value.assignment));

const rangeStart = computed(() => (meta.value.current_page - 1) * meta.value.per_page + 1);
const rangeEnd = computed(() => Math.min(meta.value.current_page * meta.value.per_page, meta.value.total));

// 1 … 4 5 6 … 12
const pageNumbers = computed(() => {
  const last = meta.value.last_page, cur = meta.value.current_page, out = [];
  for (let p = 1; p <= last; p++) {
    if (p === 1 || p === last || Math.abs(p - cur) <= 1) out.push(p);
    else if (out[out.length - 1] !== '…') out.push('…');
  }
  return out;
});

function statusMeta(s) {
  return STATUS[s] || { label: label(s || ''), icon: 'bx-purchase-tag' };
}

function label(s) {
  return String(s).replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase());
}

function initials(name) {
  return String(name || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

function setStatus(status) {
  filters.value.status = status;
  fetchTickets(1);
}

function clearFilters() {
  filters.value = { status: '', priority: '', search: '', assignment: '' };
  fetchTickets(1);
}

let searchTimer = null;
function onSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => fetchTickets(1), 350);
}

async function fetchTickets(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get(props.fetchUrl, {
      params: {
        status: filters.value.status || undefined,
        priority: filters.value.priority || undefined,
        search: filters.value.search || undefined,
        assignment: filters.value.assignment || undefined,
        page,
      },
    });
    tickets.value = data.tickets.data;
    meta.value = { current_page: data.tickets.current_page, last_page: data.tickets.last_page, total: data.tickets.total, per_page: data.tickets.per_page };
  } finally {
    loading.value = false;
  }
}

function goTo(ticket) {
  window.location.href = props.showUrlTemplate.replace('TICKET_ID', ticket.id);
}

function fullDate(dateStr) {
  return dateStr ? new Date(dateStr).toLocaleString(document.documentElement.lang || undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '';
}

function timeAgo(dateStr) {
  const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
  if (diff < 60) return 'just now';
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  if (diff < 86400 * 7) return Math.floor(diff / 86400) + 'd ago';
  return new Date(dateStr).toLocaleDateString(document.documentElement.lang || undefined, { month: 'short', day: 'numeric' });
}
</script>

<style scoped>
.tk { --ln: #e7e9f0; --ln-soft: #f1f3f7; --ink: #161b2b; --ink2: #545d70; --muted: #8a92a3; --brand: #6d4aff; --brand-2: #8f6bff; --brand-soft: #f2eeff; color: var(--ink2); }

/* Header */
.tk-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; }
.tk-head-title { display: flex; gap: 14px; align-items: flex-start; min-width: 0; }
.tk-head-icon { width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; font-size: 24px; color: #fff; background: linear-gradient(135deg, var(--brand), var(--brand-2)); box-shadow: 0 8px 18px rgba(109, 74, 255, .28); flex-shrink: 0; }
.tk-eyebrow { font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--brand); margin-bottom: 2px; }
.tk-head h4 { color: var(--ink); font-weight: 700; font-size: 1.35rem; letter-spacing: -.01em; margin: 0 0 4px; }
.tk-head p { margin: 0; font-size: .87rem; max-width: 560px; }
.tk-head-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.tk-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 40px; padding: 0 16px; border-radius: 10px; font-size: .85rem; font-weight: 600; border: 1px solid transparent; text-decoration: none; white-space: nowrap; cursor: pointer; transition: background .15s, border-color .15s, box-shadow .15s, transform .15s; }
.tk-btn i { font-size: 1.1rem; }
.tk-btn-brand { background: linear-gradient(135deg, var(--brand), var(--brand-2)); color: #fff; box-shadow: 0 4px 12px rgba(109, 74, 255, .25); }
.tk-btn-brand:hover { color: #fff; box-shadow: 0 6px 16px rgba(109, 74, 255, .35); transform: translateY(-1px); }
.tk-btn-outline { background: #fff; border-color: var(--ln); color: var(--ink); }
.tk-btn-outline:hover { background: #fafbfd; border-color: #cfd4de; color: var(--ink); }

/* Summary cards */
.tk-stats { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
.tk-stat { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: #fff; border: 1px solid var(--ln); border-radius: 14px; text-align: start; cursor: pointer; transition: border-color .15s, box-shadow .15s, transform .15s; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
.tk-stat:hover { border-color: #d5d9e2; box-shadow: 0 8px 20px rgba(16, 24, 40, .06); transform: translateY(-1px); }
.tk-stat.is-active { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
.tk-stat-icon { width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center; font-size: 20px; flex-shrink: 0; }
.tk-stat-label { display: block; font-size: .74rem; font-weight: 600; color: var(--muted); }
.tk-stat-value { display: block; font-size: 1.35rem; font-weight: 700; color: var(--ink); line-height: 1.2; }

/* Card + toolbar */
.tk-card { background: #fff; border: 1px solid var(--ln); border-radius: 16px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 12px 32px rgba(16, 24, 40, .04); overflow: hidden; }
.tk-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 16px; border-bottom: 1px solid var(--ln); }
.tk-tabs { display: inline-flex; gap: 2px; padding: 3px; background: var(--ln-soft); border-radius: 11px; flex-wrap: wrap; }
.tk-tab { border: none; background: transparent; height: 32px; padding: 0 12px; border-radius: 8px; font-size: .79rem; font-weight: 600; color: var(--ink2); white-space: nowrap; transition: background .15s, color .15s; }
.tk-tab span { margin-inline-start: 3px; font-weight: 500; color: var(--muted); }
.tk-tab:hover { color: var(--ink); }
.tk-tab.is-active { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
.tk-filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.tk-search { position: relative; margin: 0; }
.tk-search i { position: absolute; inset-inline-start: 11px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1.05rem; pointer-events: none; }
.tk-search input { height: 36px; width: 220px; border: 1px solid var(--ln); border-radius: 9px; padding-inline: 34px 10px; font-size: .83rem; color: var(--ink); background: #fff; outline: none; transition: border-color .15s, box-shadow .15s; }
.tk-search input:focus, .tk-select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); outline: none; }
.tk-select { height: 36px; border: 1px solid var(--ln); border-radius: 9px; padding-block: 0; line-height: 34px; padding-inline: 10px 28px; font-size: .82rem; font-weight: 500; color: var(--ink); background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%238a92a3' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 10px center / 10px 6px; appearance: none; cursor: pointer; }
[dir="rtl"] .tk-select { background-position: left 10px center; }
.tk-clear { height: 36px; border: none; background: transparent; color: var(--muted); font-size: .8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 2px; padding: 0 6px; border-radius: 8px; }
.tk-clear:hover { color: var(--ink); background: var(--ln-soft); }

/* Table */
.tk-table-wrap { position: relative; overflow-x: auto; min-height: 120px; transition: opacity .15s; }
.tk-table-wrap.is-loading { opacity: .55; }
.tk-loading { position: absolute; inset: 0; display: grid; place-items: center; color: var(--brand); }
.tk-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.tk-table th { text-align: start; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); padding: 11px 16px; background: #fbfbfd; border-bottom: 1px solid var(--ln); white-space: nowrap; }
.tk-table td { padding: 14px 16px; border-bottom: 1px solid var(--ln-soft); vertical-align: middle; color: var(--ink2); white-space: nowrap; }
.tk-table th.text-end, .tk-table td.text-end { text-align: end; }
.tk-table tbody tr { cursor: pointer; transition: background .12s; outline: none; }
.tk-table tbody tr:hover, .tk-table tbody tr:focus-visible { background: #fafaff; }
.tk-table tbody tr:last-child td { border-bottom: none; }

.tk-subject { display: flex; align-items: center; gap: 12px; min-width: 260px; max-width: 460px; }
.tk-subject-icon { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 18px; flex-shrink: 0; background: var(--ln-soft); color: var(--ink2); }
.tk-subject-icon.is-open { background: #eff8ff; color: #1570ef; }
.tk-subject-icon.is-in_progress { background: #fffaeb; color: #b54708; }
.tk-subject-icon.is-waiting_customer { background: #fdf2fa; color: #c11574; }
.tk-subject-icon.is-resolved { background: #ecfdf3; color: #067647; }
.tk-subject-icon.is-closed { background: var(--ln-soft); color: var(--muted); }
.min-w-0 { min-width: 0; }
.tk-subject-text { display: block; color: var(--ink); font-weight: 600; overflow: hidden; text-overflow: ellipsis; }
.tk-table tbody tr:hover .tk-subject-text { color: var(--brand); }
.tk-subject-sub { display: flex; align-items: center; gap: 8px; margin-top: 2px; font-size: .74rem; color: var(--muted); }
.tk-number { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .72rem; }
.tk-category { padding: 1px 7px; border-radius: 6px; background: var(--ln-soft); color: var(--ink2); font-weight: 600; font-size: .68rem; }

.tk-priority { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-size: .8rem; color: var(--ink2); }
.tk-priority::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
.tk-priority.is-urgent { color: #d92d20; }
.tk-priority.is-high { color: #e04f16; }
.tk-priority.is-medium { color: #b54708; }
.tk-priority.is-low { color: #079455; }

.tk-status { display: inline-flex; align-items: center; height: 24px; padding: 0 10px; border-radius: 999px; font-size: .74rem; font-weight: 600; border: 1px solid transparent; }
.tk-status.is-open { background: #eff8ff; color: #175cd3; border-color: #b2ddff; }
.tk-status.is-in_progress { background: #fffaeb; color: #b54708; border-color: #fedf89; }
.tk-status.is-waiting_customer { background: #fdf2fa; color: #c11574; border-color: #fcceee; }
.tk-status.is-resolved { background: #ecfdf3; color: #067647; border-color: #abefc6; }
.tk-status.is-closed { background: var(--ln-soft); color: var(--ink2); border-color: var(--ln); }

.tk-person { display: inline-flex; align-items: center; gap: 8px; color: var(--ink); font-weight: 500; }
.tk-initials { width: 26px; height: 26px; border-radius: 50%; display: inline-grid; place-items: center; font-size: .64rem; font-weight: 700; background: #eef0f5; color: var(--ink2); flex-shrink: 0; }
.tk-initials.is-staff { background: var(--brand-soft); color: #4f2fd6; }
.tk-muted { color: var(--muted); }
.tk-time { color: var(--ink2); font-size: .8rem; }
.tk-chevron { color: #c4c9d4; font-size: 1.2rem; vertical-align: middle; margin-inline-start: 6px; transition: transform .15s, color .15s; }
[dir="rtl"] .tk-chevron { transform: scaleX(-1); }
.tk-table tbody tr:hover .tk-chevron { color: var(--brand); transform: translateX(2px); }

/* Empty */
.tk-empty { text-align: center; padding: 48px 24px 52px; }
.tk-empty-icon { width: 64px; height: 64px; margin: 0 auto 14px; border-radius: 18px; display: grid; place-items: center; font-size: 30px; color: var(--brand); background: var(--brand-soft); }
.tk-empty h6 { color: var(--ink); font-weight: 700; margin-bottom: 4px; }
.tk-empty p { font-size: .85rem; color: var(--muted); margin-bottom: 16px; }

/* Footer */
.tk-foot { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 16px; border-top: 1px solid var(--ln); font-size: .8rem; }
.tk-foot strong { color: var(--ink); }
.tk-pager { display: flex; gap: 4px; }
.tk-page { min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid var(--ln); background: #fff; border-radius: 8px; font-size: .8rem; font-weight: 600; color: var(--ink2); display: inline-grid; place-items: center; transition: background .15s, border-color .15s; }
.tk-page:hover:not(:disabled) { background: #fafbfd; border-color: #cfd4de; color: var(--ink); }
.tk-page.is-active { background: var(--brand); border-color: var(--brand); color: #fff; }
.tk-page.is-gap { border-color: transparent; background: transparent; }
.tk-page:disabled:not(.is-gap) { opacity: .45; }
[dir="rtl"] .tk-page i { transform: scaleX(-1); }

@media (max-width: 1199.98px) {
  .tk-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 767.98px) {
  .tk-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .tk-toolbar { flex-direction: column; align-items: stretch; }
  .tk-tabs { overflow-x: auto; flex-wrap: nowrap; }
  .tk-filters, .tk-search, .tk-search input { width: 100%; }
  .tk-select { flex: 1; }
}
</style>
