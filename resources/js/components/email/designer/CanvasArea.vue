<template>
  <div class="canvas-wrap">
    <div :ref="setSectionsEl" class="canvas-sections">
      <div
        v-for="section in sections"
        :key="section.id"
        class="canvas-section"
        :data-section-id="section.id"
        :class="{ selected: isSectionSelected(section) }"
        :style="{ background: section.settings.backgroundColor !== 'transparent' ? section.settings.backgroundColor : undefined, padding: paddingCss(section.settings.padding) }"
        @click.self="selectSection(section)"
      >
        <div class="node-toolbar section-toolbar" @click.stop>
          <button type="button" title="Move up" @click="moveSection(section, -1)"><i class="bx bx-up-arrow-alt"></i></button>
          <button type="button" title="Move down" @click="moveSection(section, 1)"><i class="bx bx-down-arrow-alt"></i></button>
          <button type="button" title="Duplicate section" @click="duplicateSection(section)"><i class="bx bx-duplicate"></i></button>
          <button type="button" title="Delete section" @click="deleteSection(section)"><i class="bx bx-trash"></i></button>
        </div>

        <div class="canvas-columns" @click="selectSection(section)">
          <div
            v-for="column in section.columns"
            :key="column.id"
            class="canvas-column"
            :style="{ flexBasis: column.width, background: column.settings.backgroundColor !== 'transparent' ? column.settings.backgroundColor : undefined }"
            :class="{ selected: isColumnSelected(column) }"
            @click.stop="selectColumn(section, column)"
          >
            <div :ref="(el) => setColumnRef(column.id, el)" class="column-dropzone" :data-column-id="column.id" :data-section-id="section.id">
              <div
                v-for="block in column.blocks"
                :key="block.id"
                class="canvas-block"
                :data-block-id="block.id"
                :class="{ selected: isBlockSelected(block) }"
                @click.stop="selectBlock(section, column, block)"
              >
                <div v-if="isBlockSelected(block)" class="node-toolbar block-toolbar">
                  <span class="block-drag-handle" title="Drag to reorder"><i class="bx bx-move"></i></span>
                  <button type="button" title="Move up" @click.stop="moveBlock(column, block, -1)"><i class="bx bx-up-arrow-alt"></i></button>
                  <button type="button" title="Move down" @click.stop="moveBlock(column, block, 1)"><i class="bx bx-down-arrow-alt"></i></button>
                  <button type="button" title="Duplicate" @click.stop="duplicateBlock(column, block)"><i class="bx bx-duplicate"></i></button>
                  <button type="button" title="Delete" @click.stop="deleteBlock(column, block)"><i class="bx bx-trash"></i></button>
                </div>
                <BlockContent
                  :block="block"
                  :selected="isBlockSelected(block)"
                  @update-content="(html) => updateBlockContent(block, html)"
                  @focus="selectBlock(section, column, block)"
                />
              </div>
              <div v-if="column.blocks.length === 0" class="column-empty">Drag a block here, or click one in the panel</div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="sections.length === 0" class="canvas-empty">
        <i class="bx bx-layout"></i>
        <p>Start by adding a layout section from the panel on the left.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount } from 'vue';
import Sortable from 'sortablejs';
import BlockContent from './BlockContent.vue';
import { makeBlock, cloneBlock, SECTION_PRESETS } from '../lib/blockFactory';

const props = defineProps({
  sections: { type: Array, required: true },
  selectedPath: { type: Object, default: () => ({ sectionId: null, columnId: null, blockId: null }) },
});

const emit = defineEmits(['select', 'change']);

function paddingCss(p) {
  const v = p || {};
  return `${v.top ?? 0}px ${v.right ?? 0}px ${v.bottom ?? 0}px ${v.left ?? 0}px`;
}

function isSectionSelected(section) {
  return props.selectedPath.sectionId === section.id && !props.selectedPath.columnId && !props.selectedPath.blockId;
}
function isColumnSelected(column) {
  return props.selectedPath.columnId === column.id && !props.selectedPath.blockId;
}
function isBlockSelected(block) {
  return props.selectedPath.blockId === block.id;
}

