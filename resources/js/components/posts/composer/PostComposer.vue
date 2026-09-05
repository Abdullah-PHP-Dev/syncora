<template>

  <div class="composer-layout">

    <section class="composer-main">

      <div class="composer-header">
        <div>
          <h4 class="mb-1">Create Post</h4>
          <p class="text-muted mb-0">Create, customize and publish your content across social media platforms.</p>
        </div>
        <button type="button" class="btn-templates" @click="templatesNotice = 'Templates aren\'t wired up yet.'">
          <i class="bx bx-grid-alt me-1"></i> Templates
        </button>
      </div>
      <p v-if="templatesNotice" class="composer-inline-notice">{{ templatesNotice }}</p>

      <div class="composer-card">

        <account-selector
            :accounts="accounts"
            :selected-ids="selectedAccountIds"
            :manage-accounts-url="manageAccountsUrl"
            @update:selectedIds="selectedAccountIds = $event" />

        <hr class="composer-divider">

        <h6 class="composer-section-label">Your Post</h6>

        <label class="composer-field-label">
          Post Title <span class="text-muted fw-normal">(Optional)</span>
        </label>
        <div class="composer-input-wrap">
          <input v-model="title" type="text" class="form-control" maxlength="100" placeholder="Give your post a title">
          <span class="composer-counter">{{ title.length }}/100</span>
        </div>

        <label class="composer-field-label mt-3">Post Description</label>
        <div class="composer-input-wrap">
          <textarea v-model="description" class="form-control" rows="5" maxlength="2200"
                    placeholder="What do you want to share?"></textarea>
          <div class="composer-textarea-actions">
            <button type="button" title="Insert emoji" @click="showEmojiPicker = !showEmojiPicker"><i class="bx bx-smile"></i></button>
            <button type="button" title="Insert hashtag" @click="description += (description.endsWith(' ') || !description ? '' : ' ') + '#'"><i class="bx bx-hash"></i></button>
          </div>
          <span class="composer-counter composer-counter--textarea">{{ description.length }}/2200</span>

          <div v-if="showEmojiPicker" class="composer-emoji-picker">
            <button v-for="emoji in quickEmojis" :key="emoji" type="button" @click="insertEmoji(emoji)">{{ emoji }}</button>
          </div>
        </div>

        <hr class="composer-divider">

        <media-grid
            :items="mediaItems"
            @update:items="mediaItems = $event"
            @update:useAsFirstComment="useAsFirstComment = $event" />

        <hr class="composer-divider">

        <h6 class="composer-section-label">Options</h6>
        <div class="composer-options-bar">
          <button type="button" class="composer-option-pill" :class="{ 'composer-option-pill--active': showLocation }" @click="showLocation = !showLocation">
            <i class="bx bx-map"></i> Add Location
          </button>
          <button type="button" class="composer-option-pill" :class="{ 'composer-option-pill--active': showEvent }" @click="showEvent = !showEvent">
            <i class="bx bx-calendar-event"></i> Add Event
          </button>
          <button type="button" class="composer-option-pill" :class="{ 'composer-option-pill--active': showProduct }" @click="showProduct = !showProduct">
            <i class="bx bx-package"></i> Add Product
          </button>
        </div>

        <input v-if="showLocation" v-model="location" type="text" class="form-control mt-2" placeholder="Location name">
        <input v-if="showEvent" v-model="eventName" type="text" class="form-control mt-2" placeholder="Event name">
        <input v-if="showProduct" v-model="productName" type="text" class="form-control mt-2" placeholder="Product name">

      </div>

      <div v-if="submitError" class="composer-error">{{ submitError }}</div>

      <div class="composer-action-bar">

        <button type="button" class="btn btn-outline-secondary" :disabled="submitting" @click="openScheduler">
          <i class="bx bx-calendar me-1"></i> Schedule for later
        </button>

        <div class="composer-action-bar__right">
          <button type="button" class="btn btn-outline-secondary" :disabled="submitting" @click="saveAsDraft">
            Save as draft
          </button>
          <button type="button" class="btn btn-primary" :disabled="submitting || !canSubmit" @click="submit('now')">
            <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
            Post Now
          </button>
        </div>

      </div>

      <div v-if="showScheduler" class="composer-scheduler">
        <input v-model="scheduleAt" type="datetime-local" class="form-control" :min="minScheduleAt">
        <button type="button" class="btn btn-primary btn-sm" :disabled="submitting || !scheduleAt" @click="submit('schedule')">Confirm schedule</button>
      </div>

    </section>

    <ai-assistant-panel class="composer-ai-column" @generated="onAiGenerated" />

  </div>

