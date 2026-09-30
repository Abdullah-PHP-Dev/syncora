<template>
  <div class="mia">
    <div class="mia-actions">
      <button type="button" class="mia-btn" :disabled="busy" @click="openPicker">
        <i class="bx bx-images"></i> Media Gallery
      </button>
      <button type="button" class="mia-btn" :disabled="busy" @click="$refs.uploadInput.click()">
        <i class="bx bx-cloud-upload"></i> Upload New
      </button>
      <input ref="uploadInput" type="file" class="d-none" :accept="uploadAccept" :multiple="targetMultiple" @change="onUpload">
    </div>

    <div v-if="status" class="mia-status" :class="{ err: statusIsError }">
      <span v-if="!statusIsError" class="spinner-border spinner-border-sm"></span>
      <i v-else class="bx bx-error-circle"></i>
      {{ status }}
    </div>

    <div v-if="selected.length" class="mia-strip">
      <div v-for="asset in selected" :key="asset.id" class="mia-item" :title="asset.original_name">
        <img v-if="asset.type === 'image'" :src="asset.url" :alt="asset.original_name">
        <img v-else-if="asset.thumbnail_url" :src="asset.thumbnail_url" :alt="asset.original_name">
        <span v-else class="mia-video"><i class="bx bx-play"></i></span>
        <span class="mia-meta">
          <b>{{ asset.original_name }}</b>
          <small>From Media Gallery</small>
        </span>
        <span v-if="platform && asset.compatibility && asset.compatibility[platform]" class="mia-compat" :class="asset.compatibility[platform].ok ? 'ok' : 'warn'" :title="issuesText(asset, platform)">
          {{ asset.compatibility[platform].ok ? '✓' : '⚠' }}
        </span>
        <button type="button" class="mia-remove" :aria-label="'Remove ' + asset.original_name" @click="remove(asset)"><i class="bx bx-x"></i></button>
        <!-- Lets AdCampaignController link the campaign to its gallery items. -->
        <input type="hidden" name="media_asset_ids[]" :value="asset.id">
      </div>
    </div>

    <media-picker ref="picker" :urls="urls" :multiple="targetMultiple" :type-lock="typeLock" :platforms="platform ? [platform] : []" @select="onSelect" />
  </div>
</template>

<script setup>
// Media Gallery hook for the Blade ads campaign forms. Those forms already
// own a native <input type="file" name="media[]" id="mediaInput"> with
// their own preview/carousel/validation wired to its `change` event, so
// rather than teaching seven forms a new field, this copies the chosen
// gallery files INTO that input (DataTransfer) and fires `change` - the
// form then behaves exactly as if the user had picked the files locally,
// and AdCampaignRequest validates them per platform as before. The file
// bytes come from the same-origin admin.media-gallery.file route (the R2
// CDN sends no CORS headers).
import { ref, onMounted, onBeforeUnmount } from 'vue';
import MediaPicker from './MediaPicker.vue';
import { precheck, errorMessage, uploadToGallery, issuesText } from './mediaShared';

const props = defineProps({
  urls: { type: Object, required: true }, // index, store, file (MEDIA_ID)
  target: { type: String, default: '#mediaInput' },
  // Optional second input for a video's cover image (Facebook video ads).
  thumbnailTarget: { type: String, default: '' },
  platform: { type: String, default: '' },
});

const picker = ref(null);
const selected = ref([]);
const busy = ref(false);
const status = ref('');
const statusIsError = ref(false);
const typeLock = ref('');
const targetMultiple = ref(false);
const uploadAccept = ref('image/*,video/*');
const fileCache = new Map();
let applying = false;
let observer = null;

function input() { return document.querySelector(props.target); }

// The forms flip their input between image/video and single/multiple as
// the ad type changes (Image / Carousel / Video) - read it fresh each time.
function readTarget() {
  const el = input();
  const accept = (el?.getAttribute('accept') || '').toLowerCase();
  const images = accept.includes('image');
  const videos = accept.includes('video');
  typeLock.value = images && !videos ? 'image' : videos && !images ? 'video' : '';
  targetMultiple.value = !!el?.multiple;
  uploadAccept.value = accept || 'image/*,video/*';
}

function setStatus(text, isError = false) {
  status.value = text;
  statusIsError.value = isError;
}

function openPicker() {
  readTarget();
  picker.value.open();
}

async function onSelect(assets) {
  readTarget();
  const next = targetMultiple.value ? [...selected.value, ...assets.filter(a => !selected.value.some(s => s.id === a.id))] : assets.slice(0, 1);
  await apply(next);
}

async function onUpload(e) {
  readTarget();
  const files = Array.from(e.target.files || []);
  e.target.value = '';
  const uploaded = [];

  busy.value = true;
  for (const file of files) {
    const bad = precheck(file) || (typeLock.value && !file.type.startsWith(typeLock.value + '/') ? `This field only accepts ${typeLock.value}s.` : null);
    if (bad) { setStatus(bad, true); continue; }
    try {
      setStatus(`Uploading ${file.name}…`);
      uploaded.push(await uploadToGallery(props.urls.store, file, p => setStatus(`Uploading ${file.name}… ${p}%`)));
      // The bytes are already in this tab - no need to download them back.
      fileCache.set(uploaded[uploaded.length - 1].id, file);
    } catch (err) {
      setStatus(errorMessage(err, `Upload of "${file.name}" failed.`), true);
    }
  }
  busy.value = false;

  if (uploaded.length) {
    await onSelect(uploaded);
  }
}

