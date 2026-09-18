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
        <span>Notifications</span>
        <span v-if="count > 0" class="notif-count-pill">{{ displayCount }}</span>
      </div>

      <div v-if="loading && !items.length" class="notif-empty">Loading…</div>

      <div v-else-if="!items.length" class="notif-empty">You're all caught up.</div>

      <a
          v-for="item in items"
          :key="item.type + '-' + item.id"
          href="#"
          class="notif-item"
          @click.prevent="openItem(item)">

        <account-avatar-badge
            :avatar-url="item.avatar"
            :icon="platformIcon(item.platform)"
            :color="platformColor(item.platform)"
            :size="36" />

        <span class="notif-item-body">
          <span class="notif-item-top">
            <span class="notif-item-type-icon" :class="item.type">
              <i :class="item.type === 'comment' ? 'bx bx-comment-detail' : 'bx bx-message-rounded-dots'"></i>
            </span>
            <span class="notif-item-author">{{ item.author }}</span>
            <span class="notif-item-time">{{ timeAgo(item.created_at) }}</span>
          </span>
          <span class="notif-item-preview">{{ item.preview }}</span>
        </span>

      </a>

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

function timeAgo(value) {
  if (!value) return '';

  const seconds = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 1000));

  if (seconds < 60) return 'just now';

  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return minutes + 'm ago';

  const hours = Math.floor(minutes / 60);
  if (hours < 24) return hours + 'h ago';

  const days = Math.floor(hours / 24);
  return days + 'd ago';
}

const open = ref(false);
const loading = ref(false);
const count = ref(0);
const items = ref([]);

// count > 9 shows "9+", matching the existing dash-bell-badge convention
// (admin/posts/dashboard.blade.php's own Upcoming-Posts-adjacent bell).
const displayCount = computed(() => count.value > 9 ? '9+' : String(count.value));

let pollTimer = null;

async function refresh() {
  loading.value = true;

  try {
    const { data } = await axios.get(props.indexUrl);
    count.value = data.count || 0;
    items.value = data.items || [];
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

function markRead(item) {
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
  right: 0;
  width: 340px;
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

.notif-item-author {
  font-weight: 700;
  font-size: 13px;
  color: #1e1e2d;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.notif-item-time {
  margin-left: auto;
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

</style>
