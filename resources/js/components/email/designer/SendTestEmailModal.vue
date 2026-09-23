<template>
  <div v-if="visible" class="stm-overlay" @click.self="close">
    <div class="stm-card">
      <div class="stm-header">
        <strong>Send Test Email</strong>
        <button type="button" class="stm-close" @click="close"><i class="bx bx-x"></i></button>
      </div>

      <div class="stm-body">
        <template v-if="step === 'input'">
          <label class="stm-label">Recipient email</label>
          <input v-model="recipient" type="email" class="dash-input w-100" placeholder="you@example.com" @keyup.enter="goToConfirm" />
          <p v-if="inputError" class="stm-error">{{ inputError }}</p>
        </template>

        <template v-else-if="step === 'confirm'">
          <div class="stm-review-row"><span>Recipient</span><span>{{ recipient }}</span></div>
          <div class="stm-review-row"><span>Subject</span><span>{{ subject }}</span></div>
          <div class="stm-preview-wrap">
            <iframe class="stm-preview-frame" :srcdoc="html"></iframe>
          </div>
          <p v-if="sendError" class="stm-error">{{ sendError }}</p>
        </template>

        <template v-else-if="step === 'success'">
          <div class="stm-success"><i class="bx bx-check-circle"></i> Test email sent to {{ recipient }}.</div>
        </template>
      </div>

      <div class="stm-footer">
        <template v-if="step === 'input'">
          <button type="button" class="dash-btn dash-btn-ghost" @click="close">Cancel</button>
          <button type="button" class="dash-btn dash-btn-primary" @click="goToConfirm">Continue</button>
        </template>
        <template v-else-if="step === 'confirm'">
          <button type="button" class="dash-btn dash-btn-ghost" @click="step = 'input'">Back</button>
          <button type="button" class="dash-btn dash-btn-primary" :disabled="sending" @click="send">{{ sending ? 'Sending...' : 'Confirm & Send' }}</button>
        </template>
        <template v-else-if="step === 'success'">
          <button type="button" class="dash-btn dash-btn-primary" @click="close">Done</button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  sendTestUrl: { type: String, required: true },
  subject: { type: String, default: '' },
  html: { type: String, default: '' },
});

const visible = ref(false);
const step = ref('input');
const recipient = ref('');
const inputError = ref('');
const sendError = ref('');
const sending = ref(false);

function open() {
  visible.value = true;
  step.value = 'input';
  inputError.value = '';
  sendError.value = '';
}

function close() {
  visible.value = false;
}

function goToConfirm() {
  const isValidEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipient.value);
  if (!isValidEmail) {
    inputError.value = 'Enter a valid email address.';
    return;
  }
  inputError.value = '';
  step.value = 'confirm';
}

async function send() {
  sending.value = true;
  sendError.value = '';
  try {
    const { data } = await window.axios.post(props.sendTestUrl, {
      recipient_email: recipient.value,
      subject: props.subject,
      body: props.html,
    });
    if (data.success) {
      step.value = 'success';
    } else {
      sendError.value = data.message || 'Failed to send test email.';
    }
  } catch (e) {
    sendError.value = e.response?.data?.message || 'Failed to send test email - please try again.';
  } finally {
    sending.value = false;
  }
}

defineExpose({ open });
</script>

<style scoped>
.stm-overlay { position: fixed; inset: 0; background: rgba(20,20,40,.45); display: flex; align-items: center; justify-content: center; z-index: 1080; }
.stm-card { background: var(--dash-card, #fff); border-radius: 1rem; width: 480px; max-width: 92vw; max-height: 88vh; display: flex; flex-direction: column; overflow: hidden; }
.stm-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--dash-border); }
.stm-close { background: none; border: none; font-size: 1.25rem; color: var(--dash-muted); }
.stm-body { padding: 1.25rem; overflow-y: auto; }
.stm-label { display: block; font-size: .8125rem; margin-bottom: .4rem; color: var(--dash-text); }
.stm-error { color: var(--dash-danger, #e11d48); font-size: .78rem; margin-top: .5rem; }
.stm-review-row { display: flex; justify-content: space-between; padding: .5rem 0; border-bottom: 1px solid var(--dash-border); font-size: .85rem; }
.stm-review-row span:first-child { color: var(--dash-muted); }
.stm-review-row span:last-child { font-weight: 600; color: var(--dash-heading); text-align: right; }
.stm-preview-wrap { margin-top: .75rem; border: 1px solid var(--dash-border); border-radius: .6rem; overflow: hidden; }
.stm-preview-frame { width: 100%; height: 320px; border: 0; display: block; }
.stm-success { display: flex; align-items: center; gap: .5rem; color: var(--dash-success, #16a34a); font-weight: 600; }
.stm-footer { display: flex; justify-content: flex-end; gap: .5rem; padding: 1rem 1.25rem; border-top: 1px solid var(--dash-border); }
</style>
