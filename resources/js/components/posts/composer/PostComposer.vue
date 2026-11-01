<template>

  <div class="composer-layout" :dir="i18n.dir">

    <section class="composer-main">

      <div class="composer-header">
        <div>
          <h4 class="mb-1">{{ t('title') }}</h4>
          <p class="text-muted mb-0">{{ t('subtitle') }}</p>
        </div>
        <button type="button" class="btn btn-sm btn-templates" @click="templatesNotice = t('templates_soon')">
          <i class="bx bx-grid-alt"></i> {{ t('templates') }}
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

        <h6 class="composer-section-label">{{ t('your_post') }}</h6>

        <label class="composer-field-label">
          {{ t('post_title') }} <span class="text-muted fw-normal">{{ t('optional') }}</span>
        </label>
        <div class="composer-input-wrap">
          <input v-model="title" type="text" class="form-control" maxlength="100" :placeholder="t('title_placeholder')">
          <span class="composer-counter">{{ title.length }}/100</span>
        </div>

        <label class="composer-field-label mt-3" for="composerCategory">{{ t('category') }}</label>
        <div class="composer-category">
          <select v-if="!addingCategory" id="composerCategory" v-model="categoryId" class="form-control">
            <option v-if="!categoryOptions.length" :value="null" disabled>{{ t('no_categories') }}</option>
            <option v-for="category in categoryOptions" :key="category.id" :value="category.id">{{ category.name }}</option>
          </select>
          <template v-else>
            <input ref="newCategoryInput" v-model="newCategoryName" type="text" class="form-control" maxlength="80"
                   :placeholder="t('new_category_placeholder')" @keydown.enter.prevent="createCategory" @keydown.esc="addingCategory = false">
          </template>
          <button v-if="!addingCategory" type="button" class="composer-category-btn" @click="startNewCategory">
            <i class="bx bx-plus"></i> {{ t('new') }}
          </button>
          <template v-else>
            <button type="button" class="btn btn-primary btn-sm composer-category-add" :disabled="savingCategory || !newCategoryName.trim()" @click="createCategory">
              <span v-if="savingCategory" class="spinner-border spinner-border-sm"></span>
              <template v-else>{{ t('add') }}</template>
            </button>
            <button type="button" class="composer-category-btn" @click="addingCategory = false">{{ t('cancel') }}</button>
          </template>
        </div>
        <p v-if="categoryError" class="composer-category-error">{{ categoryError }}</p>

        <label class="composer-field-label mt-3">{{ t('description') }}</label>
        <div class="composer-input-wrap">
          <textarea v-model="description" class="form-control" rows="5" :maxlength="textLimits.platforms ? 63206 : 2200"
                    :placeholder="t('description_placeholder')" dir="auto"></textarea>
          <div class="composer-textarea-actions">
            <button type="button" :title="t('insert_emoji')" @click="showEmojiPicker = !showEmojiPicker"><i class="bx bx-smile"></i></button>
            <button type="button" :title="t('insert_hashtag')" @click="description += (description.endsWith(' ') || !description ? '' : ' ') + '#'"><i class="bx bx-hash"></i></button>
          </div>
          <span class="composer-counter composer-counter--textarea">{{ codePoints(description) }}</span>

          <div v-if="showEmojiPicker" class="composer-emoji-picker">
            <button v-for="emoji in quickEmojis" :key="emoji" type="button" @click="insertEmoji(emoji)">{{ emoji }}</button>
          </div>
        </div>

        <!-- Each selected platform's own limit (PlatformTextLimits). -->
        <div v-if="platformLimits.length" class="composer-limits">
          <span v-for="item in platformLimits" :key="item.platform" class="composer-limit"
                :class="{ 'is-over': item.problems.length, 'is-near': !item.problems.length && item.used / item.limit > 0.9 }"
                :title="item.problems.join(' ') || t('limit_title', { platform: item.label, used: num(item.used), limit: num(item.limit), unit: t('units.' + item.unit) })">
            <i class="bx" :class="item.problems.length ? 'bx-error-circle' : 'bx-check-circle'"></i>
            {{ item.label }} {{ num(item.used) }}/{{ num(item.limit) }}
          </span>
        </div>
        <p v-if="limitProblems.length" class="composer-limit-error">{{ limitProblems.join(' ') }}</p>

        <hr class="composer-divider">

        <media-grid
            :items="mediaItems"
            :gallery-urls="galleryUrls"
            :platforms="selectedPlatforms"
            @update:items="mediaItems = $event"
            @update:useAsFirstComment="useAsFirstComment = $event" />

        <hr class="composer-divider">

        <h6 class="composer-section-label">{{ t('options') }}</h6>
        <div class="composer-options-bar">
          <button type="button" class="composer-option-pill" :class="{ 'composer-option-pill--active': showLocation }" @click="showLocation = !showLocation">
            <i class="bx bx-map"></i> {{ t('add_location') }}
          </button>
          <button type="button" class="composer-option-pill" :class="{ 'composer-option-pill--active': showEvent }" @click="showEvent = !showEvent">
            <i class="bx bx-calendar-event"></i> {{ t('add_event') }}
          </button>
          <button type="button" class="composer-option-pill" :class="{ 'composer-option-pill--active': showProduct }" @click="showProduct = !showProduct">
            <i class="bx bx-package"></i> {{ t('add_product') }}
          </button>
        </div>

        <input v-if="showLocation" v-model="location" type="text" class="form-control mt-2" :placeholder="t('location_placeholder')" dir="auto">
        <input v-if="showEvent" v-model="eventName" type="text" class="form-control mt-2" :placeholder="t('event_placeholder')" dir="auto">
        <input v-if="showProduct" v-model="productName" type="text" class="form-control mt-2" :placeholder="t('product_placeholder')" dir="auto">

      </div>

      <div v-if="errorPanel" class="composer-error" :class="'is-' + errorPanel.kind" role="alert">
        <div class="composer-error-head">
          <i class="bx" :class="errorPanel.icon"></i>
          <strong>{{ errorPanel.title }}</strong>
          <button type="button" class="composer-error-close" :aria-label="t('dismiss')" @click="clearErrors"><i class="bx bx-x"></i></button>
        </div>
        <ul class="composer-error-list">
          <li v-for="(item, index) in errorPanel.items" :key="index">
            <span v-if="item.platform" class="composer-error-tag" :style="{ '--tone': platformMeta(item.platform).color }">
              <i v-if="platformMeta(item.platform).icon" class="bx" :class="platformMeta(item.platform).icon"></i>{{ platformMeta(item.platform).label }}<template v-if="item.account"> · {{ item.account }}</template>
            </span>
            <span v-else-if="item.field" class="composer-error-tag is-field">{{ item.field }}</span>
            <span class="composer-error-text">{{ item.message }}</span>
          </li>
        </ul>
        <p v-if="errorPanel.hint" class="composer-error-hint">{{ errorPanel.hint }}</p>
      </div>

      <div class="composer-action-bar">

        <button type="button" class="btn btn-sm composer-btn-secondary" :disabled="submitting" @click="openScheduler">
          <i class="bx bx-calendar"></i> {{ t('schedule_later') }}
        </button>

        <div class="composer-action-bar__right">
          <button type="button" class="btn btn-sm composer-btn-secondary" :disabled="submitting" @click="saveAsDraft">
            {{ t('save_draft') }}
          </button>
          <button type="button" class="btn btn-primary btn-sm" :disabled="submitting || !canSubmit" @click="submit('now')">
            <span v-if="submitting" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-send"></i>
            {{ t('post_now') }}
          </button>
        </div>

      </div>

      <div v-if="showScheduler" class="composer-scheduler">
        <input v-model="scheduleAt" type="datetime-local" class="form-control" :min="minScheduleAt">
        <button type="button" class="btn btn-primary btn-sm" :disabled="submitting || !scheduleAt" @click="submit('schedule')">{{ t('confirm_schedule') }}</button>
      </div>

    </section>

    <ai-assistant-panel class="composer-ai-column" :urls="aiUrls" @generated="onAiGenerated" />

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
import { ref, computed, onMounted, onBeforeUnmount, nextTick, provide } from 'vue';
import AccountSelector from './AccountSelector.vue';
import MediaGrid from './MediaGrid.vue';
import AiAssistantPanel from './AiAssistantPanel.vue';
import { createTranslator } from '../../ads/facebook/i18n';
import { setMediaTranslator } from '../../media/mediaShared';

