<template>
  <div class="mg" @dragover.prevent="dragging = true" @dragleave.self="dragging = false" @drop.prevent="onDrop">

    <!-- Header -->
    <div class="mg-head">
      <div class="mg-head-id">
        <span class="mg-head-mark"><i class="bx bx-image"></i></span>
        <div>
          <h4>Media Gallery</h4>
          <p>Manage your images and videos. Use them for content posting and ad campaigns.</p>
        </div>
      </div>
      <button type="button" class="mg-btn mg-btn-brand mg-btn-lg" @click="$refs.fileInput.click()">
        <i class="bx bx-cloud-upload"></i> Upload Media
      </button>
      <input ref="fileInput" type="file" class="d-none" multiple :accept="ACCEPT" @change="onPicked">
    </div>

    <!-- Upload queue -->
    <div v-if="queue.length" class="mg-queue">
      <div class="mg-queue-h">
        <strong>Uploads</strong>
        <button v-if="!uploadingCount" type="button" class="mg-link" @click="queue = []">Clear</button>
      </div>
      <div v-for="item in queue" :key="item.key" class="mg-queue-row" :class="item.status">
        <i :class="['bx', item.status === 'done' ? 'bxs-check-circle' : item.status === 'error' ? 'bxs-error-circle' : 'bx-loader-alt bx-spin']"></i>
        <div class="mg-queue-body">
          <div class="mg-queue-name">{{ item.name }} <small>{{ formatBytes(item.size) }}</small></div>
          <div v-if="item.status === 'uploading'" class="mg-progress"><span :style="{ width: item.progress + '%' }"></span></div>
          <div v-else-if="item.status === 'error'" class="mg-queue-err">{{ item.error }}</div>
          <div v-else-if="item.status === 'processing'" class="mg-queue-note">Checking file…</div>
        </div>
        <span v-if="item.status === 'uploading'" class="mg-queue-pct">{{ item.progress }}%</span>
      </div>
    </div>

    <!-- Toolbar + grid -->
    <div class="mg-card">
      <div class="mg-toolbar">
        <div class="mg-tabs" role="tablist">
          <button v-for="t in TABS" :key="t.key" type="button" role="tab" :aria-selected="filters.type === t.key" :class="{ on: filters.type === t.key }" @click="setType(t.key)">{{ t.label }}</button>
        </div>
        <div class="mg-tools">
          <div class="mg-search">
            <i class="bx bx-search"></i>
            <input v-model="filters.q" type="search" class="form-control" placeholder="Search media by name..." @input="debouncedFetch">
          </div>
          <select v-model="filters.platform" class="form-select mg-platform" @change="fetchMedia(1)">
            <option value="">All Platforms</option>
            <option v-for="(meta, key) in PLATFORM_META" :key="key" :value="key">Works on {{ meta.name }}</option>
          </select>
          <div class="mg-sort">
            <button type="button" class="mg-icon-btn" title="Sort" aria-label="Sort" @click.stop="sortOpen = !sortOpen"><i class="bx bx-slider-alt"></i></button>
            <div v-if="sortOpen" class="mg-menu" @click.stop>
              <button v-for="s in SORTS" :key="s.key" type="button" :class="{ on: filters.sort === s.key }" @click="setSort(s.key)">
                <i class="bx" :class="filters.sort === s.key ? 'bx-check' : ''"></i> {{ s.label }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="loading && !assets.length" class="mg-empty"><span class="spinner-border spinner-border-sm me-2"></span> Loading…</div>

      <div v-else-if="!assets.length" class="mg-empty">
        <i class="bx bx-images"></i>
        <template v-if="hasFilters">
          <strong>No media matches these filters</strong>
          <button type="button" class="mg-link" @click="clearFilters">Clear filters</button>
        </template>
        <template v-else>
          <strong>Your gallery is empty</strong>
          <span>Upload images and videos once, then reuse them in posts and ad campaigns. You can also drag files onto this page.</span>
          <button type="button" class="mg-btn mg-btn-brand mt-2" @click="$refs.fileInput.click()"><i class="bx bx-cloud-upload"></i> Upload Media</button>
        </template>
      </div>

      <div v-else class="mg-grid" :class="{ busy: loading }">
        <div v-for="asset in assets" :key="asset.id" class="mg-item">
          <button type="button" class="mg-thumb" :aria-label="'Preview ' + asset.original_name" @click="openPreview(asset)">
            <img v-if="asset.type === 'image'" :src="asset.url" :alt="asset.original_name" loading="lazy">
            <img v-else-if="asset.thumbnail_url" :src="asset.thumbnail_url" :alt="asset.original_name" loading="lazy">
            <video v-else :src="asset.url + '#t=0.5'" preload="metadata" muted playsinline></video>
            <span class="mg-badge"><i :class="['bx', asset.type === 'video' ? 'bx-play' : 'bx-image']"></i> {{ asset.type === 'video' ? 'Video' : 'Image' }}</span>
            <span v-if="asset.type === 'video'" class="mg-play"><i class="bx bx-play"></i></span>
            <span v-if="asset.type === 'video' && asset.duration" class="mg-dur">{{ formatDuration(asset.duration) }}</span>
          </button>

          <div class="mg-body">
            <div class="mg-name-row">
              <div class="mg-name" :title="asset.original_name">{{ asset.original_name }}</div>
              <div class="mg-actions">
                <button type="button" class="mg-icon-sm" aria-label="More actions" @click.stop="toggleMenu(asset.id)"><i class="bx bx-dots-vertical-rounded"></i></button>
                <div v-if="menuFor === asset.id" class="mg-menu mg-menu-item" @click.stop>
                  <button type="button" @click="openPreview(asset); menuFor = null"><i class="bx bx-show"></i> Preview</button>
                  <button type="button" @click="copyUrl(asset)"><i class="bx bx-link"></i> Copy link</button>
                  <a :href="urls.file.replace('MEDIA_ID', asset.id)" target="_blank" rel="noopener" @click="menuFor = null"><i class="bx bx-download"></i> Download</a>
                  <hr>
                  <button type="button" class="danger" @click="remove(asset)"><i class="bx bx-trash"></i> Delete</button>
                </div>
              </div>
            </div>
            <div class="mg-info-row">
              <div class="mg-info">{{ mediaInfo(asset) }}</div>
              <button type="button" class="mg-trash" :aria-label="'Delete ' + asset.original_name" title="Delete" :disabled="deleting === asset.id" @click="remove(asset)">
                <span v-if="deleting === asset.id" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-trash"></i>
              </button>
            </div>
            <div class="mg-platforms">
              <platform-icon v-for="(meta, key) in PLATFORM_META" :key="key" :platform="key" :ok="!!(asset.compatibility && asset.compatibility[key] && asset.compatibility[key].ok)" :issues="issuesText(asset, key)" />
            </div>
            <div class="mg-compat" :class="compatSummary(asset).allOk ? 'ok' : 'warn'" :title="compatTitle(asset)">
              <i :class="['bx', compatSummary(asset).allOk ? 'bxs-check-circle' : 'bxs-error']"></i>
              <span v-if="compatSummary(asset).allOk">Compatible with all platforms</span>
              <span v-else>Needs adjustment for {{ compatSummary(asset).bad.map(p => PLATFORM_META[p].name).join(', ') }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="meta.total" class="mg-foot">
        <small>Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }} media</small>
        <div v-if="meta.last_page > 1" class="mg-pages">
          <button type="button" :disabled="meta.current_page <= 1" aria-label="Previous page" @click="fetchMedia(meta.current_page - 1)"><i class="bx bx-chevron-left"></i></button>
          <button v-for="p in pageList" :key="p.key" type="button" :class="{ on: p.n === meta.current_page }" :disabled="!p.n" @click="p.n && fetchMedia(p.n)">{{ p.n || '…' }}</button>
          <button type="button" :disabled="meta.current_page >= meta.last_page" aria-label="Next page" @click="fetchMedia(meta.current_page + 1)"><i class="bx bx-chevron-right"></i></button>
        </div>
      </div>
    </div>

    <div v-if="dragging" class="mg-drop"><div><i class="bx bx-cloud-upload"></i><strong>Drop images or videos to upload</strong></div></div>

    <!-- Preview -->
    <div class="modal fade" ref="previewEl" tabindex="-1">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <div v-if="preview" class="modal-content mg-preview">
          <div class="mg-preview-media">
            <img v-if="preview.type === 'image'" :src="preview.url" :alt="preview.original_name">
            <video v-else :src="preview.url" :poster="preview.thumbnail_url || null" controls playsinline></video>
          </div>
          <div class="mg-preview-side">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <h5>{{ preview.original_name }}</h5>
              <button type="button" class="mg-close" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
            </div>
            <dl class="mg-dl">
              <dt>Type</dt><dd>{{ preview.type === 'video' ? 'Video' : 'Image' }} · {{ (preview.extension || '').toUpperCase() }}</dd>
              <dt>Size</dt><dd>{{ formatBytes(preview.size) }}</dd>
              <dt>Dimensions</dt><dd>{{ preview.width }} × {{ preview.height }}</dd>
              <template v-if="preview.type === 'video'"><dt>Duration</dt><dd>{{ formatDuration(preview.duration) }} · {{ (preview.video_codec || '').toUpperCase() }}</dd></template>
              <dt>Uploaded</dt><dd>{{ new Date(preview.created_at).toLocaleString() }}</dd>
            </dl>
            <div class="mg-side-h">Platform compatibility</div>
            <ul class="mg-compat-list">
              <li v-for="(meta, key) in PLATFORM_META" :key="key" :class="preview.compatibility && preview.compatibility[key] && preview.compatibility[key].ok ? 'ok' : 'warn'">
                <platform-icon :platform="key" :ok="!!(preview.compatibility && preview.compatibility[key] && preview.compatibility[key].ok)" :issues="issuesText(preview, key)" />
                <div>
                  <strong>{{ meta.name }}</strong>
                  <small v-if="preview.compatibility && preview.compatibility[key] && preview.compatibility[key].ok">Ready to use</small>
                  <small v-else>{{ issuesText(preview, key) || 'Requires adjustment' }}</small>
                </div>
              </li>
            </ul>
            <p class="mg-fineprint">Based on each platform's published limits. The platform makes the final check when you publish.</p>
            <button type="button" class="mg-btn mg-btn-danger w-100 mt-2" @click="remove(preview)"><i class="bx bx-trash"></i> Delete</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
