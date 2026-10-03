<template>

  <div class="tt">

    <a :href="indexUrl" class="tt-back"><i class="bx bx-arrow-back"></i> Support tickets</a>

    <!-- Header -->
    <div class="tt-head">
      <div class="tt-head-icon" :class="'is-' + ticket.status"><i class="bx" :class="statusMeta(ticket.status).icon"></i></div>
      <div class="tt-head-main">
        <div class="tt-head-badges">
          <span class="tt-number">{{ ticket.ticket_number }}</span>
          <span class="tt-status" :class="'is-' + ticket.status">{{ statusMeta(ticket.status).label }}</span>
          <span class="tt-priority" :class="'is-' + ticket.priority">{{ label(ticket.priority) }} priority</span>
        </div>
        <h4>{{ ticket.subject }}</h4>
        <p>
          Opened {{ timeAgo(ticket.created_at) }}<template v-if="isAdmin && ticket.user"> by <strong>{{ ticket.user.name }}</strong></template>
          <span class="tt-sep"></span>
          {{ publicCount }} {{ publicCount === 1 ? 'message' : 'messages' }}
        </p>
      </div>
    </div>

    <transition name="tt-fade">
      <div v-if="notice" class="tt-alert tt-alert-ok"><i class="bx bx-check-circle"></i> {{ notice }}</div>
    </transition>
    <div v-if="errorNotice" class="tt-alert tt-alert-err"><i class="bx bx-error-circle"></i> {{ errorNotice }}</div>

    <div class="tt-grid">

      <!-- Conversation -->
      <div class="tt-card tt-thread">
        <div class="tt-card-head">
          <h6>Conversation</h6>
        </div>

        <div class="tt-messages">
          <div v-if="!messages.length" class="tt-empty">No messages yet.</div>

          <div v-for="message in messages" :key="message.id" class="tt-msg" :class="[kindOf(message), { 'is-mine': isMine(message) }]">
            <span class="tt-avatar" :class="kindOf(message)">
              <i v-if="message.is_internal_note" class="bx bx-lock-alt"></i>
              <i v-else-if="message.is_agent && !isAdmin" class="bx bx-support"></i>
              <template v-else>{{ initials(authorName(message)) }}</template>
            </span>
            <div class="tt-msg-body">
              <div class="tt-msg-meta">
                <strong>{{ authorName(message) }}</strong>
                <span class="tt-role" :class="kindOf(message)">{{ roleLabel(message) }}</span>
                <span class="tt-time" :title="fullDate(message.created_at)">{{ timeAgo(message.created_at) }}</span>
              </div>
              <div class="tt-bubble" v-html="nl2br(message.body)"></div>
            </div>
          </div>
        </div>

        <!-- Composer -->
        <form v-if="ticket.status !== 'closed'" class="tt-composer" @submit.prevent="submitReply">
          <div class="tt-composer-tabs" v-if="isAdmin">
            <button type="button" :class="{ 'is-active': !isInternalNote }" @click="isInternalNote = false"><i class="bx bx-reply"></i> Reply to seller</button>
            <button type="button" :class="{ 'is-active is-note': isInternalNote }" @click="isInternalNote = true"><i class="bx bx-lock-alt"></i> Internal note</button>
          </div>
          <div class="tt-composer-box" :class="{ 'is-note': isInternalNote }">
            <textarea v-model="replyBody" rows="4" required @keydown.ctrl.enter.prevent="submitReply" @keydown.meta.enter.prevent="submitReply"
                      :placeholder="isInternalNote ? 'Write a note for the team - the seller will not see this…' : 'Write a reply…'"></textarea>
            <div class="tt-composer-foot">
              <span class="tt-hint">
                <template v-if="isInternalNote"><i class="bx bx-hide"></i> Only visible to the support team</template>
                <template v-else><kbd>Ctrl</kbd> + <kbd>Enter</kbd> to send</template>
              </span>
              <button type="submit" class="tt-btn tt-btn-brand" :class="{ 'is-note': isInternalNote }" :disabled="replying || !replyBody.trim()">
                <span v-if="replying" class="spinner-border spinner-border-sm"></span>
                <i v-else class="bx" :class="isInternalNote ? 'bx-note' : 'bx-send'"></i>
                {{ isInternalNote ? 'Add note' : 'Send reply' }}
              </button>
            </div>
          </div>
        </form>
        <div v-else class="tt-closed">
          <i class="bx bx-lock-alt"></i>
          <div>
            <strong>This ticket is closed</strong>
            <span>{{ isAdmin ? 'Change the status to reopen it.' : 'Open a new ticket if you need more help.' }}</span>
          </div>
        </div>
      </div>

      <!-- Details -->
      <aside class="tt-side">
        <div class="tt-card">
          <div class="tt-card-head"><h6>Ticket details</h6></div>
          <dl class="tt-details">
            <div><dt>Status</dt><dd><span class="tt-status" :class="'is-' + ticket.status">{{ statusMeta(ticket.status).label }}</span></dd></div>
            <div><dt>Priority</dt><dd><span class="tt-priority" :class="'is-' + ticket.priority">{{ label(ticket.priority) }}</span></dd></div>
            <div><dt>Category</dt><dd>{{ ticket.category || '—' }}</dd></div>
            <div v-if="isAdmin && ticket.user"><dt>Customer</dt><dd><span class="tt-person"><span class="tt-initials">{{ initials(ticket.user.name) }}</span>{{ ticket.user.name }}</span></dd></div>
            <div><dt>Assigned to</dt><dd>
              <span v-if="ticket.assignee" class="tt-person"><span class="tt-initials is-staff">{{ initials(ticket.assignee.name) }}</span>{{ ticket.assignee.name }}</span>
              <span v-else class="tt-muted">Unassigned</span>
            </dd></div>
            <div><dt>Created</dt><dd :title="fullDate(ticket.created_at)">{{ shortDate(ticket.created_at) }}</dd></div>
            <div><dt>Last activity</dt><dd :title="fullDate(ticket.last_activity_at)">{{ timeAgo(ticket.last_activity_at || ticket.created_at) }}</dd></div>
          </dl>
        </div>

        <div class="tt-card" v-if="isAdmin">
          <div class="tt-card-head"><h6>Manage ticket</h6></div>
          <div class="tt-manage">
            <label class="tt-field-label">Status</label>
            <div class="tt-status-grid">
              <button v-for="s in statuses" :key="s" type="button" class="tt-status-opt" :class="['is-' + s, { 'is-active': ticket.status === s }]"
                      :disabled="updating" @click="updateTicket({ status: s })">
                <i class="bx" :class="statusMeta(s).icon"></i> {{ statusMeta(s).label }}
              </button>
            </div>
            <label class="tt-field-label" for="tt-priority">Priority</label>
            <select id="tt-priority" class="tt-select" :value="ticket.priority" :disabled="updating" @change="updateTicket({ priority: $event.target.value })">
              <option v-for="p in priorities" :key="p" :value="p">{{ label(p) }}</option>
            </select>
            <div v-if="updating" class="tt-saving"><span class="spinner-border spinner-border-sm"></span> Saving…</div>
          </div>
        </div>
      </aside>
    </div>

  </div>