async function fileFor(asset) {
  if (fileCache.has(asset.id)) return fileCache.get(asset.id);
  const res = await fetch(props.urls.file.replace('MEDIA_ID', asset.id), { credentials: 'same-origin' });
  if (!res.ok) throw new Error(`Couldn't load "${asset.original_name}" from your gallery.`);
  const file = new File([await res.blob()], asset.original_name, { type: asset.mime_type });
  fileCache.set(asset.id, file);
  return file;
}

async function apply(next) {
  const el = input();
  if (!el) return;

  busy.value = true;
  setStatus(next.length ? 'Preparing media…' : '');

  try {
    const dt = new DataTransfer();
    for (const asset of next) dt.items.add(await fileFor(asset));

    applying = true;
    el.files = dt.files;
    el.dispatchEvent(new Event('change', { bubbles: true }));
    selected.value = next;
    await applyThumbnail(next);
    setStatus('');
  } catch (err) {
    setStatus(err.message || 'Could not attach media.', true);
  } finally {
    applying = false;
    busy.value = false;
  }
}

async function applyThumbnail(next) {
  const thumbEl = props.thumbnailTarget && document.querySelector(props.thumbnailTarget);
  const video = next.find(a => a.type === 'video' && a.thumbnail_url);
  if (!thumbEl || !video || thumbEl.files?.length) return;

  const res = await fetch(props.urls.file.replace('MEDIA_ID', video.id) + '?variant=thumbnail', { credentials: 'same-origin' });
  if (!res.ok) return;
  const blob = await res.blob();
  const dt = new DataTransfer();
  dt.items.add(new File([blob], video.original_name.replace(/\.[^.]+$/, '') + '-thumbnail.jpg', { type: blob.type || 'image/jpeg' }));
  thumbEl.files = dt.files;
  thumbEl.dispatchEvent(new Event('change', { bubbles: true }));
}

function remove(asset) {
  apply(selected.value.filter(a => a.id !== asset.id));
}

// A local pick through the form's own "Upload Media" button replaces the
// input's files - drop the gallery selection so the strip stays truthful.
function onExternalChange(e) {
  if (!applying && e.target === input() && selected.value.length) {
    selected.value = [];
    setStatus('');
  }
}

onMounted(() => {
  document.addEventListener('change', onExternalChange, true);
  const el = input();
  if (el) {
    // Ad type switched (eg. Image -> Video): a now-mismatched gallery
    // selection is cleared rather than silently submitted.
    observer = new MutationObserver(() => {
      readTarget();
      if (selected.value.some(a => typeLock.value && a.type !== typeLock.value) || (!targetMultiple.value && selected.value.length > 1)) {
        apply([]);
      }
    });
    observer.observe(el, { attributes: true, attributeFilter: ['accept', 'multiple'] });
  }
});

onBeforeUnmount(() => {
  document.removeEventListener('change', onExternalChange, true);
  observer?.disconnect();
});
</script>

<style scoped>
.mia { margin-top: .9rem; text-align: left; }
.mia-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: .5rem; }
.mia-btn { display: inline-flex; align-items: center; gap: .4rem; height: 38px; padding: 0 .95rem; border-radius: 10px; border: 1px solid #d9d0ff; background: #fff; color: #6d4aff; font-size: .84rem; font-weight: 600; cursor: pointer; }
.mia-btn:hover:not(:disabled) { background: #f1edff; }
.mia-btn:disabled { opacity: .6; cursor: not-allowed; }
.mia-status { display: flex; align-items: center; justify-content: center; gap: .45rem; margin-top: .6rem; font-size: .8rem; color: #5b6475; }
.mia-status.err { color: #b42318; }
.mia-strip { display: flex; flex-direction: column; gap: .45rem; margin-top: .8rem; }
.mia-item { display: flex; align-items: center; gap: .65rem; padding: .45rem .55rem; border: 1px solid #e6e8ef; border-radius: 10px; background: #fff; }
.mia-item img, .mia-video { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; flex-shrink: 0; background: #0f1016; }
.mia-video { display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.3rem; }
.mia-meta { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.mia-meta b { font-size: .82rem; color: #1b2130; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
.mia-meta small { font-size: .72rem; color: #8a93a3; }
.mia-compat { font-size: .8rem; font-weight: 700; padding: .1rem .4rem; border-radius: 6px; }
.mia-compat.ok { background: #e3f8ec; color: #067647; }
.mia-compat.warn { background: #fff4e0; color: #b54708; cursor: help; }
.mia-remove { width: 28px; height: 28px; border: 0; border-radius: 7px; background: transparent; color: #5b6475; font-size: 1.2rem; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
.mia-remove:hover { background: #fef3f2; color: #d92d20; }
/* The campaign forms style every icon inside .upload-zone (48px, brand
   colour, margins) - isolate this component's icons from that. */
.mia i { font-size: inherit !important; color: inherit !important; margin: 0 !important; line-height: 1; }
.mia-btn i { font-size: 1.1rem !important; }
.mia-remove i { font-size: 1.2rem !important; }
.mia-video i { font-size: 1.3rem !important; }
[dir="rtl"] .mia { text-align: right; }
</style>
