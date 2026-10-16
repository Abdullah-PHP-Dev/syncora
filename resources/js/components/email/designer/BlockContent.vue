<template>
  <div class="block-content" :class="'block-content-' + block.type">

    <!-- Text / Heading: real inline contenteditable, formatted via the
         contextual toolbar below (execCommand) - only text-level marks
         (bold/italic/underline/links/lists), never block layout, which
         is entirely inspector-driven. -->
    <template v-if="block.type === 'text' || block.type === 'heading'">
      <div v-if="selected" class="inline-toolbar">
        <button type="button" :title="t('content.bold')" @mousedown.prevent="exec('bold')"><i class="bx bx-bold"></i></button>
        <button type="button" :title="t('content.italic')" @mousedown.prevent="exec('italic')"><i class="bx bx-italic"></i></button>
        <button type="button" :title="t('content.underline')" @mousedown.prevent="exec('underline')"><i class="bx bx-underline"></i></button>
        <button type="button" :title="t('content.strikethrough')" @mousedown.prevent="exec('strikeThrough')"><i class="bx bx-strikethrough"></i></button>
        <span class="inline-toolbar-sep"></span>
        <button type="button" :title="t('content.bulleted_list')" @mousedown.prevent="exec('insertUnorderedList')"><i class="bx bx-list-ul"></i></button>
        <button type="button" :title="t('content.numbered_list')" @mousedown.prevent="exec('insertOrderedList')"><i class="bx bx-list-ol"></i></button>
        <button type="button" :title="t('content.insert_link')" @mousedown.prevent="insertLink"><i class="bx bx-link"></i></button>
        <span class="inline-toolbar-sep"></span>
        <button v-for="tok in tokens" :key="tok.token" type="button" :title="t('content.insert_token', { token: t(`tokens.${tok.key}`) })" class="inline-toolbar-token" @mousedown.prevent="insertToken(tok.token)">{{ t(`tokens.${tok.key}`) }}</button>
      </div>
      <component
        :is="block.type === 'heading' ? 'h' + Math.min(6, Math.max(1, Number(block.settings.level) || 2)) : 'div'"
        ref="editableEl"
        class="ce-block"
        contenteditable="true"
        :data-placeholder="t('content.click_to_edit')"
        :style="contentStyle"
        @input="onInput"
        @focus="$emit('focus')"
      ></component>
    </template>

    <!-- Image -->
    <div v-else-if="block.type === 'image'" class="image-block" :style="{ textAlign: block.settings.align }">
      <img v-if="block.settings.src" :src="block.settings.src" :alt="block.settings.alt" :style="imageStyle" />
      <div v-else class="image-placeholder">
        <i class="bx bx-image"></i>
        <span>{{ t('content.image_placeholder') }}</span>
      </div>
    </div>

    <!-- Button -->
    <div v-else-if="block.type === 'button'" class="button-block" :style="{ textAlign: block.settings.align }">
      <span class="button-preview" :style="buttonStyle">{{ block.settings.label || t('content.button_default') }}</span>
    </div>

    <!-- Link -->
    <div v-else-if="block.type === 'link'" :style="{ textAlign: block.settings.align, padding: '4px 0' }">
      <span :style="linkStyle">{{ block.settings.label || t('content.link_default') }}</span>
    </div>

    <!-- Divider -->
    <div v-else-if="block.type === 'divider'" :style="{ padding: block.settings.paddingTop + 'px 0 ' + block.settings.paddingBottom + 'px 0' }">
      <div :style="{ width: block.settings.widthPercent + '%', margin: '0 auto', borderTop: block.settings.thickness + 'px ' + block.settings.style + ' ' + block.settings.color }"></div>
    </div>

    <!-- Spacer -->
    <div v-else-if="block.type === 'spacer'" class="spacer-block" :style="{ height: block.settings.height + 'px' }">
      <span>{{ block.settings.height }}px</span>
    </div>

    <!-- Social -->
    <div v-else-if="block.type === 'social'" class="social-block" :style="{ textAlign: block.settings.align, padding: paddingCss(block.settings.padding) }">
      <template v-if="(block.settings.accounts || []).length">
        <span v-for="(a, i) in block.settings.accounts" :key="i" class="social-icon-chip" :style="{ width: block.settings.iconSize + 'px', height: block.settings.iconSize + 'px', marginRight: block.settings.gap + 'px' }">
          <i :class="'bx bxl-' + a.platform"></i>
        </span>
      </template>
      <span v-else class="dash-subtitle" style="font-size:.75rem;">{{ t('content.no_social') }}</span>
    </div>

    <!-- Custom HTML -->
    <div v-else-if="block.type === 'html'" class="html-block">
      <div class="html-block-label"><i class="bx bx-code-alt"></i> {{ t('content.custom_html') }}</div>
      <iframe class="html-block-preview" :srcdoc="block.content || ''"></iframe>
    </div>

  </div>
</template>

<script setup>
import { computed, onMounted, watch, ref } from 'vue';
import { PERSONALIZATION_TOKENS, insertTokenAtCursor } from '../lib/personalizationTokens';
import { useEmailI18n } from '../lib/i18n';