const props = defineProps({

  accounts: {
    type: Array,
    default: () => []
  },

  categories: {
    type: Array,
    default: () => []
  },

  // Prefill from an existing post ("Duplicate" on the preview page).
  prefill: {
    type: Object,
    default: null
  },

  categoryStoreUrl: {
    type: String,
    default: ''
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
  },

  // Media Gallery endpoints (index, store) for MediaGrid's picker.
  galleryUrls: {
    type: Object,
    default: null
  },

  // AI assistant endpoints (content, image) for AiAssistantPanel.
  aiUrls: {
    type: Object,
    default: null
  },

  // { platforms: {key: {label, content, ...}}, accounts: {id: contentLimit}, title }
  // from PlatformTextLimits - each platform's API text limits.
  textLimits: {
    type: Object,
    default: () => ({})
  },

  // __('composer') / __('media_gallery') for the current locale.
  messages: { type: Object, default: () => ({}) },
  mediaMessages: { type: Object, default: () => ({}) },
  locale: { type: String, default: 'en' },

});

const i18n = createTranslator(props.messages, props.locale);
const { t } = i18n;
provide('composerI18n', i18n);
// The Media picker and upload checks on this page read media_gallery.php.
setMediaTranslator(createTranslator(props.mediaMessages, props.locale).t);
const num = (n) => Number(n).toLocaleString(i18n.intl);

