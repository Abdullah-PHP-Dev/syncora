<template>
  <div class="pee">

    <!-- Top bar: language, status, actions -->
    <div class="account-card pee-bar">
      <div class="pee-bar-left">
        <span class="pee-module">{{ config.module }}</span>
        <span class="pe-mono">{{ config.key }}</span>
        <div class="pee-langs" role="group" :aria-label="labels.editor.language">
          <a v-for="loc in config.locales" :key="loc" :href="editUrl(loc)" class="pee-lang" :class="{ active: loc === config.locale }">{{ labels.languages[loc] }}</a>
        </div>
        <span class="pe-badge" :class="custom ? 'custom' : 'default'">{{ custom ? labels.badges.custom + ' v' + version : labels.badges.default }}</span>
        <span v-if="hasDraft" class="pe-badge draft">{{ labels.badges.draft }}</span>
      </div>
      <div class="pee-bar-right">
        <label v-if="!config.locked" class="pee-switch">
          <input type="checkbox" :checked="active" :disabled="busy" @change="toggle($event.target.checked)">
          <span>{{ active ? labels.editor.status_active : labels.editor.status_disabled }}</span>
        </label>
        <span v-else class="pe-badge lock" :title="labels.editor.locked_hint">{{ labels.badges.locked }}</span>
      </div>
    </div>

    <p v-if="config.mandatory && !config.locked" class="pee-hint">{{ labels.editor.mandatory_hint }}</p>
    <div v-if="message" class="alert" :class="messageError ? 'alert-danger' : 'alert-success'" role="status">{{ message }}</div>

    <div class="pee-grid">

      <!-- Editor -->
      <div class="pee-main">
        <section class="account-card account-section" :dir="dir">
          <div class="account-field pee-field">
            <label>{{ labels.editor.subject }}</label>
            <input class="form-control" v-model="content.subject" maxlength="200" @focus="focus(content, 'subject', $event)" dir="auto">
          </div>
          <div class="account-field pee-field">
            <label>{{ labels.editor.preheader }}</label>
            <input class="form-control" v-model="content.preheader" maxlength="200" @focus="focus(content, 'preheader', $event)" dir="auto">
          </div>
          <div class="account-field pee-field">
            <label>{{ labels.editor.heading }}</label>
            <input class="form-control" v-model="content.heading" maxlength="200" @focus="focus(content, 'heading', $event)" dir="auto">
          </div>
        </section>

        <section class="account-card account-section" :dir="dir">
          <div class="pee-section-head">
            <h2>{{ labels.editor.blocks }}</h2>
            <select class="form-select form-select-sm pee-add" v-model="newBlock" @change="addBlock">
              <option value="">+ {{ labels.actions.add_block }}</option>
              <option v-for="type in config.blocks" :key="type" :value="type">{{ labels.blocks[type] }}</option>
            </select>
          </div>
          <p class="pee-hint">{{ labels.editor.formatting }}</p>

          <div v-for="(block, i) in content.blocks" :key="i" class="pee-block">
            <div class="pee-block-head">
              <span>{{ labels.blocks[block.type] }}</span>
              <span class="pee-block-tools">
                <button type="button" :disabled="i === 0" :title="labels.actions.up" :aria-label="labels.actions.up" @click="move(i, -1)"><i class="bx bx-up-arrow-alt"></i></button>
                <button type="button" :disabled="i === content.blocks.length - 1" :title="labels.actions.down" :aria-label="labels.actions.down" @click="move(i, 1)"><i class="bx bx-down-arrow-alt"></i></button>
                <button type="button" :title="labels.actions.remove" :aria-label="labels.actions.remove" @click="content.blocks.splice(i, 1)"><i class="bx bx-trash"></i></button>
              </span>
            </div>

            <textarea v-if="block.type === 'text'" class="form-control" rows="4" v-model="block.text" @focus="focus(block, 'text', $event)" dir="auto"></textarea>
            <input v-else-if="block.type === 'heading'" class="form-control" v-model="block.text" @focus="focus(block, 'text', $event)" dir="auto">

            <div v-else-if="block.type === 'button'" class="pee-two">
              <input class="form-control" v-model="block.label" :placeholder="labels.blocks.label" @focus="focus(block, 'label', $event)" dir="auto">
              <input class="form-control" v-model="block.url" :placeholder="labels.blocks.url" @focus="focus(block, 'url', $event)" dir="ltr">
            </div>

            <div v-else-if="block.type === 'card'">
              <div v-for="(row, r) in block.rows" :key="r" class="pee-two pee-row">
                <input class="form-control" v-model="row.label" :placeholder="labels.blocks.label" @focus="focus(row, 'label', $event)" dir="auto">
                <input class="form-control" v-model="row.value" :placeholder="labels.blocks.value" @focus="focus(row, 'value', $event)" dir="auto">
                <button type="button" class="pee-x" :aria-label="labels.actions.remove" @click="block.rows.splice(r, 1)"><i class="bx bx-x"></i></button>
              </div>
              <button type="button" class="btn btn-light btn-sm" @click="block.rows.push({ label: '', value: '' })">+ {{ labels.actions.add_row }}</button>
            </div>

            <p v-else-if="block.type === 'details'" class="pee-hint">{{ labels.editor.details_hint }}</p>

            <input v-else-if="block.type === 'code'" class="form-control pee-code" v-model="block.text" @focus="focus(block, 'text', $event)" dir="ltr">

            <div v-else-if="block.type === 'image'" class="pee-three">
              <input class="form-control" v-model="block.url" placeholder="https://…" @focus="focus(block, 'url', $event)" dir="ltr">
              <input class="form-control" v-model="block.alt" :placeholder="labels.blocks.alt" dir="auto">
              <input class="form-control" type="number" min="40" max="600" v-model.number="block.width" :placeholder="labels.blocks.width">
            </div>

            <template v-else-if="block.type === 'html'">
              <textarea class="form-control pee-code" rows="6" v-model="block.html" @focus="focus(block, 'html', $event)" dir="ltr"></textarea>
              <p class="pee-hint">{{ labels.editor.html_hint }}</p>
            </template>
          </div>
        </section>

        <section class="account-card account-section" :dir="dir">
          <h2 class="pee-h2">{{ labels.editor.cta }}</h2>
          <div class="pee-two">
            <input class="form-control" v-model="content.cta_text" :placeholder="labels.editor.cta_text" @focus="focus(content, 'cta_text', $event)" dir="auto">
            <input class="form-control" v-model="content.cta_url" :placeholder="labels.editor.cta_url" @focus="focus(content, 'cta_url', $event)" dir="ltr">
          </div>
          <p class="pee-hint">{{ labels.editor.cta_hint }}</p>
          <h2 class="pee-h2">{{ labels.editor.secondary }}</h2>
          <div class="pee-two">
            <input class="form-control" v-model="content.secondary_text" :placeholder="labels.editor.secondary_text" @focus="focus(content, 'secondary_text', $event)" dir="auto">
            <input class="form-control" v-model="content.secondary_url" :placeholder="labels.editor.secondary_url" @focus="focus(content, 'secondary_url', $event)" dir="ltr">
          </div>
          <h2 class="pee-h2">{{ labels.editor.footer }}</h2>
          <textarea class="form-control" rows="2" v-model="content.footer" @focus="focus(content, 'footer', $event)" dir="auto"></textarea>
          <p class="pee-hint">{{ labels.editor.group_note }}</p>
        </section>

        <div class="account-card pee-actions">
          <span class="pee-state">{{ dirty ? labels.editor.unsaved : labels.editor.saved }}</span>
          <div class="pee-buttons">
            <button type="button" class="btn btn-light" :disabled="busy" @click="reset">{{ labels.actions.reset }}</button>
            <button v-for="loc in otherLocales" :key="loc" type="button" class="btn btn-light" :disabled="busy" @click="duplicate(loc)">{{ labels.actions.duplicate.replace(':language', labels.languages[loc]) }}</button>
            <button type="button" class="btn btn-outline-primary" :disabled="busy" @click="save('draft')">{{ labels.actions.save_draft }}</button>
            <button type="button" class="btn btn-primary" :disabled="busy" @click="save('publish')">{{ labels.actions.publish }}</button>
          </div>
        </div>
      </div>

      <!-- Variables, preview, test, history -->
      <aside class="pee-side">
        <section class="account-card account-section">
          <h2 class="pee-h2">{{ labels.editor.variables }}</h2>
          <p class="pee-hint">{{ labels.editor.variables_hint }}</p>
          <div v-for="group in ['event', 'general']" :key="group">
            <h3 class="pee-h3" v-if="vars(group).length">{{ labels.editor[group] }}</h3>
            <button v-for="v in vars(group)" :key="v.name" type="button" class="pee-var" :title="v.description + ' — ' + v.example" @click="insert(v.token)">
              <code>{{ v.token }}</code><span>{{ v.description }}</span><small dir="auto">{{ v.example }}</small>
            </button>
          </div>
        </section>

        <section class="account-card account-section pee-preview">
          <div class="pee-section-head">
            <h2>{{ labels.editor.preview }}</h2>
            <div class="pee-langs">
              <button type="button" class="pee-lang" :class="{ active: device === 'desktop' }" @click="device = 'desktop'"><i class="bx bx-desktop"></i> {{ labels.actions.desktop }}</button>
              <button type="button" class="pee-lang" :class="{ active: device === 'mobile' }" @click="device = 'mobile'"><i class="bx bx-mobile"></i> {{ labels.actions.mobile }}</button>
            </div>
          </div>
          <div class="pee-envelope">
            <div><b>{{ labels.editor.from }}:</b> {{ preview.from_name }} &lt;{{ preview.from_email }}&gt;</div>
            <div dir="auto"><b>{{ labels.editor.subject }}:</b> {{ preview.subject }}</div>
            <div dir="auto" class="pee-muted">{{ preview.preheader }}</div>
          </div>
          <div class="pee-frame-wrap">
            <iframe class="pee-frame" :class="device" sandbox="" :srcdoc="preview.html" title="Email preview"></iframe>
          </div>
          <form class="pee-test" @submit.prevent="sendTest">
            <input class="form-control" type="email" required v-model="testEmail" :placeholder="labels.editor.test_to" dir="ltr">
            <button class="btn btn-outline-primary" type="submit" :disabled="busy">{{ labels.actions.send_test }}</button>
          </form>
        </section>

        <section class="account-card account-section">
          <div class="pee-section-head">
            <h2>{{ labels.actions.versions }}</h2>
            <button type="button" class="btn btn-light btn-sm" @click="loadVersions"><i class="bx bx-refresh"></i></button>
          </div>
          <p v-if="versions && !versions.length" class="pee-hint">{{ labels.editor.no_versions }}</p>
          <ul v-if="versions" class="pee-versions">
            <li v-for="v in versions" :key="v.id">
              <span><b>v{{ v.version }}</b> · {{ labels.log[v.action] || v.action }} <small>{{ v.author }} · {{ formatDate(v.at) }}</small></span>
              <button v-if="v.version !== version" type="button" class="btn btn-light btn-sm" :disabled="busy" @click="restore(v.id)">{{ labels.actions.restore }}</button>
            </li>
          </ul>
        </section>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import axios from 'axios';

