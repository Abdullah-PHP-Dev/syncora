<template>
  <section class="adc-card adc-pad">
    <div class="adc-h"><div><h2>{{ title }}</h2><p>{{ sub }}</p></div><button type="button" class="adc-link" @click="open"><i class="bx bx-plus"></i>{{ t('reports.new') }}</button></div>
    <ul v-if="reports.length" class="adc-reports">
      <li v-for="r in reports" :key="r.id">
        <span class="ic"><i class="bx bx-file"></i></span>
        <span class="txt"><b dir="auto">{{ r.name }}</b><small>{{ cadence(r) }}<template v-if="r.status !== 'active'"> · {{ t('reports.paused') }}</template><template v-if="r.last_error"> · <span class="err" :title="r.last_error">{{ t('reports.failed') }}</span></template></small></span>
        <span class="fmt">CSV</span>
        <button type="button" class="adc-c-act" :disabled="busy === r.id" :title="t('reports.send_now')" :aria-label="t('reports.send_now') + ' · ' + r.name" @click="send(r)"><i class="bx bx-send"></i></button>
        <button type="button" class="adc-c-act" :disabled="busy === r.id" :title="r.status === 'active' ? t('reports.pause') : t('reports.resume')" :aria-label="(r.status === 'active' ? t('reports.pause') : t('reports.resume')) + ' · ' + r.name" @click="toggle(r)"><i class="bx" :class="r.status === 'active' ? 'bx-pause' : 'bx-play'"></i></button>
        <button type="button" class="adc-c-act" :disabled="busy === r.id" :title="t('delete')" :aria-label="t('delete') + ' · ' + r.name" @click="askDelete(r)"><i class="bx bx-trash"></i></button>
      </li>
    </ul>
    <p v-else class="adc-muted-line"><i class="bx bx-envelope"></i>{{ empty }}</p>

    <div v-if="form" class="adc-modal" role="dialog" aria-modal="true" aria-labelledby="adc-report-t" @click.self="form = null">
      <form class="adc-dialog wide" @submit.prevent="save">
        <h3 id="adc-report-t">{{ t('reports.form_title') }}</h3>
        <div class="adc-form">
          <label class="full">{{ t('reports.name') }}<input ref="nameInput" v-model.trim="form.name" type="text" maxlength="120" required dir="auto"></label>
          <label>{{ t('reports.frequency') }}<select v-model="form.frequency"><option v-for="f in ['daily', 'weekly', 'monthly']" :key="f" :value="f">{{ t('reports.frequencies.' + f) }}</option></select></label>
          <label v-if="form.frequency === 'weekly'">{{ t('reports.weekday') }}<select v-model.number="form.weekday"><option v-for="d in 7" :key="d" :value="d - 1">{{ weekdayName(d - 1) }}</option></select></label>
          <label v-if="form.frequency === 'monthly'">{{ t('reports.day_of_month') }}<select v-model.number="form.day_of_month"><option v-for="d in 28" :key="d" :value="d">{{ d }}</option></select></label>
          <label>{{ t('reports.time') }}<input v-model="form.send_time" type="time" required></label>
          <label>{{ t('reports.period') }}<select v-model.number="form.period_days"><option v-for="d in [7, 14, 30, 90]" :key="d" :value="d">{{ t('reports.period_n', { days: d }) }}</option></select></label>
          <div class="full adc-form-note"><i class="bx bx-envelope"></i><span>{{ t('reports.recipient_note', { email: recipientEmail || '—' }) }}</span></div>
          <div class="full adc-form-note"><i class="bx bx-paperclip"></i><span>{{ formatNote }}</span></div>
        </div>
        <p v-if="formError" class="adc-ai-error" role="alert">{{ formError }}</p>
        <div class="adc-dialog-a">
          <button type="button" class="adc-btn ghost" @click="form = null">{{ t('cancel') }}</button>
          <button type="submit" class="adc-btn primary" :disabled="saving"><i class="bx bx-calendar-check"></i>{{ saving ? t('reports.saving') : t('reports.save') }}</button>
        </div>
      </form>
    </div>
  </section>
</template>

<script setup>
import { ref, nextTick } from 'vue';