</template>

<script setup>

// Real submission target: PostController::store() (admin.posts.store),
// the same endpoint the legacy admin/posts/create.blade.php page already
// uses - it validates platforms[] + selected_pages[platform][] (specific
// connected account ids, not just platform keys) + media[] (a real array,
// unlike quickStore()'s single-file field) + category_id (required - see
// defaultCategoryId below for why there's no picker in this UI).
//
// "Save as draft" and the AI panel are honest gaps, not oversights: this
// app has no draft-saving endpoint (posts.status has a 'draft' value read
// elsewhere for dashboard stats, but nothing ever writes it) and no real
// AI generation endpoint (confirmed against the existing page's own
// fetch("#", ...) placeholder) - see saveAsDraft() and
// AiAssistantPanel.vue for how each surfaces that instead of faking
// success.
import { ref, computed } from 'vue';
import AccountSelector from './AccountSelector.vue';
import MediaGrid from './MediaGrid.vue';
import AiAssistantPanel from './AiAssistantPanel.vue';

const props = defineProps({

  accounts: {
    type: Array,
    default: () => []
  },

  categories: {
    type: Array,
    default: () => []
  },

  storeUrl: {
    type: String,
    required: true
  },

  manageAccountsUrl: {
    type: String,
    default: '#'
  },

  redirectUrl: {
    type: String,
    default: null
  }

});

// Every connected account starts selected (matching the reference design,
// and the reasonable default of "post everywhere I'm connected unless I
// opt out") rather than empty - AccountSelector.vue's checkboxes make
// deselecting any of them a single click.
const selectedAccountIds = ref(props.accounts.map(account => account.id));
const title = ref('');
const description = ref('');
const mediaItems = ref([]);
const useAsFirstComment = ref(false);
const showEmojiPicker = ref(false);
const quickEmojis = ['😀','🎉','🚀','🔥','❤️','👏','✨','📈','💡','🙌'];

const showLocation = ref(false);
const showEvent = ref(false);
const showProduct = ref(false);
const location = ref('');
const eventName = ref('');
const productName = ref('');

const templatesNotice = ref('');
const submitting = ref(false);
const submitError = ref('');
const showScheduler = ref(false);
const scheduleAt = ref('');

// No category picker in this design (the reference image doesn't have
// one) - PostRequest still requires category_id, so this defaults to
// whichever category the create page already loaded first. If the user
// truly has none yet, submission fails loudly with a clear message
// (below) rather than guessing an id that doesn't exist.
const defaultCategoryId = computed(() => props.categories[0]?.id ?? null);

const minScheduleAt = computed(() => {
  const d = new Date(Date.now() + 10 * 60 * 1000);
  d.setSeconds(0, 0);
  return d.toISOString().slice(0, 16);
});

const canSubmit = computed(() => {
  return selectedAccountIds.value.length > 0
    && (description.value.trim() !== '' || mediaItems.value.length > 0);
});

function insertEmoji(emoji) {
  description.value += emoji;
  showEmojiPicker.value = false;
}

function openScheduler() {
  showScheduler.value = !showScheduler.value;
}

