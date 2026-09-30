<template>
  <div class="modal fade" ref="modalEl" tabindex="-1" aria-labelledby="mpTitle">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content mp">

        <div class="mp-head">
          <div>
            <h5 id="mpTitle">Select Media</h5>
            <p>
              {{ multiple ? 'Choose one or more files' : 'Choose a file' }}{{ typeLock ? (typeLock === 'image' ? ' (images only)' : ' (videos only)') : '' }}.
              <template v-if="platforms.length">Compatibility shown for {{ platforms.map(p => (PLATFORM_META[p] || {}).name || p).join(', ') }}.</template>
            </p>
          </div>
          <button type="button" class="mp-close" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
        </div>

        <div class="mp-toolbar">
          <div class="mp-tabs" role="tablist">
            <button v-for="t in tabs" :key="t.key" type="button" role="tab" :aria-selected="type === t.key" :class="{ on: type === t.key }" @click="type = t.key; load(1)">{{ t.label }}</button>
          </div>
          <div class="mp-search">
            <i class="bx bx-search"></i>
            <input v-model="q" type="search" class="form-control" placeholder="Search media by name..." @input="debouncedLoad">
          </div>
        </div>

        <div class="mp-body">
          <div v-if="uploading" class="mp-upload-row">
            <span class="spinner-border spinner-border-sm"></span>
            <div class="flex-grow-1">
              <div class="mp-upload-name">Uploading {{ uploading.name }}…</div>
              <div class="mp-progress"><span :style="{ width: uploading.progress + '%' }"></span></div>
            </div>
            <span class="mp-pct">{{ uploading.progress }}%</span>
          </div>
          <div v-if="error" class="mp-error"><i class="bx bx-error-circle"></i> {{ error }}</div>

          <div v-if="loading && !items.length" class="mp-empty"><span class="spinner-border spinner-border-sm me-2"></span> Loading…</div>
          <div v-else-if="!items.length" class="mp-empty">
            <i class="bx bx-images"></i>
            <strong>{{ q ? 'No media matches your search' : 'No ' + (type === 'image' ? 'images' : type === 'video' ? 'videos' : 'media') + ' in your gallery yet' }}</strong>
            <span>Upload a file to add it to your gallery and select it.</span>
          </div>

          <div v-else class="mp-grid" :class="{ busy: loading }">
            <button
              v-for="asset in items" :key="asset.id" type="button" class="mp-item"
              :class="{ sel: isSelected(asset), dis: !!disabledReason(asset) }"
              :aria-pressed="isSelected(asset)"
              :title="disabledReason(asset) || asset.original_name"
              @click="toggle(asset)">
              <span class="mp-thumb">
                <img v-if="asset.type === 'image'" :src="asset.url" :alt="asset.original_name" loading="lazy">
                <img v-else-if="asset.thumbnail_url" :src="asset.thumbnail_url" :alt="asset.original_name" loading="lazy">
                <video v-else :src="asset.url + '#t=0.5'" preload="metadata" muted playsinline></video>
                <span class="mp-badge"><i :class="['bx', asset.type === 'video' ? 'bx-play' : 'bx-image']"></i> {{ asset.type === 'video' ? 'Video' : 'Image' }}</span>
                <span v-if="asset.type === 'video' && asset.duration" class="mp-dur">{{ formatDuration(asset.duration) }}</span>
                <span class="mp-check"><b v-if="multiple && isSelected(asset)">{{ selectedIndex(asset) + 1 }}</b><i v-else class="bx bx-check"></i></span>
              </span>
              <span class="mp-name">{{ asset.original_name }}</span>
              <span class="mp-info">{{ mediaInfo(asset) }}</span>
              <span v-if="platforms.length" class="mp-compat">
                <span v-for="p in platforms" :key="p" class="mp-chip" :class="compatOk(asset, p) ? 'ok' : 'warn'" :title="compatOk(asset, p) ? '' : issuesText(asset, p)">
                  {{ compatOk(asset, p) ? '✓' : '⚠' }} {{ (PLATFORM_META[p] || {}).name || p }}
                </span>
              </span>
            </button>
          </div>

          <div v-if="meta.last_page > 1" class="mp-pages">
            <button type="button" :disabled="meta.current_page <= 1" aria-label="Previous page" @click="load(meta.current_page - 1)"><i class="bx bx-chevron-left"></i></button>
            <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
            <button type="button" :disabled="meta.current_page >= meta.last_page" aria-label="Next page" @click="load(meta.current_page + 1)"><i class="bx bx-chevron-right"></i></button>
          </div>
        </div>

        <div class="mp-foot">
          <button type="button" class="mp-btn mp-btn-outline" :disabled="!!uploading" @click="$refs.fileInput.click()"><i class="bx bx-cloud-upload"></i> Upload New</button>
          <input ref="fileInput" type="file" class="d-none" :accept="acceptAttr" :multiple="multiple" @change="onUpload">
          <span class="mp-count">{{ selected.length ? selected.length + ' selected' : '' }}</span>
          <button type="button" class="mp-btn mp-btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="mp-btn mp-btn-brand" :disabled="!selected.length" @click="attach"><i class="bx bx-paperclip"></i> Attach Selected</button>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