// Every connected account starts selected (matching the reference design,
// and the reasonable default of "post everywhere I'm connected unless I
// opt out") rather than empty - AccountSelector.vue's checkboxes make
// deselecting any of them a single click.
// Returning from connecting an account (?connected=<id>, see
// ReturnToOrigin middleware): start with just that account selected.
const connectedId = Number(new URLSearchParams(window.location.search).get('connected')) || null;
const selectedAccountIds = ref(
  connectedId && props.accounts.some(account => account.id === connectedId)
    ? [connectedId]
    : props.accounts.map(account => account.id)
);
const title = ref(props.prefill?.title || '');
const description = ref(props.prefill?.content || '');
const mediaItems = ref([]);
const selectedPlatforms = computed(() => [...new Set(
  props.accounts.filter(account => selectedAccountIds.value.includes(account.id)).map(account => account.platform)
)]);
// ---- Text limits per platform (mirrors App\Support\PlatformTextLimits) ----
// Characters as the platforms count them: code points, not UTF-16 units.
function codePoints(text) {
  return [...(text || '')].length;
}

// X's weighted length (twitter-text v3): links 23, most scripts 1, CJK/emoji 2.
function xLength(text) {
  const normalized = (text || '').normalize('NFC');
  const urls = normalized.match(/https?:\/\/\S+/g) || [];
  let length = urls.length * 23;
  for (const char of normalized.replace(/https?:\/\/\S+/g, '')) {
    const code = char.codePointAt(0);
    const light = code <= 4351 || (code >= 8192 && code <= 8205) || (code >= 8208 && code <= 8223) || (code >= 8242 && code <= 8247);
    length += light ? 1 : 2;
  }
  return length;
}

