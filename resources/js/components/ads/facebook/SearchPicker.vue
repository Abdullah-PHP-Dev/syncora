<template>
  <div class="sp" :class="{ 'is-open': open }">
    <div class="sp-field" @click="focusInput">
      <span v-for="item in value" :key="keyOf(item)" class="sp-chip" :class="{ 'is-exclude': chipTone === 'exclude' }">
        <slot name="chip" :item="item">{{ labelOf(item) }}</slot>
        <button type="button" :aria-label="t('picker.remove', { name: labelOf(item) })" @click.stop="remove(item)"><i class="bx bx-x"></i></button>
      </span>
      <input
        ref="input"
        v-model="query"
        type="text"
        :placeholder="value.length ? '' : (placeholder || t('picker.search'))"
        :aria-label="placeholder || t('picker.search')"
        autocomplete="off"
        @focus="onFocus"
        @blur="onBlur"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="choose(results[active])"
        @keydown.esc="open = false"
        @input="search"
      >
      <span v-if="loading" class="spinner-border spinner-border-sm sp-spin"></span>
    </div>

    <div v-if="open && (results.length || message)" class="sp-menu" role="listbox">
      <p v-if="message" class="sp-msg">{{ message }}</p>
      <button
        v-for="(item, i) in results"
        :key="keyOf(item)"
        type="button"
        role="option"
        class="sp-opt"
        :class="{ 'is-active': i === active, 'is-picked': isPicked(item) }"
        :aria-selected="isPicked(item)"
        @mousedown.prevent="choose(item)"
        @mouseenter="active = i"
      >
        <slot name="option" :item="item">
          <span class="sp-opt-main">{{ labelOf(item) }}</span>
        </slot>
        <i v-if="isPicked(item)" class="bx bx-check sp-check"></i>
      </button>
    </div>
  </div>
</template>

<script setup>
// Searchable multi-select for the campaign builder: locations, detailed
// targeting, languages, audiences. Either searches the server (fetcher -
// debounced, min 2 characters) or filters a local list (options).
import { ref } from 'vue';
import { useBuilderI18n } from './i18n';

const props = defineProps({
  value: { type: Array, default: () => [] },
  fetcher: { type: Function, default: null },   // async (query) => items
  options: { type: Array, default: null },      // local list instead of fetcher
  itemKey: { type: String, default: 'id' },
  itemLabel: { type: String, default: 'name' },
  placeholder: { type: String, default: '' },
  max: { type: Number, default: 50 },
  chipTone: { type: String, default: 'include' },
  minChars: { type: Number, default: 2 },
});
const emit = defineEmits(['input', 'error']);
const { t } = useBuilderI18n();

const input = ref(null);
const query = ref('');
const results = ref([]);
const open = ref(false);
const loading = ref(false);
const active = ref(0);
const message = ref('');
let timer = null;
let requestId = 0;

const keyOf = (item) => String(item[props.itemKey]);
const labelOf = (item) => item[props.itemLabel];
const isPicked = (item) => props.value.some((v) => keyOf(v) === keyOf(item));

function focusInput() {
  input.value && input.value.focus();
}

function onFocus() {
  open.value = true;
  if (props.options) search();
}

function onBlur() {
  setTimeout(() => { open.value = false; }, 150);
}

function search() {
  clearTimeout(timer);
  message.value = '';
  const q = query.value.trim();

  if (props.options) {
    const needle = q.toLowerCase();
    results.value = props.options.filter((o) => !needle || String(labelOf(o)).toLowerCase().includes(needle)).slice(0, 50);
    message.value = results.value.length ? '' : t('picker.nothing');
    active.value = 0;
    return;
  }

  if (q.length < props.minChars) {
    results.value = [];
    message.value = q ? t('picker.min_chars', { count: props.minChars }) : '';
    return;
  }

  timer = setTimeout(async () => {
    const id = ++requestId;
    loading.value = true;
    try {
      const items = await props.fetcher(q);
      if (id !== requestId) return;
      results.value = items || [];
      message.value = results.value.length ? '' : t('picker.meta_nothing');
      active.value = 0;
    } catch (e) {
      if (id !== requestId) return;
      results.value = [];
      message.value = (e && e.message) || t('picker.failed');
      emit('error', message.value);
    } finally {
      if (id === requestId) loading.value = false;
    }
  }, 300);
}

function choose(item) {
  if (!item) return;
  if (isPicked(item)) {
    remove(item);
  } else if (props.value.length < props.max) {
    emit('input', [...props.value, item]);
  }
  query.value = '';
  if (!props.options) results.value = [];
  focusInput();
}

function remove(item) {
  emit('input', props.value.filter((v) => keyOf(v) !== keyOf(item)));
}

function move(step) {
  if (!results.value.length) return;
  active.value = (active.value + step + results.value.length) % results.value.length;
}
</script>

<style scoped>
.sp { position: relative; }
.sp-field { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; min-height: 42px; padding: 5px 8px; border: 1px solid #E2E5ED; border-radius: 10px; background: #fff; cursor: text; transition: border-color .15s, box-shadow .15s; }
.sp.is-open .sp-field, .sp-field:focus-within { border-color: #8F6BFF; box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
.sp-field input { flex: 1; min-width: 140px; border: 0; outline: 0; font-size: .86rem; padding: 4px 2px; background: transparent; color: #161B2B; }
.sp-spin { color: #8A92A3; }
.sp-chip { display: inline-flex; align-items: center; gap: 4px; max-width: 100%; padding: 3px 4px 3px 9px; border-radius: 999px; background: #F2EEFF; color: #4F2FD6; font-size: .78rem; font-weight: 600; }
.sp-chip.is-exclude { background: #FEF2F2; color: #B91C1C; }
.sp-chip button { border: 0; background: transparent; color: inherit; padding: 0 2px; line-height: 1; font-size: 15px; opacity: .7; }
.sp-chip button:hover { opacity: 1; }
.sp-menu { position: absolute; z-index: 40; left: 0; right: 0; top: calc(100% + 4px); max-height: 280px; overflow-y: auto; background: #fff; border: 1px solid #E7E9F0; border-radius: 12px; box-shadow: 0 18px 40px rgba(16, 24, 40, .14); padding: 4px; }
.sp-msg { margin: 0; padding: 10px 12px; color: #8A92A3; font-size: .8rem; }
.sp-opt { width: 100%; display: flex; align-items: center; gap: 8px; text-align: start; padding: 8px 10px; border: 0; background: transparent; border-radius: 8px; font-size: .84rem; color: #374151; }
.sp-opt.is-active { background: #F7F5FF; }
.sp-opt.is-picked { color: #4F2FD6; font-weight: 600; }
.sp-opt-main { flex: 1; min-width: 0; }
.sp-check { color: #6D4AFF; font-size: 18px; }
</style>
