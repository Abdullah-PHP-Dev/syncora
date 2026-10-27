<template>
  <div ref="wrap" class="gs-chart" :style="{ height: height + 'px' }">
    <svg v-if="width > 0" :width="width" :height="height" role="img" :aria-label="ariaLabel"
         tabindex="0" @mousemove="move" @mouseleave="hover = null" @focus="hover = hover === null ? lastIndex : hover" @blur="hover = null" @keydown="key">
      <!-- recessive grid + one y-axis -->
      <g class="gs-grid">
        <line v-for="tick in ticks" :key="'g' + tick.v" :x1="pad.l" :x2="width - pad.r" :y1="tick.y" :y2="tick.y" />
      </g>
      <g class="gs-axis">
        <text v-for="tick in ticks" :key="'t' + tick.v" :x="rtl ? width - pad.r + 6 : pad.l - 8" :y="tick.y + 4" :text-anchor="rtl ? 'start' : 'end'">{{ yFormat(tick.v) }}</text>
        <text v-for="label in xLabels" :key="'x' + label.i" :x="label.x" :y="height - 6" :text-anchor="label.anchor">{{ label.text }}</text>
      </g>

      <path v-if="area && paths[0]" :d="paths[0].area" :fill="series[0].color" fill-opacity="0.09" />
      <path v-for="(p, i) in paths" :key="'p' + series[i].key" :d="p.line" fill="none" :stroke="series[i].color"
            stroke-width="2" stroke-linejoin="round" stroke-linecap="round" :stroke-dasharray="series[i].dashed ? '5 5' : null" />

      <g v-if="hover !== null">
        <line class="gs-cross" :x1="x(hover)" :x2="x(hover)" :y1="pad.t" :y2="height - pad.b" />
        <template v-for="(s, i) in series">
          <circle v-if="valueAt(s, hover) !== null" :key="'c' + s.key" :cx="x(hover)" :cy="y(valueAt(s, hover))" r="4.5"
                  :fill="s.dashed ? '#fff' : s.color" :stroke="s.dashed ? s.color : '#fff'" stroke-width="2" />
        </template>
      </g>
    </svg>

    <div v-if="hover !== null" class="gs-tip" :style="tipStyle" role="status">
      <strong>{{ tipTitle }}</strong>
      <div v-for="s in series" :key="'tip' + s.key" class="gs-tip-row">
        <span class="gs-tip-key" :class="{ dashed: s.dashed }" :style="{ borderColor: s.color, background: s.dashed ? 'transparent' : s.color }"></span>
        <span>{{ s.label }}</span>
        <b>{{ valueAt(s, hover) === null ? '—' : (s.format || yFormat)(valueAt(s, hover)) }}</b>
      </div>
    </div>
  </div>
</template>

<script>
import { suite, fmt } from '../store';

/**
 * Line / area chart on ONE y-axis (never dual-axis). Series of different
 * units must be charted separately by the caller. Null values break the
 * line instead of dropping to zero.
 */
