<template>

  <div>

    <div class="tk-hero">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
          <h4><i class="bx bx-support"></i> {{ isAdmin ? 'Support Tickets (All Sellers)' : 'My Support Tickets' }}</h4>
          <p>{{ isAdmin ? "Every ticket raised across Socialeaz's sellers, newest activity first." : "Track your Socialeaz support requests, or search the Help Center first." }}</p>
        </div>
        <div class="d-flex gap-2">
          <a v-if="!isAdmin" :href="helpCenterUrl" class="btn btn-light fw-semibold"><i class="bx bx-help-circle"></i> Help Center</a>
          <a :href="createUrl" class="btn btn-light fw-semibold"><i class="bx bx-plus"></i> New Ticket</a>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Status</label>
            <select v-model="filters.status" @change="fetchTickets(1)" class="form-select">
              <option value="">All statuses</option>
              <option v-for="s in statuses" :key="s" :value="s">{{ label(s) }}</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Priority</label>
            <select v-model="filters.priority" @change="fetchTickets(1)" class="form-select">
              <option value="">All priorities</option>
              <option v-for="p in priorities" :key="p" :value="p">{{ p.charAt(0).toUpperCase() + p.slice(1) }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Ticket</th>
              <th v-if="isAdmin">From</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Assignee</th>
              <th>Last Activity</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td :colspan="isAdmin ? 6 : 5" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span> Loading...</td>
            </tr>
            <tr v-else-if="!tickets.length">
              <td :colspan="isAdmin ? 6 : 5" class="text-center text-muted py-4">No tickets yet.</td>
            </tr>
            <tr v-for="ticket in tickets" :key="ticket.id" v-else style="cursor:pointer" @click="goTo(ticket)">
              <td>
                <div class="fw-semibold">{{ ticket.subject }}</div>
                <small class="text-muted">{{ ticket.ticket_number }}</small>
              </td>
              <td v-if="isAdmin">{{ ticket.user ? ticket.user.name : '—' }}</td>
              <td><span :class="'tk-priority-' + ticket.priority" class="fw-semibold">{{ ticket.priority.charAt(0).toUpperCase() + ticket.priority.slice(1) }}</span></td>
              <td><span class="badge" :class="['resolved','closed'].includes(ticket.status) ? 'bg-label-success' : 'bg-label-warning'">{{ label(ticket.status) }}</span></td>
              <td>{{ ticket.assignee ? ticket.assignee.name : 'Unassigned' }}</td>
              <td>{{ timeAgo(ticket.last_activity_at || ticket.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center" v-if="meta.last_page > 1">
        <small class="text-muted">Page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)</small>
        <div class="btn-group btn-group-sm">
          <button type="button" class="btn btn-outline-secondary" :disabled="meta.current_page <= 1" @click="fetchTickets(meta.current_page - 1)">Prev</button>
          <button type="button" class="btn btn-outline-secondary" :disabled="meta.current_page >= meta.last_page" @click="fetchTickets(meta.current_page + 1)">Next</button>
        </div>
      </div>
    </div>

  </div>

</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  initialTickets: { type: Object, required: true },
  isAdmin: { type: Boolean, default: false },
  fetchUrl: { type: String, required: true },
  createUrl: { type: String, required: true },
  helpCenterUrl: { type: String, default: '#' },
  // route()-generated URL with the literal token TICKET_ID standing in for
  // the real id - see FaqManager.vue's updateUrlTemplate for why this is
  // a server-built template rather than a hand-concatenated base + id
  // (LaravelLocalization's locale prefix, which a plain url() base drops).
  showUrlTemplate: { type: String, required: true },
});

const tickets = ref(props.initialTickets.data || []);
const meta = ref({
  current_page: props.initialTickets.current_page || 1,
  last_page: props.initialTickets.last_page || 1,
  total: props.initialTickets.total || 0,
});
const loading = ref(false);
const filters = ref({ status: '', priority: '' });
const statuses = ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'];
const priorities = ['urgent', 'high', 'medium', 'low'];

function label(s) {
  return s.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
}

async function fetchTickets(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get(props.fetchUrl, {
      params: { status: filters.value.status || undefined, priority: filters.value.priority || undefined, page },
    });
    tickets.value = data.tickets.data;
    meta.value = { current_page: data.tickets.current_page, last_page: data.tickets.last_page, total: data.tickets.total };
  } finally {
    loading.value = false;
  }
}

function goTo(ticket) {
  window.location.href = props.showUrlTemplate.replace('TICKET_ID', ticket.id);
}

function timeAgo(dateStr) {
  const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
  if (diff < 60) return 'just now';
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  return Math.floor(diff / 86400) + 'd ago';
}
</script>

<style scoped>
.tk-hero {
  background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 55%, #8b5cf6 100%);
  border-radius: 20px;
  padding: 32px 36px;
  color: #fff;
  margin-bottom: 24px;
}
.tk-hero h4 { color: #fff; font-weight: 700; margin-bottom: 6px; }
.tk-hero p { color: rgba(255,255,255,.85); margin-bottom: 0; max-width: 620px; }
.tk-priority-urgent { color: #dc2626; }
.tk-priority-high { color: #ea580c; }
.tk-priority-medium { color: #ca8a04; }
.tk-priority-low { color: #16a34a; }
</style>