function selectSection(section) {
  emit('select', { sectionId: section.id, columnId: null, blockId: null });
}
function selectColumn(section, column) {
  emit('select', { sectionId: section.id, columnId: column.id, blockId: null });
}
function selectBlock(section, column, block) {
  emit('select', { sectionId: section.id, columnId: column.id, blockId: block.id });
}

function updateBlockContent(block, html) {
  block.content = html;
  emit('change', 'debounced');
}

function moveSection(section, dir) {
  const idx = props.sections.findIndex((s) => s.id === section.id);
  const target = idx + dir;
  if (target < 0 || target >= props.sections.length) return;
  const [item] = props.sections.splice(idx, 1);
  props.sections.splice(target, 0, item);
  emit('change', 'immediate');
}

function duplicateSection(section) {
  const idx = props.sections.findIndex((s) => s.id === section.id);
  const copy = JSON.parse(JSON.stringify(section));
  regenerateIds(copy);
  props.sections.splice(idx + 1, 0, copy);
  emit('change', 'immediate');
}

function deleteSection(section) {
  const idx = props.sections.findIndex((s) => s.id === section.id);
  if (idx === -1) return;
  props.sections.splice(idx, 1);
  if (props.selectedPath.sectionId === section.id) {
    emit('select', { sectionId: null, columnId: null, blockId: null });
  }
  emit('change', 'immediate');
}

function moveBlock(column, block, dir) {
  const idx = column.blocks.findIndex((b) => b.id === block.id);
  const target = idx + dir;
  if (target < 0 || target >= column.blocks.length) return;
  const [item] = column.blocks.splice(idx, 1);
  column.blocks.splice(target, 0, item);
  emit('change', 'immediate');
}

function duplicateBlock(column, block) {
  const idx = column.blocks.findIndex((b) => b.id === block.id);
  column.blocks.splice(idx + 1, 0, cloneBlock(block));
  emit('change', 'immediate');
}

function deleteBlock(column, block) {
  const idx = column.blocks.findIndex((b) => b.id === block.id);
  if (idx === -1) return;
  column.blocks.splice(idx, 1);
  if (props.selectedPath.blockId === block.id) {
    emit('select', { sectionId: null, columnId: null, blockId: null });
  }
  emit('change', 'immediate');
}

function regenerateIds(section) {
  section.id = 'sec_' + Math.random().toString(36).slice(2, 10);
  section.columns.forEach((col) => {
    col.id = 'col_' + Math.random().toString(36).slice(2, 10);
    col.blocks.forEach((b) => { b.id = 'blk_' + Math.random().toString(36).slice(2, 10); });
  });
}

function findColumn(columnId) {
  for (const section of props.sections) {
    const column = section.columns.find((c) => c.id === columnId);
    if (column) return column;
  }
  return null;
}

// --- SortableJS wiring -------------------------------------------------
// Two groups, kept deliberately separate so SortableJS itself prevents
// an invalid drop (a whole layout preset landing inside a block list, or
// a leaf block landing at the section level): "email-blocks" for
// block-panel items + column contents, "email-sections" for layout
// presets + the top-level section list.

const columnSortables = new Map();

function setColumnRef(columnId, el) {
  const existing = columnSortables.get(columnId);
  if (existing) {
    existing.destroy();
    columnSortables.delete(columnId);
  }
  if (!el) return;

  const sortable = Sortable.create(el, {
    group: 'email-blocks',
    animation: 150,
    handle: '.block-drag-handle',
    filter: '.column-empty',
    onAdd(evt) {
      handleBlockAdd(evt);
    },
    onEnd(evt) {
      handleBlockEnd(evt);
    },
  });
  columnSortables.set(columnId, sortable);
}

