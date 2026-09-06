<template>

  <div class="cp-panel">

    <div class="cp-header">
      <span class="cp-title"><i class="bx bx-bulb"></i> AI Copilot</span>
    </div>

    <button type="button" class="cp-find-btn" :disabled="loading" @click="findAnswer">
      <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
      <i v-else class="bx bx-search-alt me-1"></i>
      Find Answer from Knowledge Base
    </button>

    <p v-if="error" class="cp-error">{{ error }}</p>

    <div v-if="result" class="cp-result" :class="'cp-result--' + result.status">

      <template v-if="result.status === 'suggested'">
        <div class="cp-confidence">
          <span class="cp-confidence-badge" :class="result.auto_reply_eligible ? 'cp-confidence-badge--high' : 'cp-confidence-badge--medium'">
            {{ result.confidence }}% confidence
          </span>
          <small class="text-muted">matched: "{{ result.matched_faq.question }}"</small>
        </div>
        <div class="cp-reply-text">{{ result.suggested_reply }}</div>
        <div class="cp-actions">
          <button type="button" class="cp-action-btn cp-action-btn--primary" @click="insertReply">
            <i class="bx bx-paper-plane"></i> Insert into reply
          </button>
          <button type="button" class="cp-action-btn" @click="sendFeedback(true)" title="This was helpful"><i class="bx bx-like"></i></button>
          <button type="button" class="cp-action-btn" @click="sendFeedback(false)" title="Not helpful"><i class="bx bx-dislike"></i></button>
        </div>
      </template>

      <template v-else>
        <div class="cp-no-match">
          <i class="bx bx-message-square-x"></i>
          No confident match in your Knowledge Base for this message. Consider adding it as a new FAQ once you reply.
        </div>
      </template>

    </div>

  </div>

</template>

<script setup>
// Mounted fresh on every conversation-thread re-render (the surrounding
// HTML - including this component's old instance - is torn down and
// rebuilt wholesale by the legacy jQuery renderThread() function on every
// conversation switch; see dashboard.blade.php's mountCopilotWidget()).
// findAnswerUrl is a fully-resolved route() URL for THIS conversation
// (built server-side once per mount, not templated - simpler than the
// FaqManager/TicketsList placeholder-token approach since a fresh
// instance is created per conversation anyway).
import { ref } from 'vue';

const props = defineProps({
  findAnswerUrl: { type: String, required: true },
  feedbackUrlTemplate: { type: String, required: true },
});

const loading = ref(false);
const error = ref('');
const result = ref(null);

async function findAnswer() {
  loading.value = true;
  error.value = '';
  result.value = null;

  try {
    const { data } = await window.axios.post(props.findAnswerUrl);
    result.value = data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not find an answer right now.';
  } finally {
    loading.value = false;
  }
}

function insertReply() {
  const textarea = document.querySelector('#replyForm textarea[name="body"]');
  if (textarea && result.value) {
    textarea.value = result.value.suggested_reply;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.focus();
  }
}

async function sendFeedback(helpful) {
  if (!result.value) return;

  try {
    const url = props.feedbackUrlTemplate.replace('COPILOT_MESSAGE_ID', result.value.copilot_message_id);
    await window.axios.post(url, { helpful });
  } catch (e) {
    // Feedback is a nice-to-have signal, not worth surfacing an error
    // toast over - fails silently rather than interrupting the agent.
  }
}
</script>

<style scoped>
.cp-panel {
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 12px;
  padding: 14px 16px;
  margin: 0 0 12px;
}
.cp-header { display: flex; align-items: center; margin-bottom: 10px; }
.cp-title { font-weight: 600; font-size: .88rem; color: #1f2937; display: flex; align-items: center; gap: 6px; }
.cp-find-btn {
  width: 100%;
  border: 1px dashed #7c5cff;
  background: #F5F3FF;
  color: #6d28d9;
  font-weight: 600;
  font-size: .82rem;
  padding: 8px 12px;
  border-radius: 8px;
}
.cp-find-btn:disabled { opacity: .6; }
.cp-error { color: #dc2626; font-size: .78rem; margin: 8px 0 0; }
.cp-result { margin-top: 12px; }
.cp-confidence { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap; }
.cp-confidence-badge { font-size: .72rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; }
.cp-confidence-badge--high { background: #DCFCE7; color: #16A34A; }
.cp-confidence-badge--medium { background: #FEF9C3; color: #A16207; }
.cp-reply-text {
  background: #F9FAFB;
  border: 1px solid #E5E7EB;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: .84rem;
  white-space: pre-wrap;
  margin-bottom: 8px;
}
.cp-actions { display: flex; gap: 6px; }
.cp-action-btn {
  border: 1px solid #E5E7EB;
  background: #fff;
  border-radius: 7px;
  padding: 6px 10px;
  font-size: .78rem;
  font-weight: 600;
  color: #374151;
}
.cp-action-btn--primary { background: #7c5cff; border-color: #7c5cff; color: #fff; flex: 1; }
.cp-no-match { display: flex; align-items: center; gap: 8px; color: #6b7280; font-size: .8rem; }
</style>
