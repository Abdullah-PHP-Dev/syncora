<template>
  <section class="gs-card gs-account">
    <gs-service-icon service="google" />
    <div class="gs-account-id">
      <div class="gs-account-name">
        <strong>{{ suite.account.email || t('settings.unknown_email') }}</strong>
        <span class="gs-pill" :class="suite.account.usable ? 'is-success' : 'is-danger'"><span class="gs-dot"></span>{{ suite.account.usable ? t('status.authorized') : t('status.reauth') }}</span>
      </div>
      <p class="gs-muted">{{ t('overview.account_linked', { granted: suite.account.scope_count, total: suite.account.scope_total, when: fmt.ago(suite.account.last_checked_at) }) }}</p>
    </div>
    <dl class="gs-account-sync">
      <div><dt>{{ t('overview.last_full_sync') }}</dt><dd>{{ lastSync ? fmt.datetime(lastSync) : t('common.never') }}</dd></div>
      <div><dt>{{ t('common.next_sync') }}</dt><dd>{{ fmt.until(suite.nextSyncAt) }}</dd></div>
    </dl>
    <div v-if="suite.canManage" class="gs-account-actions">
      <a :href="suite.urls.connect" class="gs-btn gs-btn-ghost">{{ t('common.reauthorize') }}</a>
      <button type="button" class="gs-btn gs-btn-ghost gs-btn-danger-text" @click="confirm = true">{{ t('common.disconnect') }}</button>
    </div>
    <gs-confirm-disconnect :open="confirm" @close="confirm = false" />
  </section>
</template>

<script>
import { suite, t, fmt, selected } from '../../store';
import GsServiceIcon from '../../ui/GsServiceIcon.vue';
import GsConfirmDisconnect from './ConfirmDisconnect.vue';

/* The linked Google identity: status, permissions, sync times, re-authorize / disconnect. */
export default {
  name: 'GsAccountStrip',
  components: { GsServiceIcon, GsConfirmDisconnect },
  data: () => ({ suite, confirm: false }),
  computed: {
    fmt: () => fmt,
    lastSync() { return ['ga4', 'search_console'].map(selected).filter(Boolean).map((p) => p.last_synced_at).filter(Boolean).sort().pop() || null; },
  },
  methods: { t },
};
</script>
