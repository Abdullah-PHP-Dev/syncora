<template>
  <div class="etd-root">

    <div class="etd-toolbar">
      <div class="etd-toolbar-left">
        <button type="button" class="etd-tool-btn" :disabled="!canUndo" title="Undo" @click="undo"><i class="bx bx-undo"></i></button>
        <button type="button" class="etd-tool-btn" :disabled="!canRedo" title="Redo" @click="redo"><i class="bx bx-redo"></i></button>
        <span class="etd-toolbar-sep"></span>
        <div class="etd-settings-wrap">
          <button type="button" class="etd-tool-btn" title="Global settings" @click.stop="showSettings = !showSettings"><i class="bx bx-slider-alt"></i></button>
          <div v-if="showSettings" class="etd-settings-popover" @click.stop>
            <label>Email width</label>
            <div class="d-flex align-items-center gap-1 mb-2">
              <input type="number" class="dash-input" min="480" max="800" v-model.number="schema.settings.emailWidth" />
              <span class="inspector-suffix">px</span>
            </div>
            <label>Font</label>
            <select class="dash-input w-100 mb-2" v-model="schema.settings.fontFamily">
              <option v-for="f in fonts" :key="f.value" :value="f.value">{{ f.label }}</option>
            </select>
            <label>Email background</label>
            <input type="color" class="inspector-color" v-model="schema.settings.backgroundColor" />
          </div>
        </div>
      </div>

      <div class="etd-toolbar-right">
        <span class="etd-autosave-status" :class="autosaveStatus">
          <i class="bx" :class="autosaveIcon"></i> {{ autosaveLabel }}
        </span>
        <button type="button" class="dash-btn dash-btn-ghost" @click="fullscreenPreview = true"><i class="bx bx-show"></i> Preview Email</button>
        <button v-if="sendTestUrl" type="button" class="dash-btn dash-btn-ghost" @click="openSendTest"><i class="bx bx-send"></i> Send Test</button>
      </div>
    </div>

    <div class="etd-body">
      <div class="etd-panel etd-panel-left">
        <BlockPanel @add-block="addBlock" @add-preset="addPreset" />
      </div>

      <div class="etd-panel etd-panel-center">
        <CanvasArea :sections="schema.sections" :selected-path="selectedPath" @select="onSelect" @change="onChange" />
      </div>

      <div class="etd-panel etd-panel-right">
        <InspectorPanel
          :node="selectedNode"
          :node-kind="selectedKind"
          :social-accounts-by-platform="socialAccounts"
          :upload-media-url="uploadMediaUrl"
          @change="onChange"
        />
      </div>
    </div>

    <div v-if="fullscreenPreview" class="etd-fullscreen-preview">
      <div class="etd-fullscreen-header">
        <strong>Email Preview</strong>
        <div class="device-toggle">
          <button type="button" class="device-btn" :class="{ active: fullscreenDevice === 'desktop' }" @click="fullscreenDevice = 'desktop'"><i class="bx bx-desktop"></i></button>
          <button type="button" class="device-btn" :class="{ active: fullscreenDevice === 'mobile' }" @click="fullscreenDevice = 'mobile'"><i class="bx bx-mobile"></i></button>
        </div>
        <button type="button" class="etd-tool-btn" @click="fullscreenPreview = false"><i class="bx bx-x"></i></button>
      </div>
      <div class="etd-fullscreen-body">
        <iframe class="etd-fullscreen-frame" :style="{ width: fullscreenDevice === 'mobile' ? '375px' : '100%' }" :srcdoc="generatedHtml"></iframe>
      </div>
    </div>

    <SendTestEmailModal v-if="sendTestUrl" ref="sendTestModal" :send-test-url="sendTestUrl" :subject="liveSubject" :html="generatedHtml" />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import BlockPanel from './designer/BlockPanel.vue';