</template>

<script setup>
import { ref, computed } from 'vue';

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
const priorities = ['low', 'medium', 'high', 'urgent'];

const STATUS = {
  open: { label: 'Open', icon: 'bx-envelope-open' },
  in_progress: { label: 'In progress', icon: 'bx-loader-circle' },
  waiting_customer: { label: 'Awaiting reply', icon: 'bx-time-five' },
  resolved: { label: 'Resolved', icon: 'bx-check-circle' },
  closed: { label: 'Closed', icon: 'bx-lock-alt' },
};

const replyBody = ref('');
const isInternalNote = ref(false);
const replying = ref(false);
const updating = ref(false);
const notice = ref('');
const errorNotice = ref('');

const publicCount = computed(() => messages.value.filter(m => !m.is_internal_note).length);

let noticeTimer = null;
function flash(text) {
  notice.value = text;
  clearTimeout(noticeTimer);
  noticeTimer = setTimeout(() => { notice.value = ''; }, 3500);
}

// The store/status endpoints return the ticket without its user (and the
// reply endpoint without its assignee) - keep the loaded relations.
function mergeTicket(fresh) {
  ticket.value = {
    ...ticket.value,
    ...fresh,
    user: fresh.user ?? ticket.value.user,
    assignee: 'assignee' in fresh ? fresh.assignee : ticket.value.assignee,
  };
}

