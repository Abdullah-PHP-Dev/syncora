<template>
  <div class="gs-screen gs-narrow">
    <section class="gs-card gs-pad gs-planned">
      <div class="gs-row">
        <gs-service-icon :service="key" />
        <span class="gs-chip is-neutral">{{ t(key === 'tag_manager' ? 'status.later' : 'integrations.optional_chip') }}</span>
      </div>
      <div>
        <p class="gs-eyebrow">{{ t('outcomes.' + (key === 'tag_manager' ? 'tracking' : 'local')) }}</p>
        <h1>{{ t('planned.' + key + '_title') }}</h1>
        <p class="gs-sub">{{ t('planned.' + key + '_text') }}</p>
      </div>

      <div class="gs-planned-cols">
        <div>
          <h3>{{ t('planned.who_title') }}</h3>
          <p class="gs-muted">{{ t('planned.' + key + '_who') }}</p>
        </div>
        <div>
          <h3>{{ t('planned.what_title') }}</h3>
          <ul class="gs-checks">
            <li v-for="(p, i) in points" :key="i"><i class="bx bx-time-five"></i>{{ p }}</li>
          </ul>
        </div>
      </div>

      <div class="gs-callout"><i class="bx bx-info-circle"></i><p>{{ t('planned.' + key + '_why') }}</p></div>
      <h3>{{ t('planned.meanwhile') }}</h3>
      <p class="gs-muted">{{ key === 'tag_manager' ? t('planned.gtm_meanwhile') : t('planned.gbp_meanwhile') }}</p>
      <div class="gs-row">
        <a v-if="key === 'tag_manager'" :href="suite.urls.pixels" class="gs-btn gs-btn-ghost">{{ t('planned.open_integrations') }}</a>
        <a :href="suite.urls.integrations" class="gs-btn gs-btn-primary">{{ t('oauth.back_to_integrations') }}</a>
      </div>
    </section>
  </div>
</template>

<script>
import { suite, t, raw } from '../store';
import GsServiceIcon from '../ui/GsServiceIcon.vue';

/* Business Profile (optional, local businesses) / Tag Manager (later): not connected, so no data and no fake numbers - who it's for and why it isn't in the main experience. */
export default {
  name: 'GsPlanned',
  components: { GsServiceIcon },
  data: () => ({ suite }),
  computed: {
    key() { return suite.screen; },
    points() { return raw('planned.' + this.key + '_points') || []; },
  },
  methods: { t },
};
</script>
