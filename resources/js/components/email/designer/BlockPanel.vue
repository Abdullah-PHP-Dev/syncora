<template>
  <div class="block-panel">
    <div class="block-panel-section">
      <div class="block-panel-title">Layout</div>
      <div :ref="setLayoutGridEl" class="block-grid">
        <button v-for="preset in layoutPresets" :key="preset.preset" type="button" class="block-item" :data-layout-preset="preset.preset" @click="$emit('add-preset', preset.preset)">
          <i :class="'bx ' + preset.icon"></i>
          <span>{{ preset.label }}</span>
          <small>{{ preset.description }}</small>
        </button>
      </div>
    </div>

    <div class="block-panel-section">
      <div class="block-panel-title">Content</div>
      <div :ref="setBlockGridEl" class="block-grid">
        <button v-for="block in blockLibrary" :key="block.type" type="button" class="block-item" :data-block-type="block.type" @click="$emit('add-block', block.type)">
          <i :class="'bx ' + block.icon"></i>
          <span>{{ block.label }}</span>
          <small>{{ block.description }}</small>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount } from 'vue';
import Sortable from 'sortablejs';
import { BLOCK_LIBRARY, LAYOUT_PRESETS } from '../lib/inspectorSchemas';

defineEmits(['add-block', 'add-preset']);

const blockLibrary = BLOCK_LIBRARY;
const layoutPresets = LAYOUT_PRESETS;

let blockSortable = null;
let layoutSortable = null;

// pull:'clone' + put:false + sort:false - dragging out of these grids
// clones the item into whichever canvas list it's dropped on (see
// CanvasArea.vue's onAdd handlers), while the palette itself never
// accepts drops or reorders.
function setBlockGridEl(el) {
  blockSortable?.destroy();
  if (el) {
    blockSortable = Sortable.create(el, {
      group: { name: 'email-blocks', pull: 'clone', put: false },
      sort: false,
      animation: 150,
    });
  }
}

function setLayoutGridEl(el) {
  layoutSortable?.destroy();
  if (el) {
    layoutSortable = Sortable.create(el, {
      group: { name: 'email-sections', pull: 'clone', put: false },
      sort: false,
      animation: 150,
    });
  }
}

onBeforeUnmount(() => {
  blockSortable?.destroy();
  layoutSortable?.destroy();
});
</script>

<style scoped>
.block-panel { padding: 1rem; height: 100%; overflow-y: auto; }
.block-panel-section { margin-bottom: 1.25rem; }
.block-panel-title { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--dash-muted); margin-bottom: .6rem; }
.block-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .5rem; }
.block-item {
  display: flex; flex-direction: column; align-items: flex-start; gap: .15rem;
  border: 1px solid var(--dash-border); background: var(--dash-card); border-radius: .6rem;
  padding: .6rem .65rem; cursor: grab; text-align: left; transition: all .12s ease;
}
.block-item:hover { border-color: var(--dash-primary); background: var(--dash-card-hover); transform: translateY(-1px); }
.block-item:active { cursor: grabbing; }
.block-item .bx { font-size: 1.15rem; color: var(--dash-primary); }
.block-item span { font-size: .78rem; font-weight: 600; color: var(--dash-heading); }
.block-item small { font-size: .65rem; color: var(--dash-muted); line-height: 1.2; }
</style>
