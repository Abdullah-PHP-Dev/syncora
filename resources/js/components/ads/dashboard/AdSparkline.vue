<template>
  <svg class="ad-spark" :viewBox="`0 0 ${w} ${h}`" :width="w" :height="h" aria-hidden="true" focusable="false">
    <path v-if="path" :d="path" fill="none" :stroke="color" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
  </svg>
</template>

<script setup>
import { computed } from 'vue';

/* Small trend line for a KPI card - decorative, the number next to it carries the meaning. */
const props = defineProps({
  values: { type: Array, default: () => [] },
  color: { type: String, default: '#004CFF' },
  w: { type: Number, default: 96 },
  h: { type: Number, default: 30 },
});

const path = computed(() => {
  const v = props.values.map((n) => Number(n) || 0);
  if (v.length < 2) return '';
  const min = Math.min(...v); const max = Math.max(...v); const span = max - min || 1;
  const x = (i) => (i / (v.length - 1)) * (props.w - 2) + 1;
  const y = (n) => props.h - 2 - ((n - min) / span) * (props.h - 4);
  return v.map((n, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(n).toFixed(1)}`).join(' ');
});
</script>

<style scoped>
.ad-spark { display: block; overflow: visible; flex-shrink: 0; }
</style>
