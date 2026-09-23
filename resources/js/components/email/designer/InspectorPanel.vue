<template>
  <div class="inspector-panel">
    <div v-if="!node" class="inspector-empty">
      <i class="bx bx-cursor"></i>
      <p>Select a block to edit its properties.</p>
    </div>

    <template v-else>
      <div class="inspector-header">
        <strong>{{ headerLabel }}</strong>
      </div>

      <div v-for="group in schema" :key="group.group" class="inspector-group">
        <div class="inspector-group-title">{{ group.group }}</div>

        <div v-for="field in group.fields" :key="field.key" class="inspector-field">
          <label>{{ field.label }}</label>

          <template v-if="field.type === 'text' || field.type === 'url'">
            <input :type="field.type === 'url' ? 'url' : 'text'" class="dash-input w-100" :value="getValue(field)" @input="setValue(field, $event.target.value)" />
          </template>

          <template v-else-if="field.type === 'textarea'">
            <textarea class="dash-input w-100" :rows="field.rows || 6" :value="getValue(field)" @input="setValue(field, $event.target.value)"></textarea>
            <small v-if="field.note" class="inspector-note">{{ field.note }}</small>
          </template>

          <template v-else-if="field.type === 'number'">
            <div class="d-flex align-items-center gap-1">
              <input type="number" class="dash-input" :min="field.min" :max="field.max" :step="field.step || 1" :value="getValue(field)" @input="setValue(field, Number($event.target.value))" />
              <span v-if="field.suffix" class="inspector-suffix">{{ field.suffix }}</span>
            </div>
          </template>

          <template v-else-if="field.type === 'select'">
            <select class="dash-input w-100" :value="getValue(field)" @change="setValue(field, coerceSelect(field, $event.target.value))">
              <option v-for="opt in field.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </template>

          <template v-else-if="field.type === 'font'">
            <select class="dash-input w-100" :value="getValue(field)" @change="setValue(field, $event.target.value)">
              <option v-for="f in fonts" :key="f.value" :value="f.value">{{ f.label }}</option>
            </select>
          </template>

          <template v-else-if="field.type === 'color'">
            <div class="d-flex align-items-center gap-2">
              <input type="color" class="inspector-color" :value="colorValue(field)" @input="setValue(field, $event.target.value)" />
              <button v-if="field.allowTransparent" type="button" class="dash-btn dash-btn-ghost btn-sm" @click="setValue(field, 'transparent')">None</button>
            </div>
          </template>

          <template v-else-if="field.type === 'toggle'">
            <label class="inspector-switch">
              <input type="checkbox" :checked="Boolean(getValue(field))" @change="setValue(field, $event.target.checked)" />
              <span class="inspector-switch-track"></span>
            </label>
          </template>

          <template v-else-if="field.type === 'align'">
            <div class="inspector-align-group">
              <button v-for="a in aligns" :key="a" type="button" class="inspector-align-btn" :class="{ active: getValue(field) === a }" @click="setValue(field, a)">
                <i :class="'bx ' + alignIcon(a)"></i>
              </button>
            </div>
          </template>

          <template v-else-if="field.type === 'padding'">
            <div class="inspector-padding-grid">
              <input v-for="side in paddingSides" :key="side" type="number" min="0" class="dash-input" :placeholder="side" :value="(node.settings.padding || {})[side]" @input="setPaddingSide(side, Number($event.target.value))" />
            </div>
          </template>

          <template v-else-if="field.type === 'image'">
            <div class="inspector-image-field">
              <div class="inspector-image-preview">
                <img v-if="getValue(field)" :src="getValue(field)" alt="" />
                <i v-else class="bx bx-image"></i>
              </div>
              <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="d-none" @change="onImagePicked(field, $event)" />
              <button type="button" class="dash-btn dash-btn-ghost btn-sm" :disabled="uploading" @click="$event.target.closest('.inspector-image-field').querySelector('input[type=file]').click()">
                {{ uploading ? 'Uploading...' : (getValue(field) ? 'Replace' : 'Upload') }}
              </button>
            </div>
          </template>

          <template v-else-if="field.type === 'socialAccounts'">
            <div class="inspector-social-list">
              <label v-for="account in flatAccounts" :key="account.platform + account.id" class="inspector-social-item">
                <input type="checkbox" :checked="isAccountSelected(account)" @change="toggleAccount(account, $event.target.checked)" />
                <span>{{ account.platform }} - {{ account.name }}</span>
              </label>
              <p v-if="flatAccounts.length === 0" class="inspector-note">No connected social accounts yet.</p>
            </div>
          </template>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { INSPECTOR_SCHEMAS } from '../lib/inspectorSchemas';
import { WEB_SAFE_FONTS } from '../lib/fonts';

const props = defineProps({
  node: { type: Object, default: null },
  nodeKind: { type: String, default: null }, // 'section' | 'column' | 'block'
  socialAccountsByPlatform: { type: Object, default: () => ({}) },
  uploadMediaUrl: { type: String, default: '' },
});

const emit = defineEmits(['change']);

const fonts = WEB_SAFE_FONTS;
const aligns = ['left', 'center', 'right'];
const paddingSides = ['top', 'right', 'bottom', 'left'];
const uploading = ref(false);

const schema = computed(() => {
  if (!props.node) return [];
  const key = props.nodeKind === 'block' ? props.node.type : props.nodeKind;
  return INSPECTOR_SCHEMAS[key] || [];
});

const headerLabel = computed(() => {
  if (props.nodeKind === 'section') return 'Section';
  if (props.nodeKind === 'column') return 'Column';
  return props.node?.type ? props.node.type.charAt(0).toUpperCase() + props.node.type.slice(1) : '';
});

