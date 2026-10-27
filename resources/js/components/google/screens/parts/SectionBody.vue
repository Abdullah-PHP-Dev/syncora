<template>
  <div class="gs-section-body">
    <div v-if="loading && !result" class="gs-skel-block" aria-hidden="true">
      <span v-for="n in 4" :key="n" class="gs-skel" :style="{ width: 90 - n * 12 + '%' }"></span>
    </div>
    <gs-state v-else-if="result && result.error" kind="error" :error="result.error" compact @retry="$emit('retry')" />
    <slot v-else-if="result && result.data" />
  </div>
</template>

<script>
import GsState from '../../ui/GsState.vue';

/* Loading skeleton → error with next step → content, for one service's data. */
export default {
  name: 'GsSectionBody',
  components: { GsState },
  props: { result: { type: Object, default: null }, loading: Boolean, service: { type: String, default: '' } },
};
</script>