const props = defineProps({
  block: { type: Object, required: true },
  selected: { type: Boolean, default: false },
});

const emit = defineEmits(['update-content', 'focus']);

const { t } = useEmailI18n();

const editableEl = ref(null);
const tokens = PERSONALIZATION_TOKENS;

function syncFromModel() {
  const el = editableEl.value?.$el || editableEl.value;
  if (el && document.activeElement !== el) {
    el.innerHTML = props.block.content || '';
  }
}

onMounted(syncFromModel);
watch(() => props.block.content, syncFromModel);
watch(() => props.block.id, syncFromModel);

function onInput() {
  const el = editableEl.value?.$el || editableEl.value;
  emit('update-content', el.innerHTML);
}

function exec(command) {
  document.execCommand(command, false);
  onInput();
}

function insertLink() {
  const url = window.prompt(t('content.link_prompt'));
  if (url) {
    document.execCommand('createLink', false, url);
    onInput();
  }
}

function insertToken(token) {
  const el = editableEl.value?.$el || editableEl.value;
  insertTokenAtCursor(el, token);
  onInput();
}

function paddingCss(p) {
  const v = p || {};
  return `${v.top ?? 0}px ${v.right ?? 0}px ${v.bottom ?? 0}px ${v.left ?? 0}px`;
}

const contentStyle = computed(() => {
  const s = props.block.settings;
  return {
    fontFamily: s.fontFamily,
    fontSize: s.fontSize + 'px',
    fontWeight: s.fontWeight,
    lineHeight: s.lineHeight,
    letterSpacing: s.letterSpacing + 'px',
    color: s.color,
    backgroundColor: s.backgroundColor !== 'transparent' ? s.backgroundColor : undefined,
    textAlign: s.align,
    padding: paddingCss(s.padding),
  };
});

const imageStyle = computed(() => {
  const s = props.block.settings;
  return {
    width: '100%',
    maxWidth: s.width + 'px',
    borderRadius: s.borderRadius + 'px',
    border: s.borderWidth ? `${s.borderWidth}px solid ${s.borderColor}` : 'none',
  };
});

const buttonStyle = computed(() => {
  const s = props.block.settings;
  return {
    display: 'inline-block',
    background: s.bg,
    color: s.color,
    fontFamily: s.fontFamily,
    fontSize: s.fontSize + 'px',
    fontWeight: s.fontWeight,
    padding: `${s.paddingY}px ${s.paddingX}px`,
    borderRadius: s.radius + 'px',
    width: s.fullWidth ? '100%' : 'auto',
    boxSizing: 'border-box',
    textAlign: 'center',
  };
});

const linkStyle = computed(() => {
  const s = props.block.settings;
  return {
    fontFamily: s.fontFamily,
    fontSize: s.fontSize + 'px',
    fontWeight: s.fontWeight,
    color: s.color,
    textDecoration: s.underline ? 'underline' : 'none',
  };
});
</script>

<style scoped>
.block-content { position: relative; }
.ce-block { outline: none; min-height: 1.4em; }
.ce-block:empty::before { content: attr(data-placeholder); color: var(--dash-muted); }
.inline-toolbar { position: absolute; top: -38px; left: 0; z-index: 5; display: flex; align-items: center; gap: .15rem; background: var(--dash-heading, #1e1e2d); border-radius: .5rem; padding: .3rem; box-shadow: 0 4px 12px rgba(0,0,0,.15); }
.inline-toolbar button { width: 26px; height: 26px; border: none; background: transparent; color: #fff; border-radius: .3rem; font-size: .85rem; }
.inline-toolbar button:hover { background: rgba(255,255,255,.15); }
.inline-toolbar-sep { width: 1px; height: 18px; background: rgba(255,255,255,.2); margin: 0 .2rem; }
.inline-toolbar-token { width: auto !important; padding: 0 .4rem; font-size: .65rem !important; white-space: nowrap; }
.image-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .4rem; padding: 2rem; border: 1px dashed var(--dash-border); border-radius: .5rem; color: var(--dash-muted); background: var(--dash-card-hover); }
.image-placeholder .bx { font-size: 1.75rem; }
.image-placeholder span { font-size: .72rem; text-align: center; max-width: 220px; }
.button-preview { display: inline-block; }
.spacer-block { position: relative; background: repeating-linear-gradient(45deg, var(--dash-card-hover), var(--dash-card-hover) 6px, transparent 6px, transparent 12px); display: flex; align-items: center; justify-content: center; }
.spacer-block span { font-size: .65rem; color: var(--dash-muted); background: var(--dash-card); padding: 0 .4rem; border-radius: .3rem; }
.social-icon-chip { display: inline-flex; align-items: center; justify-content: center; background: var(--dash-primary); color: #fff; border-radius: 50%; font-size: .8rem; }
.html-block { border: 1px dashed var(--dash-border); border-radius: .5rem; overflow: hidden; }
.html-block-label { display: flex; align-items: center; gap: .3rem; font-size: .7rem; font-weight: 600; color: var(--dash-muted); padding: .4rem .6rem; background: var(--dash-card-hover); }
.html-block-preview { width: 100%; height: 100px; border: 0; display: block; background: #fff; }
</style>