function handleBlockAdd(evt) {
  // A palette item (data-block-type) was cloned in - replace the raw
  // cloned DOM node with a real block inserted into the model; Vue then
  // renders the actual BlockContent in that position.
  const blockType = evt.item.dataset.blockType;
  if (!blockType) return;

  evt.item.remove();
  const column = findColumn(evt.to.dataset.columnId);
  if (!column) return;

  const block = makeBlock(blockType);
  column.blocks.splice(evt.newIndex, 0, block);
  emit('select', { sectionId: evt.to.dataset.sectionId, columnId: column.id, blockId: block.id });
  emit('change', 'immediate');
}

function handleBlockEnd(evt) {
  if (evt.item.dataset.blockType) return; // handled by onAdd
  if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;

  const fromColumn = findColumn(evt.from.dataset.columnId);
  const toColumn = findColumn(evt.to.dataset.columnId);
  if (!fromColumn || !toColumn) return;

  const blockId = evt.item.dataset.blockId;
  const idx = fromColumn.blocks.findIndex((b) => b.id === blockId);
  if (idx === -1) return;

  const [block] = fromColumn.blocks.splice(idx, 1);
  toColumn.blocks.splice(evt.newIndex, 0, block);
  emit('change', 'immediate');
}

let sectionsSortable = null;

function initSectionsSortable(el) {
  if (!el) return;
  sectionsSortable = Sortable.create(el, {
    group: 'email-sections',
    animation: 150,
    filter: '.canvas-empty',
    onAdd(evt) {
      const preset = evt.item.dataset.layoutPreset;
      evt.item.remove();
      if (!preset || !SECTION_PRESETS[preset]) return;

      const section = SECTION_PRESETS[preset]();
      props.sections.splice(evt.newIndex, 0, section);
      emit('select', { sectionId: section.id, columnId: null, blockId: null });
      emit('change', 'immediate');
    },
    onUpdate(evt) {
      const [item] = props.sections.splice(evt.oldIndex, 1);
      props.sections.splice(evt.newIndex, 0, item);
      emit('change', 'immediate');
    },
  });
}

function setSectionsEl(el) {
  if (sectionsSortable) {
    sectionsSortable.destroy();
    sectionsSortable = null;
  }
  if (el) {
    initSectionsSortable(el);
  }
}

onBeforeUnmount(() => {
  columnSortables.forEach((s) => s.destroy());
  sectionsSortable?.destroy();
});
</script>

<style scoped>
.canvas-wrap { padding: 2rem 1.5rem; min-height: 100%; }
.canvas-sections { max-width: 680px; margin: 0 auto; background: #fff; box-shadow: 0 2px 16px rgba(20,20,50,.08); min-height: 200px; }
.canvas-section { position: relative; border: 1px dashed transparent; }
.canvas-section:hover { border-color: var(--dash-border); }
.canvas-section.selected { border-color: var(--dash-primary); }
.canvas-columns { display: flex; gap: 0; }
.canvas-column { position: relative; min-width: 0; border: 1px dashed transparent; }
.canvas-column:hover { border-color: var(--dash-border); }
.canvas-column.selected { border-color: var(--dash-primary); }
.column-dropzone { min-height: 40px; }
.column-empty { padding: 1rem; text-align: center; font-size: .72rem; color: var(--dash-muted); border: 1px dashed var(--dash-border); border-radius: .4rem; margin: .5rem; }
.canvas-block { position: relative; border: 1px solid transparent; }
.canvas-block:hover { border-color: rgba(124,92,255,.35); }
.canvas-block.selected { border-color: var(--dash-primary); }
.canvas-empty { padding: 4rem 1rem; text-align: center; color: var(--dash-muted); }
.canvas-empty .bx { font-size: 2rem; margin-bottom: .5rem; }

.node-toolbar { position: absolute; top: -30px; right: 4px; z-index: 6; display: flex; gap: .15rem; background: var(--dash-heading, #1e1e2d); border-radius: .4rem; padding: .2rem; }
.section-toolbar { top: -30px; right: 4px; }
.node-toolbar button, .node-toolbar .block-drag-handle { width: 24px; height: 24px; border: none; background: transparent; color: #fff; border-radius: .3rem; font-size: .8rem; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.node-toolbar button:hover { background: rgba(255,255,255,.15); }
.block-drag-handle { cursor: grab; }
</style>
