<template>

  <div>

    <h6 class="composer-section-label">Media</h6>

    <div class="media-grid">

      <div v-for="(item, index) in items" :key="item.previewUrl" class="media-thumb" :title="item.name || ''">

        <img v-if="item.posterUrl" :src="item.posterUrl" :alt="`Media ${index + 1}`">
        <video v-else-if="item.type === 'video'" :src="item.previewUrl" muted></video>
        <img v-else :src="item.previewUrl" :alt="`Media ${index + 1}`">

        <span v-if="item.assetId" class="media-thumb__source" title="From Media Gallery"><i class="bx bx-images"></i></span>

        <span v-if="item.type === 'video' && item.duration" class="media-thumb__duration">
          {{ item.duration }}
        </span>

        <button type="button" class="media-thumb__remove" title="Remove" @click="remove(index)">
          <i class="bx bx-x"></i>
        </button>

      </div>

      <div v-if="uploading" class="media-thumb media-thumb--uploading">
        <span class="spinner-border spinner-border-sm"></span>
        <small>{{ uploading.progress }}%</small>
      </div>

      <button type="button" class="media-add-box" @click="openPicker('image/*,video/*')">
        <i class="bx bx-plus"></i>
      </button>

    </div>

    <div class="media-source-actions">
      <button type="button" :disabled="!galleryUrls" @click="$refs.picker.open()">
        <i class="bx bx-images"></i> Attach from Media Gallery
      </button>
      <button type="button" :disabled="!galleryUrls || !!uploading" @click="$refs.galleryUpload.click()">
        <i class="bx bx-cloud-upload"></i> Upload New
      </button>
      <input ref="galleryUpload" type="file" class="d-none" multiple :accept="ACCEPT" @change="onGalleryUpload">
    </div>
    <div v-if="galleryError" class="media-gallery-error"><i class="bx bx-error-circle"></i> {{ galleryError }}</div>

    <media-picker v-if="galleryUrls" ref="picker" :urls="galleryUrls" :platforms="platforms" @select="onGallerySelect" />

    <div class="media-quick-actions">
      <button type="button" title="Add photo" @click="openPicker('image/*', false)"><i class="bx bx-image"></i></button>
      <button type="button" title="Add video" @click="openPicker('video/*', false)"><i class="bx bx-video"></i></button>
      <button type="button" title="Add carousel (multiple photos)" @click="openPicker('image/*', true)"><i class="bx bx-carousel"></i></button>
      <button type="button" title="Add GIF" @click="openPicker('image/gif', false)"><i class="bx bx-file-gif"></i></button>
      <button type="button" title="Add reel" @click="openPicker('video/*', false)"><i class="bx bx-mobile"></i></button>
    </div>

    <input
        ref="fileInput"
        type="file"
        class="d-none"
        :accept="acceptFilter"
        :multiple="multipleAllowed"
        @change="onFilesPicked">

    <div class="form-check form-switch d-flex align-items-center gap-2 mt-3">
      <input id="composerFirstComment" v-model="firstComment" class="form-check-input" type="checkbox" role="switch"
             @change="$emit('update:useAsFirstComment', firstComment)">
      <label class="form-check-label small mb-0" for="composerFirstComment">Use as first comment</label>
      <i class="bx bx-info-circle text-muted" title="Some platforms (Instagram) show hashtags separately when they're sent as the first comment instead of in the caption itself."></i>
    </div>

  </div>

</template>

<script setup>

// Local file previews only - the actual upload happens when the whole
// composer submits (see PostComposer.vue's buildFormData()), matching how
// the rest of this app's media pickers work (nothing here talks to the
// server on its own). PostController::store()'s PostRequest validates
// media as an ARRAY (media.* file) - genuinely multi-file, unlike
// quickStore()'s single `media` field - so this grid's multi-select is
// real, not decorative.
//
// TikTok does reject mixed photo/video or >1 video in a single post
// (TiktokPostService::pushMediaToTiktok() already enforces this
// server-side) - not re-validated here client-side, since this composer
// isn't platform-specific the way that guard is; the server error will
// surface through submitError same as any other platform rejection.
import { ref, computed } from 'vue';
import MediaPicker from '../../media/MediaPicker.vue';
import { ACCEPT, precheck, errorMessage, uploadToGallery } from '../../media/mediaShared';

const props = defineProps({

  items: {
    type: Array,
    default: () => []
  },

  // Media Gallery endpoints (index, store) - null hides the gallery
  // options. Gallery items are carried as { assetId, ... } and submitted
  // as media_asset_ids[] (see PostComposer.vue's buildFormData()), so the
  // server reuses the gallery's R2 file instead of re-uploading it.
  galleryUrls: {
    type: Object,
    default: null
  },

  // Platforms of the currently selected accounts - the picker shows
  // per-platform compatibility for these.
  platforms: {
    type: Array,
    default: () => []
  }

});

const emit = defineEmits(['update:items', 'update:useAsFirstComment']);