// Reusable "Select Media" modal - used by the posts composer (MediaGrid.vue)
// and the ads campaign forms (MediaInputAttach.vue). Call open() via a ref;
// emits `select` with the chosen asset objects (in pick order).
import { ref, computed } from 'vue';
import {
  PLATFORM_META, ACCEPT, precheck, formatDuration, mediaInfo, issuesText,
  errorMessage, uploadToGallery, fetchGallery,
} from './mediaShared';

const props = defineProps({
  urls: { type: Object, required: true }, // index, store
  multiple: { type: Boolean, default: true },
  // 'image' | 'video' | '' - restricts what can be picked (eg. a Facebook
  // IMAGE ad's input only accepts images).
  typeLock: { type: String, default: '' },
  platforms: { type: Array, default: () => [] },
  max: { type: Number, default: 20 },
});

const emit = defineEmits(['select']);

const modalEl = ref(null);
let modal = null;
const items = ref([]);
const meta = ref({ current_page: 1, last_page: 1 });
const loading = ref(false);
const type = ref('');
const q = ref('');
const selected = ref([]);
const error = ref('');
const uploading = ref(null);

const tabs = computed(() => {
  const all = [{ key: '', label: 'All' }, { key: 'image', label: 'Images' }, { key: 'video', label: 'Videos' }];
  return props.typeLock ? all.filter(t => t.key === props.typeLock) : all;
});

const acceptAttr = computed(() => {
  if (props.typeLock === 'image') return '.jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp';
  if (props.typeLock === 'video') return '.mp4,.m4v,.mov,.webm,video/mp4,video/quicktime,video/webm';
  return ACCEPT;
});

function open() {
  selected.value = [];
  error.value = '';
  q.value = '';
  type.value = props.typeLock || '';
  if (!modal) modal = new window.bootstrap.Modal(modalEl.value);
  modal.show();
  load(1);
}

let timer = null;
function debouncedLoad() {
  clearTimeout(timer);
  timer = setTimeout(() => load(1), 300);
}

async function load(page = 1) {
  loading.value = true;
  try {
    const params = { page, per_page: 18 };
    if (type.value) params.type = type.value;
    if (q.value) params.q = q.value;
    const data = await fetchGallery(props.urls.index, params);
    items.value = data.data;
    meta.value = { current_page: data.current_page, last_page: data.last_page };
  } catch (e) {
    error.value = errorMessage(e, 'Could not load your gallery.');
  } finally {
    loading.value = false;
  }
}

function compatOk(asset, platform) {
  return !!asset.compatibility?.[platform]?.ok;
}

function disabledReason(asset) {
  if (props.typeLock && asset.type !== props.typeLock) return `This field only accepts ${props.typeLock}s.`;
  return '';
}

function isSelected(asset) { return selected.value.some(a => a.id === asset.id); }
function selectedIndex(asset) { return selected.value.findIndex(a => a.id === asset.id); }

