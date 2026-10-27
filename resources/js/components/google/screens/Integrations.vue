<template>
  <div class="gs-screen">
    <div class="gs-head">
      <div>
        <p class="gs-eyebrow">{{ t('brand') }} / {{ t('nav.integrations') }}</p>
        <h1>{{ t('integrations.title') }}</h1>
        <p class="gs-sub">{{ t('integrations.subtitle') }}</p>
      </div>
      <a :href="suite.urls.health" class="gs-btn gs-btn-ghost"><i class="bx bx-pulse"></i>{{ t('health.title') }}</a>
    </div>

    <account-strip v-if="suite.account.connected" />

    <h2 class="gs-h2">{{ t('integrations.available_now') }}</h2>
    <div class="gs-grid-3">
      <article v-for="card in available" :key="card.service" class="gs-card gs-int">
        <div class="gs-svc-top">
          <gs-service-icon :service="card.service" />
          <div class="gs-row">
            <span v-if="card.readOnly" class="gs-chip is-neutral">{{ t('integrations.read_only') }}</span>
            <span class="gs-pill" :class="card.tone"><span class="gs-dot"></span>{{ card.status }}</span>
          </div>
        </div>
        <h3>{{ serviceName(card.service) }}</h3>
        <p>{{ card.value }}</p>
        <p class="gs-muted sm"><i class="bx bx-key"></i> {{ card.access }}</p>
        <p v-if="card.property" class="gs-mono gs-svc-sub">{{ card.property }}</p>
        <div class="gs-svc-actions">
          <a v-for="(a, i) in card.actions" :key="i" :href="a.url" class="gs-btn" :class="i === 0 ? 'gs-btn-primary gs-btn-block' : 'gs-btn-ghost'">{{ a.label }}</a>
        </div>
      </article>
    </div>
    <p class="gs-note"><i class="bx bx-lock-alt"></i>{{ t('integrations.scope_note') }}</p>

    <h2 class="gs-h2">{{ t('integrations.planned') }}</h2>
    <div class="gs-grid-3">
      <article v-for="card in planned" :key="card.service" class="gs-card gs-int is-planned">
        <div class="gs-svc-top">
          <gs-service-icon :service="card.service" />
          <span class="gs-chip is-neutral">{{ t('common.coming_soon') }}</span>
        </div>
        <h3>{{ serviceName(card.service) }}</h3>
        <p>{{ card.value }}</p>
        <p class="gs-muted sm"><i class="bx bx-info-circle"></i> {{ card.access }}</p>
        <div class="gs-svc-actions">
          <a :href="card.url" class="gs-btn gs-btn-ghost gs-btn-block">{{ t('common.learn_more') }}</a>
          <a v-if="card.extra" :href="card.extra.url" class="gs-btn gs-btn-ghost">{{ card.extra.label }}</a>
        </div>
      </article>
    </div>
  </div>
</template>

<script>
import { suite, t, selected, grants, serviceName } from '../store';
import GsServiceIcon from '../ui/GsServiceIcon.vue';
import AccountStrip from './parts/AccountStrip.vue';

export default {
  name: 'GsIntegrations',
  components: { GsServiceIcon, AccountStrip },
  data: () => ({ suite }),
  computed: {
    available() {
      const u = suite.urls;
      const card = (service, value, access, dashboard, settings) => {
        const prop = selected(service);
        const base = { service, value: t(value), access: t(access), readOnly: true, property: prop ? prop.name + ' · ' + prop.external_id.replace('properties/', '') : null };
        if (!suite.account.connected) return { ...base, tone: 'is-neutral', status: t('status.not_connected'), actions: [{ url: u.setup, label: t('common.connect') }] };
        if (!suite.account.usable || !grants(service)) return { ...base, tone: 'is-danger', status: t('status.reauth'), actions: [{ url: u.connect, label: t('common.reconnect') }, { url: settings, label: t('common.manage') }] };
        if (!prop) return { ...base, tone: 'is-warning', status: t('status.needs_setup'), actions: [{ url: u.properties + '#svc-' + service, label: t('common.configure') }] };
        if (prop.last_error_code) return { ...base, tone: 'is-warning', status: t('status.warning'), actions: [{ url: settings, label: t('common.manage') }, { url: dashboard, label: t('common.open_dashboard') }] };
        return { ...base, tone: 'is-success', status: t('status.connected'), actions: [{ url: dashboard, label: t('common.open_dashboard') }, { url: settings, label: t('common.manage') }] };
      };
      return [
        card('ga4', 'integrations.ga4_value', 'integrations.ga4_access', u.analytics, u.settings_ga4),
        card('search_console', 'integrations.sc_value', 'integrations.sc_access', u.search_console, u.settings_search_console),
        { service: 'google_ads', value: t('integrations.ads_value'), access: t('integrations.ads_access'), tone: 'is-brand', status: t('status.in_ads_manager'), actions: [{ url: u.ads, label: t('overview.open_ads') }, { url: u.hub, label: t('common.manage') }] },
      ];
    },
    planned() {
      return [
        { service: 'business_profile', value: t('integrations.gbp_value'), access: t('integrations.gbp_access'), url: suite.urls.business_profile },
        { service: 'tag_manager', value: t('integrations.gtm_value'), access: t('integrations.gtm_access'), url: suite.urls.tag_manager, extra: { url: suite.urls.pixels, label: t('integrations.pixels_link') } },
      ];
    },
  },
  methods: { t, serviceName },
};
</script>
