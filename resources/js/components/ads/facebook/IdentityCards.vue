<template>
  <div class="ic-grid" role="radiogroup" :aria-label="label">
    <button
      v-if="emptyLabel"
      type="button"
      role="radio"
      class="ic-card"
      :class="{ on: value === null }"
      :aria-checked="value === null"
      @click="$emit('input', null)"
    >
      <span class="ic-av is-empty"><i class="bx" :class="emptyIcon"></i></span>
      <span class="ic-main"><strong>{{ emptyLabel }}</strong><small v-if="emptySub">{{ emptySub }}</small></span>
      <i v-if="value === null" class="bx bxs-check-circle ic-check"></i>
    </button>
    <button
      v-for="item in items"
      :key="item.id"
      type="button"
      role="radio"
      class="ic-card"
      :class="{ on: value === item.id }"
      :aria-checked="value === item.id"
      @click="$emit('input', item.id)"
    >
      <span class="ic-av">
        <img v-if="item.picture && !broken[item.id]" :src="item.picture" alt="" loading="lazy" @error="$set(broken, item.id, true)">
        <span v-else class="ic-initial">{{ initial(item.name) }}</span>
        <span class="ic-badge" :class="'is-' + platform"><i class="bx" :class="platform === 'instagram' ? 'bxl-instagram' : 'bxl-facebook'"></i></span>
      </span>
      <span class="ic-main"><strong>{{ item.name }}</strong><small v-if="item.sub">{{ item.sub }}</small></span>
      <i v-if="value === item.id" class="bx bxs-check-circle ic-check"></i>
    </button>
  </div>
</template>

<script setup>
// Facebook Page / Instagram account chooser for the campaign builder:
// avatar cards (like the composer's "Post to" accounts) instead of a
// dropdown. items: [{ id, name, picture, sub }].
import { reactive } from 'vue';

defineProps({
  value: { type: String, default: null },
  items: { type: Array, default: () => [] },
  platform: { type: String, default: 'facebook' },
  label: { type: String, default: '' },
  emptyLabel: { type: String, default: '' },
  emptySub: { type: String, default: '' },
  emptyIcon: { type: String, default: 'bxl-facebook' },
});
defineEmits(['input']);

const broken = reactive({});
const initial = (name) => String(name || '?').trim().charAt(0).toUpperCase();
</script>

<style scoped>
.ic-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; }
.ic-card { position: relative; display: flex; align-items: center; gap: 10px; min-width: 0; padding: 10px 12px; border: 1.5px solid #E2E5ED; border-radius: 12px; background: #fff; text-align: start; transition: border-color .15s, background .15s, box-shadow .15s; }
.ic-card:hover { border-color: #B9CCFF; }
.ic-card.on { border-color: #004CFF; background: #F7F9FF; box-shadow: 0 0 0 3px rgba(0, 76, 255, .10); }
.ic-av { position: relative; width: 40px; height: 40px; flex-shrink: 0; border-radius: 50%; background: #EEF3FF; display: grid; place-items: center; }
.ic-av img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 1px solid #EEF0F5; }
.ic-av.is-empty { color: #004CFF; font-size: 20px; }
.ic-initial { color: #0039CC; font-weight: 700; font-size: 1rem; }
.ic-badge { position: absolute; inset-inline-end: -3px; bottom: -3px; width: 18px; height: 18px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 11px; border: 2px solid #fff; }
.ic-badge.is-facebook { background: #1877F2; }
.ic-badge.is-instagram { background: radial-gradient(circle at 30% 107%, #FDF497 0%, #FD5949 45%, #D6249F 60%, #285AEB 90%); }
.ic-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; padding-inline-end: 16px; }
.ic-main strong { color: #161B2B; font-size: .86rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ic-main small { color: #8A92A3; font-size: .74rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ic-check { position: absolute; top: 6px; inset-inline-end: 6px; color: #22C55E; font-size: 18px; }
</style>