function toggle(asset) {
  if (disabledReason(asset)) return;
  if (isSelected(asset)) {
    selected.value = selected.value.filter(a => a.id !== asset.id);
  } else if (!props.multiple) {
    selected.value = [asset];
  } else if (selected.value.length < props.max) {
    selected.value = [...selected.value, asset];
  } else {
    error.value = `You can select up to ${props.max} files.`;
  }
}

async function onUpload(e) {
  const files = Array.from(e.target.files || []);
  e.target.value = '';
  error.value = '';

  for (const file of files) {
    const bad = precheck(file) || (props.typeLock && !file.type.startsWith(props.typeLock + '/') ? `"${file.name}" isn't ${props.typeLock === 'image' ? 'an image' : 'a video'}.` : null);
    if (bad) { error.value = bad; continue; }

    uploading.value = { name: file.name, progress: 0 };
    try {
      const asset = await uploadToGallery(props.urls.store, file, p => { uploading.value.progress = p; });
      items.value = [asset, ...items.value.filter(a => a.id !== asset.id)];
      toggle(asset);
    } catch (err) {
      error.value = errorMessage(err, `Upload of "${file.name}" failed.`);
    } finally {
      uploading.value = null;
    }
  }
}

function attach() {
  emit('select', selected.value);
  modal?.hide();
}

defineExpose({ open });
</script>