const platformLimits = computed(() => {
  const platforms = props.textLimits?.platforms || {};
  const text = description.value || '';

  return selectedPlatforms.value.filter(key => platforms[key]).map(key => {
    const rules = platforms[key];
    // The strictest selected account (eg. one X account without Premium).
    const accountLimits = props.accounts
      .filter(account => account.platform === key && selectedAccountIds.value.includes(account.id))
      .map(account => props.textLimits.accounts?.[account.id])
      .filter(Boolean);
    const limit = accountLimits.length ? Math.min(...accountLimits) : rules.content;
    const used = rules.weighted ? xLength(text) : rules.content_bytes ? new TextEncoder().encode(text).length : codePoints(text);
    const unit = rules.content_bytes ? 'bytes' : 'characters';
    const p = { platform: rules.label, limit: num(limit), used: num(used), unit: t('units.' + unit) };

    const problems = [];
    if (used > limit) {
      problems.push(t('limit_over', p) + (key === 'x' && limit === 280 ? t('x_premium') : ''));
    }
    if (rules.hashtags && (text.match(/(^|\s)#[\p{L}\p{N}_]+/gu) || []).length > rules.hashtags) {
      problems.push(t('limit_hashtags', { platform: rules.label, count: rules.hashtags }));
    }
    if (rules.mentions && (text.match(/(^|\s)@[\w.]+/gu) || []).length > rules.mentions) {
      problems.push(t('limit_mentions', { platform: rules.label, count: rules.mentions }));
    }
    if (rules.no_angle_brackets && /[<>]/.test(text + title.value)) {
      problems.push(t('limit_brackets', { platform: rules.label }));
    }

    return { platform: key, label: rules.label, used, limit, unit, problems };
  });
});

const limitProblems = computed(() => platformLimits.value.flatMap(item => item.problems));

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
const published = ref(false);

// Unsaved-changes guard: only once something was actually written or
// attached, never after a successful publish/schedule.
const hasUnsavedWork = computed(() => !published.value && (title.value.trim() !== '' || description.value.trim() !== '' || mediaItems.value.length > 0));
const warnBeforeLeaving = (event) => {
  if (!hasUnsavedWork.value) return;
  event.preventDefault();
  event.returnValue = '';
};
onMounted(() => window.addEventListener('beforeunload', warnBeforeLeaving));
onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeLeaving));
// ---- Errors: validation (Laravel {field: [messages]}), platform/API
// failures ([{platform, account, message}]), our own pre-publish checks,
// and network/server problems - each shown as a titled, itemised panel.
const errorPanel = ref(null);

const PLATFORM_META = {
  facebook: { label: 'Facebook', icon: 'bxl-facebook-circle', color: '#1877F2' },
  instagram: { label: 'Instagram', icon: 'bxl-instagram', color: '#E1306C' },
  x: { label: 'X', icon: null, color: '#111111' },
  linkedin: { label: 'LinkedIn', icon: 'bxl-linkedin-square', color: '#0A66C2' },
  tiktok: { label: 'TikTok', icon: 'bxl-tiktok', color: '#111111' },
  youtube: { label: 'YouTube', icon: 'bxl-youtube', color: '#FF0000' },
  threads: { label: 'Threads', icon: 'bx-at', color: '#111111' },
  pinterest: { label: 'Pinterest', icon: 'bxl-pinterest', color: '#E60023' },
  google: { label: 'Google Business', icon: 'bxl-google', color: '#4285F4' },
  whatsapp: { label: 'WhatsApp', icon: 'bxl-whatsapp', color: '#25D366' },
};
function platformMeta(key) {
  return PLATFORM_META[key] || { label: key, icon: 'bx-globe', color: '#6b7280' };
}

const fieldLabel = (key) => { const v = t('fields.' + key); return v === 'fields.' + key ? null : v; };
// "facebook.pages" -> the platform; "media.0" -> "Media".
function fieldInfo(key) {
  const [root] = String(key).split('.');
  if (PLATFORM_META[root]) return { platform: root };
  return { field: fieldLabel(root) || root.replace(/_/g, ' ').replace(/^./, c => c.toUpperCase()) };
}
// Messages that start with a platform's name ("X allows 280 ...") get its tag.
function platformFromMessage(message) {
  return Object.keys(PLATFORM_META).find(key => String(message).startsWith(PLATFORM_META[key].label + ' '));
}

