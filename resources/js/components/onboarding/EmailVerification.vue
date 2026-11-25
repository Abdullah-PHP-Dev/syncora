<template>
  <div class="ve-panel">
    <span v-if="justRegistered && !verified" class="ve-badge">✓ {{ t.account_created }}</span>
    <h1 class="ve-title">{{ verified ? t.success : t.title }}</h1>
    <p class="ve-lead">
      <template v-if="verified">{{ t.redirecting }}</template>
      <template v-else>{{ t.sent_to }}<br><strong dir="ltr">{{ masked }}</strong></template>
    </p>

    <form class="ve-form" novalidate @submit.prevent="submit">
      <fieldset :disabled="busy || verified" style="border:0;margin:0;padding:0;min-width:0;">
        <legend class="sr-only">{{ t.enter_code }}</legend>
        <div class="ve-otp" :class="{ 'is-error': state === 'error', 'is-success': verified }" @paste.prevent="onPaste">
          <input
            v-for="(digit, i) in digits"
            :key="i"
            :ref="'d' + i"
            :value="digit"
            :class="{ 'is-filled': digit !== '' }"
            :aria-label="t.digit.replace(':n', i + 1)"
            :autocomplete="i === 0 ? 'one-time-code' : 'off'"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="6"
            @input="onInput(i, $event)"
            @keydown="onKeydown(i, $event)"
            @focus="$event.target.select()"
          >
        </div>
      </fieldset>

      <p class="ve-message" :class="messageClass" role="status" aria-live="polite">{{ message }}</p>

      <button v-if="!verified" type="submit" class="ve-button" :disabled="busy || code.length !== 6">
        <span v-if="busy" class="ve-spinner" aria-hidden="true"></span>
        {{ busy ? t.verifying : t.verify }}
      </button>
    </form>

    <template v-if="!verified">
      <p class="ve-meta">{{ t.expires.replace(':minutes', codeMinutes) }} {{ t.or_link }}</p>

      <p class="ve-resend">
        {{ t.no_code }}
        <button type="button" class="ve-link" :disabled="wait > 0 || resending" @click="resend">
          {{ wait > 0 ? t.resend_in.replace(':seconds', wait) : t.resend }}
        </button>
      </p>

      <div class="ve-footer">
        <span>{{ t.wrong_email }}</span>
        <button type="button" class="ve-link" @click="changing = !changing">{{ t.change_email }}</button>
        <span aria-hidden="true">·</span>
        <form method="POST" :action="urls.logout">
          <input type="hidden" name="_token" :value="csrf">
          <button type="submit" class="ve-link">{{ t.sign_out }}</button>
        </form>
      </div>

      <form v-if="changing" class="ve-change" novalidate @submit.prevent="changeEmail">
        <label class="sr-only" for="ve-new-email">{{ t.new_email }}</label>
        <input id="ve-new-email" ref="newEmail" v-model.trim="newEmail" type="email" autocomplete="email" :placeholder="t.new_email" required>
        <button type="submit" class="ve-button" :disabled="changeBusy || !newEmail">{{ t.send_code }}</button>
        <button type="button" class="ve-button ve-button-light" @click="changing = false">{{ t.cancel }}</button>
      </form>
    </template>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  maskedEmail: String, codeMinutes: Number, resendWait: Number, justRegistered: Boolean, emailFailed: Boolean,
  urls: Object, csrf: String, labels: Object,
});

const vm = getCurrentInstance().proxy;
const t = props.labels;
const digits = ref(['', '', '', '', '', '']);
const busy = ref(false);
const verified = ref(false);
const state = ref('');      // '', 'error', 'success', 'info'
const message = ref(props.emailFailed ? t.email_failed : '');
const wait = ref(props.resendWait || 0);
const resending = ref(false);
const masked = ref(props.maskedEmail);
const changing = ref(false);
const newEmail = ref('');
const changeBusy = ref(false);
let timer = null;
let poll = null;

const code = computed(() => digits.value.join(''));
const messageClass = computed(() => ({ 'is-error': state.value === 'error' || props.emailFailed && !state.value, 'is-success': state.value === 'success', 'is-info': state.value === 'info' }));

function input(i) {
  const el = vm.$refs['d' + i];
  return Array.isArray(el) ? el[0] : el;
}

function focus(i) {
  nextTick(() => input(Math.max(0, Math.min(5, i)))?.focus());
}

/** Spreads digits from position `from` (typing, pasting, or a phone's one-time-code autofill). */
function fill(from, text) {
  const chars = String(text).replace(/\D/g, '').slice(0, 6 - from).split('');
  if (!chars.length) return;
  const next = [...digits.value];
  chars.forEach((c, k) => { next[from + k] = c; });
  digits.value = next;
  clearError();
  focus(from + chars.length);
}

function onInput(i, e) {
  const raw = e.target.value.replace(/\D/g, '');
  if (!raw) {
    e.target.value = digits.value[i]; // letters and symbols are ignored
    return;
  }
  // A whole code in one box (autofill, some mobile pastes) fills every box; otherwise the typed digit.
  if (raw.length >= 6) fill(0, raw.slice(0, 6));
  else fill(i, raw.slice(-1));
  e.target.value = digits.value[i];
}