<style scoped>
.mp { --ln: #e6e8ef; --ln-soft: #f0f2f6; --ink: #1b2130; --ink2: #5b6475; --muted: #8a93a3; --brand: #6d4aff; --brand-soft: #f1edff; border: 0; border-radius: 16px; }
.mp-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 1.15rem 1.4rem .8rem; border-bottom: 1px solid var(--ln-soft); }
.mp-head h5 { margin: 0; font-weight: 700; color: var(--ink); }
.mp-head p { margin: .2rem 0 0; font-size: .8rem; color: var(--muted); }
.mp-close { width: 32px; height: 32px; border-radius: 8px; border: 0; background: transparent; color: var(--ink2); font-size: 1.4rem; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
.mp-close:hover { background: #f2f4f7; }
.mp-toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .7rem; padding: .8rem 1.4rem 0; }
.mp-tabs { display: inline-flex; gap: .3rem; padding: .22rem; background: #f4f5f9; border-radius: 11px; }
.mp-tabs button { border: 0; background: transparent; height: 34px; min-width: 70px; padding: 0 .9rem; border-radius: 8px; font-size: .84rem; font-weight: 600; color: var(--ink2); cursor: pointer; }
.mp-tabs button.on { background: var(--brand); color: #fff; }
.mp-search { position: relative; }
.mp-search i { position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); color: var(--muted); }
.mp-search .form-control { width: 260px; height: 38px; padding-left: 2.2rem; border-radius: 10px; border-color: var(--ln); font-size: .85rem; }
.mp-body { padding: 1rem 1.4rem; min-height: 320px; }
.mp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: .85rem; }
.mp-grid.busy { opacity: .55; pointer-events: none; }
.mp-item { display: flex; flex-direction: column; text-align: left; gap: .2rem; padding: .45rem; border: 2px solid var(--ln); border-radius: 12px; background: #fff; cursor: pointer; min-width: 0; transition: border-color .12s, box-shadow .12s; }
.mp-item:hover { border-color: #cfc4ff; }
.mp-item.sel { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .15); }
.mp-item.dis { opacity: .45; cursor: not-allowed; }
.mp-thumb { position: relative; display: block; aspect-ratio: 4 / 3; border-radius: 8px; overflow: hidden; background: #0f1016; }
.mp-thumb img, .mp-thumb video { width: 100%; height: 100%; object-fit: cover; display: block; }
.mp-badge { position: absolute; top: .35rem; left: .35rem; background: rgba(255, 255, 255, .92); color: var(--ink); border-radius: 6px; padding: .08rem .38rem; font-size: .66rem; font-weight: 600; display: inline-flex; align-items: center; gap: .2rem; }
.mp-dur { position: absolute; right: .35rem; bottom: .35rem; background: rgba(0, 0, 0, .7); color: #fff; font-size: .66rem; padding: .05rem .35rem; border-radius: 5px; }
.mp-check { position: absolute; top: .35rem; right: .35rem; width: 24px; height: 24px; border-radius: 50%; border: 2px solid #fff; background: rgba(0, 0, 0, .3); color: transparent; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; }
.mp-check b { font-size: .72rem; color: #fff; }
.mp-item.sel .mp-check { background: var(--brand); color: #fff; }
.mp-name { font-size: .8rem; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: .3rem; }
.mp-info { font-size: .7rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mp-compat { display: flex; flex-wrap: wrap; gap: .2rem; margin-top: .2rem; }
.mp-chip { font-size: .64rem; font-weight: 600; border-radius: 5px; padding: .05rem .3rem; }
.mp-chip.ok { background: #e3f8ec; color: #067647; }
.mp-chip.warn { background: #fff4e0; color: #b54708; }
.mp-empty { text-align: center; padding: 3rem 1rem; color: var(--muted); font-size: .84rem; }
.mp-empty > i { display: block; font-size: 2.3rem; color: #c9cedb; margin-bottom: .4rem; }
.mp-empty strong { display: block; color: var(--ink); margin-bottom: .2rem; }
.mp-empty span { display: block; }
.mp-error { display: flex; align-items: center; gap: .5rem; padding: .6rem .8rem; border-radius: 10px; background: #fef3f2; color: #b42318; border: 1px solid #fecdca; font-size: .82rem; margin-bottom: .8rem; }
.mp-upload-row { display: flex; align-items: center; gap: .7rem; padding: .6rem .8rem; border-radius: 10px; background: var(--brand-soft); margin-bottom: .8rem; color: var(--brand); }
.mp-upload-name { font-size: .8rem; color: var(--ink); }
.mp-progress { height: 5px; background: #fff; border-radius: 5px; overflow: hidden; margin-top: .3rem; }
.mp-progress span { display: block; height: 100%; background: var(--brand); transition: width .2s; }
.mp-pct { font-size: .75rem; color: var(--ink2); }
.mp-pages { display: flex; justify-content: center; align-items: center; gap: .8rem; margin-top: 1rem; font-size: .8rem; color: var(--ink2); }
.mp-pages button { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--ln); background: #fff; cursor: pointer; }
.mp-pages button:disabled { opacity: .5; cursor: default; }
.mp-foot { display: flex; align-items: center; gap: .6rem; padding: .85rem 1.4rem; border-top: 1px solid var(--ln-soft); }
.mp-count { margin-left: auto; font-size: .82rem; color: var(--ink2); font-weight: 600; }
.mp-btn { display: inline-flex; align-items: center; gap: .4rem; height: 40px; padding: 0 1rem; border-radius: 10px; font-size: .86rem; font-weight: 600; border: 1px solid transparent; cursor: pointer; }
.mp-btn:disabled { opacity: .55; cursor: not-allowed; }
.mp-btn-brand { background: var(--brand); color: #fff; }
.mp-btn-brand:hover:not(:disabled) { background: #5a36f0; }
.mp-btn-outline { background: #fff; border-color: #d9d0ff; color: var(--brand); }
.mp-btn-outline:hover:not(:disabled) { background: var(--brand-soft); }
.mp-btn-light { background: #fff; border-color: var(--ln); color: var(--ink2); }
@media (max-width: 575.98px) {
  .mp-search, .mp-search .form-control { width: 100%; }
  .mp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .mp-foot { flex-wrap: wrap; }
}
[dir="rtl"] .mp-item { text-align: right; }
[dir="rtl"] .mp-search i { left: auto; right: .75rem; }
[dir="rtl"] .mp-search .form-control { padding-left: .75rem; padding-right: 2.2rem; }
[dir="rtl"] .mp-count { margin-left: 0; margin-right: auto; }
</style>