/*
 * Scheduled email reports of a campaigns page (AdReportController): the
 * "Detailed reports" card, the schedule form, send now, pause/resume and
 * delete. The page opens the form through the exposed open(), passes its
 * confirm dialog as ask(), and shows messages from the "toast" event.
 */
const props = defineProps({
  i18n: { type: Object, required: true },
  reports: { type: Array, required: true }, // owned by the page, updated in place so it survives tab switches
  urls: { type: Object, required: true }, // { reports, report }
  recipientEmail: { type: String, default: '' },
  title: { type: String, required: true },
  sub: { type: String, default: '' },
  empty: { type: String, default: '' },
  defaultName: { type: String, required: true },
  formatNote: { type: String, default: '' },
  ask: { type: Function, required: true },
  csrf: { type: String, default: '' },
});
const emit = defineEmits(['toast']);
const { t } = props.i18n;

const form = ref(null); const formError = ref(''); const saving = ref(false); const busy = ref(null); const nameInput = ref(null);
const headers = () => ({ 'X-CSRF-TOKEN': props.csrf, Accept: 'application/json' });
const errorText = (e, fallback) => (e.response && e.response.data && (e.response.data.message || e.response.data.error)) || fallback;

function reportUrl(id, suffix = '') { return props.urls.report.replace(/\/0$/, '/' + id) + suffix; }
function weekdayName(d) { return new Date(2023, 0, 1 + d).toLocaleDateString(props.i18n.intl, { weekday: 'long' }); } // 1 Jan 2023 was a Sunday
function cadence(r) {
  if (r.frequency === 'weekly') return t('reports.every_weekday', { day: weekdayName(r.weekday), time: r.send_time });
  if (r.frequency === 'monthly') return t('reports.every_month', { day: r.day_of_month, time: r.send_time });
  return t('reports.every_day', { time: r.send_time });
}
function replace(u) { const i = props.reports.findIndex((r) => r.id === u.id); if (i > -1) props.reports.splice(i, 1, u); }

function open() {
  formError.value = '';
  form.value = { name: props.defaultName, frequency: 'weekly', weekday: 0, day_of_month: 1, send_time: '09:00', period_days: 7 };
  nextTick(() => nameInput.value && nameInput.value.focus());
}
function close() { form.value = null; }
async function save() {
  saving.value = true; formError.value = '';
  try {
    const timezone = (window.Intl && Intl.DateTimeFormat().resolvedOptions().timeZone) || 'Asia/Riyadh';
    const { data } = await window.axios.post(props.urls.reports, { ...form.value, timezone }, { headers: headers() });
    props.reports.unshift(data.report); form.value = null; emit('toast', data.message || t('reports.saved'), true);
  } catch (e) {
    const errs = e.response && e.response.data && e.response.data.errors;
    formError.value = errs ? Object.values(errs)[0][0] : errorText(e, t('reports.save_failed'));
  } finally { saving.value = false; }
}
async function send(r) {
  busy.value = r.id;
  try { const { data } = await window.axios.post(reportUrl(r.id, '/send'), {}, { headers: headers() }); replace(data.report); emit('toast', data.message, true); } catch (e) {
    if (e.response && e.response.data && e.response.data.report) replace(e.response.data.report);
    emit('toast', errorText(e, t('reports.send_failed')), false);
  } finally { busy.value = null; }
}
async function toggle(r) {
  busy.value = r.id;
  try { const { data } = await window.axios.patch(reportUrl(r.id), { status: r.status === 'active' ? 'paused' : 'active' }, { headers: headers() }); replace(data.report); } catch (e) { emit('toast', errorText(e, t('reports.save_failed')), false); } finally { busy.value = null; }
}
function askDelete(r) {
  props.ask({ title: t('reports.delete_title'), text: t('reports.delete_text', { name: r.name }), ok: t('delete'), danger: true,
    run: async () => { try { await window.axios.delete(reportUrl(r.id), { headers: headers() }); const i = props.reports.findIndex((x) => x.id === r.id); if (i > -1) props.reports.splice(i, 1); } catch (e) { emit('toast', errorText(e, t('reports.save_failed')), false); } } });
}

defineExpose({ open, close });
</script>