// Seller's Media Gallery page (admin.media-gallery.index). Talks to
// MediaGalleryController's JSON endpoints; the same assets are picked from
// MediaPicker.vue in the posts composer and ads campaign forms.
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import PlatformIcon from './PlatformIcon.vue';
import {
  PLATFORM_META, ACCEPT, precheck, formatBytes, formatDuration, mediaInfo,
  compatSummary, issuesText, errorMessage, uploadToGallery, fetchGallery,
} from './mediaShared';

const props = defineProps({
  initialMedia: { type: Object, required: true },
  urls: { type: Object, required: true }, // index, store, destroy (MEDIA_ID), file (MEDIA_ID)
});

const TABS = [{ key: '', label: 'All' }, { key: 'image', label: 'Images' }, { key: 'video', label: 'Videos' }];
const SORTS = [
  { key: 'newest', label: 'Newest first' },
  { key: 'oldest', label: 'Oldest first' },
  { key: 'largest', label: 'Largest first' },
  { key: 'name', label: 'Name (A–Z)' },
];

const assets = ref(props.initialMedia.data || []);
const meta = ref(pageMeta(props.initialMedia));
const filters = ref({ type: '', q: '', platform: '', sort: 'newest' });
const loading = ref(false);
const sortOpen = ref(false);
const menuFor = ref(null);
const deleting = ref(null);
const dragging = ref(false);
const queue = ref([]);
const preview = ref(null);
const previewEl = ref(null);
let previewModal = null;