import CanvasArea from './designer/CanvasArea.vue';
import InspectorPanel from './designer/InspectorPanel.vue';
import SendTestEmailModal from './designer/SendTestEmailModal.vue';
import { generateEmailHtml } from './lib/htmlGenerator';
import { createHistory } from './lib/undoRedo';
import { emptySchema, makeBlock, SECTION_PRESETS, wrapLegacyHtmlAsSchema } from './lib/blockFactory';
import { WEB_SAFE_FONTS } from './lib/fonts';

/**
 * Root of the block-based email template designer. Owns the single
 * source of truth (`schema`, the JSON block tree - see the shape
 * documented in blockFactory.js/htmlGenerator.js) plus selection,
 * undo/redo history, and autosave. Mirrors the mounting pattern already
 * used by PostComposer.vue: registered globally in app.js, dropped into
 * the Blade view as a custom element with @json-bound props, and kept in
 * sync with two plain hidden <input> fields (#templateSchemaJson,
 * #templateBody) that the surrounding real <form> submits normally on
 * Save/Save as Draft - this component never intercepts that submit.
 */
const props = defineProps({
  initialSchema: { type: [Object, null], default: null },
  initialBody: { type: String, default: '' },
  socialAccounts: { type: Object, default: () => ({}) },
  uploadMediaUrl: { type: String, default: '' },
  isExisting: { type: Boolean, default: false },
  autosaveUrl: { type: String, default: '' },
  sendTestUrl: { type: String, default: '' },
  subject: { type: String, default: '' },
});

const fonts = WEB_SAFE_FONTS;

function initialSchemaState() {
  if (props.initialSchema && Array.isArray(props.initialSchema.sections)) {
    return props.initialSchema;
  }
  if (props.initialBody && props.initialBody.trim() !== '') {
    return wrapLegacyHtmlAsSchema(props.initialBody);
  }
  return emptySchema();
}

const schema = reactive(initialSchemaState());
const selectedPath = reactive({ sectionId: null, columnId: null, blockId: null });
const showSettings = ref(false);
const fullscreenPreview = ref(false);
const fullscreenDevice = ref('desktop');
const sendTestModal = ref(null);

const history = createHistory(schema);

const generatedHtml = computed(() => generateEmailHtml(schema));

const selectedNode = computed(() => {
  if (selectedPath.blockId) {
    for (const section of schema.sections) {
      for (const column of section.columns) {
        const block = column.blocks.find((b) => b.id === selectedPath.blockId);
        if (block) return block;
      }
    }
  } else if (selectedPath.columnId) {
    for (const section of schema.sections) {
      const column = section.columns.find((c) => c.id === selectedPath.columnId);
      if (column) return column;
    }
  } else if (selectedPath.sectionId) {
    return schema.sections.find((s) => s.id === selectedPath.sectionId) || null;
  }
  return null;
});

const selectedKind = computed(() => {
  if (selectedPath.blockId) return 'block';
  if (selectedPath.columnId) return 'column';
  if (selectedPath.sectionId) return 'section';
  return null;
});

function onSelect(path) {
  selectedPath.sectionId = path.sectionId;
  selectedPath.columnId = path.columnId;
  selectedPath.blockId = path.blockId;
}

function onChange(kind) {
  if (kind === 'debounced') {
    history.pushDebounced(schema);
  } else {
    history.push(schema);
  }
}

function replaceSchema(newSchema) {
  schema.version = newSchema.version;
  schema.settings = newSchema.settings;
  schema.sections = newSchema.sections;
  onSelect({ sectionId: null, columnId: null, blockId: null });
  onChange('immediate');
}

function addPreset(preset) {
  if (!SECTION_PRESETS[preset]) return;
  const section = SECTION_PRESETS[preset]();
  schema.sections.push(section);
  onSelect({ sectionId: section.id, columnId: null, blockId: null });
  onChange('immediate');
}

