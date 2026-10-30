<template>
  <section class="adc-card adc-pad">
    <div class="adc-h"><div><h2>{{ title }}</h2><p v-if="sub">{{ sub }}</p></div><slot name="side"></slot></div>
    <template v-for="g in shownGroups">
      <h3 v-if="g.label" :key="'h' + g.key" class="adc-group-h">{{ g.label }}</h3>
      <ul :key="g.key" class="adc-compare">
        <li v-for="r in g.rows" :key="r.key">
          <div class="h">
            <span class="nm"><i :style="{ background: r.color }"></i><bdi dir="auto">{{ r.label }}</bdi></span>
            <small v-if="r.hint">{{ r.hint }}</small>
          </div>
          <div class="v"><div class="adc-track"><span :style="{ width: r.width + '%', background: r.color }"></span></div><b>{{ r.display }}</b></div>
        </li>
      </ul>
    </template>
    <p v-if="!shownGroups.length" class="adc-muted-line"><i class="bx bx-info-circle"></i>{{ empty }}</p>
    <p v-if="foot" class="adc-foot">{{ foot }}</p>
  </section>
</template>

<script setup>
import { computed } from 'vue';

/*
 * A card of ranked rows with bars, in the campaigns pages' design
 * (ads-campaigns.css). Rows: { key, label, display, width (0-100), color, hint }.
 */
const props = defineProps({
  title: { type: String, required: true },
  sub: { type: String, default: '' },
  groups: { type: Array, default: () => [] }, // [{ key, label, rows }]
  empty: { type: String, default: '' },
  foot: { type: String, default: '' },
});

const shownGroups = computed(() => props.groups.filter((g) => g.rows && g.rows.length));
</script>
