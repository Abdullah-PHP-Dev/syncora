<template>
  <div ref="root" class="ad-tc" @mouseleave="hover = null">
    <svg v-if="width" :width="width" :height="height" role="img" :aria-label="ariaLabel" @mousemove="move">
      <g class="ad-tc-grid">
        <g v-for="tick in ticks" :key="tick.v">
          <line :x1="pad.l" :x2="width - pad.r" :y1="tick.y" :y2="tick.y" />
          <text :x="pad.l - 8" :y="tick.y + 4" text-anchor="end">{{ format(tick.v) }}</text>
        </g>
      </g>
      <path v-if="area" :d="area" class="ad-tc-area" />
      <path v-if="previousPath" :d="previousPath" fill="none" stroke="#A3ABBE" stroke-width="1.6" stroke-dasharray="4 4" />
      <path :d="currentPath" fill="none" :stroke="color" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round" />
      <circle v-if="last" :cx="last.x" :cy="last.y" r="4.5" fill="#fff" :stroke="color" stroke-width="2" />
      <g class="ad-tc-x">
        <text v-for="l in xLabels" :key="l.i" :x="l.x" :y="height - 6" text-anchor="middle">{{ l.text }}</text>
      </g>
      <line v-if="hover !== null" class="ad-tc-cross" :x1="xAt(hover)" :x2="xAt(hover)" :y1="pad.t" :y2="height - pad.b" />
      <circle v-if="hover !== null" :cx="xAt(hover)" :cy="yAt(current[hover])" r="4" :fill="color" />
    </svg>
    <div v-if="hover !== null" class="ad-tc-tip" :style="tipStyle">
      <strong>{{ labels[hover] }}</strong>
      <span><i :style="{ background: color }"></i>{{ currentLabel }} <b>{{ format(current[hover]) }}</b></span>
      <span v-if="previous.length"><i class="dash"></i>{{ previousLabel }} <b>{{ format(previous[hover]) }}</b></span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

/* Current vs previous period line chart; one y-axis because both series share a unit. */
const props = defineProps({
  current: { type: Array, required: true },
  previous: { type: Array, default: () => [] },
  labels: { type: Array, default: () => [] },
  color: { type: String, default: '#004CFF' },
  height: { type: Number, default: 240 },
  format: { type: Function, default: (v) => Number(v || 0).toLocaleString() },
  currentLabel: { type: String, default: '' },
  previousLabel: { type: String, default: '' },
  ariaLabel: { type: String, default: '' },
});

const root = ref(null);
const width = ref(0);
const hover = ref(null);
const pad = { l: 52, r: 14, t: 12, b: 28 };
let observer = null;

const max = computed(() => {
  const m = Math.max(0, ...props.current.map(Number), ...props.previous.map(Number));
  if (!m) return 1;
  const step = Math.pow(10, Math.floor(Math.log10(m)));
  return Math.ceil(m / step) * step;
});
const ticks = computed(() => [0, 1, 2, 3, 4].map((i) => ({ v: (max.value / 4) * i, y: yAt((max.value / 4) * i) })));
function xAt(i) { const n = Math.max(1, props.current.length - 1); return pad.l + (i / n) * (width.value - pad.l - pad.r); }
function yAt(v) { return pad.t + (1 - (Number(v) || 0) / max.value) * (props.height - pad.t - pad.b); }
const line = (values) => values.map((v, i) => `${i ? 'L' : 'M'}${xAt(i).toFixed(1)},${yAt(v).toFixed(1)}`).join(' ');
const currentPath = computed(() => (width.value ? line(props.current) : ''));
const previousPath = computed(() => (width.value && props.previous.length ? line(props.previous) : ''));
const area = computed(() => {
  if (!currentPath.value || props.current.length < 2) return '';
  const base = yAt(0).toFixed(1);
  return `${currentPath.value} L${xAt(props.current.length - 1).toFixed(1)},${base} L${xAt(0).toFixed(1)},${base} Z`;
});
const last = computed(() => (props.current.length ? { x: xAt(props.current.length - 1), y: yAt(props.current[props.current.length - 1]) } : null));
const xLabels = computed(() => {
  const n = props.labels.length;
  if (!n || !width.value) return [];
  const count = Math.min(n, Math.max(2, Math.floor(width.value / 110)));
  const step = (n - 1) / (count - 1 || 1);
  return Array.from({ length: count }, (_, k) => Math.round(k * step)).map((i) => ({ i, x: xAt(i), text: props.labels[i] }));
});
const tipStyle = computed(() => {
  const x = xAt(hover.value);
  return x > width.value / 2 ? { right: `${width.value - x + 12}px` } : { left: `${x + 12}px` };
});

function move(e) {
  const rect = root.value.getBoundingClientRect();
  const n = props.current.length;
  if (n < 1) return;
  const ratio = (e.clientX - rect.left - pad.l) / (width.value - pad.l - pad.r);
  hover.value = Math.min(n - 1, Math.max(0, Math.round(ratio * (n - 1))));
}

onMounted(() => {
  const measure = () => { width.value = root.value ? root.value.clientWidth : 0; };
  measure();
  if (window.ResizeObserver) { observer = new ResizeObserver(measure); observer.observe(root.value); } else { window.addEventListener('resize', measure); }
});
onBeforeUnmount(() => { if (observer) observer.disconnect(); });
</script>

<style scoped>
.ad-tc { position: relative; width: 100%; }
.ad-tc svg { display: block; overflow: visible; }
.ad-tc-grid line { stroke: #EEF0F5; }
.ad-tc-grid text, .ad-tc-x text { fill: #8A92A3; font-size: 11px; font-variant-numeric: tabular-nums; }
.ad-tc-area { fill: rgba(0, 76, 255, .06); }
.ad-tc-cross { stroke: #C3C9D6; stroke-dasharray: 3 3; }
.ad-tc-tip { position: absolute; top: 8px; z-index: 3; pointer-events: none; display: flex; flex-direction: column; gap: 4px; min-width: 170px; padding: 10px 12px; border-radius: 10px; background: #161B2B; color: #fff; font-size: .76rem; box-shadow: 0 10px 24px rgba(16, 24, 40, .22); }
.ad-tc-tip strong { color: #C9D1E6; font-weight: 600; }
.ad-tc-tip span { display: flex; align-items: center; gap: 6px; }
.ad-tc-tip b { margin-inline-start: auto; font-variant-numeric: tabular-nums; }
.ad-tc-tip i { width: 10px; height: 3px; border-radius: 2px; display: inline-block; }
.ad-tc-tip i.dash { background: repeating-linear-gradient(90deg, #A3ABBE 0 3px, transparent 3px 5px); }
</style>