const KINDS = {
  check: { title: t('kinds.check'), icon: 'bx-info-circle' },
  validation: { title: t('kinds.validation'), icon: 'bx-error-circle' },
  publish: { title: t('kinds.publish'), icon: 'bx-error' },
  network: { title: t('kinds.network'), icon: 'bx-wifi-off' },
};
function showErrors(kind, items, hint = null) {
  const clean = items.filter(item => item && item.message);
  if (!clean.length) return;
  errorPanel.value = { kind, ...KINDS[kind], items: clean, hint };
  // The panel sits above the action bar - bring it into view.
  nextTick(() => document.querySelector('.composer-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
}
function showError(kind, message, extra = {}) {
  showErrors(kind, [{ message, ...extra }]);
}
function clearErrors() {
  errorPanel.value = null;
}

// Any axios failure -> a readable panel.
function showResponseErrors(error) {
  const response = error.response;
  if (!response) {
    return showError('network', t('errors.network'));
  }

  const data = response.data || {};
  const errors = data.errors;

  if (errors && !Array.isArray(errors) && typeof errors === 'object') {
    // Laravel validation: {field: [messages]}
    const items = Object.entries(errors).flatMap(([key, messages]) => [].concat(messages).map(message => {
      const info = fieldInfo(key);
      const platform = info.platform || platformFromMessage(message);
      return platform ? { platform, message } : { field: info.field, message };
    }));
    return showErrors('validation', items);
  }

  if (Array.isArray(errors) && errors.length) {
    // Publishing: [{platform, account|page_name, message}]
    const items = errors.map(e => ({
      platform: e.platform || platformFromMessage(e.message),
      account: e.account || e.page_name || null,
      message: e.message,
    }));
    return showErrors('publish', items, t('errors.publish_hint'));
  }

  if (response.status === 413) {
    return showError('validation', t('errors.too_large'), { field: t('fields.media') });
  }
  if (response.status === 419) {
    return showError('network', t('errors.session'));
  }
  if (response.status >= 500) {
    return showError('network', t('errors.server'));
  }

  return showError('publish', data.message || t('errors.generic'));
}
const showScheduler = ref(false);
const scheduleAt = ref('');

// Category (PostRequest requires one): the seller's categories, newest
// first - PostController::composer() guarantees at least a "General" one.
// "+ New" creates another inline via admin.categories.store.
const categoryOptions = ref([...props.categories]);
const categoryId = ref(
  props.categories.some(c => c.id === props.prefill?.category_id) ? props.prefill.category_id : (props.categories[0]?.id ?? null)
);
const defaultCategoryId = computed(() => categoryId.value);
const addingCategory = ref(false);
const newCategoryName = ref('');
const savingCategory = ref(false);
const categoryError = ref('');
const newCategoryInput = ref(null);

function startNewCategory() {
  addingCategory.value = true;
  newCategoryName.value = '';
  categoryError.value = '';
  setTimeout(() => newCategoryInput.value?.focus(), 0);
}

async function createCategory() {
  const name = newCategoryName.value.trim();
  if (!name || savingCategory.value || !props.categoryStoreUrl) return;

  savingCategory.value = true;
  categoryError.value = '';

  try {
    const { data } = await window.axios.post(props.categoryStoreUrl, { name, description: name });
    categoryOptions.value = [{ id: data.data.id, name: data.data.name }, ...categoryOptions.value];
    categoryId.value = data.data.id;
    addingCategory.value = false;
  } catch (error) {
    categoryError.value = error.response?.data?.message || t('category_failed');
  } finally {
    savingCategory.value = false;
  }
}

const minScheduleAt = computed(() => {
  const d = new Date(Date.now() + 10 * 60 * 1000);
  d.setSeconds(0, 0);
  return d.toISOString().slice(0, 16);
});

const canSubmit = computed(() => {
  return selectedAccountIds.value.length > 0
    && !!categoryId.value
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
      showError('check', t('errors.ai_image_attach'), { field: t('fields.media') });
    }
  }
}

function saveAsDraft() {
  showError('check', t('errors.drafts'));
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
    // PostRequest's validation rule reads {platform}.pages (raw field
    // {platform}[pages][]), then builds its own 'selected_pages'
    // structure internally for PostController::store() to read -
    // sending 'selected_pages[...]' directly here skips that rule
    // entirely, so the backend sees platforms[] with no matching
    // {platform}.pages data at all and fails every selected platform
    // with "field is required" (confirmed live: exactly the error
    // reported after submitting this form for real).
    grouped[platform].forEach(id => formData.append(`${platform}[pages][]`, id));
  });

  // Local picks are uploaded as files; Media Gallery picks are sent by id
  // and reuse the gallery's stored file server-side.
  mediaItems.value.forEach(item => {
    if (item.assetId) formData.append('media_asset_ids[]', item.assetId);
    else formData.append('media[]', item.file);
  });

  if (mode === 'schedule') {
    formData.append('schedule_mode', '1');
    formData.append('schedule_at', scheduleAt.value.replace('T', ' ') + ':00');
    // The time is as typed; the server converts it from this zone.
    formData.append('timezone', (window.Intl && Intl.DateTimeFormat().resolvedOptions().timeZone) || '');
  } else {
    formData.append('schedule_mode', '0');
  }

  return formData;
}