function pageMeta(p) {
  return { current_page: p.current_page || 1, last_page: p.last_page || 1, total: p.total || 0, from: p.from || 0, to: p.to || 0 };
}

const hasFilters = computed(() => !!(filters.value.type || filters.value.q || filters.value.platform));
const uploadingCount = computed(() => queue.value.filter(q => ['uploading', 'processing'].includes(q.status)).length);
const pageList = computed(() => {
  const { current_page: cur, last_page: last } = meta.value;
  const nums = [...new Set([1, last, cur - 1, cur, cur + 1].filter(n => n >= 1 && n <= last))].sort((a, b) => a - b);
  const out = [];
  nums.forEach((n, i) => {
    if (i && n - nums[i - 1] > 1) out.push({ key: 'gap' + n, n: null });
    out.push({ key: n, n });
  });
  return out;
});

function toast(icon, title, text = '') {
  if (window.Swal) window.Swal.fire({ icon, title, text, timer: icon === 'success' ? 1800 : undefined, showConfirmButton: icon !== 'success' });
  else if (icon === 'error') alert(`${title}\n${text}`);
}

let timer = null;
function debouncedFetch() {
  clearTimeout(timer);
  timer = setTimeout(() => fetchMedia(1), 350);
}

async function fetchMedia(page = meta.value.current_page) {
  loading.value = true;
  try {
    const params = { page };
    Object.entries(filters.value).forEach(([k, v]) => { if (v) params[k] = v; });
    const data = await fetchGallery(props.urls.index, params);
    assets.value = data.data;
    meta.value = pageMeta(data);
  } catch (e) {
    toast('error', 'Could not load media', errorMessage(e, 'Please refresh the page.'));
  } finally {
    loading.value = false;
  }
}

