<template>
  <div class="gs-screen gs-narrow">
    <section class="gs-card gs-pad gs-status" :class="'is-' + view.tone" role="status">
      <span class="gs-status-icon"><i class="bx" :class="view.icon"></i></span>
      <h1>{{ view.title }}</h1>
      <p>{{ view.text }}</p>

      <ol v-if="oauth && ['success', 'partial'].includes(oauth.status)" class="gs-progress">
        <li class="done"><i class="bx bx-check"></i>{{ t('oauth.progress.0') }}</li>
        <li :class="oauth.status === 'success' ? 'done' : 'warn'"><i class="bx" :class="oauth.status === 'success' ? 'bx-check' : 'bx-error'"></i>{{ t('oauth.progress.1') }}</li>
        <li :class="discoveryOk ? 'done' : 'warn'"><i class="bx" :class="discoveryOk ? 'bx-check' : 'bx-error'"></i>{{ t('oauth.progress.2') }}</li>
      </ol>
      <p v-if="foundText" class="gs-muted">{{ foundText }}</p>
      <p v-for="msg in discoveryErrors" :key="msg" class="gs-muted sm">{{ msg }}</p>

      <div class="gs-row gs-center">
        <a v-if="oauth && ['success', 'partial'].includes(oauth.status)" :href="suite.urls.properties" class="gs-btn gs-btn-primary">{{ t('oauth.choose_properties') }}</a>
        <a v-if="!oauth || oauth.status !== 'success'" :href="suite.canManage ? (oauth ? suite.urls.connect : suite.urls.setup) : suite.urls.setup" class="gs-btn" :class="oauth && ['success', 'partial'].includes(oauth.status) ? 'gs-btn-ghost' : 'gs-btn-primary'">
          {{ oauth && oauth.status === 'partial' ? t('common.reconnect') : t('oauth.try_again') }}
        </a>
        <a :href="suite.urls.integrations" class="gs-btn gs-btn-ghost">{{ t('oauth.back_to_integrations') }}</a>
      </div>
    </section>
  </div>
</template>

<script>
import { suite, t, serviceName } from '../store';

/* Shows only what the callback confirmed (flashed by GoogleSuiteAuth) - never assumes success. */
export default {
  name: 'GsStatus',
  data: () => ({ suite }),
  computed: {
    oauth() { return suite.extra.oauth || null; },
    discoveryOk() { const f = (this.oauth && this.oauth.found) || {}; return Object.values(f).every((x) => !x.error); },
    discoveryErrors() {
      const f = (this.oauth && this.oauth.found) || {};
      return Object.keys(f).filter((k) => f[k].error && f[k].error !== 'missing_scope').map((k) => t('oauth.found_error', { service: serviceName(k) }));
    },
    foundText() {
      const f = this.oauth && this.oauth.found;
      if (!f) return '';
      return t('oauth.found', { ga4: (f.ga4 && f.ga4.count) || 0, sc: (f.search_console && f.search_console.count) || 0 });
    },
    view() {
      const o = this.oauth;
      if (!o) return { tone: 'neutral', icon: 'bx-info-circle', title: t('oauth.none_title'), text: t('oauth.none_text') };
      if (o.status === 'success') return { tone: 'success', icon: 'bxs-check-circle', title: t('oauth.success_title'), text: t('oauth.success_text') };
      if (o.status === 'partial') return { tone: 'warning', icon: 'bxs-error', title: t('oauth.partial_title'), text: t('oauth.partial_text', { services: (o.missing || []).map(serviceName).join(', ') }) };
      if (o.status === 'cancelled') return { tone: 'neutral', icon: 'bx-x-circle', title: t('oauth.cancelled_title'), text: t('oauth.cancelled_text') };
      if (o.status === 'denied') return { tone: 'danger', icon: 'bxs-shield-x', title: t('oauth.denied_title'), text: t('oauth.denied_text') };
      return { tone: 'danger', icon: 'bxs-error-circle', title: t('oauth.failed_title'), text: t('errors.' + (o.code || 'generic')) };
    },
  },
  methods: { t },
};
</script>