// Administrator editor for one platform email in one language (Admin\PlatformEmailTemplateController).
// Content is structured (fields + blocks); the server validates, sanitises and renders it.
const props = defineProps({
  config: { type: Object, required: true },
  labels: { type: Object, required: true },
});

const clone = (value) => JSON.parse(JSON.stringify(value));
const normalise = (c) => ({
  subject: '', preheader: '', heading: '', cta_text: '', cta_url: '', secondary_text: '', secondary_url: '', footer: '',
  ...c, blocks: (c.blocks || []).map((b) => (b.type === 'card' ? { ...b, rows: b.rows || [] } : { ...b })),
});

const content = reactive(normalise(clone(props.config.content)));
const dir = props.config.locale === 'ar' ? 'rtl' : 'ltr';
const active = ref(props.config.active);
const custom = ref(props.config.custom);
const hasDraft = ref(props.config.has_draft);
const version = ref(props.config.version);
const busy = ref(false);
const dirty = ref(false);
const message = ref('');
const messageError = ref(false);
const newBlock = ref('');
const device = ref('desktop');
const testEmail = ref('');
const versions = ref(null);
const preview = reactive({ subject: '', preheader: '', from_name: '', from_email: '', html: '' });
const otherLocales = computed(() => props.config.locales.filter((l) => l !== props.config.locale));
let active_field = null;
let timer = null;
let loading = true;

