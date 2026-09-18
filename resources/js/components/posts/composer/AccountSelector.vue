<template>

  <div>

    <h6 class="composer-section-label">Post to</h6>

    <div class="account-selector-grid">

      <button
          v-for="account in accounts"
          :key="account.id"
          type="button"
          class="account-card"
          :class="{ 'account-card--selected': isSelected(account.id) }"
          :aria-pressed="isSelected(account.id)"
          @click="toggle(account.id)">

        <account-avatar-badge
            :avatar-url="account.avatar_url"
            :icon="metaFor(account.platform).icon"
            :color="metaFor(account.platform).color"
            :size="40" />

        <span class="account-card__text">
          <span class="account-card__platform">{{ metaFor(account.platform).name }}</span>
          <span class="account-card__name">{{ account.name || account.username || 'Connected account' }}</span>
        </span>

        <!-- Always visible (checked or empty), not just-appears-when-
             checked - a checkbox has to show BOTH states to read as a
             checkbox at all; the earlier version only ever rendered the
             checkmark, so every card looked identical (and equally
             "unselected") whether or not it actually was one. -->
        <span class="account-card__check" :class="{ 'account-card__check--on': isSelected(account.id) }">
          <i v-if="isSelected(account.id)" class="bx bx-check"></i>
        </span>

      </button>

      <p v-if="!accounts.length" class="account-selector-empty">
        No connected accounts yet - connect one from
        <a :href="manageAccountsUrl">Manage Channels</a> first.
      </p>

    </div>

  </div>

</template>

<script setup>

// "Post to" account cards - the reference design shows one card per
// platform (Facebook Page, Instagram, X, LinkedIn, YouTube), but this app
// can have MULTIPLE connected accounts per platform (two Facebook Pages,
// say), so this renders one card per real connected SocialAccount instead
// of one per platform - picking a specific Page/Instagram account is what
// PostController::store() actually needs (selected_pages[platform][] =
// account id, not just platforms[] = platform key), and it's what the
// existing calendar quick-post picker already does for the same reason
// (see dashboard.blade.php's own account-picker comment).
import AccountAvatarBadge from '../AccountAvatarBadge.vue';
import { platformMeta } from '../../../data/mockPosts';

const props = defineProps({

  accounts: {
    type: Array,
    default: () => []
  },

  selectedIds: {
    type: Array,
    default: () => []
  },

  manageAccountsUrl: {
    type: String,
    default: '#'
  }

});

const emit = defineEmits(['update:selectedIds']);

function metaFor(platform) {
  return platformMeta[platform] || { name: platform, icon: 'bx bx-globe', color: '#7c5cff' };
}

function isSelected(id) {
  return props.selectedIds.includes(id);
}

function toggle(id) {
  const next = isSelected(id)
    ? props.selectedIds.filter(existing => existing !== id)
    : [...props.selectedIds, id];

  emit('update:selectedIds', next);
}

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

.account-selector-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.account-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px 10px 10px;
  border: 1px solid #E5E7EB;
  border-radius: 12px;
  background: #fff;
  cursor: pointer;
  transition: border-color .15s ease, background .15s ease;
  text-align: left;
}

.account-card:hover {
  border-color: #c7d2fe;
}

.account-card--selected {
  border-color: #7c5cff;
  background: #F5F3FF;
}

.account-card__text {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
}

.account-card__platform {
  font-size: .82rem;
  font-weight: 600;
  color: #1f2937;
}

.account-card__name {
  font-size: .74rem;
  color: #6b7280;
}

.account-card__check {
  position: absolute;
  top: -6px;
  right: -6px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid #d1d5db;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  transition: background .15s ease, border-color .15s ease;
}

.account-card__check--on {
  background: #22c55e;
  border-color: #fff;
}

.account-selector-empty {
  font-size: .82rem;
  color: #6b7280;
  margin: 0;
}

</style>
