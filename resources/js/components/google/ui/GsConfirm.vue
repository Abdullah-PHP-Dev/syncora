<template>
  <div v-if="open" class="gs-modal" role="presentation" @click.self="$emit('close')" @keydown.esc="$emit('close')">
    <div ref="dialog" class="gs-dialog" role="alertdialog" aria-modal="true" :aria-labelledby="id + '-t'" :aria-describedby="id + '-d'" tabindex="-1">
      <h3 :id="id + '-t'">{{ title }}</h3>
      <div :id="id + '-d'" class="gs-dialog-body"><slot /></div>
      <div class="gs-dialog-actions">
        <button ref="cancel" type="button" class="gs-btn gs-btn-ghost" @click="$emit('close')">{{ t('common.cancel') }}</button>
        <button type="button" class="gs-btn" :class="danger ? 'gs-btn-danger' : 'gs-btn-primary'" :disabled="busy" @click="$emit('confirm')">
          <span v-if="busy" class="gs-spinner sm" aria-hidden="true"></span>{{ confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { t } from '../store';

/* Confirmation dialog; focus starts on Cancel so Enter never confirms by accident. */
export default {
  name: 'GsConfirm',
  props: { open: Boolean, title: String, confirmLabel: String, danger: Boolean, busy: Boolean },
  data: () => ({ id: 'gs-confirm-' + Math.random().toString(36).slice(2, 8) }),
  watch: { open(v) { if (v) this.$nextTick(() => this.$refs.cancel && this.$refs.cancel.focus()); } },
  methods: { t },
};
</script>
