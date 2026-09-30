<template>
  <span class="mpi" :class="{ off: !ok }" :style="ok ? { color: meta.color } : {}" :title="title">
    <i v-if="meta.icon" :class="['bx', meta.icon]"></i>
    <b v-else class="mpi-x">𝕏</b>
    <span v-if="!ok" class="mpi-warn" aria-hidden="true"></span>
  </span>
</template>

<script setup>
import { computed } from 'vue';
import { PLATFORM_META } from './mediaShared';

const props = defineProps({
  platform: { type: String, required: true },
  ok: { type: Boolean, default: true },
  issues: { type: String, default: '' },
});

const meta = computed(() => PLATFORM_META[props.platform] || { name: props.platform, icon: 'bx-globe', color: '#64748b' });
const title = computed(() => (props.ok ? `✓ ${meta.value.name}` : `⚠ ${meta.value.name}: ${props.issues || 'Requires adjustment'}`));
</script>

<style scoped>
.mpi { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; font-size: 1.15rem; line-height: 1; cursor: default; }
.mpi.off { color: #c4c9d4; }
.mpi-x { font-size: .88rem; font-weight: 800; }
.mpi-warn { position: absolute; right: -1px; bottom: 0; width: 8px; height: 8px; border-radius: 50%; background: #f59e0b; box-shadow: 0 0 0 2px #fff; }
</style>