function setType(type) { filters.value.type = type; fetchMedia(1); }
function setSort(sort) { filters.value.sort = sort; sortOpen.value = false; fetchMedia(1); }
function clearFilters() { filters.value = { type: '', q: '', platform: '', sort: filters.value.sort }; fetchMedia(1); }
function toggleMenu(id) { menuFor.value = menuFor.value === id ? null : id; }

function compatTitle(asset) {
  const { bad } = compatSummary(asset);
  return bad.map(p => `${PLATFORM_META[p].name}: ${issuesText(asset, p)}`).join('\n');
}

// --- upload ---
function onPicked(e) {
  enqueue(Array.from(e.target.files || []));
  e.target.value = '';
}

function onDrop(e) {
  dragging.value = false;
  enqueue(Array.from(e.dataTransfer?.files || []));
}

let seq = 0;
async function enqueue(files) {
  if (!files.length) return;

  const items = files.map(file => {
    const err = precheck(file);
    const item = { key: ++seq, name: file.name, size: file.size, file, progress: 0, status: err ? 'error' : 'queued', error: err };
    queue.value.push(item);
    return item;
  });

  // One at a time - large videos in parallel would saturate the upload.
  let uploaded = 0;
  for (const item of items.filter(i => i.status === 'queued')) {
    const row = queue.value.find(q => q.key === item.key);
    row.status = 'uploading';
    try {
      await uploadToGallery(props.urls.store, item.file, p => {
        row.progress = p;
        if (p >= 100) row.status = 'processing';
      });
      row.status = 'done';
      uploaded++;
    } catch (e) {
      row.status = 'error';
      row.error = errorMessage(e, 'Upload failed.');
    }
    row.file = null;
  }

  if (uploaded) {
    await fetchMedia(1);
    setTimeout(() => { queue.value = queue.value.filter(q => q.status !== 'done'); }, 2500);
  }
}

