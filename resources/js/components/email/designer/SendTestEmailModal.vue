<template>
  <div v-if="visible" class="stm-overlay" @click.self="close">
    <div class="stm-card">
      <div class="stm-header">
        <strong>{{ t('send_test.title') }}</strong>
        <button type="button" class="stm-close" :title="t('send_test.close')" @click="close"><i class="bx bx-x"></i></button>
      </div>

      <div class="stm-body">
        <template v-if="step === 'input'">
          <label class="stm-label">{{ t('send_test.recipient_label') }}</label>
          <input v-model="recipient" type="email" class="dash-input w-100" placeholder="you@example.com" dir="ltr" @keyup.enter="goToConfirm" />
          <p v-if="inputError" class="stm-error">{{ inputError }}</p>
        </template>

        <template v-else-if="step === 'confirm'">
          <div class="stm-review-row"><span>{{ t('send_test.recipient') }}</span><span dir="ltr">{{ recipient }}</span></div>
          <div class="stm-review-row"><span>{{ t('send_test.subject') }}</span><span dir="auto">{{ subject }}</span></div>
          <div class="stm-preview-wrap">
            <iframe class="stm-preview-frame" :srcdoc="html"></iframe>
          </div>
          <p v-if="sendError" class="stm-error">{{ sendError }}</p>
        </template>

        <template v-else-if="step === 'success'">
          <div class="stm-success"><i class="bx bx-check-circle"></i> {{ t('send_test.success', { email: recipient }) }}</div>
        </template>
      </div>

      <div class="stm-footer">
        <template v-if="step === 'input'">
          <button type="button" class="dash-btn dash-btn-ghost" @click="close">{{ t('send_test.cancel') }}</button>
          <button type="button" class="dash-btn dash-btn-primary" @click="goToConfirm">{{ t('send_test.continue') }}</button>
        </template>
        <template v-else-if="step === 'confirm'">
          <button type="button" class="dash-btn dash-btn-ghost" @click="step = 'input'">{{ t('send_test.back') }}</button>
          <button type="button" class="dash-btn dash-btn-primary" :disabled="sending" @click="send">{{ sending ? t('send_test.sending') : t('send_test.confirm_send') }}</button>
        </template>
        <template v-else-if="step === 'success'">
          <button type="button" class="dash-btn dash-btn-primary" @click="close">{{ t('send_test.done') }}</button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useEmailI18n } from '../lib/i18n';

const props = defineProps({
  sendTestUrl: { type: String, required: true },
  subject: { type: String, default: '' },
  html: { type: String, default: '' },
});

const { t } = useEmailI18n();

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
    inputError.value = t('send_test.invalid_email');
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
      sendError.value = data.message || t('send_test.failed');
    }
  } catch (e) {
    sendError.value = e.response?.data?.message || t('send_test.retry');
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
.stm-review-row span:last-child { font-weight: 600; color: var(--dash-heading); text-align: end; }
.stm-preview-wrap { margin-top: .75rem; border: 1px solid var(--dash-border); border-radius: .6rem; overflow: hidden; }
.stm-preview-frame { width: 100%; height: 320px; border: 0; display: block; }
.stm-success { display: flex; align-items: center; gap: .5rem; color: var(--dash-success, #16a34a); font-weight: 600; }
.stm-footer { display: flex; justify-content: flex-end; gap: .5rem; padding: 1rem 1.25rem; border-top: 1px solid var(--dash-border); }
</style>
