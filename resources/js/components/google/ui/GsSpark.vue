<template>
  <svg class="gs-spark" :viewBox="`0 0 ${w} ${h}`" preserveAspectRatio="none" :width="w" :height="h" aria-hidden="true">
    <path :d="d" fill="none" :stroke="color" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
  </svg>
</template>

<script>
/* Decorative trend line beside a KPI; the number next to it carries the value. */
export default {
  name: 'GsSpark',
  props: { values: { type: Array, default: () => [] }, color: { type: String, default: '#7008E7' }, w: { type: Number, default: 140 }, h: { type: Number, default: 40 } },
  computed: {
    d() {
      const v = this.values.filter((x) => x !== null && x !== undefined);
      if (v.length < 2) return '';
      const min = Math.min(...v); const max = Math.max(...v); const span = max - min || 1;
      return v.map((x, i) => `${i ? 'L' : 'M'}${((i / (v.length - 1)) * (this.w - 4) + 2).toFixed(1)},${(this.h - 3 - ((x - min) / span) * (this.h - 6)).toFixed(1)}`).join('');
    },
  },
};
</script>