const fileInput = ref(null);
const acceptFilter = ref('image/*,video/*');
const multipleAllowed = ref(true);
const firstComment = ref(false);

function openPicker(accept, multiple = true) {
  acceptFilter.value = accept;
  multipleAllowed.value = multiple;
  // Vue needs a tick for the :accept/:multiple bindings above to reach the
  // real <input> before it's clicked, otherwise the picker opens with the
  // PREVIOUS filter still applied.
  requestAnimationFrame(() => fileInput.value?.click());
}

function onFilesPicked(event) {
  const files = Array.from(event.target.files || []);
  const next = [...props.items];

  files.forEach(file => {
    next.push({
      file,
      type: file.type.startsWith('video/') ? 'video' : 'image',
      previewUrl: URL.createObjectURL(file),
      // Real duration isn't known until the browser decodes the video -
      // left blank rather than showing a fake number; the reference
      // design's "0:30" badge is cosmetic for already-uploaded media, not
      // something derivable synchronously from a freshly-picked File.
      duration: null,
    });
  });

  emit('update:items', next);
  event.target.value = '';
}

const uploading = ref(null);
const galleryError = ref('');

function assetToItem(asset) {
  return {
    assetId: asset.id,
    name: asset.original_name,
    type: asset.type,
    previewUrl: asset.url,
    posterUrl: asset.type === 'video' ? asset.thumbnail_url : null,
    duration: asset.duration ? `${Math.floor(asset.duration / 60)}:${String(Math.round(asset.duration % 60)).padStart(2, '0')}` : null,
  };
}

function onGallerySelect(assets) {
  galleryError.value = '';
  const existing = new Set(props.items.filter(i => i.assetId).map(i => i.assetId));
  emit('update:items', [...props.items, ...assets.filter(a => !existing.has(a.id)).map(assetToItem)]);
}

// "Upload New": saved to the Media Gallery first (so it's reusable and
// validated server-side), then attached like any gallery pick.
async function onGalleryUpload(event) {
  const files = Array.from(event.target.files || []);
  event.target.value = '';
  galleryError.value = '';
  let next = [...props.items];

  for (const file of files) {
    const bad = precheck(file);
    if (bad) { galleryError.value = bad; continue; }

    uploading.value = { progress: 0 };
    try {
      const asset = await uploadToGallery(props.galleryUrls.store, file, p => { uploading.value.progress = p; });
      next = [...next, assetToItem(asset)];
      emit('update:items', next);
    } catch (error) {
      galleryError.value = errorMessage(error, `Upload of "${file.name}" failed.`);
    } finally {
      uploading.value = null;
    }
  }
}

function remove(index) {
  const item = props.items[index];
  if (item?.previewUrl && item.previewUrl.startsWith('blob:')) {
    URL.revokeObjectURL(item.previewUrl);
  }
  emit('update:items', props.items.filter((_, i) => i !== index));
}

defineExpose({});

</script>

<style scoped>

.composer-section-label {
  font-size: .8rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: .03em;
  margin-bottom: 10px;
}

.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 12px;
}

.media-thumb {
  position: relative;
  aspect-ratio: 1;
  border-radius: 12px;
  overflow: hidden;
  background: #0f0f14;
}

.media-thumb img,
.media-thumb video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.media-thumb__duration {
  position: absolute;
  bottom: 6px;
  right: 6px;
  background: rgba(0,0,0,.65);
  color: #fff;
  font-size: .68rem;
  padding: 1px 6px;
  border-radius: 6px;
}

.media-thumb__remove {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: none;
  background: rgba(0,0,0,.55);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.media-add-box {
  aspect-ratio: 1;
  border: 1.5px dashed #d1d5db;
  border-radius: 12px;
  background: #fafafa;
  color: #9ca3af;
  font-size: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.media-add-box:hover {
  border-color: #7c5cff;
  color: #7c5cff;
}

.media-thumb__source {
  position: absolute;
  top: 6px;
  left: 6px;
  width: 22px;
  height: 22px;
  border-radius: 6px;
  background: rgba(109,74,255,.9);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .8rem;
}

.media-thumb--uploading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background: #f5f3ff;
  color: #7c5cff;
  border: 1.5px dashed #c4b5fd;
}

.media-source-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

.media-source-actions button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 14px;
  border-radius: 9px;
  border: 1px solid #d9d0ff;
  background: #fff;
  color: #6d4aff;
  font-size: .82rem;
  font-weight: 600;
}

.media-source-actions button:hover:not(:disabled) {
  background: #f1edff;
}

.media-source-actions button:disabled {
  opacity: .55;
}

.media-gallery-error {
  margin-top: 8px;
  font-size: .8rem;
  color: #b42318;
  display: flex;
  align-items: center;
  gap: 6px;
}

.media-quick-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}

.media-quick-actions button {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  border: 1px solid #E5E7EB;
  background: #fff;
  color: #4b5563;
  display: flex;
  align-items: center;
  justify-content: center;
}

.media-quick-actions button:hover {
  border-color: #7c5cff;
  color: #7c5cff;
}

</style>