// AiAssistantPanel.vue's response always carries title/description/
// hashtags together (one Gemini call generates all three), but `kind`
// says which trigger the user actually clicked - "Generate Hashtags"
// should only touch the description's hashtag line, not silently
// overwrite a title/description the user already wrote by hand.
function onAiGenerated({ kind, title: aiTitle, description: aiDescription, hashtags, image_data_uri: imageDataUri }) {
  if (['all', 'description'].includes(kind) && aiDescription) {
    description.value = aiDescription;
  }

  if (['all', 'title'].includes(kind) && aiTitle) {
    title.value = aiTitle;
  }

  if (['all', 'hashtags'].includes(kind) && hashtags?.length) {
    const tagLine = hashtags.join(' ');
    description.value = description.value.trim()
      ? `${description.value.trim()}\n\n${tagLine}`
      : tagLine;
  }

  if (kind === 'image' && imageDataUri) {
    // buildFormData() below needs a real File per mediaItems entry to
    // append to media[] (the same array PostController::store() already
    // validates for hand-picked uploads) - decoding the data: URI
    // generateAiImage() returns builds one with zero network requests.
    //
    // This used to fetch(image_url) instead - the R2-hosted URL the
    // backend also returns - but cdn.socialeaz.com sends no
    // Access-Control-Allow-Origin header, so that fetch was blocked by
    // CORS from every admin origin (confirmed live: "No
    // 'Access-Control-Allow-Origin' header is present"). Fixing that
    // would mean configuring CORS on the R2 bucket itself, outside this
    // codebase; decoding bytes the backend already sent avoids needing
    // to touch that at all.
    try {
      const [, mimeType, base64] = imageDataUri.match(/^data:([^;]+);base64,(.+)$/) || [];
      const binary = atob(base64);
      const bytes = new Uint8Array(binary.length);
      for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);

      const blob = new Blob([bytes], { type: mimeType || 'image/jpeg' });
      const file = new File([blob], 'ai-generated.jpg', { type: blob.type });

      mediaItems.value = [...mediaItems.value, {
        file,
        type: 'image',
        previewUrl: URL.createObjectURL(blob),
        duration: null,
      }];
    } catch (error) {
      submitError.value = 'The image was generated, but couldn\'t be attached automatically - please try again.';
    }
  }
}

function saveAsDraft() {
  submitError.value = 'Saving a draft isn\'t wired to a backend endpoint yet - there\'s no draft-saving route in this app today. Use "Post Now" or "Schedule for later" instead.';
}

function selectedAccountsByPlatform() {
  const grouped = {};
  props.accounts
    .filter(account => selectedAccountIds.value.includes(account.id))
    .forEach(account => {
      grouped[account.platform] = grouped[account.platform] || [];
      grouped[account.platform].push(account.id);
    });
  return grouped;
}

function buildFormData(mode) {
  const grouped = selectedAccountsByPlatform();
  const formData = new FormData();

  formData.append('content', description.value);
  if (title.value) formData.append('title', title.value);
  formData.append('category_id', defaultCategoryId.value ?? '');

  Object.keys(grouped).forEach(platform => {
    formData.append('platforms[]', platform);
    grouped[platform].forEach(id => formData.append(`selected_pages[${platform}][]`, id));
  });

  mediaItems.value.forEach(item => formData.append('media[]', item.file));

  if (mode === 'schedule') {
    formData.append('schedule_mode', '1');
    formData.append('schedule_at', scheduleAt.value.replace('T', ' ') + ':00');
  } else {
    formData.append('schedule_mode', '0');
  }

  return formData;
}