function vars(group) {
  return props.config.variables.filter((v) => v.group === group);
}

function editUrl(locale) {
  return props.config.urls.edit.replace('__LOCALE__', locale);
}

function focus(target, prop, event) {
  active_field = { target, prop, el: event.target };
}

// Inserts a variable at the cursor of the last focused field (or appends to the subject).
function insert(token) {
  const field = active_field || { target: content, prop: 'subject', el: null };
  const value = field.target[field.prop] || '';
  const start = field.el && field.el.selectionStart != null ? field.el.selectionStart : value.length;
  const end = field.el && field.el.selectionEnd != null ? field.el.selectionEnd : value.length;
  field.target[field.prop] = value.slice(0, start) + token + value.slice(end);
  if (field.el) {
    requestAnimationFrame(() => {
      field.el.focus();
      field.el.setSelectionRange(start + token.length, start + token.length);
    });
  }
}

function addBlock() {
  const type = newBlock.value;
  if (!type) return;
  const blank = { text: { text: '' }, heading: { text: '' }, button: { label: '', url: '{{ action_url }}' }, card: { rows: [{ label: '', value: '' }] },
    image: { url: '', alt: '', width: 600 }, html: { html: '' }, code: { text: '{{ verification_otp }}' } }[type] || {};
  content.blocks.push({ type, ...blank });
  newBlock.value = '';
}

