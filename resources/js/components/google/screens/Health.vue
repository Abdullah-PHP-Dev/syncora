<template>
  <div class="gs-screen">
    <div class="gs-head">
      <div>
        <h1>{{ t('health.title') }}</h1>
        <p class="gs-sub">{{ t('health.subtitle') }}</p>
      </div>
      <div class="gs-head-actions">
        <button v-if="suite.account.connected" type="button" class="gs-btn gs-btn-ghost" :disabled="checking" @click="check"><i class="bx bx-pulse"></i>{{ checking ? t('settings.checking') : t('health.check_now') }}</button>
        <button v-if="anySelected" type="button" class="gs-btn gs-btn-primary" :disabled="syncing" @click="sync"><i class="bx bx-refresh" :class="{ 'bx-spin': syncing }"></i>{{ syncing ? t('common.refreshing') : t('health.retry_sync') }}</button>
      </div>
    </div>

    <section class="gs-card gs-pad">
      <div class="gs-card-head"><h2>{{ t('health.authorization') }}</h2></div>
      <div class="gs-health-row">
        <gs-service-icon service="google" />
        <div class="gs-health-main">
          <strong>{{ serviceName('google') }}</strong>
          <small v-if="suite.account.connected">{{ suite.account.email || t('settings.unknown_email') }} · {{ t('health.token') }}: {{ t('health.token_refreshed', { when: fmt.ago(suite.account.last_refreshed_at || suite.account.last_checked_at) }) }}</small>
          <small v-else>{{ t('errors.not_connected') }}</small>
        </div>
        <span class="gs-pill" :class="accountTone"><span class="gs-dot"></span>{{ suite.account.connected ? t('health.connection_status.' + suite.account.status) : t('status.not_connected') }}</span>
        <a v-if="suite.canManage && (!suite.account.connected || !suite.account.usable)" :href="suite.account.connected ? suite.urls.connect : suite.urls.setup" class="gs-btn gs-btn-primary gs-btn-sm">{{ suite.account.connected ? t('common.reconnect') : t('common.connect') }}</a>
      </div>
    </section>

    <section class="gs-card gs-pad">
      <h2>{{ t('nav.integrations') }}</h2>
      <div class="gs-table-wrap">
        <table class="gs-table">
          <thead><tr>
            <th scope="col">{{ t('nav.integrations') }}</th><th scope="col">{{ t('health.permissions') }}</th><th scope="col">{{ t('settings.property') }}</th>
            <th scope="col">{{ t('health.last_retrieval') }}</th><th scope="col">{{ t('health.last_problem') }}</th><th scope="col">{{ t('health.suggested') }}</th>
          </tr></thead>
          <tbody>
            <tr v-for="row in rows" :key="row.service">
              <td><span class="gs-row"><gs-service-icon :service="row.service" />{{ serviceName(row.service) }}</span></td>
              <td><span class="gs-chip" :class="row.granted ? 'is-success' : 'is-danger'">{{ row.granted ? t('settings.granted') : t('settings.not_granted') }}</span></td>
              <td>{{ row.prop ? row.prop.name : '—' }}</td>
              <td>{{ row.prop && row.prop.last_synced_at ? fmt.datetime(row.prop.last_synced_at) : t('common.never') }}</td>
              <td :class="{ 'gs-text-danger': row.code }">{{ row.code ? t('errors.' + row.code) : t('health.none') }}</td>
              <td>
                <a v-if="row.action.url" :href="row.action.url" class="gs-link">{{ row.action.text }}</a>
                <span v-else>{{ row.action.text }}</span>
              </td>
            </tr>
            <tr v-for="p in ['google_ads', 'business_profile', 'tag_manager']" :key="p" class="is-muted">
              <td><span class="gs-row"><gs-service-icon :service="p" />{{ serviceName(p) }}</span></td>
              <td colspan="4">{{ p === 'google_ads' ? t('integrations.ads_access') : t('common.coming_soon') }}</td>
              <td><a v-if="p === 'google_ads'" :href="suite.urls.hub" class="gs-link">{{ t('common.manage') }}</a><span v-else>—</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="gs-card gs-pad">
      <h2>{{ t('health.log') }}</h2>
      <ul v-if="suite.events.length" class="gs-activity">
        <li v-for="e in suite.events" :key="e.id">
          <span class="gs-dot" :style="{ background: e.status === 'error' ? '#C5221F' : e.status === 'warning' ? '#B45309' : '#188038' }"></span>
          <div>{{ eventText(e) }}<template v-if="e.event === 'failed' && e.message"> — {{ t('errors.' + e.message) }}</template><small>{{ fmt.datetime(e.at) }}</small></div>
        </li>
      </ul>
      <p v-else class="gs-muted">{{ t('health.log_empty') }}</p>
    </section>
  </div>
</template>

<script>
import { suite, t, fmt, api, selected, grants, serviceName, eventText, toast } from '../store';
import GsServiceIcon from '../ui/GsServiceIcon.vue';

export default {
  name: 'GsHealth',
  components: { GsServiceIcon },
  data: () => ({ suite, checking: false, syncing: false }),
  computed: {
    fmt: () => fmt,
    anySelected() { return !!(selected('ga4') || selected('search_console')); },
    accountTone() { return !suite.account.connected ? 'is-neutral' : suite.account.usable ? 'is-success' : 'is-danger'; },
    rows() {
      return ['ga4', 'search_console'].map((service) => {
        const prop = selected(service);
        const granted = grants(service);
        let code = prop ? prop.last_error_code : null;
        if (suite.account.connected && !suite.account.usable) code = 'auth_expired';
        else if (suite.account.connected && !granted) code = 'missing_scope';
        const key = code || (suite.account.connected && !prop ? 'no_property' : 'ok');
        const url = ['auth_expired', 'missing_scope'].includes(key) ? (suite.canManage ? suite.urls.connect : null)
          : ['no_property', 'not_found', 'insufficient_permissions'].includes(key) ? suite.urls.properties + '#svc-' + service : null;
        return { service, prop, granted, code: code || (key === 'no_property' ? 'no_property' : null), action: { text: suite.account.connected ? t('health.actions.' + key) : t('errors.not_connected'), url: suite.account.connected ? url : suite.urls.setup } };
      });
    },
  },
  methods: {
    t, serviceName, eventText,
    async check() {
      this.checking = true;
      try { const res = await api('post', suite.urls.api.check); suite.account = res.account; toast(res.account.usable ? t('status.healthy') : t('errors.auth_expired'), res.account.usable ? 'success' : 'error'); }
      catch (e) { toast(e.message, 'error'); } finally { this.checking = false; }
    },
    async sync() {
      this.syncing = true;
      try {
        const res = await api('post', suite.urls.api.refresh);
        suite.properties = res.properties;
        const failed = Object.keys(res.results).filter((k) => res.results[k] !== 'ok');
        toast(failed.length ? t('states.sync_failed', { services: failed.map(serviceName).join(', ') }) : t('states.synced'), failed.length ? 'error' : 'success');
      } catch (e) { toast(e.message, 'error'); } finally { this.syncing = false; }
    },
  },
};
</script>
