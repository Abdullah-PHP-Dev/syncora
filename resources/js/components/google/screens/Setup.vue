<template>
  <div class="gs-screen gs-narrow">
    <div class="gs-head">
      <div>
        <p class="gs-eyebrow">{{ t('brand') }} / {{ t('nav.integrations') }} / Google / Setup</p>
        <h1>{{ t('setup.title') }}</h1>
        <p class="gs-sub">{{ t('setup.subtitle') }}</p>
      </div>
    </div>

    <ol class="gs-steps">
      <li :class="suite.account.connected ? 'done' : 'current'"><span class="gs-step-n">1</span><div><strong>{{ t('setup.steps.authorize') }}</strong></div></li>
      <li><span class="gs-step-n">2</span><div><strong>{{ t('setup.steps.choose') }}</strong></div></li>
      <li><span class="gs-step-n">3</span><div><strong>{{ t('setup.steps.verify') }}</strong></div></li>
    </ol>

    <section class="gs-card gs-pad gs-setup">
      <div class="gs-setup-brand">
        <gs-service-icon service="google" />
        <div><h2>Google Analytics 4 + Search Console</h2><p class="gs-muted sm">Google OAuth 2.0</p></div>
      </div>

      <gs-state v-if="!suite.configured" kind="error" icon="bx-wrench" :title="t('setup.not_configured_title')" :text="t('setup.not_configured_text')" :error="null" />

      <div class="gs-setup-cols">
        <div>
          <h3>{{ t('setup.enables_title') }}</h3>
          <ul class="gs-checks">
            <li class="done"><i class="bx bxs-check-circle"></i>{{ t('setup.enables_ga4') }}</li>
            <li class="done"><i class="bx bxs-check-circle"></i>{{ t('setup.enables_sc') }}</li>
            <li class="done"><i class="bx bxs-check-circle"></i>{{ t('setup.enables_health') }}</li>
          </ul>
        </div>
        <div>
          <h3>{{ t('setup.access_title') }}</h3>
          <ul class="gs-scopes">
            <li><i class="bx bx-bar-chart-alt-2"></i><span>{{ t('setup.scope_ga4') }}</span><code>analytics.readonly</code></li>
            <li><i class="bx bx-search"></i><span>{{ t('setup.scope_sc') }}</span><code>webmasters.readonly</code></li>
            <li><i class="bx bx-user"></i><span>{{ t('setup.scope_identity') }}</span><code>openid email</code></li>
          </ul>
        </div>
      </div>

      <div class="gs-privacy">
        <i class="bx bx-shield-quarter"></i>
        <div>
          <strong>{{ t('setup.privacy_title') }}</strong>
          <p>{{ t('setup.privacy_text') }}</p>
          <p><a :href="suite.urls.privacy" class="gs-link" target="_blank" rel="noopener">{{ t('setup.privacy_link') }}</a> · <a :href="suite.urls.google_permissions" class="gs-link" target="_blank" rel="noopener">{{ t('setup.google_permissions') }} ↗</a></p>
        </div>
      </div>

      <div class="gs-setup-cta">
        <template v-if="suite.account.connected && suite.account.usable">
          <span class="gs-pill is-success"><span class="gs-dot"></span>{{ t('setup.already', { email: suite.account.email || t('settings.unknown_email') }) }}</span>
          <a :href="suite.urls.properties" class="gs-btn gs-btn-primary">{{ t('setup.continue') }}</a>
        </template>
        <p v-if="!suite.canManage" class="gs-muted">{{ t('common.owner_only') }}</p>
        <a v-else-if="suite.configured" :href="suite.urls.connect" class="gs-btn-google" :class="{ busy: redirecting }" :aria-disabled="redirecting ? 'true' : 'false'" @click="go">
          <gs-service-icon service="google" />
          <span>{{ redirecting ? t('setup.redirecting') : (suite.account.connected ? t('common.reauthorize') : t('setup.button')) }}</span>
          <span v-if="redirecting" class="gs-spinner sm" aria-hidden="true"></span>
        </a>
      </div>
    </section>
  </div>
</template>

<script>
import { suite, t } from '../store';
import GsState from '../ui/GsState.vue';
import GsServiceIcon from '../ui/GsServiceIcon.vue';

export default {
  name: 'GsSetup',
  components: { GsState, GsServiceIcon },
  data: () => ({ suite, redirecting: false }),
  mounted() {
    // Coming back with the browser's Back button from Google's screen.
    window.addEventListener('pageshow', () => { this.redirecting = false; });
  },
  methods: {
    t,
    go(e) { if (this.redirecting) { e.preventDefault(); return; } this.redirecting = true; },
  },
};
</script>
