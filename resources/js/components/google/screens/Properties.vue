<template>
  <div class="gs-screen">
    <div class="gs-head">
      <div>
        <p class="gs-eyebrow">{{ t('brand') }} / {{ t('nav.integrations') }} / Google / {{ t('nav.credentials') }}</p>
        <h1>{{ t('properties.title') }}</h1>
        <p class="gs-sub">{{ t('properties.subtitle') }}</p>
      </div>
    </div>

    <ol class="gs-steps">
      <li :class="suite.account.connected ? 'done' : 'current'">
        <span class="gs-step-n"><i v-if="suite.account.connected" class="bx bx-check"></i><template v-else>1</template></span>
        <div><strong>{{ t('properties.step_authorize') }}</strong><small>{{ suite.account.email || t('status.not_connected') }}</small></div>
      </li>
      <li :class="!suite.account.connected ? '' : readyCount === 2 || verified ? 'done' : 'current'">
        <span class="gs-step-n"><i v-if="suite.account.connected && (readyCount === 2 || verified)" class="bx bx-check"></i><template v-else>2</template></span>
        <div><strong>{{ t('properties.step_choose') }}</strong><small>{{ t('properties.step_in_progress', { done: readyCount }) }}</small></div>
      </li>
      <li :class="verified ? 'done' : readyCount > 0 ? 'current' : ''">
        <span class="gs-step-n"><i v-if="verified" class="bx bx-check"></i><template v-else>3</template></span>
        <div><strong>{{ t('properties.step_verify') }}</strong><small>{{ verified ? t('status.healthy') : t('properties.verify') }}</small></div>
      </li>
    </ol>

    <gs-state v-if="!suite.account.connected" kind="setup" icon="bxl-google" :title="t('states.first_time_title')" :text="t('states.first_time_text')">
      <a v-if="suite.canManage" :href="suite.urls.setup" class="gs-btn gs-btn-primary gs-btn-sm"><i class="bx bxl-google"></i>{{ t('setup.button') }}</a>
      <span v-else class="gs-muted sm">{{ t('common.owner_only') }}</span>
    </gs-state>

    <div v-else class="gs-wizard">
      <aside class="gs-card gs-wiz-nav">
        <a v-for="item in navItems" :key="item.service" :href="item.href" class="gs-wiz-item" :class="{ active: item.active }">
          <gs-service-icon :service="item.service" /><span>{{ serviceName(item.service) }}</span>
          <span class="gs-chip" :class="item.tone">{{ item.label }}</span>
        </a>
        <p class="gs-wiz-note"><i class="bx bx-shield-quarter"></i>{{ t('setup.privacy_text') }}</p>
      </aside>

      <div class="gs-stack">
        <gs-state v-if="!suite.account.usable" kind="error" :error="{ code: 'auth_expired', message: t('errors.auth_expired'), reconnect: true }" />

        <section v-for="service in ['ga4', 'search_console']" :id="'svc-' + service" :key="service" class="gs-card gs-pad gs-prop-card" :class="{ focus: focusService === service }">
          <div class="gs-card-head">
            <div class="gs-row">
              <gs-service-icon :service="service" />
              <div>
                <h2>{{ t(service === 'ga4' ? 'properties.ga4_title' : 'properties.sc_title') }}</h2>
                <p class="gs-muted sm">{{ t(service === 'ga4' ? 'properties.ga4_subtitle' : 'properties.sc_subtitle') }}</p>
              </div>
            </div>
            <span v-if="results[service]" class="gs-pill" :class="results[service] === 'ok' ? 'is-success' : 'is-danger'"><span class="gs-dot"></span>{{ results[service] === 'ok' ? t('status.healthy') : t('status.error') }}</span>
            <span v-else-if="saved[service]" class="gs-pill is-success"><span class="gs-dot"></span>{{ t('status.connected') }}</span>
          </div>

          <gs-state v-if="results[service] && results[service] !== 'ok'" kind="error" compact
                    :error="{ code: results[service], message: t('errors.' + results[service]), reconnect: ['auth_expired', 'missing_scope'].includes(results[service]), retryable: false }" />
          <gs-state v-if="!grants(service)" kind="error" compact :error="{ code: 'missing_scope', message: t('properties.scope_missing', { service: serviceName(service) }), reconnect: true }" />
          <gs-state v-else-if="discovering && !list(service).length" kind="loading" :title="t('properties.discovering')" compact />
          <gs-state v-else-if="discoverError" kind="error" :error="discoverError" compact @retry="discover" />
          <gs-state v-else-if="!list(service).length" kind="empty" icon="bx-search-alt" :title="t(service === 'ga4' ? 'properties.ga4_empty_title' : 'properties.sc_empty_title')" :text="t(service === 'ga4' ? 'properties.ga4_empty_text' : 'properties.sc_empty_text')" compact>
            <button type="button" class="gs-btn gs-btn-ghost gs-btn-sm" :disabled="discovering" @click="discover"><i class="bx bx-refresh"></i>{{ t('properties.refresh_list') }}</button>
            <a :href="service === 'ga4' ? 'https://analytics.google.com/' : 'https://search.google.com/search-console'" target="_blank" rel="noopener" class="gs-link">{{ t(service === 'ga4' ? 'properties.open_ga' : 'properties.open_sc') }} ↗</a>
          </gs-state>

          <template v-else>
            <div class="gs-row gs-prop-tools">
              <label class="gs-search">
                <i class="bx bx-search" aria-hidden="true"></i>
                <span class="gs-sr">{{ t('properties.search') }}</span>
                <input v-model.trim="search[service]" type="search" :placeholder="t('properties.search')">
              </label>
              <button type="button" class="gs-btn gs-btn-ghost gs-btn-sm" :disabled="discovering" @click="discover"><i class="bx bx-refresh" :class="{ 'bx-spin': discovering }"></i>{{ t('properties.refresh_list') }}</button>
            </div>

            <fieldset class="gs-choices">
              <legend class="gs-sr">{{ serviceName(service) }}</legend>
              <label v-for="p in filtered(service)" :key="p.id" class="gs-choice" :class="{ checked: choice[service] === p.id }">
                <input v-model="choice[service]" type="radio" :name="'gs-' + service" :value="p.id">
                <span class="gs-choice-main">
                  <strong>{{ p.name }}</strong>
                  <small v-if="service === 'ga4'">{{ [p.account_name, p.website_url].filter(Boolean).join(' · ') }}</small>
                  <small v-else>{{ t(p.property_type === 'domain' ? 'properties.domain' : 'properties.url_prefix') }}<template v-if="p.permission_level"> · {{ t('properties.permission', { level: permission(p.permission_level) }) }}</template></small>
                </span>
                <span class="gs-choice-ids gs-mono">
                  <template v-if="service === 'ga4'">{{ p.external_id.replace('properties/', '') }}<template v-if="p.measurement_id"> · {{ p.measurement_id }}</template></template>
                  <template v-else>{{ p.external_id }}</template>
                </span>
              </label>
              <p v-if="search[service] && !filtered(service).length" class="gs-muted sm">{{ t('properties.no_matches', { q: search[service] }) }}</p>
              <label class="gs-choice is-none" :class="{ checked: choice[service] === null }">
                <input v-model="choice[service]" type="radio" :name="'gs-' + service" :value="null">
                <span class="gs-choice-main"><strong>{{ t('properties.none_selected') }}</strong></span>
              </label>
            </fieldset>
            <p v-if="service === 'ga4'" class="gs-note"><i class="bx bx-info-circle"></i>{{ t('properties.measurement_note') }}</p>
          </template>
        </section>

        <section v-for="service in ['business_profile', 'tag_manager']" :key="service" class="gs-card gs-pad is-planned">
          <div class="gs-row">
            <gs-service-icon :service="service" />
            <div><h2>{{ serviceName(service) }}</h2><p class="gs-muted sm">{{ t('planned.' + service + '_blocker') }}</p></div>
            <span class="gs-chip is-neutral gs-push">{{ t('common.coming_soon') }}</span>
          </div>
        </section>

        <div v-if="verified" class="gs-card gs-pad gs-success-box" role="status">
          <i class="bx bxs-check-circle"></i>
          <div><strong>{{ t('properties.verified_title') }}</strong><p>{{ t('properties.verified_text') }}</p></div>
          <a :href="suite.urls.overview" class="gs-btn gs-btn-primary">{{ t('properties.go_overview') }}</a>
        </div>
        <gs-state v-else-if="verifyFailed" kind="error" :error="{ code: 'generic', message: t('properties.verify_failed'), retryable: false }" compact />
      </div>
    </div>

    <div v-if="suite.account.connected" class="gs-card gs-sticky-bar">
      <p><strong>{{ t('properties.ready', { n: chosenCount }) }}</strong></p>
      <div class="gs-row">
        <button type="button" class="gs-btn gs-btn-ghost" :disabled="saving || !dirty" @click="save()">{{ saving && !verifying ? t('common.saving') : t('properties.save') }}</button>
        <button type="button" class="gs-btn gs-btn-primary" :disabled="saving || chosenCount === 0" @click="verify">
          <span v-if="verifying" class="gs-spinner sm light" aria-hidden="true"></span>{{ verifying ? t('properties.verifying') : t('properties.verify') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { suite, t, api, selected, grants, serviceName, toast } from '../store';
import GsState from '../ui/GsState.vue';
import GsServiceIcon from '../ui/GsServiceIcon.vue';

const PAGE = 30;

export default {
  name: 'GsProperties',
  components: { GsState, GsServiceIcon },
  data() {
    return {
      suite, discovering: false, discoverError: null, saving: false, verifying: false, verified: false, verifyFailed: false,
      search: { ga4: '', search_console: '' },
      choice: { ga4: selected('ga4') ? selected('ga4').id : null, search_console: selected('search_console') ? selected('search_console').id : null },
      saved: { ga4: !!selected('ga4'), search_console: !!selected('search_console') },
      results: {},
      focusService: (window.location.hash || '').replace('#svc-', ''),
    };
  },
  computed: {
    readyCount() { return ['ga4', 'search_console'].filter((s) => selected(s)).length; },
    chosenCount() { return ['ga4', 'search_console'].filter((s) => this.choice[s] !== null).length; },
    dirty() { return ['ga4', 'search_console'].some((s) => (selected(s) ? selected(s).id : null) !== this.choice[s]); },
    navItems() {
      return [
        ...['ga4', 'search_console'].map((service) => {
          const ok = !!selected(service);
          return { service, href: '#svc-' + service, active: this.focusService === service, tone: ok ? 'is-success' : grants(service) ? 'is-warning' : 'is-danger', label: ok ? t('status.connected') : grants(service) ? t('status.needs_setup') : t('status.reauth') };
        }),
        { service: 'business_profile', href: suite.urls.business_profile, tone: 'is-neutral', label: t('common.coming_soon') },
        { service: 'tag_manager', href: suite.urls.tag_manager, tone: 'is-neutral', label: t('common.coming_soon') },
      ];
    },
  },
  mounted() {
    const empty = !suite.properties.ga4.length && !suite.properties.search_console.length;
    if (suite.account.connected && suite.account.usable && empty) this.discover();
  },
  methods: {
    t, grants, serviceName,
    list(service) { return suite.properties[service] || []; },
    filtered(service) {
      const q = (this.search[service] || '').toLowerCase();
      const rows = q ? this.list(service).filter((p) => [p.name, p.external_id, p.account_name, p.website_url, p.measurement_id].filter(Boolean).some((v) => v.toLowerCase().includes(q))) : this.list(service);
      return q ? rows : rows.slice(0, PAGE);
    },
    permission(level) { return String(level).replace(/^site/, '').replace(/([A-Z])/g, ' $1').trim(); },
    async discover() {
      this.discovering = true; this.discoverError = null;
      try {
        const res = await api('post', suite.urls.api.discover);
        suite.properties = res.properties;
        ['ga4', 'search_console'].forEach((s) => {
          if (this.choice[s] !== null && !this.list(s).some((p) => p.id === this.choice[s])) this.choice[s] = null;
          if (this.choice[s] === null && this.list(s).length === 1) this.choice[s] = this.list(s)[0].id; // only one option: preselect, still needs Save
        });
      } catch (e) { this.discoverError = e; } finally { this.discovering = false; }
    },
    async save(quiet = false) {
      this.saving = true;
      try {
        for (const service of ['ga4', 'search_console']) {
          const current = selected(service) ? selected(service).id : null;
          if (current === this.choice[service]) continue;
          const res = await api('put', suite.urls.api.select, { data: { service, property_id: this.choice[service] } });
          suite.properties = res.properties;
          this.saved[service] = this.choice[service] !== null;
        }
        if (!quiet) toast(t('properties.saved'));
        return true;
      } catch (e) {
        toast(e.message, 'error');
        return false;
      } finally { this.saving = false; }
    },
    async verify() {
      this.verifying = true; this.verified = false; this.verifyFailed = false; this.results = {};
      try {
        if (!(await this.save(true))) return;
        this.saving = true;
        const res = await api('post', suite.urls.api.refresh);
        suite.properties = res.properties;
        this.results = res.results;
        const values = Object.values(res.results);
        this.verified = values.length > 0 && values.every((v) => v === 'ok');
        this.verifyFailed = !this.verified;
      } catch (e) {
        toast(e.message, 'error');
      } finally { this.verifying = false; this.saving = false; }
    },
  },
};
</script>
