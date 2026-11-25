<template>

  <div class="notif-center">

    <button
        type="button"
        class="admin-navbar-icon admin-notification-button"
        title="Notifications"
        @click="toggleOpen">

      <i class="bx bx-bell"></i>

      <span v-if="count > 0" class="admin-notification-dot"></span>

    </button>

    <div v-if="open" class="notif-dropdown">

      <div class="notif-dropdown-header">
        <span>{{ t('title') }}</span>
        <span class="notif-header-actions">
          <span v-if="count > 0" class="notif-count-pill">{{ displayCount }}</span>
          <a v-if="settingsUrl" :href="settingsUrl" class="notif-header-icon" :title="t('settings')" :aria-label="t('settings')"><i class="bx bx-cog"></i></a>
        </span>
      </div>

      <div v-if="loading && !items.length" class="notif-empty">{{ t('loading') }}</div>

      <div v-else-if="!items.length" class="notif-empty">{{ t('empty') }}</div>

      <a
          v-for="item in items"
          :key="item.type + '-' + item.id"
          href="#"
          class="notif-item"
          @click.prevent="openItem(item)">

        <span v-if="item.type === 'platform'" class="notif-platform-icon" :class="'tone-' + item.tone">
          <i :class="'bx ' + item.icon"></i>
        </span>

        <account-avatar-badge
            v-else
            :avatar-url="item.avatar"
            :icon="platformIcon(item.platform)"
            :color="platformColor(item.platform)"
            :size="36" />

        <span class="notif-item-body">
          <span class="notif-item-top">
            <span v-if="item.type !== 'platform'" class="notif-item-type-icon" :class="item.type">
              <i :class="typeIcon(item.type)"></i>
            </span>
            <span v-else-if="item.priority === 0" class="notif-critical-dot" aria-hidden="true"></span>
            <span class="notif-item-author">{{ item.author }}<template v-if="item.count > 1"> (×{{ item.count }})</template></span>
            <span class="notif-item-time">{{ timeAgo(item.created_at) }}</span>
          </span>
          <span v-if="item.preview" class="notif-item-preview">{{ item.preview }}</span>
          <span v-if="item.workspace" class="notif-item-workspace"><bdi>{{ item.workspace }}</bdi></span>
        </span>

      </a>

      <div class="notif-dropdown-footer">
        <button v-if="readAllUrl && count > 0" type="button" class="notif-footer-btn" @click="readAll">{{ t('mark_all_read') }}</button>
        <a v-if="centerUrl" :href="centerUrl" class="notif-footer-btn">{{ t('view_all') }}</a>
      </div>

    </div>

  </div>

</template>

<script setup>

import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

// Combined unread Comments + Messages badge/dropdown for the shared admin
// navbar (resources/views/layouts/partials/navbar.blade.php). Mounted as
// its own Vue root (see resources/js/app.js) rather than inside the app's
// main `#app` root, since the navbar renders outside that root's DOM
// subtree - see the plan notes for why.
//
// Messages get live updates via the same Echo `inbox.{userId}` private
// channel and `.message.created` event the chat dashboard already
// listens on (app/Events/Messaging/MessageCreated) - no new broadcasting
// infrastructure. Comments have no broadcast event yet (new inbound rows
// are created from many different platform-specific places, each needing
// its own review to broadcast correctly), so they - and the badge as a
// whole, as a safety net matching admin/chats/dashboard.blade.php's own
// "Echo primary, poll as fallback" convention - are refreshed by polling.
const props = defineProps({

  indexUrl: {
    type: String,
    required: true
  },

  // CRM alerts (G-02) come from the CRM's own endpoint, which follows the
  // CRM plan and team permission; when it is not allowed it is simply skipped.
  crmIndexUrl: {
    type: String,
    default: ''
  },

  // Both carry a literal ':id' placeholder, replaced with the item's real
  // id before the request - same convention as admin/chats/dashboard.blade.php's
  // own *UrlTemplate constants.
  commentReadUrlTemplate: {
    type: String,
    required: true
  },

  conversationReadUrlTemplate: {
    type: String,
    required: true
  },

  // Translated UI strings from the navbar (resources/views/layouts/partials/navbar.blade.php).
  labels: {
    type: Object,
    default: () => ({})
  },

  currentUserId: {
    type: [Number, String],
    default: null
  },

  pollIntervalMs: {
    type: Number,
    default: 25000
  }

});

