<template>
  <div class="gsv">
    <div class="gsv-intro">
      <h3>{{ t('google_services.title') }}</h3>
      <p>{{ t('google_services.subtitle') }}</p>
    </div>

    <section v-for="group in groups" :key="group.key" class="gsv-group" :aria-label="t('google_services.groups.' + group.key)">
      <h4 class="gsv-group-title">{{ t('google_services.groups.' + group.key) }}</h4>

      <article v-for="s in group.services" :key="s.key" class="gsv-row" :class="{ 'is-off': s.status === 'unavailable' }">
        <div class="gsv-main">
          <span class="gsv-icon" :class="'is-' + s.brand"><i class="bx" :class="s.icon"></i></span>
          <div class="gsv-text">
            <strong>{{ t('google_services.services.' + s.key + '.name') }}</strong>
            <span>{{ t('google_services.services.' + s.key + '.text') }}</span>

            <!-- Which Google account(s) it runs on -->
            <ul v-if="s.accounts.length" class="gsv-accounts">
              <li v-for="a in s.accounts" :key="a.connection_id">
                <span class="gsv-dot" :class="tone(a.status)"></span>
                <span class="gsv-email">{{ a.email || t('google_services.account_unknown') }}</span>
                <span v-if="a.assets !== null" class="gsv-muted">· {{ t('google_services.assets.' + s.key, { n: a.assets }) }}</span>
                <span v-if="a.status !== 'connected'" class="gsv-muted">· {{ t('google_services.status.' + a.status) }}</span>
                <template v-if="canManage">
                  <button v-if="confirming !== key(s, a)" type="button" class="gsv-link is-danger" @click="confirming = key(s, a)">{{ t('google_services.disconnect') }}</button>
                  <span v-else class="gsv-confirm" role="alertdialog">
                    <span>{{ confirmText(s, a) }}</span>
                    <button type="button" class="gsv-btn gsv-btn-danger sm" :disabled="busy[a.connection_id]" @click="$emit('disconnect', a.connection_id); confirming = null">{{ t('google_services.disconnect') }}</button>
                    <button type="button" class="gsv-btn gsv-btn-ghost sm" @click="confirming = null">{{ t('cancel') }}</button>
                  </span>
                </template>
              </li>
            </ul>

            <small v-if="hint(s)" class="gsv-hint" :class="{ 'is-warn': s.status !== 'connected' && s.status !== 'unavailable' }"><i class="bx bx-info-circle"></i> {{ hint(s) }}</small>
          </div>
        </div>

        <div class="gsv-side">
          <span class="gsv-pill" :class="tone(s.status)">{{ statusLabel(s) }}</span>
          <div class="gsv-actions">
            <a v-if="primary(s)" :href="primary(s).url" class="gsv-btn sm" :class="primary(s).cls"><i class="bx" :class="primary(s).icon"></i> {{ primary(s).label }}</a>
            <a v-if="s.status === 'connected' && s.connect_url && canManage" :href="s.connect_url" class="gsv-btn gsv-btn-ghost sm" :title="t('google_services.add_account_hint')"><i class="bx bx-plus"></i> {{ t('google_services.add_account') }}</a>
          </div>
        </div>
      </article>
    </section>

    <p class="gsv-foot"><i class="bx bx-shield-quarter"></i> {{ t('google_services.privacy') }}</p>
  </div>
</template>

<script>
/*
 * Google services in the Connection Hub (GoogleServiceStatus): one row per
 * service, its status, the Google account(s) it runs on, and the next step.
 * Connecting asks Google only for that service's permissions; disconnecting
 * is per Google account, because Google grants one consent per account.
 */
const GROUPS = [
  { key: 'marketing', services: ['youtube', 'google_ads', 'business_profile'] },
  { key: 'website', services: ['analytics', 'search_console', 'pagespeed'] },
  { key: 'later', services: ['tag_manager', 'maps'] },
];