function addBlock(type) {
  const block = makeBlock(type);
  let column = selectedPath.columnId ? findColumn(selectedPath.columnId) : null;

  if (!column) {
    // No column selected - drop it into the last section's first
    // column, or create a brand new one-column section if the canvas is
    // still empty, so "click a block in the panel" always works even
    // before anything's been added yet.
    if (schema.sections.length === 0) {
      const section = SECTION_PRESETS.oneColumn();
      section.columns[0].blocks = [];
      schema.sections.push(section);
    }
    column = schema.sections[schema.sections.length - 1].columns[0];
  }

  column.blocks.push(block);
  onSelect({ sectionId: findSectionIdForColumn(column.id), columnId: column.id, blockId: block.id });
  onChange('immediate');
}

function findColumn(columnId) {
  for (const section of schema.sections) {
    const column = section.columns.find((c) => c.id === columnId);
    if (column) return column;
  }
  return null;
}

function findSectionIdForColumn(columnId) {
  for (const section of schema.sections) {
    if (section.columns.some((c) => c.id === columnId)) return section.id;
  }
  return null;
}

const canUndo = ref(false);
const canRedo = ref(false);

function refreshHistoryFlags() {
  canUndo.value = history.canUndo();
  canRedo.value = history.canRedo();
}

function undo() {
  const state = history.undo();
  applyHistoryState(state);
}

function redo() {
  const state = history.redo();
  applyHistoryState(state);
}

function applyHistoryState(state) {
  schema.version = state.version;
  schema.settings = state.settings;
  schema.sections = state.sections;
  refreshHistoryFlags();
}

// --- Hidden-input sync + autosave ---------------------------------------

// Looked up fresh on every sync rather than cached once: <email-template-
// designer> is a custom element that upgrades (runs this setup script) the
// moment the HTML parser reaches its tag - and in _form.blade.php the
// #templateSchemaJson/#templateBody hidden inputs are siblings that come
// AFTER it in source order, so a one-time getElementById() here captured
// null forever, silently no-opping every sync (including the onMounted()
// call below) and leaving the real <form> submit with empty body/schema_json.

const autosaveStatus = ref('saved'); // saved | unsaved | saving | failed
let autosaveTimer = null;
let autosaveInFlight = false;
let autosaveQueued = false;

const autosaveIcon = computed(() => ({
  saved: 'bx-check-circle',
  unsaved: 'bx-circle',
  saving: 'bx-loader-alt bx-spin',
  failed: 'bx-error-circle',
}[autosaveStatus.value]));

const autosaveLabel = computed(() => ({
  saved: 'Saved',
  unsaved: 'Unsaved changes',
  saving: 'Saving...',
  failed: 'Failed to save - retrying',
}[autosaveStatus.value]));

function syncHiddenInputs() {
  const templateSchemaJsonInput = document.getElementById('templateSchemaJson');
  const templateBodyInput = document.getElementById('templateBody');

  if (templateSchemaJsonInput) templateSchemaJsonInput.value = JSON.stringify(schema);
  if (templateBodyInput) templateBodyInput.value = generatedHtml.value;
}

async function runAutosave() {
  if (!props.isExisting || !props.autosaveUrl) return;

  if (autosaveInFlight) {
    autosaveQueued = true;
    return;
  }

  autosaveInFlight = true;
  autosaveStatus.value = 'saving';

  try {
    await window.axios.post(props.autosaveUrl, {
      schema_json: JSON.stringify(schema),
      body: generatedHtml.value,
    });
    autosaveStatus.value = 'saved';
  } catch (e) {
    autosaveStatus.value = 'failed';
  } finally {
    autosaveInFlight = false;
    if (autosaveQueued) {
      autosaveQueued = false;
      runAutosave();
    }
  }
}

watch(schema, () => {
  syncHiddenInputs();
  refreshHistoryFlags();

  if (props.isExisting && props.autosaveUrl) {
    autosaveStatus.value = 'unsaved';
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(runAutosave, 2000);
  }
}, { deep: true });

function openSendTest() {
  sendTestModal.value?.open();
}

function onAiContentLoaded(event) {
  replaceSchema(wrapLegacyHtmlAsSchema(event.detail));
}

function onDocumentClick() {
  showSettings.value = false;
}