const platformMeta = {
  facebook: { icon: 'bxl-facebook', color: '#1877F2' },
  instagram: { icon: 'bxl-instagram', color: '#E1306C' },
  tiktok: { icon: 'bxl-tiktok', color: '#111827' },
  x: { icon: 'bxl-twitter', color: '#111827' },
  twitter: { icon: 'bxl-twitter', color: '#111827' },
  linkedin: { icon: 'bxl-linkedin', color: '#0A66C2' },
  youtube: { icon: 'bxl-youtube', color: '#FF0000' },
  google: { icon: 'bxl-google', color: '#4285F4' },
  pinterest: { icon: 'bx-share-alt', color: '#E60023' },
  whatsapp: { icon: 'bxl-whatsapp', color: '#25D366' },
  threads: { icon: 'bx-at', color: '#000000' }
};

function platformIcon(platform) {
  return (platformMeta[platform] || {}).icon || 'bx-globe';
}

function platformColor(platform) {
  return (platformMeta[platform] || {}).color || '#7c5cff';
}

const fallbackLabels = {
  title: 'Notifications', empty: "You're all caught up.", loading: 'Loading…', mark_all_read: 'Mark all as read',
  view_all: 'View all notifications', settings: 'Notification settings', just_now: 'just now', m: ':count m ago', h: ':count h ago', d: ':count d ago'
};

function t(key, count) {
  const text = (props.labels && props.labels[key]) || fallbackLabels[key] || key;
  return count === undefined ? text : text.replace(':count', count);
}

function timeAgo(value) {
  if (!value) return '';

  const seconds = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 1000));

  if (seconds < 60) return t('just_now');

  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return t('m', minutes);

  const hours = Math.floor(minutes / 60);
  if (hours < 24) return t('h', hours);

  return t('d', Math.floor(hours / 24));
}

const open = ref(false);
const loading = ref(false);
const count = ref(0);
const items = ref([]);
const centerUrl = ref('');
const settingsUrl = ref('');
const readAllUrl = ref('');

// count > 9 shows "9+", matching the existing dash-bell-badge convention
// (admin/posts/dashboard.blade.php's own Upcoming-Posts-adjacent bell).
const displayCount = computed(() => count.value > 9 ? '9+' : String(count.value));

let pollTimer = null;

async function refresh() {
  loading.value = true;

  try {
    const [main, crm] = await Promise.allSettled([
      axios.get(props.indexUrl),
      props.crmIndexUrl ? axios.get(props.crmIndexUrl) : Promise.reject(new Error('no crm')),
    ]);
    const feeds = [main, crm].filter((r) => r.status === 'fulfilled').map((r) => r.value.data);
    if (main.status === 'fulfilled') {
      centerUrl.value = main.value.data.center_url || '';
      settingsUrl.value = main.value.data.settings_url || '';
      readAllUrl.value = main.value.data.read_all_url || '';
    }
    if (feeds.length) {
      count.value = feeds.reduce((sum, d) => sum + (d.count || 0), 0);
      items.value = feeds.flatMap((d) => d.items || [])
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, 20);
    }
  } catch (e) {
    // A failed poll shouldn't wipe out whatever was already showing -
    // just try again on the next interval/Echo event.
  } finally {
    loading.value = false;
  }
}

function toggleOpen() {
  open.value = !open.value;

  if (open.value) {
    refresh();
  }
}

function typeIcon(type) {
  return { comment: 'bx bx-comment-detail', connection: 'bx bx-plug', crm: 'bx bx-id-card' }[type] || 'bx bx-message-rounded-dots';
}

function readAll() {
  axios.post(readAllUrl.value).finally(refresh);
}

function markRead(item) {
  // Connection warnings have no read state - they clear once the
  // connection is fixed (NotificationController). Platform items are
  // marked read by their own "open" link (which also opens the right workspace).
  if (item.type === 'connection' || item.type === 'platform') {
    return Promise.resolve();
  }

  // Items that bring their own mark-read URL (CRM alerts).
  if (item.read_url) {
    return axios.patch(item.read_url);
  }

  const template = item.type === 'comment'
    ? props.commentReadUrlTemplate
    : props.conversationReadUrlTemplate;

  return axios.patch(template.replace(':id', item.id));
}