export default {
  name: 'GoogleServices',
  props: {
    services: { type: Array, required: true },
    t: { type: Function, required: true },
    busy: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: true },
  },
  data: () => ({ confirming: null }),
  computed: {
    groups() {
      const byKey = Object.fromEntries(this.services.map((s) => [s.key, s]));
      return GROUPS
        .map((g) => ({ key: g.key, services: g.services.map((k) => byKey[k]).filter(Boolean) }))
        .filter((g) => g.services.length);
    },
  },
  methods: {
    key(s, a) { return s.key + ':' + a.connection_id; },
    tone(status) {
      return { connected: 'is-ok', expired: 'is-warn', permission_required: 'is-warn', error: 'is-bad', unavailable: 'is-off' }[status] || 'is-idle';
    },
    statusLabel(s) {
      if (s.key === 'pagespeed' && s.status === 'connected') return this.t('google_services.status.ready');
      if (s.status === 'unavailable') return this.t('google_services.status.unavailable_' + (s.reason === 'approval' ? 'approval' : 'planned'));
      return this.t('google_services.status.' + s.status);
    },
    /* The one thing to do next. */
    primary(s) {
      if (s.auth === 'api_key') {
        return s.manage_url ? { url: s.manage_url + '#speed-api-key', cls: s.status === 'connected' ? 'gsv-btn-ghost' : 'gsv-btn-primary', icon: s.status === 'connected' ? 'bx-right-arrow-alt' : 'bx-key', label: this.t(s.status === 'connected' ? 'google_services.open' : 'google_services.add_key') } : null;
      }
      if (s.status === 'unavailable') {
        return s.manage_url ? { url: s.manage_url, cls: 'gsv-btn-ghost', icon: 'bx-info-circle', label: this.t('google_services.learn_more') } : null;
      }
      if (s.status === 'connected') {
        return s.manage_url ? { url: s.manage_url, cls: 'gsv-btn-ghost', icon: 'bx-right-arrow-alt', label: this.t('google_services.open') } : null;
      }
      if (!this.canManage || !s.connect_url) return null;
      return s.status === 'not_connected'
        ? { url: s.connect_url, cls: 'gsv-btn-primary', icon: 'bx-link', label: this.t('google_services.connect') }
        : { url: s.connect_url, cls: 'gsv-btn-primary', icon: 'bx-revision', label: this.t('google_services.reconnect') };
    },
    hint(s) {
      if (s.status === 'unavailable') return this.t('google_services.reasons.' + (s.reason || 'planned') + (s.key === 'maps' ? '_maps' : ''));
      if (s.auth === 'api_key') return this.t('google_services.reasons.' + s.reason);
      if (s.status === 'connected' && s.note) return this.t('google_services.notes.' + s.note + '.' + s.key);
      if (['expired', 'permission_required', 'error'].includes(s.status)) return this.t('google_services.fix.' + s.status);
      if (!this.canManage && s.status === 'not_connected') return this.t('google_services.owner_connects');
      return null;
    },
    /* Disconnecting removes the whole Google consent: name every service it also powers. */
    confirmText(s, a) {
      const also = s.shares_with
        .filter((k) => (this.services.find((x) => x.key === k)?.accounts || []).some((x) => x.connection_id === a.connection_id))
        .map((k) => this.t('google_services.services.' + k + '.name'));
      return also.length
        ? this.t('google_services.confirm_shared', { account: a.email || this.t('google_services.account_unknown'), services: also.join(', ') })
        : this.t('google_services.confirm', { account: a.email || this.t('google_services.account_unknown') });
    },
  },
};
</script>

