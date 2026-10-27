<template>
  <div class="gs-screen">
    <div class="gs-head">
      <div>
        <h1>{{ t('settings.title', { service: serviceName(service) }) }}</h1>
        <p class="gs-sub">{{ t('settings.subtitle') }}</p>
      </div>
      <div class="gs-head-actions">
        <a :href="dashboardUrl" class="gs-btn gs-btn-ghost">{{ t('common.open_dashboard') }}</a>
      </div>
    </div>

    <gs-state v-if="!suite.account.connected" kind="setup" icon="bxl-google" :title="t('states.first_time_title')" :text="t('states.first_time_text')">
      <a v-if="suite.canManage" :href="suite.urls.setup" class="gs-btn gs-btn-primary gs-btn-sm">{{ t('setup.button') }}</a>
    </gs-state>

    <div v-else class="gs-split">
      <div class="gs-stack">
        <section class="gs-card gs-pad">
          <h2>{{ t('settings.account') }}</h2>
          <dl class="gs-dl">
            <div><dt>{{ t('settings.account') }}</dt><dd>{{ suite.account.email || t('settings.unknown_email') }}<small v-if="suite.account.connected_at" class="gs-muted"> · {{ t('settings.connected_since', { date: fmt.date(suite.account.connected_at, { day: 'numeric', month: 'short', year: 'numeric' }) }) }}</small></dd></div>
            <div><dt>{{ t('settings.status') }}</dt><dd><span class="gs-pill" :class="suite.account.usable ? 'is-success' : 'is-danger'"><span class="gs-dot"></span>{{ t('health.connection_status.' + suite.account.status) }}</span></dd></div>
            <div><dt>{{ t('settings.permission') }}</dt><dd><span class="gs-chip" :class="grants(service) ? 'is-success' : 'is-danger'">{{ grants(service) ? t('settings.granted') : t('settings.not_granted') }}</span> <code class="gs-code">{{ service === 'ga4' ? 'analytics.readonly' : 'webmasters.readonly' }}</code></dd></div>
          </dl>
          <div v-if="suite.canManage" class="gs-row">
            <button type="button" class="gs-btn gs-btn-ghost" :disabled="checking" @click="check">{{ checking ? t('settings.checking') : t('settings.check_connection') }}</button>
            <a :href="suite.urls.connect" class="gs-btn gs-btn-ghost">{{ t('common.reconnect') }}</a>
            <button type="button" class="gs-btn gs-btn-ghost gs-btn-danger-text" @click="confirm = true">{{ t('common.disconnect') }}</button>
          </div>
          <p v-else class="gs-muted sm">{{ t('common.owner_only') }}</p>
        </section>

        <section class="gs-card gs-pad">
          <div class="gs-card-head">
            <h2>{{ t('settings.property') }}</h2>
            <a :href="suite.urls.properties + '#svc-' + service" class="gs-btn gs-btn-ghost gs-btn-sm">{{ t('settings.change_property') }}</a>
          </div>
          <template v-if="prop">
            <dl class="gs-dl">
              <div><dt>{{ t('settings.name') }}</dt><dd><strong>{{ prop.name }}</strong></dd></div>
              <div><dt>{{ service === 'ga4' ? t('properties.property_id') : t('properties.website') }}</dt><dd class="gs-mono">{{ prop.external_id }}</dd></div>
              <div v-if="prop.account_name"><dt>{{ t('properties.account') }}</dt><dd>{{ prop.account_name }}</dd></div>
              <div v-if="prop.website_url"><dt>{{ t('properties.website') }}</dt><dd>{{ prop.website_url }}</dd></div>
              <div v-if="prop.measurement_id"><dt>{{ t('properties.measurement_id') }}</dt><dd class="gs-mono">{{ prop.measurement_id }}</dd></div>
              <div v-if="prop.property_type && service === 'search_console'"><dt>{{ t('settings.type') }}</dt><dd>{{ t(prop.property_type === 'domain' ? 'properties.domain' : 'properties.url_prefix') }}</dd></div>
              <div v-if="prop.time_zone"><dt>{{ t('preferences.timezone') }}</dt><dd>{{ prop.time_zone }}<template v-if="prop.currency_code"> · {{ prop.currency_code }}</template></dd></div>
              <div><dt>{{ t('settings.last_success') }}</dt><dd>{{ prop.last_synced_at ? fmt.datetime(prop.last_synced_at) : t('common.never') }}</dd></div>
              <div v-if="prop.last_error_code"><dt>{{ t('settings.last_error') }}</dt><dd class="gs-text-danger">{{ t('errors.' + prop.last_error_code) }} <small class="gs-muted">({{ fmt.ago(prop.last_error_at) }})</small></dd></div>
            </dl>
            <button type="button" class="gs-btn gs-btn-primary" :disabled="refreshing" @click="refresh"><i class="bx bx-refresh" :class="{ 'bx-spin': refreshing }"></i>{{ refreshing ? t('common.refreshing') : t('settings.refresh_now') }}</button>
          </template>
          <gs-state v-else kind="empty" icon="bx-list-check" :title="t('settings.no_property')" :text="t('states.no_property_text', { service: serviceName(service) })" compact>
            <a :href="suite.urls.properties + '#svc-' + service" class="gs-btn gs-btn-primary gs-btn-sm">{{ t('overview.choose_property') }}</a>
          </gs-state>
        </section>
      </div>

      <aside class="gs-stack">
        <section class="gs-card gs-pad">
          <h2>{{ t('settings.capabilities') }}</h2>
          <ul class="gs-checks"><li v-for="c in caps" :key="c" class="done"><i class="bx bxs-check-circle"></i>{{ c }}</li></ul>
        </section>
        <section class="gs-card gs-pad">
          <h2>{{ t('settings.troubleshooting') }}</h2>
          <ul class="gs-tips">
            <li><i class="bx bx-refresh"></i>{{ t('settings.tips.reconnect') }}</li>
            <li><i class="bx bx-user-check"></i>{{ t('settings.tips.access') }}</li>
            <li v-if="service === 'search_console'"><i class="bx bx-time-five"></i>{{ t('settings.tips.delay') }}</li>
          </ul>
          <a :href="suite.urls.health" class="gs-link">{{ t('health.title') }} →</a>
        </section>
      </aside>
    </div>

    <confirm-disconnect :open="confirm" @close="confirm = false" />
  </div>
