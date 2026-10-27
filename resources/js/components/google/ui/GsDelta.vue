<template>
  <span v-if="shown" class="gs-delta" :class="tone">
    <i class="bx" :class="icon" aria-hidden="true"></i>{{ text }}<span v-if="suffix" class="gs-delta-suffix"> {{ suffix }}</span>
  </span>
</template>

<script>
import { fmt, change, t, suite } from '../store';

/**
 * Change vs the previous period. mode: pct (relative %), pts (rate
 * difference in percentage points), places (average position, lower is better).
 */
export default {
  name: 'GsDelta',
  props: { current: { type: Number, default: null }, previous: { type: Number, default: null }, mode: { type: String, default: 'pct' }, suffix: { type: String, default: '' }, inverse: { type: Boolean, default: false } },
  computed: {
    shown() { return suite.compare && this.diff !== null; },
    diff() {
      if (this.current === null || this.previous === null || this.previous === undefined) return null;
      if (this.mode === 'pct') return change(this.current, this.previous);
      if (this.mode === 'places') return this.previous ? this.previous - this.current : null;
      return this.current - this.previous;
    },
    better() { return this.inverse ? this.diff < 0 : this.diff > 0; },
    tone() { return Math.abs(this.diff) < 0.0005 ? 'flat' : this.better ? 'up' : 'down'; },
    icon() { return this.tone === 'flat' ? 'bx-minus' : (this.diff > 0) ? 'bxs-up-arrow' : 'bxs-down-arrow'; },
    text() {
      if (this.tone === 'flat') return t('common.no_change');
      const abs = Math.abs(this.diff);
      if (this.mode === 'pts') return t('analytics.pts', { n: fmt.dec(abs * 100, 1) });
      if (this.mode === 'places') return t(this.diff > 0 ? 'search.places_better' : 'search.places_worse', { n: fmt.dec(abs, 1) });
      return fmt.pct(abs, 1);
    },
  },
};
</script>
