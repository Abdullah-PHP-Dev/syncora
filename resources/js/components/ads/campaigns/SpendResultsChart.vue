<template>
  <div ref="root" class="srs" @mouseleave="hover = null">
    <svg v-if="width" :width="width" :height="height" role="img" :aria-label="ariaLabel" @mousemove="move">
      <g class="srs-grid">
        <g v-for="(tick, i) in ticks" :key="i">
          <line :x1="pad.l" :x2="width - pad.r" :y1="tick.y" :y2="tick.y" />
          <text :x="pad.l - 8" :y="tick.y + 4" text-anchor="end">{{ formatSpend(tick.spend) }}</text>
          <text v-if="hasResults" :x="width - pad.r + 8" :y="tick.y + 4" text-anchor="start">{{ formatCount(tick.result) }}</text>
        </g>
      </g>
      <rect v-for="(p, i) in points" :key="'b' + i" :x="p.x - barW / 2" :y="p.ySpend" :width="barW" :height="Math.max(0, base - p.ySpend)" rx="3" :class="['srs-bar', { on: hover === i }]" />
      <path v-if="hasResults && linePath" :d="linePath" fill="none" :stroke="color" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round" />
      <circle v-if="hasResults && last" :cx="last.x" :cy="last.yResult" r="4.5" :fill="color" stroke="#fff" stroke-width="2" />
      <g class="srs-x">
        <text v-for="l in xLabels" :key="l.i" :x="l.x" :y="height - 6" text-anchor="middle">{{ l.text }}</text>
      </g>
    </svg>
    <div v-if="hover !== null" class="srs-tip" :style="tipStyle">
      <strong>{{ labels[hover] }}</strong>
      <span><i class="sq"></i>{{ spendLabel }} <b>{{ formatSpend(spend[hover]) }}</b></span>
      <span v-if="hasResults"><i class="ln" :style="{ background: color }"></i>{{ resultLabel }} <b>{{ formatCount(results[hover]) }}</b></span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

/* Daily spend (bars, left axis) and results (line, right axis): two units, two axes, both labelled. */
const props = defineProps({
  spend: { type: Array, required: true },
  results: { type: Array, default: () => [] },
  labels: { type: Array, default: () => [] },
  height: { type: Number, default: 250 },
  color: { type: String, default: '#4F46E5' },
  formatSpend: { type: Function, default: (v) => Number(v || 0).toLocaleString() },
  formatCount: { type: Function, default: (v) => Number(v || 0).toLocaleString() },
  spendLabel: { type: String, default: '' },
  resultLabel: { type: String, default: '' },
  ariaLabel: { type: String, default: '' },
});

const root = ref(null);
const width = ref(0);
const hover = ref(null);
let observer = null;

const hasResults = computed(() => props.results.length === props.spend.length && props.results.some((v) => v !== null));
const pad = computed(() => ({ l: 54, r: hasResults.value ? 40 : 12, t: 12, b: 26 }));
const nice = (m) => { if (!m) return 1; const step = Math.pow(10, Math.floor(Math.log10(m))); return Math.ceil(m / step) * step; };
const maxSpend = computed(() => nice(Math.max(0, ...props.spend.map(Number))));
const maxResult = computed(() => nice(Math.max(0, ...props.results.map((v) => Number(v || 0)))));
const base = computed(() => props.height - pad.value.b);
const ticks = computed(() => [0, 1, 2, 3, 4].map((i) => ({ spend: (maxSpend.value / 4) * i, result: (maxResult.value / 4) * i, y: base.value - (i / 4) * (base.value - pad.value.t) })));
const step = computed(() => (width.value - pad.value.l - pad.value.r) / Math.max(1, props.spend.length));
const barW = computed(() => Math.max(3, Math.min(26, step.value * 0.62)));
const points = computed(() => props.spend.map((s, i) => ({
  x: pad.value.l + step.value * (i + 0.5),
  ySpend: base.value - (Number(s || 0) / maxSpend.value) * (base.value - pad.value.t),
  yResult: base.value - (Number(props.results[i] || 0) / maxResult.value) * (base.value - pad.value.t),
})));
const linePath = computed(() => points.value.map((p, i) => `${i ? 'L' : 'M'}${p.x.toFixed(1)},${p.yResult.toFixed(1)}`).join(' '));
const last = computed(() => points.value[points.value.length - 1] || null);
const xLabels = computed(() => {
  const n = props.labels.length;
  if (!n || !width.value) return [];
  const count = Math.min(n, Math.max(2, Math.floor(width.value / 110)));
  const gap = (n - 1) / (count - 1 || 1);
  return Array.from({ length: count }, (_, k) => Math.round(k * gap)).map((i) => ({ i, x: points.value[i].x, text: props.labels[i] }));
});
const tipStyle = computed(() => {
  const x = points.value[hover.value].x;
  return x > width.value / 2 ? { right: `${width.value - x + 12}px` } : { left: `${x + 12}px` };
});
function move(e) {
  const rect = root.value.getBoundingClientRect();
  const i = Math.floor((e.clientX - rect.left - pad.value.l) / step.value);
  hover.value = i >= 0 && i < props.spend.length ? i : null;
}
onMounted(() => {
  const measure = () => { width.value = root.value ? root.value.clientWidth : 0; };
  measure();
  if (window.ResizeObserver) { observer = new ResizeObserver(measure); observer.observe(root.value); }
});
onBeforeUnmount(() => { if (observer) observer.disconnect(); });
</script>

<style scoped>
.srs { position: relative; width: 100%; }
.srs svg { display: block; overflow: visible; }
.srs-grid line { stroke: #EEF0F5; }
.srs-grid text, .srs-x text { fill: #8A92A3; font-size: 11px; font-variant-numeric: tabular-nums; }
.srs-bar { fill: #CDD3E2; transition: fill .15s; }
.srs-bar.on { fill: #A9B3CB; }
.srs-tip { position: absolute; top: 8px; z-index: 3; pointer-events: none; display: flex; flex-direction: column; gap: 4px; min-width: 160px; padding: 10px 12px; border-radius: 10px; background: #161B2B; color: #fff; font-size: .76rem; box-shadow: 0 10px 24px rgba(16, 24, 40, .22); }
.srs-tip strong { color: #C9D1E6; font-weight: 600; }
.srs-tip span { display: flex; align-items: center; gap: 6px; }
.srs-tip b { margin-inline-start: auto; font-variant-numeric: tabular-nums; }
.srs-tip i { display: inline-block; flex-shrink: 0; }
.srs-tip i.sq { width: 9px; height: 9px; border-radius: 2px; background: #CDD3E2; }
.srs-tip i.ln { width: 12px; height: 3px; border-radius: 2px; }
</style>