async function submit(mode) {
  submitError.value = '';

  if (!defaultCategoryId.value) {
    submitError.value = 'No post category exists yet for your account, and this form needs one - create a category first, then try again.';
    return;
  }

  if (!selectedAccountIds.value.length) {
    submitError.value = 'Select at least one account to post to.';
    return;
  }

  if (mode === 'schedule' && !scheduleAt.value) {
    submitError.value = 'Pick a date/time to schedule for.';
    return;
  }

  submitting.value = true;

  try {
    const { data } = await window.axios.post(props.storeUrl, buildFormData(mode), {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    if (!data.success) {
      submitError.value = (data.errors || []).map(e => e.message).filter(Boolean).join(' ') || 'Failed to publish this post.';
      return;
    }

    if (window.Swal) {
      window.Swal.fire('Success!', data.message || 'Post published successfully!', 'success')
        .then(() => { window.location.href = data.redirect_url || props.redirectUrl || window.location.href; });
    } else {
      window.location.href = data.redirect_url || props.redirectUrl || window.location.href;
    }
  } catch (error) {
    submitError.value = error.response?.data?.errors?.map(e => e.message).filter(Boolean).join(' ')
      || error.response?.data?.message
      || 'Something went wrong while publishing. Please try again.';
  } finally {
    submitting.value = false;
  }
}

</script>

<style scoped>

.composer-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 20px;
  align-items: start;
}

@media (max-width: 991px) {
  .composer-layout {
    grid-template-columns: 1fr;
  }
}

.composer-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 16px;
}

.btn-templates {
  border: 1px solid #E5E7EB;
  background: #fff;
  border-radius: 9px;
  padding: 7px 14px;
  font-size: .82rem;
  font-weight: 600;
  color: #374151;
}

.composer-card {
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 16px;
  padding: 22px;
}

.composer-divider {
  border-top: 1px solid #F1F2F6;
  margin: 20px 0;
}

.composer-section-label {
  font-size: .8rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: .03em;
  margin-bottom: 10px;
}

.composer-field-label {
  font-size: .82rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
  display: block;
}

.composer-input-wrap {
  position: relative;
}

.composer-input-wrap textarea {
  resize: vertical;
  padding-bottom: 34px;
}

.composer-counter {
  position: absolute;
  bottom: 8px;
  right: 12px;
  font-size: .7rem;
  color: #9ca3af;
}

.composer-counter--textarea {
  right: 70px;
}

.composer-textarea-actions {
  position: absolute;
  bottom: 6px;
  right: 12px;
  display: flex;
  gap: 4px;
}

.composer-textarea-actions button {
  border: none;
  background: transparent;
  color: #6b7280;
  width: 24px;
  height: 24px;
}

.composer-emoji-picker {
  position: absolute;
  bottom: 40px;
  right: 12px;
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
  padding: 8px;
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  width: 180px;
  box-shadow: 0 8px 24px rgba(0,0,0,.08);
  z-index: 5;
}

.composer-emoji-picker button {
  border: none;
  background: transparent;
  font-size: 18px;
  width: 30px;
  height: 30px;
}

.composer-options-bar {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.composer-option-pill {
  display: flex;
  align-items: center;
  gap: 6px;
  border: 1px solid #E5E7EB;
  background: #fff;
  border-radius: 9px;
  padding: 7px 14px;
  font-size: .8rem;
  color: #374151;
}

.composer-option-pill--active {
  border-color: #7c5cff;
  background: #F5F3FF;
  color: #6D28D9;
}

.composer-inline-notice,
.composer-error {
  font-size: .8rem;
  color: #9a3412;
  background: #FFF7ED;
  border: 1px solid #FED7AA;
  border-radius: 8px;
  padding: 8px 12px;
  margin-bottom: 12px;
}

.composer-action-bar {
  position: sticky;
  bottom: 0;
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 14px;
  padding: 14px 18px;
  margin-top: 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 -4px 16px rgba(0,0,0,.04);
}

.composer-action-bar__right {
  display: flex;
  gap: 10px;
}

.composer-scheduler {
  display: flex;
  gap: 10px;
  margin-top: 10px;
}

.composer-ai-column {
  align-self: start;
}

</style>
