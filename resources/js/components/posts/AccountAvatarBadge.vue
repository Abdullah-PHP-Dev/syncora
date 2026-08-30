<template>

  <span class="account-avatar-badge" :style="{ width: size + 'px', height: size + 'px' }">

    <img
        v-if="avatarUrl"
        class="account-avatar-badge__img"
        :src="avatarUrl">

    <span
        v-else
        class="account-avatar-badge__fallback"
        :style="{ background: color + '1a', color: color, fontSize: Math.round(size * 0.4) + 'px' }">
      <i :class="icon"></i>
    </span>

    <span class="account-avatar-badge__badge" :style="{ background: color }">
      <i :class="icon"></i>
    </span>

  </span>

</template>

<script setup>

// Avatar photo (or a tinted fallback icon when there's no photo yet) with a
// small solid-color platform-icon badge overlaid bottom-right - the same
// "which account, which platform" identity chip PostsDashboard.vue used to
// hand-roll three separate times (the platform filter tabs, a post card's
// per-platform pill, and the Create Post modal's account picker), each a
// copy of the same markup+CSS at a different size. This component is only
// the avatar+badge piece - the clickable wrapper, active state, and name
// label differ per call site and stay there.
//
// PostsDashboard.vue itself stays Options API (data/computed/methods) -
// it's the whole interactive posts.index page, and rewriting its paradigm
// is separable, higher-risk scope than eliminating this duplication.
// <script setup> components register the same way for Options API
// consumers, so `components: { AccountAvatarBadge }` there needed no
// change.
defineProps({

  avatarUrl: {
    type: String,
    default: null
  },

  icon: {
    type: String,
    required: true
  },

  color: {
    type: String,
    default: '#7c5cff'
  },

  // Pixel size of the avatar circle. The platform badge stays a fixed
  // 18px/9px-icon regardless - that's already the size used everywhere
  // this shows up, so it never needed to scale with the avatar.
  size: {
    type: Number,
    default: 32
  }

});

</script>

<style scoped>

.account-avatar-badge {
  position: relative;
  display: inline-block;
  flex-shrink: 0;
}

.account-avatar-badge__img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  display: block;
}

.account-avatar-badge__fallback {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.account-avatar-badge__badge {
  position: absolute;
  bottom: -2px;
  right: -2px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #fff;
}

.account-avatar-badge__badge i {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  font-size: 9px;
  color: #fff;
  line-height: 1;
  margin: 0;
}

</style>