async function submit(mode) {
  clearErrors();

  if (!defaultCategoryId.value) {
    showError('check', t('errors.category'), { field: t('fields.category_id') });
    return;
  }

  if (!selectedAccountIds.value.length) {
    showError('check', t('errors.accounts'), { field: t('fields.platforms') });
    return;
  }

  // YouTube only publishes videos - catch it here instead of at publish time.
  if (selectedPlatforms.value.includes('youtube') && !mediaItems.value.some(item => item.type === 'video')) {
    showError('check', t('errors.youtube_video'), { platform: 'youtube' });
    return;
  }

  if (limitProblems.value.length) {
    showErrors('check', platformLimits.value.flatMap(item => item.problems.map(message => ({ platform: item.platform, message }))), t('errors.limits_hint'));
    return;
  }

  if (mode === 'schedule' && !scheduleAt.value) {
    showError('check', t('errors.schedule_date'), { field: t('fields.schedule_at') });
    return;
  }

  submitting.value = true;

  try {
    const { data } = await window.axios.post(props.storeUrl, buildFormData(mode), {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    if (!data.success) {
      showResponseErrors({ response: { status: 422, data } });
      return;
    }

    published.value = true;

    if (window.Swal) {
      window.Swal.fire(t('success'), data.message || t('published'), 'success')
        .then(() => { window.location.href = data.redirect_url || props.redirectUrl || window.location.href; });
    } else {
      window.location.href = data.redirect_url || props.redirectUrl || window.location.href;
    }
  } catch (error) {
    showResponseErrors(error);
  } finally {
    submitting.value = false;
  }
}

</script>

<style scoped>

/* Theme .btn .btn-primary .btn-sm (as on the campaigns list) with the homepage brand gradient. */
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap; }
.btn-primary { background-image: linear-gradient(105deg, #004cff, #5235ff); border-color: transparent; color: #fff; }
.btn-primary:hover:not(:disabled) { background-image: linear-gradient(105deg, #0043e0, #4429f0); color: #fff; }

.composer-btn-secondary { background: #fff; border-color: #E5E7EB; color: #374151; }
.composer-btn-secondary:hover:not(:disabled) { border-color: #b9ccff; color: #004cff; background: #fff; }

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
  border-color: #E5E7EB;
  background: #fff;
  color: #374151;
}

.btn-templates:hover {
  border-color: #b9ccff;
  color: #004cff;
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

.composer-category {
  display: flex;
  gap: 8px;
}

.composer-category .form-select,
.composer-category .form-control {
  flex: 1;
  min-width: 0;
  height: 40px;
  padding-top: 0;
  padding-bottom: 0;
  font-size: .9rem;
}

/* form-control drops a select's native arrow (appearance: none) - draw one. */
.composer-category select.form-control {
  padding-inline-end: 2.25rem;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%238a92a3' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right .85rem center;
  background-size: 12px 10px;
  cursor: pointer;
}

[dir="rtl"] .composer-category select.form-control {
  background-position: left .85rem center;
}

.composer-category-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 40px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #374151;
  font-size: .82rem;
  font-weight: 600;
  white-space: nowrap;
}

.composer-category-btn:hover:not(:disabled) {
  background: #f9fafb;
}

.composer-category-btn.is-primary {
  background: #004cff;
  border-color: #004cff;
  color: #fff;
}

.composer-category-btn:disabled {
  opacity: .55;
}

.composer-category-error {
  margin: 6px 0 0;
  font-size: .78rem;
  color: #dc2626;
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

.composer-limits {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.composer-limit {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  color: #4b5563;
  font-size: 12px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.composer-limit i {
  font-size: 14px;
  color: #10b981;
}

.composer-limit.is-near {
  border-color: #fcd34d;
  background: #fffbeb;
  color: #92400e;
}

.composer-limit.is-near i {
  color: #f59e0b;
}

.composer-limit.is-over {
  border-color: #fecaca;
  background: #fef2f2;
  color: #b91c1c;
}

.composer-limit.is-over i {
  color: #dc2626;
}

.composer-limit-error {
  margin: 6px 0 0;
  color: #b91c1c;
  font-size: 13px;
}

.composer-input-wrap > input.form-control {
  padding-inline-end: 64px; /* room for the 0/100 counter */
}

.composer-counter {
  position: absolute;
  bottom: 8px;
  inset-inline-end: 12px;
  font-size: .7rem;
  color: #9ca3af;
}

.composer-counter--textarea {
  inset-inline-end: 70px;
}

.composer-textarea-actions {
  position: absolute;
  bottom: 6px;
  inset-inline-end: 12px;
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
  inset-inline-end: 12px;
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
  border-color: #004cff;
  background: #eef3ff;
  color: #0039cc;
}

.composer-inline-notice,
.composer-error {
  --err: #b91c1c;
  --err-bg: #fef2f2;
  --err-line: #fecaca;
  font-size: .84rem;
  color: #374151;
  background: var(--err-bg);
  border: 1px solid var(--err-line);
  border-left: 4px solid var(--err);
  border-radius: 10px;
  padding: 10px 12px;
  margin-bottom: 12px;
}

.composer-error.is-check {
  --err: #b45309;
  --err-bg: #fffbeb;
  --err-line: #fde68a;
}

.composer-error.is-network {
  --err: #4b5563;
  --err-bg: #f9fafb;
  --err-line: #e5e7eb;
}

.composer-error-head {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--err);
}

.composer-error-head i {
  font-size: 18px;
}

.composer-error-close {
  margin-inline-start: auto;
  border: 0;
  background: transparent;
  color: #6b7280;
  font-size: 18px;
  line-height: 1;
  padding: 0;
}

.composer-error-list {
  list-style: none;
  margin: 8px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.composer-error-list li {
  display: flex;
  align-items: baseline;
  gap: 8px;
  flex-wrap: wrap;
}

.composer-error-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 1px 8px;
  border-radius: 999px;
  background: #fff;
  border: 1px solid var(--err-line);
  color: var(--tone, #374151);
  font-size: .75rem;
  font-weight: 600;
  white-space: nowrap;
}

.composer-error-tag i {
  font-size: 14px;
}

.composer-error-tag.is-field {
  color: #374151;
}

.composer-error-text {
  flex: 1;
  min-width: 200px;
  word-break: break-word;
}

.composer-error-hint {
  margin: 8px 0 0;
  color: #6b7280;
  font-size: .78rem;
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