</template>

<script>
import { suite, t, fmt, api, selected, grants, serviceName, toast, raw } from '../store';
import GsState from '../ui/GsState.vue';
import ConfirmDisconnect from './parts/ConfirmDisconnect.vue';

export default {
  name: 'GsServiceSettings',
  components: { GsState, ConfirmDisconnect },
  data: () => ({ suite, confirm: false, checking: false, refreshing: false }),
  computed: {
    fmt: () => fmt,
    service() { return suite.extra.service; },
    prop() { return selected(this.service); },
    caps() { return raw('settings.caps.' + this.service) || []; },
    dashboardUrl() { return this.service === 'ga4' ? suite.urls.analytics : suite.urls.search_console; },
  },
  methods: {
    t, grants, serviceName,
    async check() {
      this.checking = true;
      try {
        const res = await api('post', suite.urls.api.check);
        suite.account = res.account;
        toast(res.account.usable ? t('status.healthy') : t('errors.auth_expired'), res.account.usable ? 'success' : 'error');
      } catch (e) { toast(e.message, 'error'); } finally { this.checking = false; }
    },
    async refresh() {
      this.refreshing = true;
      try {
        const res = await api('post', suite.urls.api.refresh);
        suite.properties = res.properties;
        const code = res.results[this.service];
        toast(code === 'ok' ? t('states.synced') : t('errors.' + code), code === 'ok' ? 'success' : 'error');
      } catch (e) { toast(e.message, 'error'); } finally { this.refreshing = false; }
    },
  },
};
</script>
