<template>
  <div class="gs-donut">
    <svg :width="size" :height="size" :viewBox="`0 0 ${size} ${size}`" role="img" :aria-label="aria">
      <circle :cx="c" :cy="c" :r="r" fill="none" stroke="#EEF0F5" :stroke-width="stroke" />
      <circle v-for="seg in segments" :key="seg.key" :cx="c" :cy="c" :r="r" fill="none" :stroke="seg.color" :stroke-width="stroke"
              :stroke-dasharray="`${seg.len} ${circ - seg.len}`" :stroke-dashoffset="-seg.offset" :transform="`rotate(-90 ${c} ${c})`"
              @mouseenter="active = seg.key" @mouseleave="active = null"><title>{{ seg.label }}: {{ seg.pctText }}</title></circle>
      <text v-if="center" :x="c" :y="c + 6" text-anchor="middle" class="gs-donut-center">{{ center }}</text>
    </svg>
    <ul class="gs-legend-list">
      <li v-for="seg in segments" :key="'l' + seg.key" :class="{ dim: active && active !== seg.key }">
        <span class="gs-dot" :style="{ background: seg.color }"></span>
        <span class="gs-legend-label">{{ seg.label }}</span>
        <b>{{ seg.pctText }}</b>
      </li>
    </ul>
  </div>
</template>

<script>
import { fmt } from '../store';

/* Part-to-whole for <= 5 parts, with a 2px gap between segments and a legend with values. */
export default {
  name: 'GsDonut',
  props: { items: { type: Array, default: () => [] }, size: { type: Number, default: 160 }, stroke: { type: Number, default: 22 }, center: { type: String, default: '' }, aria: { type: String, default: '' } },
  data: () => ({ active: null }),
  computed: {
    c() { return this.size / 2; },
    r() { return (this.size - this.stroke) / 2; },
    circ() { return 2 * Math.PI * this.r; },
    total() { return this.items.reduce((s, i) => s + (i.value || 0), 0) || 1; },
    segments() {
      let offset = 0;
      const gap = this.items.length > 1 ? 2 : 0;
      return this.items.map((item) => {
        const share = (item.value || 0) / this.total;
        const len = Math.max(0, share * this.circ - gap);
        const seg = { ...item, len, offset, pctText: fmt.pct(share, 0) };
        offset += share * this.circ;
        return seg;
      });
    },
  },
};
</script>