const flatAccounts = computed(() => {
  const out = [];
  Object.entries(props.socialAccountsByPlatform || {}).forEach(([platform, accounts]) => {
    (accounts || []).forEach((a) => out.push({ ...a, platform }));
  });
  return out;
});

function getValue(field) {
  if (field.target === 'content') return props.node.content;
  if (field.target === 'width') return props.node.width;
  return props.node.settings ? props.node.settings[field.key] : undefined;
}

function setValue(field, value) {
  if (field.target === 'content') {
    props.node.content = value;
  } else if (field.target === 'width') {
    props.node.width = value;
  } else {
    props.node.settings[field.key] = value;
  }
  emit('change', field.type === 'text' || field.type === 'textarea' || field.type === 'number' ? 'debounced' : 'immediate');
}

function setPaddingSide(side, value) {
  if (!props.node.settings.padding) props.node.settings.padding = {};
  props.node.settings.padding[side] = value;
  emit('change', 'immediate');
}

function coerceSelect(field, raw) {
  const match = (field.options || []).find((o) => String(o.value) === String(raw));
  return match ? match.value : raw;
}

function colorValue(field) {
  const v = getValue(field);
  return v && v !== 'transparent' ? v : '#ffffff';
}

function alignIcon(a) {
  return a === 'left' ? 'bx-align-left' : (a === 'right' ? 'bx-align-right' : 'bx-align-middle');
}

function isAccountSelected(account) {
  return (props.node.settings.accounts || []).some((a) => a.platform === account.platform && a.url === account.url);
}

function toggleAccount(account, checked) {
  const accounts = props.node.settings.accounts || (props.node.settings.accounts = []);
  const idx = accounts.findIndex((a) => a.platform === account.platform && a.url === account.url);
  if (checked && idx === -1) {
    accounts.push({ platform: account.platform, url: account.url });
  } else if (!checked && idx !== -1) {
    accounts.splice(idx, 1);
  }
  emit('change', 'immediate');
}

async function onImagePicked(field, event) {
  const file = event.target.files[0];
  if (!file || !props.uploadMediaUrl) return;

  uploading.value = true;
  try {
    const formData = new FormData();
    formData.append('file', file);
    const { data } = await window.axios.post(props.uploadMediaUrl, formData);
    if (data.success) {
      setValue(field, data.url);
    }
  } catch (e) {
    // Left in place - the field simply keeps its previous value, and the
    // Upload button re-enables so the seller can retry.
  } finally {
    uploading.value = false;
    event.target.value = '';
  }
}
</script>

<style scoped>
.inspector-panel { padding: 1rem; height: 100%; overflow-y: auto; }
.inspector-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--dash-muted); text-align: center; gap: .5rem; }
.inspector-empty .bx { font-size: 2rem; }
.inspector-header { font-size: .8125rem; color: var(--dash-heading); text-transform: uppercase; letter-spacing: .03em; padding-bottom: .75rem; border-bottom: 1px solid var(--dash-border); margin-bottom: .75rem; }
.inspector-group { margin-bottom: 1.25rem; }
.inspector-group-title { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--dash-muted); margin-bottom: .5rem; }
.inspector-field { margin-bottom: .65rem; }
.inspector-field label { display: block; font-size: .75rem; color: var(--dash-text); margin-bottom: .25rem; }
.inspector-note { display: block; color: var(--dash-muted); font-size: .7rem; margin-top: .25rem; }
.inspector-suffix { font-size: .75rem; color: var(--dash-muted); }
.inspector-color { width: 34px; height: 30px; padding: 0; border: 1px solid var(--dash-border); border-radius: .4rem; background: none; cursor: pointer; }
.inspector-align-group { display: flex; gap: .25rem; }
.inspector-align-btn { width: 32px; height: 32px; border: 1px solid var(--dash-border); background: var(--dash-card); border-radius: .4rem; color: var(--dash-muted); }
.inspector-align-btn.active { background: var(--dash-primary); border-color: var(--dash-primary); color: #fff; }
.inspector-padding-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: .35rem; }
.inspector-padding-grid input { padding: .3rem .4rem; font-size: .75rem; }
.inspector-image-field { display: flex; align-items: center; gap: .6rem; }
.inspector-image-preview { width: 56px; height: 42px; border: 1px solid var(--dash-border); border-radius: .4rem; display: flex; align-items: center; justify-content: center; overflow: hidden; background: var(--dash-card-hover); flex-shrink: 0; }
.inspector-image-preview img { width: 100%; height: 100%; object-fit: cover; }
.inspector-image-preview .bx { color: var(--dash-muted); }
.inspector-social-list { display: flex; flex-direction: column; gap: .4rem; }
.inspector-social-item { display: flex; align-items: center; gap: .4rem; font-size: .8125rem; text-transform: capitalize; }
.inspector-switch { position: relative; display: inline-block; width: 36px; height: 20px; }
.inspector-switch input { opacity: 0; width: 0; height: 0; }
.inspector-switch-track { position: absolute; inset: 0; background: var(--dash-border); border-radius: 20px; transition: .15s; }
.inspector-switch-track::before { content: ''; position: absolute; width: 16px; height: 16px; left: 2px; top: 2px; background: #fff; border-radius: 50%; transition: .15s; }
.inspector-switch input:checked + .inspector-switch-track { background: var(--dash-primary); }
.inspector-switch input:checked + .inspector-switch-track::before { transform: translateX(16px); }
</style>
