<template>

  <div>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
      <div>
        <h5 class="mb-1">{{ ticket.subject }}</h5>
        <small class="text-muted">
          {{ ticket.ticket_number }} &middot; opened {{ timeAgo(ticket.created_at) }}
          <template v-if="isAdmin && ticket.user"> by {{ ticket.user.name }}</template>
        </small>
      </div>
      <a :href="indexUrl" class="btn btn-label-secondary btn-sm"><i class="bx bx-arrow-back"></i> Back to tickets</a>
    </div>

    <div v-if="notice" class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ notice }}</div>
    <div v-if="errorNotice" class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ errorNotice }}</div>

    <div class="row">
      <div class="col-md-8">
        <div class="card">
          <div class="card-body">
            <p v-if="!messages.length" class="text-muted mb-0">No messages yet.</p>
            <div v-for="message in messages" :key="message.id"
                 class="tk-msg"
                 :class="message.is_internal_note ? 'tk-msg-note' : (message.is_agent ? 'tk-msg-agent' : 'tk-msg-seller')">
              <div class="tk-msg-meta">
                {{ message.is_internal_note ? 'Internal note' : (message.is_agent ? 'Socialeaz Support' : (message.user ? message.user.name : 'Seller')) }}
                &middot; {{ timeAgo(message.created_at) }}
              </div>
              <div v-html="nl2br(message.body)"></div>
            </div>
          </div>
          <div class="card-footer" v-if="ticket.status !== 'closed'">
            <form @submit.prevent="submitReply">
              <textarea v-model="replyBody" class="form-control mb-2" rows="3" placeholder="Write a reply..." required></textarea>
              <div class="d-flex justify-content-between align-items-center">
                <div class="form-check" v-if="isAdmin">
                  <input type="checkbox" v-model="isInternalNote" class="form-check-input" id="internalNote">
                  <label class="form-check-label small" for="internalNote">Internal note (agent-only, not visible to the seller)</label>
                </div>
                <span v-else></span>
                <button type="submit" class="btn btn-primary btn-sm" :disabled="replying">
                  <span v-if="replying" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="bx bx-send"></i> Send
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card">
          <div class="card-header"><strong>Ticket Details</strong></div>
          <div class="card-body">
            <div class="mb-2">
              <span class="text-muted small">Status</span><br>
              <span class="badge" :class="['resolved','closed'].includes(ticket.status) ? 'bg-label-success' : 'bg-label-warning'">{{ label(ticket.status) }}</span>
            </div>
            <div class="mb-2"><span class="text-muted small">Priority</span><br>{{ ticket.priority.charAt(0).toUpperCase() + ticket.priority.slice(1) }}</div>
            <div class="mb-2"><span class="text-muted small">Category</span><br>{{ ticket.category || '—' }}</div>
            <div class="mb-0"><span class="text-muted small">Assigned to</span><br>{{ ticket.assignee ? ticket.assignee.name : 'Unassigned' }}</div>
          </div>
          <div class="card-footer" v-if="isAdmin">
            <label class="form-label small text-muted mb-1">Update status</label>
            <select v-model="statusDraft" @change="updateStatus" class="form-select form-select-sm mb-2" :disabled="updatingStatus">
              <option v-for="s in statuses" :key="s" :value="s">{{ label(s) }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

  </div>

</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  initialTicket: { type: Object, required: true },
  initialMessages: { type: Array, default: () => [] },
  isAdmin: { type: Boolean, default: false },
  storeMessageUrl: { type: String, required: true },
  statusUpdateUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
});

const ticket = ref(props.initialTicket);
const messages = ref(props.initialMessages);
const statuses = ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'];
const statusDraft = ref(props.initialTicket.status);

const replyBody = ref('');
const isInternalNote = ref(false);
const replying = ref(false);
const updatingStatus = ref(false);
const notice = ref('');
const errorNotice = ref('');

function label(s) {
  return s.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function nl2br(text) {
  const escaped = (text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  return escaped.replace(/\n/g, '<br>');
}

function timeAgo(dateStr) {
  const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
  if (diff < 60) return 'just now';
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  return Math.floor(diff / 86400) + 'd ago';
}

async function submitReply() {
  replying.value = true;
  errorNotice.value = '';

  try {
    const { data } = await window.axios.post(props.storeMessageUrl, {
      body: replyBody.value,
      is_internal_note: props.isAdmin ? isInternalNote.value : false,
    });

    messages.value.push(data.message_row);
    ticket.value = data.ticket;
    statusDraft.value = data.ticket.status;
    notice.value = data.message;
    replyBody.value = '';
    isInternalNote.value = false;
  } catch (error) {
    errorNotice.value = error.response?.data?.message || 'Failed to send reply.';
  } finally {
    replying.value = false;
  }
}

async function updateStatus() {
  updatingStatus.value = true;
  errorNotice.value = '';

  try {
    const { data } = await window.axios.patch(props.statusUpdateUrl, { status: statusDraft.value });
    ticket.value = data.ticket;
    notice.value = data.message;
  } catch (error) {
    errorNotice.value = error.response?.data?.message || 'Failed to update status.';
    statusDraft.value = ticket.value.status;
  } finally {
    updatingStatus.value = false;
  }
}
</script>

<style scoped>
.tk-msg { border-radius: 12px; padding: 14px 16px; margin-bottom: 12px; max-width: 80%; }
.tk-msg-agent { background: #EEF2FF; margin-right: auto; }
.tk-msg-seller { background: #F3F4F6; margin-left: auto; }
.tk-msg-note { background: #FFFBEB; border: 1px dashed #FDE68A; max-width: 100%; }
.tk-msg-meta { font-size: .72rem; color: #6b7280; margin-bottom: 4px; }
</style>