function openItem(item) {
  markRead(item).finally(() => {
    window.location.href = item.url;
  });
}

function handleClickOutside(event) {
  if (open.value && !event.target.closest('.notif-center')) {
    open.value = false;
  }
}

onMounted(() => {
  refresh();

  pollTimer = setInterval(refresh, props.pollIntervalMs);

  document.addEventListener('click', handleClickOutside);

  if (window.Echo && props.currentUserId) {
    window.Echo.private('inbox.' + props.currentUserId)
      .listen('.message.created', refresh);
  }
});

onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer);
  document.removeEventListener('click', handleClickOutside);
});

</script>

<style scoped>

.notif-center {
  position: relative;
}

.notif-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  inset-inline-end: 0;
  width: 360px;
  max-height: 420px;
  overflow-y: auto;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 16px 40px rgba(20, 20, 50, 0.16);
  border: 1px solid rgba(20, 20, 40, 0.08);
  z-index: 1050;
}

.notif-dropdown-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  font-weight: 700;
  font-size: 14px;
  color: #1e1e2d;
  border-bottom: 1px solid rgba(20, 20, 40, 0.08);
}

.notif-count-pill {
  background: #7c5cff;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
}

.notif-empty {
  padding: 28px 16px;
  text-align: center;
  color: #8b8d9c;
  font-size: 13px;
}

.notif-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 16px;
  text-decoration: none;
  color: inherit;
  border-bottom: 1px solid rgba(20, 20, 40, 0.06);
  transition: background 0.15s ease;
}

.notif-item:last-child {
  border-bottom: none;
}

.notif-item:hover {
  background: #f7f7fc;
  color: inherit;
  text-decoration: none;
}

.notif-item-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.notif-item-top {
  display: flex;
  align-items: center;
  gap: 6px;
}

.notif-item-type-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  font-size: 10px;
  color: #fff;
  flex-shrink: 0;
}

.notif-item-type-icon.comment {
  background: #0891b2;
}

.notif-item-type-icon.conversation {
  background: #7c5cff;
}

.notif-item-type-icon.crm {
  background: #16a34a;
}

.notif-item-type-icon.connection {
  background: #D97706;
}

.notif-item-author {
  font-weight: 700;
  font-size: 13px;
  color: #1e1e2d;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.notif-item-time {
  margin-inline-start: auto;
  font-size: 11px;
  color: #8b8d9c;
  flex-shrink: 0;
}

.notif-item-preview {
  font-size: 12.5px;
  color: #4b4d5c;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.notif-header-actions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.notif-header-icon {
  color: #8b8d9c;
  font-size: 17px;
  line-height: 1;
}

.notif-header-icon:hover {
  color: #7c5cff;
}

.notif-platform-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  color: #fff;
  font-size: 18px;
  flex-shrink: 0;
}

.notif-platform-icon.tone-success { background: #16A34A; }
.notif-platform-icon.tone-info { background: #2563EB; }
.notif-platform-icon.tone-warning { background: #D97706; }
.notif-platform-icon.tone-action { background: #7C3AED; }
.notif-platform-icon.tone-critical { background: #DC2626; }
.notif-platform-icon.tone-report { background: #0F172A; }

.notif-critical-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #DC2626;
  flex-shrink: 0;
}

.notif-item-workspace {
  font-size: 11px;
  color: #8b8d9c;
}

.notif-dropdown-footer {
  position: sticky;
  bottom: 0;
  display: flex;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 16px;
  background: #fff;
  border-top: 1px solid rgba(20, 20, 40, 0.08);
}

.notif-footer-btn {
  background: none;
  border: 0;
  padding: 0;
  font-size: 12.5px;
  font-weight: 600;
  color: #7c5cff;
  cursor: pointer;
  text-decoration: none;
}

.notif-footer-btn:hover {
  text-decoration: underline;
}

@media (max-width: 575px) {
  .notif-dropdown {
    position: fixed;
    top: 64px;
    left: 8px;
    right: 8px;
    width: auto;
    max-height: 75vh;
  }
}

</style>