function onKeydown(i, e) {
  if (e.key === 'Backspace') {
    e.preventDefault();
    const next = [...digits.value];
    if (next[i]) {
      next[i] = '';
    } else if (i > 0) {
      next[i - 1] = '';
      focus(i - 1);
    }
    digits.value = next;
    clearError();
  } else if (e.key === 'ArrowLeft') {
    e.preventDefault(); focus(i - 1);
  } else if (e.key === 'ArrowRight') {
    e.preventDefault(); focus(i + 1);
  } else if (e.key === 'Home') {
    e.preventDefault(); focus(0);
  } else if (e.key === 'End') {
    e.preventDefault(); focus(5);
  } else if (e.key === 'Enter') {
    e.preventDefault(); submit();
  }
}

function onPaste(e) {
  const text = (e.clipboardData || window.clipboardData).getData('text');
  if (/\d/.test(text)) fill(0, text);
}

function clearError() {
  if (state.value === 'error') { state.value = ''; message.value = ''; }
}

async function post(url, body) {
  const response = await fetch(url, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': props.csrf, 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(body || {}),
  });
  let data = {};
  try { data = await response.json(); } catch (e) { /* non-JSON error page */ }
  return { ok: response.ok, status: response.status, data };
}

function errorMessage(data) {
  if (data.message) return data.message;
  const first = data.errors && Object.values(data.errors)[0];
  return (first && first[0]) || t.error_generic;
}

async function submit() {
  if (busy.value || verified.value || code.value.length !== 6) return;
  busy.value = true;
  try {
    const { ok, status, data } = await post(props.urls.verify, { code: code.value });
    if (ok && data.verified) {
      succeed(data);
      return;
    }
    state.value = 'error';
    message.value = status === 429 ? t.error_generic : errorMessage(data);
    if (data.error === 'invalid') {
      digits.value = ['', '', '', '', '', ''];
      focus(0);
    }
  } catch (e) {
    state.value = 'error';
    message.value = t.error_generic;
  } finally {
    busy.value = false;
  }
}

function succeed(data) {
  verified.value = true;
  state.value = 'success';
  message.value = t.success;
  stopTimers();
  setTimeout(() => { window.location.href = data.redirect; }, 1200);
}

async function resend() {
  if (wait.value > 0 || resending.value) return;
  resending.value = true;
  try {
    const { ok, data } = await post(props.urls.resend);
    if (data.verified) { succeed(data); return; }
    state.value = ok ? 'info' : 'error';
    message.value = ok ? `${t.resent} ${t.check_spam}` : errorMessage(data);
    startCountdown(data.wait ?? (ok ? 60 : 0));
    if (ok) { digits.value = ['', '', '', '', '', '']; focus(0); }
  } catch (e) {
    state.value = 'error';
    message.value = t.error_generic;
  } finally {
    resending.value = false;
  }
}

async function changeEmail() {
  if (changeBusy.value || !newEmail.value) return;
  changeBusy.value = true;
  try {
    const { ok, data } = await post(props.urls.change, { email: newEmail.value });
    state.value = ok ? 'info' : 'error';
    message.value = errorMessage(data);
    if (data.maskedEmail) masked.value = data.maskedEmail;
    if (ok) {
      changing.value = false;
      newEmail.value = '';
      digits.value = ['', '', '', '', '', ''];
      startCountdown(data.wait || 60);
      focus(0);
    }
  } catch (e) {
    state.value = 'error';
    message.value = t.error_generic;
  } finally {
    changeBusy.value = false;
  }
}

function startCountdown(seconds) {
  clearInterval(timer);
  wait.value = Math.max(0, seconds | 0);
  if (!wait.value) return;
  timer = setInterval(() => {
    wait.value -= 1;
    if (wait.value <= 0) { wait.value = 0; clearInterval(timer); }
  }, 1000);
}

/** Verified with the link in another tab or on the phone: continue here too. */
async function checkStatus() {
  if (document.hidden || verified.value || busy.value) return;
  try {
    const response = await fetch(props.urls.status, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    const data = response.ok ? await response.json() : null;
    if (data && data.verified) succeed(data);
  } catch (e) { /* offline: try again later */ }
}

function stopTimers() {
  clearInterval(timer);
  clearInterval(poll);
  document.removeEventListener('visibilitychange', checkStatus);
}

// Every box filled: verify straight away (once per complete code).
watch(code, (value, old) => {
  if (value.length === 6 && old.length !== 6 && digits.value.every((d) => d !== '')) submit();
});

watch(changing, (open) => { if (open) nextTick(() => vm.$refs.newEmail?.focus()); });

onMounted(() => {
  focus(0);
  startCountdown(wait.value);
  poll = setInterval(checkStatus, 5000);
  document.addEventListener('visibilitychange', checkStatus);
});

onBeforeUnmount(stopTimers);
</script>