export default {
  name: 'GsLineChart',
  props: {
    series: { type: Array, required: true }, // [{key, label, color, values, dashed?, format?}]
    labels: { type: Array, default: () => [] }, // ISO dates
    height: { type: Number, default: 260 },
    area: { type: Boolean, default: false },
    yFormat: { type: Function, default: (v) => fmt.compact(v) },
    tipLabels: { type: Array, default: null }, // optional per-index title override
    ariaLabel: { type: String, default: '' },
    invert: { type: Boolean, default: false }, // average position: 1 is best, so up = better
  },
  data: () => ({ width: 0, hover: null, observer: null }),
  computed: {
    rtl() { return suite.dir === 'rtl'; },
    pad() { return { t: 12, r: this.rtl ? 48 : 12, b: 28, l: this.rtl ? 12 : 48 }; },
    count() { return Math.max(0, ...this.series.map((s) => s.values.length)); },
    lastIndex() { return Math.max(0, this.count - 1); },
    extent() {
      const values = this.series.flatMap((s) => s.values).filter((v) => v !== null && v !== undefined);
      if (!values.length) return [0, 1];
      let min = this.invert ? Math.min(...values) : Math.min(0, ...values);
      let max = Math.max(...values);
      if (max === min) max = min + 1;
      const step = this.niceStep((max - min) / 4);
      return [Math.floor(min / step) * step, Math.ceil(max / step) * step];
    },
    ticks() {
      const [min, max] = this.extent;
      const step = this.niceStep((max - min) / 4);
      const out = [];
      for (let v = min; v <= max + step / 2; v += step) out.push({ v: +v.toFixed(6), y: this.y(v) });
      return out;
    },
    paths() {
      return this.series.map((s) => {
        let line = '';
        let pen = false;
        const points = [];
        s.values.forEach((v, i) => {
          if (v === null || v === undefined) { pen = false; return; }
          line += (pen ? 'L' : 'M') + this.x(i).toFixed(1) + ',' + this.y(v).toFixed(1);
          pen = true;
          points.push([this.x(i), this.y(v)]);
        });
        const base = this.y(this.extent[0]);
        const area = points.length ? `M${points[0][0]},${base}` + points.map((p) => `L${p[0]},${p[1]}`).join('') + `L${points[points.length - 1][0]},${base}Z` : '';
        return { line, area };
      });
    },
    xLabels() {
      if (!this.labels.length) return [];
      const n = this.labels.length;
      const want = Math.min(n, this.width < 480 ? 3 : 5);
      const idx = want <= 1 ? [0] : Array.from({ length: want }, (_, k) => Math.round((k * (n - 1)) / (want - 1)));
      return [...new Set(idx)].map((i, k, all) => ({
        i, x: this.x(i), text: fmt.date(this.labels[i]),
        anchor: k === 0 ? (this.rtl ? 'end' : 'start') : k === all.length - 1 ? (this.rtl ? 'start' : 'end') : 'middle',
      }));
    },
    tipTitle() {
      if (this.tipLabels && this.tipLabels[this.hover]) return this.tipLabels[this.hover];
      return this.labels[this.hover] ? fmt.date(this.labels[this.hover], { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) : '';
    },
    tipStyle() {
      const left = this.x(this.hover);
      const flip = left > this.width * 0.6;
      return { top: '8px', left: flip ? 'auto' : left + 14 + 'px', right: flip ? this.width - left + 14 + 'px' : 'auto' };
    },
  },
  mounted() {
    this.measure();
    this.observer = new ResizeObserver(() => this.measure());
    this.observer.observe(this.$refs.wrap);
  },
  beforeDestroy() { if (this.observer) this.observer.disconnect(); },
  methods: {
    measure() { this.width = this.$refs.wrap ? this.$refs.wrap.clientWidth : 0; },
    niceStep(raw) {
      if (raw <= 0) return 1;
      const power = Math.pow(10, Math.floor(Math.log10(raw)));
      const n = raw / power;
      return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * power;
    },
    x(i) {
      const inner = this.width - this.pad.l - this.pad.r;
      const pos = this.count <= 1 ? inner / 2 : (i / (this.count - 1)) * inner;
      return this.rtl ? this.width - this.pad.r - pos : this.pad.l + pos;
    },
    y(v) {
      const [min, max] = this.extent;
      const inner = this.height - this.pad.t - this.pad.b;
      const ratio = (v - min) / (max - min);
      return this.invert ? this.pad.t + ratio * inner : this.pad.t + (1 - ratio) * inner;
    },
    valueAt(s, i) { const v = s.values[i]; return v === undefined ? null : v; },
    move(e) {
      if (!this.count) return;
      const rect = e.currentTarget.getBoundingClientRect();
      const inner = this.width - this.pad.l - this.pad.r;
      let pos = e.clientX - rect.left - this.pad.l;
      if (this.rtl) pos = this.width - this.pad.r - (e.clientX - rect.left);
      this.hover = Math.max(0, Math.min(this.count - 1, Math.round((pos / inner) * (this.count - 1))));
    },
    key(e) {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
      e.preventDefault();
      const forward = (e.key === 'ArrowRight') !== this.rtl;
      if (e.key === 'Home') this.hover = 0;
      else if (e.key === 'End') this.hover = this.lastIndex;
      else this.hover = Math.max(0, Math.min(this.lastIndex, (this.hover === null ? 0 : this.hover) + (forward ? 1 : -1)));
    },
  },
};
</script>
