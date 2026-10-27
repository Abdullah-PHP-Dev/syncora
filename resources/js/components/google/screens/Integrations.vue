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

    <section v-for="group in groups" :key="group.key" class="gs-int-group" :aria-labelledby="'gs-int-' + group.key">
      <div class="gs-int-group-head">
        <h2 :id="'gs-int-' + group.key" class="gs-h2">{{ t('integrations.tiers.' + group.key) }}</h2>
        <p class="gs-muted sm">{{ t('integrations.tiers.' + group.key + '_hint') }}</p>
      </div>
      <div class="gs-grid-2">
        <article v-for="card in group.cards" :key="card.service" class="gs-card gs-int" :class="{ 'is-planned': card.planned }">
          <div class="gs-svc-top">
            <gs-service-icon :service="card.service" />
            <div class="gs-row">
              <span v-if="card.chip" class="gs-chip" :class="card.chip.tone">{{ card.chip.text }}</span>
              <span class="gs-pill" :class="card.tone"><span class="gs-dot"></span>{{ card.status }}</span>
            </div>
          </div>
          <p class="gs-svc-outcome">{{ t('outcomes.' + card.outcome) }}</p>
          <h3>{{ serviceName(card.service) }}</h3>
          <p>{{ t('integrations.value.' + card.service) }}</p>
          <ul class="gs-int-points">
            <li v-for="(p, i) in tr('integrations.helps.' + card.service)" :key="i"><i class="bx bx-check"></i>{{ p }}</li>
          </ul>
          <p class="gs-muted sm gs-int-access"><i class="bx" :class="card.planned ? 'bx-info-circle' : 'bx-key'"></i> {{ t('integrations.access.' + card.service) }}</p>
          <p v-if="card.property" class="gs-mono gs-svc-sub">{{ card.property }}</p>
          <div class="gs-svc-actions">
            <a v-for="(a, i) in card.actions" :key="i" :href="a.url" class="gs-btn" :class="i === 0 && !card.planned ? 'gs-btn-primary gs-btn-block' : 'gs-btn-ghost' + (i === 0 ? ' gs-btn-block' : '')">{{ a.label }}</a>
          </div>
        </article>
      </div>
    </section>
    <p class="gs-note"><i class="bx bx-lock-alt"></i>{{ t('integrations.scope_note') }}</p>
  </div>
</template>

<script>
import { suite, t, raw, selected, grants, serviceName } from '../store';
import GsServiceIcon from '../ui/GsServiceIcon.vue';
import AccountStrip from './parts/AccountStrip.vue';

/*
 * Services grouped by how much they matter to a typical SocialEaz customer:
 * core (GA4, Search Console), recommended (Google Ads, PageSpeed), optional
 * (Business Profile - local businesses only) and later (Tag Manager).
 */
export default {
  name: 'GsIntegrations',
  components: { GsServiceIcon, AccountStrip },
  data: () => ({ suite }),
  computed: {
    groups() {
      const u = suite.urls;
      const google = (service, dashboard, settings) => {
        const prop = selected(service);
        const base = { service, outcome: service === 'ga4' ? 'traffic' : 'search', chip: { tone: 'is-neutral', text: t('integrations.read_only') }, property: prop ? prop.name + ' · ' + prop.external_id.replace('properties/', '') : null };
        if (!suite.account.connected) return { ...base, tone: 'is-neutral', status: t('status.not_connected'), actions: [{ url: u.setup, label: t('common.connect') }] };
        if (!suite.account.usable || !grants(service)) return { ...base, tone: 'is-danger', status: t('status.reauth'), actions: [{ url: u.connect, label: t('common.reconnect') }, { url: settings, label: t('common.manage') }] };
        if (!prop) return { ...base, tone: 'is-warning', status: t('status.needs_setup'), actions: [{ url: u.properties + '#svc-' + service, label: t('common.configure') }] };
        if (prop.last_error_code) return { ...base, tone: 'is-warning', status: t('status.warning'), actions: [{ url: settings, label: t('common.manage') }, { url: dashboard, label: t('common.open_dashboard') }] };
        return { ...base, tone: 'is-success', status: t('status.connected'), actions: [{ url: dashboard, label: t('common.open_dashboard') }, { url: settings, label: t('common.manage') }] };
      };
      const ads = suite.extra.ads_account
        ? { service: 'google_ads', outcome: 'advertising', tone: 'is-success', status: t('status.connected'), property: suite.extra.ads_account.name + ' · ' + suite.extra.ads_account.customer_id, actions: [{ url: u.advertising, label: t('common.open_dashboard') }, { url: u.ads, label: t('overview.open_ads') }] }
        : { service: 'google_ads', outcome: 'advertising', tone: 'is-neutral', status: t('status.not_connected'), actions: [{ url: u.ads_connect, label: t('ads.connect') }] };
      return [
        { key: 'core', cards: [google('ga4', u.analytics, u.settings_ga4), google('search_console', u.search_console, u.settings_search_console)] },
        { key: 'recommended', cards: [ads, {
          service: 'pagespeed', outcome: 'performance', tone: 'is-success', status: t('status.available'), chip: { tone: 'is-brand', text: t('speed.no_signin') },
          actions: [{ url: u.pagespeed, label: t('speed.open') }],
        }] },
        { key: 'optional', cards: [{
          service: 'business_profile', outcome: 'local', planned: true, tone: 'is-neutral', status: t('status.planned'), chip: { tone: 'is-neutral', text: t('integrations.optional_chip') },
          actions: [{ url: u.business_profile, label: t('common.learn_more') }],
        }] },
        { key: 'later', cards: [{
          service: 'tag_manager', outcome: 'tracking', planned: true, tone: 'is-neutral', status: t('status.later'), chip: { tone: 'is-neutral', text: t('integrations.advanced_chip') },
          actions: [{ url: u.tag_manager, label: t('common.learn_more') }, { url: u.pixels, label: t('integrations.pixels_link') }],
        }] },
      ];
    },
  },
  methods: { t, serviceName, tr: (key) => raw(key) || [] },
};
</script>
