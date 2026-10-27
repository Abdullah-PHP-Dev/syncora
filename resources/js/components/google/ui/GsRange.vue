<template>
  <div ref="root" class="gs-range">
    <button type="button" class="gs-btn gs-btn-ghost" :aria-expanded="open ? 'true' : 'false'" aria-haspopup="dialog" @click="open = !open">
      <i class="bx bx-calendar"></i><span>{{ label }}</span><i class="bx bx-chevron-down"></i>
    </button>
    <button v-if="showCompare" type="button" class="gs-btn gs-btn-ghost" :aria-pressed="suite.compare ? 'true' : 'false'" @click="suite.compare = !suite.compare">
      {{ suite.compare ? t('ranges.compare') : t('ranges.compare_off') }}
    </button>
    <div v-if="open" class="gs-pop" role="dialog" :aria-label="t('ranges.label')" @keydown.esc="open = false">
      <button v-for="p in presets" :key="p" type="button" class="gs-pop-item" :class="{ active: suite.range.key === p }" @click="pick(p)">{{ t('ranges.' + p) }}</button>
      <div class="gs-pop-custom">
        <span class="gs-pop-title">{{ t('ranges.custom') }}</span>
        <label>{{ t('ranges.from') }}<input v-model="start" type="date" :max="end || today" :min="minDate"></label>
        <label>{{ t('ranges.to') }}<input v-model="end" type="date" :min="start || minDate" :max="today"></label>
        <p v-if="invalid" class="gs-field-error">{{ t('validation.custom_range') }}</p>
        <button type="button" class="gs-btn gs-btn-primary gs-btn-sm" :disabled="!start || !end || invalid" @click="applyCustom">{{ t('ranges.apply') }}</button>
      </div>
    </div>
  </div>
</template>

<script>
import { suite, t, fmt, setRange } from '../store';

const iso = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);

/* Date range presets + custom range (validated like the server) + compare toggle. */
export default {
  name: 'GsRange',
  props: { presets: { type: Array, default: () => ['today', '7d', '28d', '30d', '90d'] }, showCompare: { type: Boolean, default: true } },
  data() { return { suite, open: false, start: suite.range.start || '', end: suite.range.end || '' }; },
  computed: {
    today() { return iso(new Date()); },
    minDate() { const d = new Date(); d.setMonth(d.getMonth() - 16); return iso(d); },
    invalid() {
      if (!this.start || !this.end) return false;
      const days = (new Date(this.end) - new Date(this.start)) / 86400000 + 1;
      return days < 1 || days > 366 || this.end > this.today || this.start < this.minDate;
    },
    label() {
      const r = suite.range;
      if (r.key === 'custom' && r.start && r.end) return `${fmt.date(r.start)} – ${fmt.date(r.end)}`;
      return t('ranges.' + r.key);
    },
  },
  mounted() { document.addEventListener('click', this.outside); },
  beforeDestroy() { document.removeEventListener('click', this.outside); },
  methods: {
    t,
    outside(e) { if (this.open && this.$refs.root && !this.$refs.root.contains(e.target)) this.open = false; },
    pick(key) { setRange({ key }); this.open = false; this.$emit('change'); },
    applyCustom() { setRange({ key: 'custom', start: this.start, end: this.end }); this.open = false; this.$emit('change'); },
  },
};
</script>