// The Subject field lives outside this component (a plain input in
// _form.blade.php, shared with the Name/Category fields) - subject is
// only passed in as an initial prop, so the Send Test Email modal reads
// its live value here rather than going stale the moment a seller edits
// it after this component mounted.
const liveSubject = ref(props.subject);
let subjectInput = null;
function onSubjectInput(event) {
  liveSubject.value = event.target.value;
}

onMounted(() => {
  syncHiddenInputs();
  refreshHistoryFlags();
  document.addEventListener('email-designer:load-html', onAiContentLoaded);
  document.addEventListener('click', onDocumentClick);

  subjectInput = document.getElementById('templateSubject');
  subjectInput?.addEventListener('input', onSubjectInput);
});

onBeforeUnmount(() => {
  document.removeEventListener('email-designer:load-html', onAiContentLoaded);
  document.removeEventListener('click', onDocumentClick);
  subjectInput?.removeEventListener('input', onSubjectInput);
  clearTimeout(autosaveTimer);
});
</script>

<style scoped>
.etd-root { display: flex; flex-direction: column; height: 78vh; min-height: 560px; border: 1px solid var(--dash-border); border-radius: .85rem; overflow: hidden; background: var(--dash-card); }
.etd-toolbar { display: flex; justify-content: space-between; align-items: center; padding: .6rem .9rem; border-bottom: 1px solid var(--dash-border); background: var(--dash-card); flex-shrink: 0; }
.etd-toolbar-left, .etd-toolbar-right { display: flex; align-items: center; gap: .5rem; }
.etd-toolbar-sep { width: 1px; height: 20px; background: var(--dash-border); }
.etd-tool-btn { width: 30px; height: 30px; border: 1px solid var(--dash-border); background: var(--dash-card); border-radius: .4rem; color: var(--dash-text); display: inline-flex; align-items: center; justify-content: center; }
.etd-tool-btn:disabled { opacity: .4; cursor: not-allowed; }
.etd-tool-btn:not(:disabled):hover { border-color: var(--dash-primary); color: var(--dash-primary); }
.etd-settings-wrap { position: relative; }
.etd-settings-popover { position: absolute; top: 36px; left: 0; z-index: 20; width: 220px; background: var(--dash-card); border: 1px solid var(--dash-border); border-radius: .6rem; padding: .75rem; box-shadow: 0 8px 24px rgba(20,20,50,.12); }
.etd-settings-popover label { display: block; font-size: .72rem; color: var(--dash-muted); margin-bottom: .2rem; }
.etd-autosave-status { display: inline-flex; align-items: center; gap: .3rem; font-size: .75rem; color: var(--dash-muted); }
.etd-autosave-status.saved .bx { color: var(--dash-success, #16a34a); }
.etd-autosave-status.failed .bx { color: var(--dash-danger, #e11d48); }
.etd-body { flex: 1; display: flex; min-height: 0; }
.etd-panel { overflow: hidden; }
.etd-panel-left { width: 260px; border-right: 1px solid var(--dash-border); flex-shrink: 0; overflow-y: auto; }
.etd-panel-center { flex: 1; background: var(--dash-bg, #f5f5fa); overflow-y: auto; }
.etd-panel-right { width: 300px; border-left: 1px solid var(--dash-border); flex-shrink: 0; overflow-y: auto; }

.etd-fullscreen-preview { position: fixed; inset: 0; background: #fff; z-index: 1090; display: flex; flex-direction: column; }
.etd-fullscreen-header { display: flex; align-items: center; justify-content: space-between; padding: .75rem 1.25rem; border-bottom: 1px solid var(--dash-border); }
.etd-fullscreen-body { flex: 1; overflow: auto; display: flex; justify-content: center; background: var(--dash-bg, #f5f5fa); padding: 1.5rem; }
.etd-fullscreen-frame { height: 100%; min-height: 600px; border: 0; background: #fff; box-shadow: 0 2px 16px rgba(20,20,50,.1); }
</style>