function move(i, step) {
  const [block] = content.blocks.splice(i, 1);
  content.blocks.splice(i + step, 0, block);
}

function notify(text, error = false) {
  message.value = text;
  messageError.value = error;
}

function errorText(e) {
  const data = (e.response && e.response.data) || {};
  if (data.errors) return Object.values(data.errors).flat().join(' ');
  return data.message || 'Error';
}

async function request(method, url, data) {
  busy.value = true;
  try {
    const res = await axios({ method, url, data });
    if (res.data.message) notify(res.data.message);
    return res.data;
  } catch (e) {
    notify(errorText(e), true);
    throw e;
  } finally {
    busy.value = false;
  }
}

async function refreshPreview() {
  try {
    const res = await axios.post(props.config.urls.preview, content);
    Object.assign(preview, res.data);
  } catch (e) {
    // Invalid content (e.g. an unknown variable) is reported; the last good preview stays.
    if (!loading) notify(errorText(e), true);
  }
}

async function save(kind) {
  if (kind === 'publish' && !window.confirm(props.labels.editor.confirm_publish)) return;
  const data = await request('post', kind === 'publish' ? props.config.urls.publish : props.config.urls.draft, content);
  dirty.value = false;
  if (kind === 'publish') {
    custom.value = true;
    hasDraft.value = false;
    version.value = data.version;
    if (versions.value) loadVersions();
  } else {
    hasDraft.value = true;
  }
}

async function reset() {
  if (!window.confirm(props.labels.editor.confirm_reset)) return;
  const data = await request('post', props.config.urls.reset);
  replace(data.content);
  custom.value = false;
  hasDraft.value = false;
  dirty.value = false;
  version.value = data.version;
  if (versions.value) loadVersions();
}

async function duplicate(locale) {
  const data = await request('post', props.config.urls.duplicate, { to: locale });
  window.location.href = data.redirect;
}

async function toggle(enabled) {
  if (!enabled && !window.confirm(props.labels.editor.confirm_disable)) return;
  const data = await request('post', props.config.urls.toggle, { enabled });
  active.value = data.active;
}

async function sendTest() {
  await request('post', props.config.urls.test, { ...content, email: testEmail.value });
}

async function loadVersions() {
  const res = await axios.get(props.config.urls.versions);
  versions.value = res.data.versions;
}

async function restore(id) {
  const data = await request('post', props.config.urls.restore.replace('__VERSION__', id));
  replace(data.content);
  custom.value = data.custom;
  hasDraft.value = false;
  version.value = data.version;
  dirty.value = false;
  loadVersions();
}

function replace(next) {
  loading = true;
  Object.assign(content, normalise(clone(next)));
  refreshPreview().finally(() => { loading = false; });
}

function formatDate(iso) {
  return iso ? new Date(iso).toLocaleString() : '';
}

watch(content, () => {
  if (!loading) dirty.value = true;
  clearTimeout(timer);
  timer = setTimeout(refreshPreview, 600);
}, { deep: true });

onMounted(() => {
  refreshPreview().finally(() => { loading = false; });
  window.addEventListener('beforeunload', (e) => {
    if (dirty.value) {
      e.preventDefault();
      e.returnValue = '';
    }
  });
});
</script>