// --- delete ---
async function confirmDelete(asset) {
  const text = `"${asset.original_name}" will be removed from your gallery. Posts that already use it keep their copy.`;
  if (window.Swal) {
    const r = await window.Swal.fire({ icon: 'warning', title: 'Delete this media?', text, showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d92d20' });
    return r.isConfirmed;
  }
  return window.confirm(text);
}

async function remove(asset) {
  menuFor.value = null;
  if (!(await confirmDelete(asset))) return;

  deleting.value = asset.id;
  try {
    const { data } = await window.axios.delete(props.urls.destroy.replace('MEDIA_ID', asset.id));
    if (preview.value?.id === asset.id) previewModal?.hide();
    toast('success', data.message);
    const lastOnPage = assets.value.length === 1 && meta.value.current_page > 1;
    await fetchMedia(lastOnPage ? meta.value.current_page - 1 : meta.value.current_page);
  } catch (e) {
    toast('error', 'Could not delete', errorMessage(e, 'Please try again.'));
  } finally {
    deleting.value = null;
  }
}

// --- misc ---
async function copyUrl(asset) {
  menuFor.value = null;
  try {
    await navigator.clipboard.writeText(asset.url);
    toast('success', 'Link copied');
  } catch (e) {
    window.prompt('Copy this link:', asset.url);
  }
}

function openPreview(asset) {
  preview.value = asset;
  if (!previewModal) {
    previewModal = new window.bootstrap.Modal(previewEl.value);
    previewEl.value.addEventListener('hidden.bs.modal', () => {
      previewEl.value.querySelector('video')?.pause();
    });
  }
  previewModal.show();
}

function closeMenus() { menuFor.value = null; sortOpen.value = false; }
onMounted(() => document.addEventListener('click', closeMenus));
onBeforeUnmount(() => document.removeEventListener('click', closeMenus));
</script>

<style scoped>
.mg { --ln: #e6e8ef; --ln-soft: #f0f2f6; --ink: #1b2130; --ink2: #5b6475; --muted: #8a93a3; --brand: #6d4aff; --brand-soft: #f1edff; --radius: 14px; position: relative; }

.mg-btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; height: 40px; padding: 0 1rem; border-radius: 10px; font-size: .86rem; font-weight: 600; border: 1px solid transparent; cursor: pointer; white-space: nowrap; text-decoration: none; }
.mg-btn i { font-size: 1.15rem; }
.mg-btn-lg { height: 46px; padding: 0 1.4rem; font-size: .95rem; border-radius: 12px; }
.mg-btn-brand { background: var(--brand); color: #fff; box-shadow: 0 6px 16px rgba(109, 74, 255, .25); }
.mg-btn-brand:hover { background: #5a36f0; color: #fff; }
.mg-btn-danger { background: #fff; border-color: #f5c2c0; color: #d92d20; }
.mg-btn-danger:hover { background: #fef3f2; }
.mg-link { background: none; border: 0; padding: 0; color: var(--brand); font-size: .8rem; font-weight: 600; cursor: pointer; }
.mg-link:hover { text-decoration: underline; }

/* Header */
.mg-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.4rem 1.6rem; background: #fff; border: 1px solid var(--ln); border-radius: 18px; margin-bottom: 1.25rem; }
.mg-head-id { display: flex; align-items: center; gap: 1rem; min-width: 0; }
.mg-head-mark { width: 52px; height: 52px; border-radius: 14px; background: var(--brand-soft); color: var(--brand); display: inline-flex; align-items: center; justify-content: center; font-size: 1.65rem; flex-shrink: 0; }
.mg-head h4 { margin: 0; font-weight: 800; font-size: 1.6rem; color: var(--ink); letter-spacing: -.01em; }
.mg-head p { margin: .15rem 0 0; color: var(--ink2); font-size: .9rem; }

/* Queue */
.mg-queue { background: #fff; border: 1px solid var(--ln); border-radius: var(--radius); padding: .8rem 1rem; margin-bottom: 1.25rem; }
.mg-queue-h { display: flex; justify-content: space-between; align-items: center; margin-bottom: .4rem; font-size: .85rem; color: var(--ink); }
.mg-queue-row { display: flex; align-items: center; gap: .7rem; padding: .45rem 0; border-top: 1px solid var(--ln-soft); }
.mg-queue-row > i { font-size: 1.25rem; color: var(--brand); flex-shrink: 0; }
.mg-queue-row.done > i { color: #079455; }
.mg-queue-row.error > i { color: #d92d20; }
.mg-queue-body { flex: 1; min-width: 0; }
.mg-queue-name { font-size: .83rem; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mg-queue-name small { color: var(--muted); margin-left: .35rem; }
.mg-queue-err { font-size: .76rem; color: #b42318; }
.mg-queue-note { font-size: .76rem; color: var(--muted); }
.mg-queue-pct { font-size: .76rem; color: var(--ink2); font-variant-numeric: tabular-nums; }
.mg-progress { height: 5px; background: var(--ln-soft); border-radius: 5px; overflow: hidden; margin-top: .3rem; }
.mg-progress span { display: block; height: 100%; background: var(--brand); transition: width .2s; }

/* Card + toolbar */
.mg-card { background: #fff; border: 1px solid var(--ln); border-radius: 18px; padding: 1.1rem 1.1rem .9rem; }
.mg-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .8rem; margin-bottom: 1.1rem; }
.mg-tabs { display: inline-flex; gap: .35rem; padding: .25rem; background: #f4f5f9; border-radius: 12px; }
.mg-tabs button { border: 0; background: transparent; height: 36px; min-width: 76px; padding: 0 1rem; border-radius: 9px; font-size: .86rem; font-weight: 600; color: var(--ink2); cursor: pointer; }
.mg-tabs button.on { background: var(--brand); color: #fff; box-shadow: 0 4px 12px rgba(109, 74, 255, .25); }
.mg-tools { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
.mg-search { position: relative; }
.mg-search i { position: absolute; left: .8rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1.1rem; }
.mg-search .form-control { width: 280px; height: 40px; padding-left: 2.3rem; border-radius: 10px; border-color: var(--ln); font-size: .86rem; }
.mg-platform { width: 170px; height: 40px; border-radius: 10px; border-color: var(--ln); font-size: .86rem; }
.mg .form-control:focus, .mg .form-select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
.mg-sort { position: relative; }
.mg-icon-btn { width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--ln); background: #fff; color: var(--ink2); font-size: 1.2rem; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.mg-icon-btn:hover { border-color: #cfd4de; color: var(--ink); }

.mg-menu { position: absolute; right: 0; top: calc(100% + 6px); z-index: 30; min-width: 180px; background: #fff; border: 1px solid var(--ln); border-radius: 12px; box-shadow: 0 12px 32px rgba(16, 24, 40, .14); padding: .35rem; }
.mg-menu button, .mg-menu a { display: flex; align-items: center; gap: .5rem; width: 100%; background: none; border: 0; padding: .5rem .6rem; border-radius: 8px; font-size: .84rem; color: var(--ink); text-decoration: none; cursor: pointer; text-align: left; }
.mg-menu button:hover, .mg-menu a:hover { background: #f5f6fa; }
.mg-menu button i, .mg-menu a i { width: 16px; font-size: 1.05rem; color: var(--ink2); }
.mg-menu button.on { color: var(--brand); font-weight: 600; }
.mg-menu button.on i { color: var(--brand); }
.mg-menu .danger, .mg-menu .danger i { color: #d92d20; }
.mg-menu hr { margin: .3rem 0; opacity: 1; border-color: var(--ln-soft); }

/* Grid */
.mg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(215px, 1fr)); gap: 1rem; transition: opacity .15s; }
.mg-grid.busy { opacity: .55; pointer-events: none; }
.mg-item { border: 1px solid var(--ln); border-radius: 14px; padding: .55rem; background: #fff; transition: box-shadow .15s, transform .15s; min-width: 0; }
.mg-item:hover { box-shadow: 0 10px 24px rgba(16, 24, 40, .08); transform: translateY(-1px); }
.mg-thumb { position: relative; display: block; width: 100%; aspect-ratio: 16 / 11; border: 0; padding: 0; border-radius: 10px; overflow: hidden; background: #0f1016; cursor: zoom-in; }
.mg-thumb img, .mg-thumb video { width: 100%; height: 100%; object-fit: cover; display: block; }
.mg-badge { position: absolute; top: .5rem; left: .5rem; display: inline-flex; align-items: center; gap: .25rem; background: rgba(255, 255, 255, .92); color: var(--ink); border-radius: 7px; padding: .15rem .45rem; font-size: .7rem; font-weight: 600; }
.mg-play { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 40px; height: 40px; border-radius: 50%; background: rgba(0, 0, 0, .55); color: #fff; font-size: 1.5rem; display: inline-flex; align-items: center; justify-content: center; border: 2px solid rgba(255, 255, 255, .85); }
.mg-dur { position: absolute; right: .5rem; bottom: .5rem; background: rgba(0, 0, 0, .7); color: #fff; font-size: .7rem; font-weight: 600; padding: .1rem .4rem; border-radius: 6px; font-variant-numeric: tabular-nums; }
.mg-body { padding: .6rem .25rem .1rem; }
.mg-name-row, .mg-info-row { display: flex; align-items: center; justify-content: space-between; gap: .4rem; }
.mg-name { font-weight: 600; font-size: .87rem; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
.mg-info { font-size: .75rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mg-actions { position: relative; flex-shrink: 0; }
.mg-icon-sm { width: 26px; height: 26px; border: 0; background: transparent; border-radius: 7px; color: var(--ink2); font-size: 1.15rem; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.mg-icon-sm:hover { background: #f2f4f7; }
.mg-trash { width: 26px; height: 26px; border: 0; background: transparent; border-radius: 7px; color: #d92d20; font-size: 1.1rem; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
.mg-trash:hover { background: #fef3f2; }
.mg-platforms { display: flex; flex-wrap: wrap; gap: .12rem .2rem; margin: .55rem 0 .45rem; }
.mg-compat { display: flex; align-items: center; gap: .3rem; font-size: .74rem; font-weight: 500; }
.mg-compat.ok { color: #079455; }
.mg-compat.warn { color: #b54708; }
.mg-compat span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mg-compat i { font-size: .95rem; flex-shrink: 0; }

.mg-empty { text-align: center; padding: 3.5rem 1rem; color: var(--muted); }
.mg-empty > i { display: block; font-size: 2.6rem; color: #c9cedb; margin-bottom: .5rem; }
.mg-empty strong { display: block; color: var(--ink); margin-bottom: .25rem; }
.mg-empty span { display: block; max-width: 440px; margin: 0 auto; font-size: .84rem; }

.mg-foot { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-top: 1.1rem; color: var(--muted); flex-wrap: wrap; }
.mg-pages { display: flex; gap: .3rem; }
.mg-pages button { min-width: 32px; height: 32px; padding: 0 .5rem; border-radius: 8px; border: 1px solid var(--ln); background: #fff; color: var(--ink2); font-size: .8rem; font-weight: 600; cursor: pointer; }
.mg-pages button.on { background: var(--brand); border-color: var(--brand); color: #fff; }
.mg-pages button:disabled:not(.on) { opacity: .5; cursor: default; }

.mg-drop { position: fixed; inset: 0; z-index: 1080; background: rgba(109, 74, 255, .12); backdrop-filter: blur(2px); display: flex; align-items: center; justify-content: center; pointer-events: none; }
.mg-drop > div { background: #fff; border: 2px dashed var(--brand); border-radius: 18px; padding: 2rem 3rem; text-align: center; color: var(--ink); }
.mg-drop i { display: block; font-size: 2.6rem; color: var(--brand); }

/* Preview */
.mg-preview { display: grid; grid-template-columns: minmax(0, 1fr) 320px; border: 0; border-radius: 16px; overflow: hidden; }
.mg-preview-media { background: #0f1016; display: flex; align-items: center; justify-content: center; min-height: 420px; max-height: 80vh; }
.mg-preview-media img, .mg-preview-media video { max-width: 100%; max-height: 80vh; display: block; }
.mg-preview-side { padding: 1.2rem; overflow-y: auto; max-height: 80vh; }
.mg-preview-side h5 { font-size: 1rem; font-weight: 700; color: var(--ink); word-break: break-all; margin: 0; }
.mg-close { flex-shrink: 0; width: 30px; height: 30px; border-radius: 8px; border: 0; background: transparent; color: var(--ink2); font-size: 1.35rem; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.mg-close:hover { background: #f2f4f7; }
.mg-dl { display: grid; grid-template-columns: 90px 1fr; gap: .35rem .5rem; font-size: .8rem; margin: .9rem 0; }
.mg-dl dt { color: var(--muted); font-weight: 500; }
.mg-dl dd { margin: 0; color: var(--ink); }
.mg-side-h { font-size: .8rem; font-weight: 700; color: var(--ink); margin-bottom: .4rem; }
.mg-compat-list { list-style: none; padding: 0; margin: 0; }
.mg-compat-list li { display: flex; gap: .6rem; align-items: flex-start; padding: .45rem 0; border-bottom: 1px solid var(--ln-soft); }
.mg-compat-list strong { display: block; font-size: .8rem; color: var(--ink); }
.mg-compat-list small { display: block; font-size: .74rem; color: var(--muted); }
.mg-compat-list li.warn small { color: #b54708; }
.mg-fineprint { font-size: .72rem; color: var(--muted); margin: .7rem 0 0; }

@media (max-width: 991.98px) {
  .mg-preview { grid-template-columns: 1fr; }
  .mg-preview-media { min-height: 260px; }
}
@media (max-width: 767.98px) {
  .mg-tools, .mg-search { width: 100%; }
  .mg-search { flex: 1 1 100%; }
  .mg-search .form-control { width: 100%; }
  .mg-platform { flex: 1; width: auto; }
  .mg-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
}

[dir="rtl"] .mg-search i { left: auto; right: .8rem; }
[dir="rtl"] .mg-search .form-control { padding-left: .75rem; padding-right: 2.3rem; }
[dir="rtl"] .mg-menu { right: auto; left: 0; }
[dir="rtl"] .mg-menu button, [dir="rtl"] .mg-menu a { text-align: right; }
[dir="rtl"] .mg-badge { left: auto; right: .5rem; }
[dir="rtl"] .mg-dur { right: auto; left: .5rem; }
</style>
