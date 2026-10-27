<template>
  <div class="gs-state" :class="['is-' + kind, { compact }]" :role="kind === 'error' ? 'alert' : 'status'">
    <span v-if="kind === 'loading'" class="gs-spinner" aria-hidden="true"></span>
    <span v-else class="gs-state-icon" aria-hidden="true"><i class="bx" :class="iconClass"></i></span>
    <div class="gs-state-body">
      <strong v-if="heading">{{ heading }}</strong>
      <p v-if="body">{{ body }}</p>
      <div v-if="$slots.default || actionsShown" class="gs-state-actions">
        <a v-if="error && error.reconnect && suite.canManage" :href="suite.urls.connect" class="gs-btn gs-btn-primary gs-btn-sm"><i class="bx bxl-google"></i>{{ t('common.reconnect') }}</a>
        <a v-if="error && ['no_property', 'not_found', 'insufficient_permissions'].includes(error.code)" :href="suite.urls.properties" class="gs-btn gs-btn-primary gs-btn-sm">{{ t('overview.choose_property') }}</a>
        <a v-if="error && error.code === 'not_connected' && suite.canManage" :href="suite.urls.setup" class="gs-btn gs-btn-primary gs-btn-sm"><i class="bx bxl-google"></i>{{ t('setup.button') }}</a>
        <button v-if="error && error.retryable !== false && !error.reconnect && !['no_property', 'not_connected'].includes(error.code)" type="button" class="gs-btn gs-btn-ghost gs-btn-sm" @click="$emit('retry')"><i class="bx bx-refresh"></i>{{ t('common.retry') }}</button>
        <slot />
      </div>
    </div>
  </div>
</template>

<script>
import { suite, t } from '../store';

/** One component for every loading / empty / error / setup state, with next steps. */
export default {
  name: 'GsState',
  props: { kind: { type: String, default: 'empty' }, title: { type: String, default: '' }, text: { type: String, default: '' }, error: { type: Object, default: null }, icon: { type: String, default: '' }, compact: { type: Boolean, default: false } },
  data: () => ({ suite }),
  computed: {
    heading() { return this.title || (this.kind === 'loading' ? t('states.loading') : ''); },
    body() { return this.text || (this.error ? this.error.message : ''); },
    actionsShown() { return !!this.error; },
    iconClass() {
      if (this.icon) return this.icon;
      if (!this.error) return this.kind === 'empty' ? 'bx-bar-chart-alt-2' : 'bx-info-circle';
      return { auth_expired: 'bx-lock-alt', missing_scope: 'bx-shield-x', quota_exceeded: 'bx-time-five', provider_outage: 'bx-cloud-lightning', no_property: 'bx-list-check', not_connected: 'bxl-google' }[this.error.code] || 'bx-error-circle';
    },
  },
  methods: { t },
};
</script>