<style scoped>
.pee-bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 18px; margin-bottom: 14px; }
.pee-bar-left, .pee-bar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.pee-module { font-weight: 700; font-size: 13px; }
.pee-langs { display: inline-flex; gap: 4px; flex-wrap: wrap; }
.pee-lang { border: 1px solid #e5e6f0; background: #fff; border-radius: 999px; padding: 4px 12px; font-size: 12px; font-weight: 600; color: #626498; text-decoration: none; cursor: pointer; }
.pee-lang.active { background: #f3eeff; color: #004cff; border-color: transparent; }
.pee-switch { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer; margin: 0; }
.pee-switch input { width: 36px; height: 20px; accent-color: #004cff; }
.pee-grid { display: grid; grid-template-columns: minmax(0, 1fr) 420px; gap: 18px; align-items: start; }
.pee-main > section, .pee-side > section { margin-bottom: 14px; }
.pee-side { position: sticky; top: 84px; }
.pee-field { margin-bottom: 14px; }
.pee-section-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 8px; }
.pee-section-head h2, .pee-h2 { font-size: 14px; font-weight: 700; margin: 12px 0 8px; }
.pee-section-head h2 { margin: 0; }
.pee-h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #8b8d9c; margin: 12px 0 6px; }
.pee-add { width: auto; }
.pee-hint { font-size: 11.5px; color: #8b8d9c; margin: 6px 0 10px; line-height: 1.6; }
.pee-block { border: 1px solid #eef0f5; border-radius: 12px; padding: 10px 12px 12px; margin-bottom: 10px; background: #fcfcff; }
.pee-block-head { display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 700; color: #626498; margin-bottom: 8px; }
.pee-block-tools button, .pee-x { border: 0; background: none; color: #8b8d9c; width: 28px; height: 28px; border-radius: 7px; cursor: pointer; }
.pee-block-tools button:hover:not(:disabled), .pee-x:hover { background: #f3eeff; color: #004cff; }
.pee-block-tools button:disabled { opacity: .35; cursor: default; }
.pee-two { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.pee-row { grid-template-columns: 1fr 1fr 32px; margin-bottom: 6px; }
.pee-three { display: grid; grid-template-columns: 2fr 1fr 90px; gap: 8px; }
.pee-code { font-family: ui-monospace, Menlo, monospace; font-size: 12px; }
.pee-actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 18px; }
.pee-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.pee-state { font-size: 12px; color: #8b8d9c; }
.pee-var { display: grid; grid-template-columns: auto 1fr; gap: 2px 8px; width: 100%; text-align: start; border: 1px solid #eef0f5; background: #fff; border-radius: 9px; padding: 7px 9px; margin-bottom: 6px; cursor: pointer; }
.pee-var:hover { border-color: #c9b8ff; background: #faf8ff; }
.pee-var code { font-size: 11.5px; color: #004cff; direction: ltr; }
.pee-var span { font-size: 12px; color: #3a4152; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pee-var small { grid-column: 1 / -1; font-size: 11px; color: #8b8d9c; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pee-envelope { font-size: 12px; line-height: 1.7; padding: 10px 12px; background: #f8f9fc; border-radius: 10px; margin-bottom: 10px; overflow-wrap: anywhere; }
.pee-muted { color: #8b8d9c; }
.pee-frame-wrap { background: #f4f5f9; border-radius: 12px; padding: 8px; display: flex; justify-content: center; }
.pee-frame { border: 0; width: 100%; height: 620px; background: #fff; border-radius: 8px; transition: width .2s; }
.pee-frame.mobile { width: 375px; max-width: 100%; }
.pee-test { display: flex; gap: 8px; margin-top: 10px; }
.pee-versions { list-style: none; padding: 0; margin: 0; }
.pee-versions li { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 8px 0; border-top: 1px solid #f1f2f8; font-size: 12.5px; }
.pee-versions small { display: block; color: #8b8d9c; }
@media (max-width: 1199px) {
  .pee-grid { grid-template-columns: 1fr; }
  .pee-side { position: static; }
}
@media (max-width: 575px) {
  .pee-two, .pee-three { grid-template-columns: 1fr; }
  .pee-row { grid-template-columns: 1fr 32px; }
  .pee-frame { height: 520px; }
}
</style>
