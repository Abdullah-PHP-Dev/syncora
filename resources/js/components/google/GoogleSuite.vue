<template>
  <div class="gs" :dir="suite.dir">
    <header class="gs-top">
      <div class="gs-top-id">
        <a v-if="backUrl" :href="backUrl" class="gs-back" :aria-label="t('nav.back')"><i class="bx" :class="suite.dir === 'rtl' ? 'bx-chevron-right' : 'bx-chevron-left'"></i></a>
        <div>
          <p class="gs-eyebrow">{{ t('brand') }} / {{ t('title') }}</p>
          <p class="gs-top-title">{{ t('workspace') }}</p>
        </div>
      </div>
      <span class="gs-pill" :class="statusPill.tone"><span class="gs-dot" aria-hidden="true"></span>{{ statusPill.text }}</span>
    </header>

    <nav class="gs-tabs" :aria-label="t('nav.tabs_aria')">
      <div class="gs-tabs-main">
        <a v-for="tab in tabs" :key="tab.key" :href="tab.url" class="gs-tab" :class="{ active: tab.active }" :aria-current="tab.active ? 'page' : null">
          <span v-if="tab.color" class="gs-tab-dot" :style="{ background: tab.color }" aria-hidden="true"></span>{{ tab.label }}
          <span v-if="tab.planned" class="gs-tab-badge">{{ t('common.coming_soon') }}</span>
        </a>
      </div>
      <div class="gs-tabs-side">
        <a v-for="tab in sideTabs" :key="tab.key" :href="tab.url" class="gs-tab" :class="{ active: tab.active }" :aria-current="tab.active ? 'page' : null">{{ tab.label }}</a>
      </div>
    </nav>

    <main class="gs-main">
      <component :is="screenComponent" />
    </main>

    <transition name="gs-fade">
      <div v-if="suite.toast" :key="suite.toast.id" class="gs-toast" :class="'is-' + suite.toast.type" role="status" aria-live="polite">
        <i class="bx" :class="suite.toast.type === 'error' ? 'bx-error-circle' : 'bx-check-circle'"></i>{{ suite.toast.message }}
      </div>
    </transition>
  </div>
</template>

<script>
import './google-suite.css';
import { suite, init, t, selected, fmt, COLORS } from './store';
import Overview from './screens/Overview.vue';
import Integrations from './screens/Integrations.vue';
import Setup from './screens/Setup.vue';
import Status from './screens/Status.vue';
import Properties from './screens/Properties.vue';
import ServiceSettings from './screens/ServiceSettings.vue';
import Analytics from './screens/Analytics.vue';
import SearchConsole from './screens/SearchConsole.vue';
import Planned from './screens/Planned.vue';
import Reports from './screens/Reports.vue';
import Preferences from './screens/Preferences.vue';
import Health from './screens/Health.vue';

const SCREENS = {
  overview: Overview, integrations: Integrations, setup: Setup, status: Status, properties: Properties, settings: ServiceSettings,
  analytics: Analytics, search_console: SearchConsole, business_profile: Planned, tag_manager: Planned, reports: Reports, preferences: Preferences, health: Health,
};

/**
 * Google Suite (Analytics & Insights). One app, mounted per server route
 * (GoogleSuiteController) on the screen that route names.
 */
export default {
  name: 'GoogleSuite',
  props: { boot: { type: Object, required: true } },
  data: () => ({ suite }),
  computed: {
    screenComponent() { return SCREENS[suite.screen] || Overview; },
    backUrl() { return suite.screen === 'overview' ? null : suite.urls.overview; },
    tabs() {
      const u = suite.urls; const s = suite.screen;
      return [
        { key: 'overview', label: t('nav.tab_overview'), url: u.overview, active: s === 'overview' },
        { key: 'analytics', label: t('nav.tab_analytics'), url: u.analytics, active: s === 'analytics' || (s === 'settings' && suite.extra.service === 'ga4'), color: COLORS.ga4 },
        { key: 'search_console', label: t('nav.tab_search_console'), url: u.search_console, active: s === 'search_console' || (s === 'settings' && suite.extra.service === 'search_console'), color: COLORS.search_console },
        { key: 'business_profile', label: t('nav.tab_business_profile'), url: u.business_profile, active: s === 'business_profile', color: COLORS.business_profile, planned: true },
        { key: 'tag_manager', label: t('nav.tab_tag_manager'), url: u.tag_manager, active: s === 'tag_manager', color: '#174EA6', planned: true },
      ];
    },
    sideTabs() {
      const u = suite.urls; const s = suite.screen;
      return [
        { key: 'integrations', label: t('nav.integrations'), url: u.integrations, active: s === 'integrations' },
        { key: 'reports', label: t('nav.reports'), url: u.reports, active: s === 'reports' },
        { key: 'credentials', label: t('nav.credentials'), url: u.properties, active: ['properties', 'setup', 'status'].includes(s) },
        { key: 'settings', label: t('nav.settings'), url: u.preferences, active: ['preferences', 'health'].includes(s) },
      ];
    },
    statusPill() {
      const a = suite.account;
      if (!a.connected) return { tone: 'is-neutral', text: t('status.not_connected') };
      if (!a.usable) return { tone: 'is-danger', text: t('status.reauth') };
      const props = ['ga4', 'search_console'].map(selected).filter(Boolean);
      if (props.some((p) => p.last_error_code)) return { tone: 'is-warning', text: t('status.warning') };
      const last = props.map((p) => p.last_synced_at).filter(Boolean).sort().pop();
      if (!props.length) return { tone: 'is-warning', text: t('status.needs_setup') };
      return { tone: 'is-success', text: last ? `${t('common.last_synced')} ${fmt.ago(last)}` : t('status.connected') };
    },
  },
  created() { init(this.boot); },
  methods: { t },
};
</script>
