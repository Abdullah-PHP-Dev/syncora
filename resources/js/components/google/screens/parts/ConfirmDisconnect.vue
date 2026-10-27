<template>
  <gs-confirm :open="open" :title="t('settings.disconnect_title')" :confirm-label="t('settings.disconnect_confirm')" danger :busy="busy"
              @close="$emit('close')" @confirm="disconnect">
    <p>{{ t('settings.disconnect_text') }}</p>
    <p class="gs-muted sm">{{ t('settings.disconnect_shared') }} <a :href="suite.urls.google_permissions" target="_blank" rel="noopener" class="gs-link">{{ t('setup.google_permissions') }}</a></p>
    <p v-if="error" class="gs-field-error" role="alert">{{ error }}</p>
  </gs-confirm>
</template>

<script>
import { suite, t, api } from '../../store';
import GsConfirm from '../../ui/GsConfirm.vue';

/* Disconnect with confirmation; the server revokes / forgets and the page returns to Integrations. */
export default {
  name: 'GsConfirmDisconnect',
  components: { GsConfirm },
  props: { open: Boolean },
  data: () => ({ suite, busy: false, error: '' }),
  methods: {
    t,
    async disconnect() {
      this.busy = true;
      this.error = '';
      try {
        await api('delete', suite.urls.api.disconnect);
        window.location.href = suite.urls.integrations;
      } catch (e) {
        this.error = e.message;
        this.busy = false;
      }
    },
  },
};
</script>