<style scoped>
/* Buttons: the hub's .ch-btn look (its styles are scoped to ConnectionHub). */
.gsv-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 34px; padding: 0 12px; border-radius: 10px; font-size: 12.5px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; white-space: nowrap; transition: transform .15s, box-shadow .15s, background .15s, color .15s, border-color .15s; }
.gsv-btn-primary { background: linear-gradient(135deg, #0866FF 0%, #00A3FF 100%); color: #fff; box-shadow: 0 6px 16px rgba(8,102,255,.28); }
.gsv-btn-primary:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 10px 22px rgba(8,102,255,.32); }
.gsv-btn-ghost { background: #fff; border-color: var(--line, #E7E9F0); color: var(--text, #4B5263); }
.gsv-btn-ghost:hover { border-color: #C9D7F5; color: #0866FF; }
.gsv-btn-danger { background: #DC2626; color: #fff; }
.gsv-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
.gsv-btn:focus-visible { outline: 2px solid #0866FF; outline-offset: 2px; }
@media (prefers-reduced-motion: reduce) { .gsv-btn { transition: none; } .gsv-btn-primary:hover { transform: none; } }
.gsv { display: flex; flex-direction: column; gap: 16px; }
.gsv-intro h3 { margin: 0 0 4px; font-size: 15px; font-weight: 700; color: var(--ink, #161B2B); }
.gsv-intro p { margin: 0; font-size: 13px; color: var(--muted, #8A92A3); line-height: 1.5; }
.gsv-group { display: flex; flex-direction: column; border: 1px solid var(--line, #E7E9F0); border-radius: 14px; overflow: hidden; background: #fff; }
.gsv-group-title { margin: 0; padding: 10px 14px; font-size: 11.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted, #8A92A3); background: #FAFBFD; border-bottom: 1px solid var(--line-soft, #F1F3F7); }
.gsv-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; padding: 14px; border-top: 1px solid var(--line-soft, #F1F3F7); }
.gsv-group-title + .gsv-row { border-top: 0; }
.gsv-row.is-off .gsv-icon, .gsv-row.is-off .gsv-text > strong { opacity: .6; }
.gsv-main { display: flex; gap: 12px; min-width: 0; flex: 1 1 auto; }
.gsv-icon { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 19px; flex-shrink: 0; background: #EEF3FE; color: #1A73E8; }
.gsv-icon.is-youtube { background: #FFECEC; color: #E62117; }
.gsv-text { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.gsv-text > strong { font-size: 14px; color: var(--ink, #161B2B); }
.gsv-text > span { font-size: 12.5px; color: var(--text, #4B5263); line-height: 1.45; }
.gsv-accounts { list-style: none; margin: 6px 0 0; padding: 0; display: flex; flex-direction: column; gap: 4px; font-size: 12.5px; }
.gsv-accounts li { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.gsv-email { font-weight: 600; color: var(--ink, #161B2B); overflow-wrap: anywhere; }
.gsv-muted { color: var(--muted, #8A92A3); }
.gsv-dot { width: 7px; height: 7px; border-radius: 50%; background: #B4BBCB; flex-shrink: 0; }
.gsv-dot.is-ok { background: #12B76A; } .gsv-dot.is-warn { background: #F79009; } .gsv-dot.is-bad { background: #F04438; }
.gsv-link { border: 0; background: none; padding: 0; font: inherit; font-size: 12px; font-weight: 600; color: var(--brand, #6D4AFF); cursor: pointer; }
.gsv-link.is-danger { color: #D92D20; }
.gsv-link:focus-visible { outline: 2px solid var(--brand, #6D4AFF); outline-offset: 2px; border-radius: 4px; }
.gsv-confirm { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 6px 8px; border-radius: 10px; background: #FEF3F2; color: #B42318; }
.gsv-hint { display: flex; gap: 5px; align-items: flex-start; margin-top: 4px; font-size: 12px; color: var(--muted, #8A92A3); line-height: 1.45; }
.gsv-hint.is-warn { color: #B54708; }
.gsv-side { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0; }
.gsv-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
.gsv-pill { font-size: 11.5px; font-weight: 600; padding: 3px 9px; border-radius: 999px; background: #F2F4F7; color: #475467; white-space: nowrap; }
.gsv-pill.is-ok { background: #ECFDF3; color: #027A48; }
.gsv-pill.is-warn { background: #FFFAEB; color: #B54708; }
.gsv-pill.is-bad { background: #FEF3F2; color: #B42318; }
.gsv-pill.is-off { background: #F2F4F7; color: #667085; }
.gsv-foot { display: flex; gap: 6px; margin: 0; font-size: 12px; color: var(--muted, #8A92A3); line-height: 1.5; }
[dir="rtl"] .gsv-side { align-items: flex-start; }
[dir="rtl"] .gsv-actions { justify-content: flex-start; }
@media (max-width: 560px) {
  .gsv-row { flex-direction: column; }
  .gsv-side { flex-direction: row; align-items: center; justify-content: space-between; width: 100%; }
  [dir="rtl"] .gsv-side { align-items: center; }
}
</style>