function statusMeta(s) {
  return STATUS[s] || { label: label(s || ''), icon: 'bx-purchase-tag' };
}

function label(s) {
  return String(s || '').replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase());
}

function initials(name) {
  return String(name || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

// Like a messenger: the viewer's own side on the right. For staff that's
// the support team (replies + internal notes), for a seller their own
// messages.
function isMine(message) {
  return props.isAdmin ? !!(message.is_agent || message.is_internal_note) : !message.is_agent;
}

function kindOf(message) {
  if (message.is_internal_note) return 'is-note';
  return message.is_agent ? 'is-agent' : 'is-seller';
}

function authorName(message) {
  if (message.is_agent && !props.isAdmin) return 'Socialeaz Support';
  return message.user ? message.user.name : (message.is_agent ? 'Socialeaz Support' : 'Seller');
}

function roleLabel(message) {
  if (message.is_internal_note) return 'Internal note';
  return message.is_agent ? 'Support' : 'Customer';
}

function nl2br(text) {
  const escaped = (text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  return escaped.replace(/\n/g, '<br>');
}

function fullDate(dateStr) {
  return dateStr ? new Date(dateStr).toLocaleString(document.documentElement.lang || undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '';
}

function shortDate(dateStr) {
  return dateStr ? new Date(dateStr).toLocaleDateString(document.documentElement.lang || undefined, { dateStyle: 'medium' }) : '—';
}

function timeAgo(dateStr) {
  if (!dateStr) return '—';
  const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
  if (diff < 60) return 'just now';
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  if (diff < 86400 * 7) return Math.floor(diff / 86400) + 'd ago';
  return shortDate(dateStr);
}

async function submitReply() {
  if (replying.value || !replyBody.value.trim()) return;
  replying.value = true;
  errorNotice.value = '';

  try {
    const { data } = await window.axios.post(props.storeMessageUrl, {
      body: replyBody.value,
      is_internal_note: props.isAdmin ? isInternalNote.value : false,
    });

    messages.value.push(data.message_row);
    mergeTicket(data.ticket);
    flash(data.message);
    replyBody.value = '';
    isInternalNote.value = false;
  } catch (error) {
    errorNotice.value = error.response?.data?.message || 'Failed to send reply.';
  } finally {
    replying.value = false;
  }
}

// Status is always required by the endpoint; priority is optional.
async function updateTicket(changes) {
  if (updating.value) return;
  if (changes.status === ticket.value.status && !changes.priority) return;
  updating.value = true;
  errorNotice.value = '';

  try {
    const { data } = await window.axios.patch(props.statusUpdateUrl, { status: ticket.value.status, ...changes });
    mergeTicket(data.ticket);
    flash(changes.priority ? 'Priority updated.' : data.message);
  } catch (error) {
    errorNotice.value = error.response?.data?.message || 'Failed to update the ticket.';
  } finally {
    updating.value = false;
  }
}
</script>

<style scoped>
.tt { --ln: #e7e9f0; --ln-soft: #f1f3f7; --ink: #161b2b; --ink2: #545d70; --muted: #8a92a3; --brand: #6d4aff; --brand-2: #8f6bff; --brand-soft: #f2eeff; --note: #b54708; --note-bg: #fffaeb; --note-ln: #fedf89; --danger: #d92d20; color: var(--ink2); }

.tt-back { display: inline-flex; align-items: center; gap: 4px; font-size: .8rem; font-weight: 600; color: var(--muted); text-decoration: none; margin-bottom: 12px; }
.tt-back:hover { color: var(--brand); }
[dir="rtl"] .tt-back i { transform: scaleX(-1); }

/* Header */
.tt-head { display: flex; gap: 16px; align-items: flex-start; background: #fff; border: 1px solid var(--ln); border-radius: 16px; padding: 20px 22px; margin-bottom: 18px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
.tt-head-icon { width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; font-size: 23px; flex-shrink: 0; background: var(--ln-soft); color: var(--ink2); }
.tt-head-icon.is-open { background: #eff8ff; color: #1570ef; }
.tt-head-icon.is-in_progress { background: #fffaeb; color: #b54708; }
.tt-head-icon.is-waiting_customer { background: #fdf2fa; color: #c11574; }
.tt-head-icon.is-resolved { background: #ecfdf3; color: #067647; }
.tt-head-main { min-width: 0; }
.tt-head-badges { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 6px; }
.tt-number { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .74rem; color: var(--muted); }
.tt-head h4 { color: var(--ink); font-weight: 700; font-size: 1.3rem; letter-spacing: -.01em; margin: 0 0 4px; word-break: break-word; }
.tt-head p { margin: 0; font-size: .82rem; color: var(--muted); display: flex; align-items: center; flex-wrap: wrap; gap: 4px 10px; }
.tt-head p strong { color: var(--ink); font-weight: 600; }
.tt-sep { width: 4px; height: 4px; border-radius: 50%; background: #cfd3dc; }

.tt-status { display: inline-flex; align-items: center; height: 24px; padding: 0 10px; border-radius: 999px; font-size: .74rem; font-weight: 600; border: 1px solid transparent; }
.tt-status.is-open { background: #eff8ff; color: #175cd3; border-color: #b2ddff; }
.tt-status.is-in_progress { background: #fffaeb; color: #b54708; border-color: #fedf89; }
.tt-status.is-waiting_customer { background: #fdf2fa; color: #c11574; border-color: #fcceee; }
.tt-status.is-resolved { background: #ecfdf3; color: #067647; border-color: #abefc6; }
.tt-status.is-closed { background: var(--ln-soft); color: var(--ink2); border-color: var(--ln); }
.tt-priority { display: inline-flex; align-items: center; gap: 6px; font-size: .78rem; font-weight: 600; }
.tt-priority::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
.tt-priority.is-urgent { color: #d92d20; }
.tt-priority.is-high { color: #e04f16; }
.tt-priority.is-medium { color: #b54708; }
.tt-priority.is-low { color: #079455; }

.tt-alert { display: flex; align-items: center; gap: 8px; border-radius: 12px; padding: 10px 14px; font-size: .85rem; font-weight: 500; margin-bottom: 16px; border: 1px solid; }
.tt-alert i { font-size: 1.15rem; }
.tt-alert-ok { background: #ecfdf3; border-color: #abefc6; color: #067647; }
.tt-alert-err { background: #fef3f2; border-color: #fecdca; color: var(--danger); }
.tt-fade-enter-active, .tt-fade-leave-active { transition: opacity .25s; }
.tt-fade-enter, .tt-fade-leave-to { opacity: 0; }

/* Layout */
.tt-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
.tt-card { background: #fff; border: 1px solid var(--ln); border-radius: 16px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
.tt-card-head { padding: 14px 20px; border-bottom: 1px solid var(--ln-soft); }
.tt-card-head h6 { margin: 0; color: var(--ink); font-weight: 700; font-size: .9rem; }

/* Messages */
.tt-messages { padding: 22px 20px; display: flex; flex-direction: column; gap: 16px; background: #fbfbfd; }
.tt-empty { text-align: center; color: var(--muted); font-size: .85rem; padding: 24px 0; }
.tt-msg { display: flex; gap: 10px; align-items: flex-end; max-width: 78%; align-self: flex-start; }
.tt-msg.is-mine { flex-direction: row-reverse; align-self: flex-end; }
.tt-avatar { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; font-size: .72rem; font-weight: 700; position: relative; z-index: 1; box-shadow: 0 0 0 3px #fff; }
.tt-avatar i { font-size: 1.05rem; }
.tt-avatar.is-seller { background: #eef0f5; color: var(--ink2); }
.tt-avatar.is-agent { background: linear-gradient(135deg, var(--brand), var(--brand-2)); color: #fff; }
.tt-avatar.is-note { background: var(--note-bg); color: var(--note); border: 1px solid var(--note-ln); }
.tt-avatar { margin-bottom: 2px; }
.tt-msg-body { min-width: 0; display: flex; flex-direction: column; align-items: flex-start; }
.tt-msg.is-mine .tt-msg-body { align-items: flex-end; }
.tt-msg-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 5px; font-size: .78rem; padding-inline: 4px; }
.tt-msg.is-mine .tt-msg-meta { flex-direction: row-reverse; }
.tt-msg-meta strong { color: var(--ink); font-weight: 700; }
.tt-role { font-size: .66rem; font-weight: 700; padding: 1px 7px; border-radius: 6px; text-transform: uppercase; letter-spacing: .04em; }
.tt-role.is-seller { background: var(--ln-soft); color: var(--ink2); }
.tt-role.is-agent { background: var(--brand-soft); color: #4f2fd6; }
.tt-role.is-note { background: var(--note-bg); color: var(--note); }
.tt-time { color: var(--muted); font-size: .74rem; }
.tt-bubble { width: fit-content; max-width: 100%; padding: 11px 15px; border-radius: 16px; border-end-start-radius: 5px; font-size: .88rem; line-height: 1.6; color: var(--ink); word-break: break-word; border: 1px solid var(--ln); background: #fff; box-shadow: 0 1px 2px rgba(16, 24, 40, .05); }
.tt-msg.is-mine .tt-bubble { border-end-start-radius: 16px; border-end-end-radius: 5px; background: linear-gradient(135deg, var(--brand), var(--brand-2)); border-color: transparent; color: #fff; box-shadow: 0 4px 12px rgba(109, 74, 255, .2); }
.tt-msg.is-note .tt-bubble, .tt-msg.is-mine.is-note .tt-bubble { background: var(--note-bg); border: 1px dashed var(--note-ln); color: var(--ink); box-shadow: none; }

/* Composer */
.tt-composer { border-top: 1px solid var(--ln); padding: 16px 20px 20px; background: #fbfbfd; }
.tt-composer-tabs { display: inline-flex; gap: 2px; padding: 3px; background: var(--ln-soft); border-radius: 10px; margin-bottom: 10px; }
.tt-composer-tabs button { border: none; background: transparent; height: 30px; padding: 0 12px; border-radius: 8px; font-size: .78rem; font-weight: 600; color: var(--ink2); display: inline-flex; align-items: center; gap: 5px; }
.tt-composer-tabs button.is-active { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
.tt-composer-tabs button.is-note { color: var(--note); }
.tt-composer-box { border: 1px solid var(--ln); border-radius: 12px; background: #fff; transition: border-color .15s, box-shadow .15s; }
.tt-composer-box:focus-within { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
.tt-composer-box.is-note { background: #fffdf5; border-color: var(--note-ln); }
.tt-composer-box.is-note:focus-within { border-color: #f79009; box-shadow: 0 0 0 3px rgba(247, 144, 9, .14); }
.tt-composer-box textarea { display: block; width: 100%; border: none; background: transparent; resize: vertical; min-height: 96px; padding: 12px 14px; font-size: .88rem; line-height: 1.55; color: var(--ink); outline: none; }
.tt-composer-foot { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 10px 10px 14px; }
.tt-hint { font-size: .74rem; color: var(--muted); display: inline-flex; align-items: center; gap: 4px; }
.tt-hint kbd { font-size: .66rem; padding: 1px 5px; border-radius: 4px; background: var(--ln-soft); color: var(--ink2); border: 1px solid var(--ln); box-shadow: none; }
.tt-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; padding: 0 16px; border-radius: 10px; font-size: .84rem; font-weight: 600; border: none; cursor: pointer; transition: box-shadow .15s, transform .15s, opacity .15s; }
.tt-btn i { font-size: 1.05rem; }
.tt-btn-brand { background: linear-gradient(135deg, var(--brand), var(--brand-2)); color: #fff; box-shadow: 0 4px 12px rgba(109, 74, 255, .25); }
.tt-btn-brand.is-note { background: #f79009; box-shadow: 0 4px 12px rgba(247, 144, 9, .25); }
.tt-btn-brand:hover:not(:disabled) { transform: translateY(-1px); }
.tt-btn-brand:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }
.tt-closed { display: flex; gap: 12px; align-items: center; border-top: 1px solid var(--ln); padding: 16px 20px; background: #fbfbfd; font-size: .82rem; color: var(--muted); }
.tt-closed > i { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; background: var(--ln-soft); color: var(--ink2); font-size: 1.1rem; flex-shrink: 0; }
.tt-closed strong { display: block; color: var(--ink); font-size: .86rem; }

/* Side */
.tt-side { display: flex; flex-direction: column; gap: 14px; position: sticky; top: 90px; }
.tt-details { margin: 0; padding: 6px 20px 12px; }
.tt-details > div { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 9px 0; border-bottom: 1px solid var(--ln-soft); font-size: .82rem; }
.tt-details > div:last-child { border-bottom: none; }
.tt-details dt { font-weight: 500; color: var(--muted); }
.tt-details dd { margin: 0; color: var(--ink); font-weight: 600; text-align: end; min-width: 0; overflow: hidden; text-overflow: ellipsis; }
.tt-person { display: inline-flex; align-items: center; gap: 7px; }
.tt-initials { width: 24px; height: 24px; border-radius: 50%; display: inline-grid; place-items: center; font-size: .6rem; font-weight: 700; background: #eef0f5; color: var(--ink2); flex-shrink: 0; }
.tt-initials.is-staff { background: var(--brand-soft); color: #4f2fd6; }
.tt-muted { color: var(--muted); font-weight: 500; }

.tt-manage { padding: 14px 20px 18px; }
.tt-field-label { display: block; font-size: .74rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin: 0 0 8px; }
.tt-field-label + .tt-select { margin-bottom: 0; }
.tt-status-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 16px; }
.tt-status-opt { display: flex; align-items: center; gap: 6px; height: 34px; padding: 0 10px; border: 1px solid var(--ln); border-radius: 9px; background: #fff; font-size: .76rem; font-weight: 600; color: var(--ink2); text-align: start; transition: border-color .15s, background .15s; }
.tt-status-opt i { font-size: .95rem; }
.tt-status-opt:hover:not(:disabled) { border-color: #cfd4de; color: var(--ink); }
.tt-status-opt:last-child { grid-column: span 2; }
.tt-status-opt.is-active.is-open { background: #eff8ff; border-color: #84caff; color: #175cd3; }
.tt-status-opt.is-active.is-in_progress { background: #fffaeb; border-color: #fec84b; color: #b54708; }
.tt-status-opt.is-active.is-waiting_customer { background: #fdf2fa; border-color: #faa7e0; color: #c11574; }
.tt-status-opt.is-active.is-resolved { background: #ecfdf3; border-color: #75e0a7; color: #067647; }
.tt-status-opt.is-active.is-closed { background: var(--ln-soft); border-color: #cfd4de; color: var(--ink); }
.tt-status-opt:disabled { cursor: wait; }
.tt-select { width: 100%; height: 38px; border: 1px solid var(--ln); border-radius: 9px; padding-block: 0; line-height: 36px; padding-inline: 12px 30px; font-size: .84rem; font-weight: 500; color: var(--ink); background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%238a92a3' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 12px center / 10px 6px; appearance: none; cursor: pointer; }
[dir="rtl"] .tt-select { background-position: left 12px center; }
.tt-select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); outline: none; }
.tt-saving { display: flex; align-items: center; gap: 6px; margin-top: 10px; font-size: .76rem; color: var(--brand); }

@media (max-width: 991.98px) {
  .tt-grid { grid-template-columns: 1fr; }
  .tt-side { position: static; }
}
@media (max-width: 575.98px) {
  .tt-msg { max-width: 92%; }
  .tt-head { padding: 16px; }
  .tt-messages { padding: 16px; }
  .tt-composer { padding: 14px 16px 16px; }
  .tt-hint { display: none; }
  .tt-composer-foot { justify-content: flex-end; }
}
</style>
