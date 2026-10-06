<template>
  <div class="ch">

    <header class="ch-head">
      <div>
        <h1 class="ch-title">Connections</h1>
        <p class="ch-sub">Connect each platform once. Ads, Publishing and Inbox all use the same connection.</p>
      </div>
      <div class="ch-summary">
        <span class="ch-sum"><i class="bx bx-check-shield"></i> {{ summary.connected }} connected</span>
        <span class="ch-sum" :class="{ 'is-warn': summary.attention }"><i class="bx bx-error-circle"></i> {{ summary.attention }} need attention</span>
      </div>
    </header>

    <div v-if="flashMessage" class="ch-flash" :class="flashMessage.tone">
      <i class="bx" :class="flashMessage.tone === 'is-error' ? 'bx-error-circle' : 'bx-check-circle'"></i>
      <span>{{ flashMessage.text }}</span>
      <button type="button" class="ch-flash-x" @click="flashMessage = null" aria-label="Dismiss"><i class="bx bx-x"></i></button>
    </div>

    <section v-for="card in cards" :key="card.platform" class="ch-card" :id="card.platform">

      <div class="ch-card-head">
        <div class="ch-brand">
          <span class="ch-brand-stack">
            <span class="ch-badge is-facebook"><i class="bx bxl-facebook"></i></span>
            <span class="ch-badge is-instagram"><i class="bx bxl-instagram"></i></span>
            <span class="ch-badge is-whatsapp"><i class="bx bxl-whatsapp"></i></span>
          </span>
          <div>
            <h2>{{ card.label }}</h2>
            <p>Facebook Pages · Instagram · Messenger · WhatsApp · Ads</p>
          </div>
        </div>
        <span class="ch-pill" :class="cardStatus(card).tone"><i class="bx" :class="cardStatus(card).icon"></i> {{ cardStatus(card).label }}</span>
      </div>

      <!-- Steps -->
      <div class="ch-steps">
        <div v-for="step in card.steps" :key="step.key" class="ch-step" :class="{ 'is-primary': step.primary, 'is-off': !step.available }">
          <div class="ch-step-main">
            <span class="ch-step-icon" :class="stepIcon(step.key).cls"><i class="bx" :class="stepIcon(step.key).icon"></i></span>
            <div>
              <strong>{{ step.label }} <span v-if="step.connected" class="ch-mini-ok"><i class="bx bx-check"></i> Connected</span></strong>
              <span class="ch-step-desc">{{ step.description }}</span>
              <span v-if="!step.available && step.note" class="ch-step-note"><i class="bx bx-info-circle"></i> {{ step.note }}</span>
            </div>
          </div>
          <a v-if="step.available" :href="step.connect_url" class="ch-btn" :class="step.primary ? 'ch-btn-primary' : 'ch-btn-ghost'">
            <i class="bx" :class="step.connected ? 'bx-refresh' : 'bx-link'"></i>
            {{ step.connected ? (step.primary ? 'Add or change accounts' : 'Reconnect') : (step.primary ? 'Connect with Facebook' : 'Connect') }}
          </a>
          <span v-else class="ch-btn ch-btn-disabled">Not available</span>
        </div>
      </div>

      <!-- Not connected yet -->
      <div v-if="!card.connections.length" class="ch-empty">
        <div class="ch-empty-art"><i class="bx bx-plug"></i></div>
        <h3>One consent for everything Meta</h3>
        <p>Choose the Pages, Instagram accounts and ad accounts SocialEaz may use. You can change your choice at any time, here or in your Facebook settings.</p>
        <ul class="ch-gets">
          <li v-for="cap in capabilityList" :key="cap.key"><i class="bx" :class="cap.icon"></i> {{ cap.long }}</li>
        </ul>
      </div>

      <!-- Consents + assets -->
      <div v-for="conn in card.connections" :key="conn.id" class="ch-conn">

        <div class="ch-conn-head">
          <div class="ch-conn-id">
            <span class="ch-step-icon sm" :class="stepIcon(conn.step).cls"><i class="bx" :class="stepIcon(conn.step).icon"></i></span>
            <div>
              <strong>{{ stepLabel(card, conn.step) }}</strong>
              <span class="ch-conn-meta">
                <span class="ch-status" :class="statusInfo(conn.status).tone"><i class="ch-dot"></i>{{ statusInfo(conn.status).label }}</span>
                <span v-if="conn.expires_in_days !== null"><i class="bx bx-time-five"></i> {{ expiryLabel(conn) }}</span>
                <span v-if="conn.last_checked_at"><i class="bx bx-pulse"></i> Checked {{ ago(conn.last_checked_at) }}</span>
                <span v-if="conn.provider_account_id" class="ch-muted">ID {{ conn.provider_account_id }}</span>
              </span>
            </div>
          </div>
          <div class="ch-conn-actions">
            <button type="button" class="ch-btn ch-btn-ghost sm" :disabled="busy[conn.id]" @click="check(card, conn)">
              <i class="bx" :class="busy[conn.id] === 'check' ? 'bx-loader-alt bx-spin' : 'bx-revision'"></i> Check now
            </button>
            <template v-if="conn.status !== 'revoked'">
              <button v-if="confirming !== conn.id" type="button" class="ch-btn ch-btn-danger-ghost sm" @click="confirming = conn.id">
                <i class="bx bx-unlink"></i> Disconnect
              </button>
              <span v-else class="ch-confirm">
                <span>Remove SocialEaz's access?</span>
                <button type="button" class="ch-btn ch-btn-danger sm" :disabled="busy[conn.id]" @click="disconnect(card, conn)">Disconnect</button>
                <button type="button" class="ch-btn ch-btn-ghost sm" @click="confirming = null">Cancel</button>
              </span>
            </template>
          </div>
        </div>

        <div v-if="conn.needs_attention" class="ch-alert" :class="statusInfo(conn.status).tone">
          <i class="bx bx-error-circle"></i>
          <div>
            <strong>{{ attentionTitle(conn) }}</strong>
            <span v-if="conn.last_error">{{ conn.last_error }}</span>
          </div>
          <a :href="conn.reconnect_url" class="ch-btn ch-btn-primary sm"><i class="bx bx-refresh"></i> Reconnect</a>
        </div>

        <!-- Capabilities of this consent -->
        <div class="ch-caps">
          <span v-for="cap in capabilityList" :key="cap.key" class="ch-cap" :class="{ 'is-on': conn.capabilities.includes(cap.key) }">
            <i class="bx" :class="conn.capabilities.includes(cap.key) ? cap.icon : 'bx-lock-alt'"></i>
            {{ cap.label }}
            <a v-if="!conn.capabilities.includes(cap.key) && conn.step === 'meta.login'" :href="conn.reconnect_url" class="ch-cap-up">Upgrade</a>
          </span>
        </div>

        <!-- Asset picker -->
        <div v-for="group in assetGroups(conn)" :key="group.kind" class="ch-group">
          <div class="ch-group-head">
            <h4><i class="bx" :class="group.icon"></i> {{ group.label }}</h4>
            <span class="ch-count">{{ group.items.length }}</span>
          </div>
          <div class="ch-assets">
            <div v-for="asset in group.items" :key="asset.id" class="ch-asset" :class="{ 'is-off': !asset.enabled_capabilities.length }">
              <span class="ch-avatar" :class="'is-' + group.brand">
                <img v-if="asset.avatar_url" :src="asset.avatar_url" alt="" @error="asset.avatar_url = null">
                <i v-else class="bx" :class="group.icon"></i>
              </span>
              <span class="ch-asset-id">
                <strong>{{ asset.name || asset.external_id }}</strong>
                <small>{{ asset.username ? '@' + asset.username : asset.external_id }}<template v-if="!asset.token_ok"> · <span class="ch-warn-text">needs reconnect</span></template></small>
              </span>
              <span class="ch-asset-caps">
                <button
                    v-for="cap in asset.available_capabilities"
                    :key="cap"
                    type="button"
                    class="ch-toggle-pill"
                    :class="{ 'is-on': asset.enabled_capabilities.includes(cap) }"
                    :disabled="saving[asset.id]"
                    :title="(asset.enabled_capabilities.includes(cap) ? 'Turn off ' : 'Turn on ') + capability(cap).label"
                    @click="toggleCapability(conn, asset, cap)">
                  <i class="bx" :class="capability(cap).icon"></i> {{ capability(cap).label }}
                </button>
                <span v-if="!asset.available_capabilities.length" class="ch-muted">No permissions granted</span>
              </span>
              <label class="ch-switch" :title="asset.enabled_capabilities.length ? 'Stop using this account' : 'Use this account'">
                <input type="checkbox" :checked="asset.enabled_capabilities.length > 0" :disabled="saving[asset.id] || !asset.available_capabilities.length" @change="toggleAsset(conn, asset)">
                <span></span>
              </label>
            </div>
          </div>
        </div>

        <p v-if="!assetGroups(conn).length" class="ch-muted ch-none">No accounts linked to this connection yet. Use “Add or change accounts” to choose them.</p>
      </div>
    </section>

    <section class="ch-upcoming">
      <h3>Moving here next</h3>
      <p class="ch-sub">Until then, these connect from their module pages as before.</p>
      <div class="ch-up-grid">
        <div v-for="p in hub.upcoming" :key="p.key" class="ch-up">
          <span class="ch-up-icon" :class="'is-' + p.key"><i class="bx" :class="upcomingIcon(p.key)"></i></span>
          <span><strong>{{ p.label }}</strong><small>{{ p.detail }}</small></span>
        </div>
      </div>
    </section>

  </div>
</template>

<script>
const CAPABILITIES = [
  { key: 'posting', label: 'Publishing', long: 'Publish and schedule posts to Pages and Instagram', icon: 'bx-send' },
  { key: 'messaging', label: 'Inbox', long: 'Answer Messenger, Instagram and WhatsApp messages', icon: 'bx-message-rounded-dots' },
  { key: 'ads', label: 'Ads', long: 'Create and manage campaigns on your ad accounts', icon: 'bx-bullseye' },
  { key: 'insights', label: 'Insights', long: 'Read Page and Instagram insights for reports', icon: 'bx-bar-chart-alt-2' }
];

const GROUPS = [
  { kind: 'page', label: 'Facebook Pages', icon: 'bxl-facebook', brand: 'facebook' },
  { kind: 'instagram', label: 'Instagram accounts', icon: 'bxl-instagram', brand: 'instagram' },
  { kind: 'ad_account', label: 'Ad accounts', icon: 'bx-bullseye', brand: 'meta' },
  { kind: 'whatsapp', label: 'WhatsApp numbers', icon: 'bxl-whatsapp', brand: 'whatsapp' }
];

export default {

  props: {
    hub: { type: Object, required: true },
    urls: { type: Object, required: true },
    flash: { type: [Object, Array], default: () => ({}) }
  },

  data() {
    const flash = Array.isArray(this.flash) ? {} : this.flash;

    return {
      cards: JSON.parse(JSON.stringify(this.hub.cards)),
      busy: {},
      saving: {},
      confirming: null,
      flashMessage: flash.error
        ? { tone: 'is-error', text: flash.error }
        : (flash.success ? { tone: 'is-success', text: flash.success } : null),
      capabilityList: CAPABILITIES
    };
  },

  computed: {
    summary() {
      return {
        connected: this.cards.filter(c => c.connected).length,
        attention: this.cards.reduce((n, c) => n + c.connections.filter(x => x.needs_attention).length, 0)
      };
    }
  },

  methods: {

    capability(key) {
      return CAPABILITIES.find(c => c.key === key) || { label: key, icon: 'bx-check' };
    },

    statusInfo(status) {
      return {
        active: { label: 'Active', tone: 'is-ok' },
        expiring: { label: 'Expiring soon', tone: 'is-warn' },
        expired: { label: 'Expired', tone: 'is-warn' },
        needs_reauth: { label: 'Reconnect needed', tone: 'is-bad' },
        revoked: { label: 'Disconnected', tone: 'is-muted' },
        error: { label: 'Check failed', tone: 'is-bad' }
      }[status] || { label: status, tone: 'is-muted' };
    },

    cardStatus(card) {
      if (card.connections.some(c => c.needs_attention && c.status !== 'revoked')) return { label: 'Needs attention', tone: 'is-warn', icon: 'bx-error' };
      if (card.connected) return { label: 'Connected', tone: 'is-ok', icon: 'bx-check-circle' };
      return { label: 'Not connected', tone: 'is-muted', icon: 'bx-plug' };
    },

    attentionTitle(conn) {
      return {
        expiring: 'Access expires soon. Reconnect to keep everything running.',
        expired: 'Access expired.',
        needs_reauth: 'Facebook needs you to log in again.',
        revoked: 'This connection was disconnected.',
        error: 'We couldn’t check this connection.'
      }[conn.status] || 'Needs attention';
    },

    stepIcon(key) {
      return {
        'meta.login': { icon: 'bxl-facebook', cls: 'is-facebook' },
        'meta.whatsapp': { icon: 'bxl-whatsapp', cls: 'is-whatsapp' },
        'meta.instagram_login': { icon: 'bxl-instagram', cls: 'is-instagram' }
      }[key] || { icon: 'bx-link', cls: 'is-meta' };
    },

    stepLabel(card, key) {
      const step = card.steps.find(s => s.key === key);
      return step ? step.label : key;
    },

    upcomingIcon(key) {
      return { google: 'bxl-google', x: 'bxl-x-logo', linkedin: 'bxl-linkedin', tiktok: 'bxl-tiktok', snapchat: 'bxl-snapchat', threads: 'bx-at', pinterest: 'bxl-pinterest' }[key] || 'bx-link';
    },

    assetGroups(conn) {
      return GROUPS
        .map(g => ({ ...g, items: conn.assets[g.kind] || [] }))
        .filter(g => g.items.length);
    },

    expiryLabel(conn) {
      const d = conn.expires_in_days;
      if (d < 0) return 'Expired';
      if (d === 0) return 'Expires today';
      return 'Expires in ' + d + (d === 1 ? ' day' : ' days');
    },

    ago(iso) {
      const s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
      if (s < 60) return 'just now';
      if (s < 3600) return Math.round(s / 60) + ' min ago';
      if (s < 86400) return Math.round(s / 3600) + ' h ago';
      return Math.round(s / 86400) + ' d ago';
    },

    url(name, id) {
      return this.urls[name].replace('__ID__', id);
    },

    replaceConnection(card, updated) {
      const i = card.connections.findIndex(c => c.id === updated.id);
      if (i !== -1) this.$set(card.connections, i, updated);
    },

    toggleAsset(conn, asset) {
      const next = asset.enabled_capabilities.length ? [] : asset.available_capabilities.slice();
      this.save(conn, asset, next);
    },

    toggleCapability(conn, asset, cap) {
      const on = asset.enabled_capabilities.includes(cap);
      const next = on ? asset.enabled_capabilities.filter(c => c !== cap) : asset.enabled_capabilities.concat(cap);
      this.save(conn, asset, next);
    },

    // Optimistic: flip now, put it back if the server says no.
    save(conn, asset, next) {
      const card = this.cards.find(c => c.connections.includes(conn));
      const previous = asset.enabled_capabilities;
      asset.enabled_capabilities = next;
      this.$set(this.saving, asset.id, true);

      window.axios.patch(this.url('asset', asset.id), { enabled_capabilities: next })
        .then(({ data }) => this.replaceConnection(card, data.connection))
        .catch(() => {
          asset.enabled_capabilities = previous;
          this.flashMessage = { tone: 'is-error', text: 'Couldn’t save that change. Please try again.' };
        })
        .finally(() => this.$delete(this.saving, asset.id));
    },

    check(card, conn) {
      this.$set(this.busy, conn.id, 'check');

      window.axios.post(this.url('check', conn.id))
        .then(({ data }) => {
          this.replaceConnection(card, data.connection);
          this.flashMessage = data.connection.needs_attention
            ? { tone: 'is-error', text: this.attentionTitle(data.connection) }
            : { tone: 'is-success', text: 'Connection checked: everything works.' };
        })
        .catch(() => { this.flashMessage = { tone: 'is-error', text: 'The check didn’t complete. Please try again.' }; })
        .finally(() => this.$delete(this.busy, conn.id));
    },

    disconnect(card, conn) {
      this.$set(this.busy, conn.id, 'disconnect');

      window.axios.delete(this.url('disconnect', conn.id))
        .then(({ data }) => {
          const i = this.cards.indexOf(card);
          this.$set(this.cards, i, data.card);
          this.confirming = null;
          this.flashMessage = { tone: 'is-success', text: 'Disconnected. Reconnect at any time from this page.' };
        })
        .catch(() => { this.flashMessage = { tone: 'is-error', text: 'Couldn’t disconnect. Please try again.' }; })
        .finally(() => this.$delete(this.busy, conn.id));
    }
  }
};
</script>

<style scoped>
.ch {
  --ink: #161B2B; --text: #4B5263; --muted: #8A92A3; --line: #E7E9F0; --line-soft: #F1F3F7;
  --brand: #6D4AFF; --brand-2: #8F6BFF; --brand-soft: #F2EEFF;
  --fb: linear-gradient(180deg, #18ACFE 0%, #0163E0 100%);
  --ig: radial-gradient(circle at 30% 107%, #FDF497 0%, #FDF497 5%, #FD5949 45%, #D6249F 60%, #285AEB 90%);
  --wa: linear-gradient(180deg, #5FFC7B 0%, #28D146 100%);
  --meta: linear-gradient(135deg, #0866FF 0%, #00A3FF 100%);
  padding: 24px; display: flex; flex-direction: column; gap: 20px;
}

.ch-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px; }
.ch-title { margin: 0 0 4px; font-size: 24px; font-weight: 700; letter-spacing: -.01em; color: var(--ink); line-height: 1.25; }
.ch-sub { margin: 0; color: var(--muted); font-size: 13.5px; line-height: 1.5; }
.ch-summary { display: flex; gap: 8px; flex-wrap: wrap; }
.ch-sum { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px; border-radius: 10px; background: #fff; border: 1px solid var(--line); color: var(--text); font-size: 13px; font-weight: 600; }
.ch-sum i { font-size: 16px; color: #16A34A; }
.ch-sum.is-warn i { color: #D97706; }
.ch-sum:not(.is-warn):nth-child(2) i { color: var(--muted); }

.ch-flash { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 12px; font-size: 13.5px; }
.ch-flash i { font-size: 18px; }
.ch-flash.is-success { background: #E8F8EE; color: #166534; }
.ch-flash.is-error { background: #FDECEC; color: #B42318; }
.ch-flash-x { margin-left: auto; border: none; background: none; color: inherit; font-size: 18px; cursor: pointer; }

.ch-card { background: #fff; border: 1px solid var(--line); border-radius: 18px; box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 8px 24px rgba(16,24,40,.04); padding: 22px 24px; display: flex; flex-direction: column; gap: 18px; }
.ch-card-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.ch-brand { display: flex; align-items: center; gap: 14px; }
.ch-brand h2 { margin: 0; font-size: 18px; font-weight: 700; color: var(--ink); line-height: 1.3; }
.ch-brand p { margin: 2px 0 0; font-size: 12.5px; color: var(--muted); }
.ch-brand-stack { display: inline-flex; }
.ch-brand-stack .ch-badge + .ch-badge { margin-left: -10px; }
.ch-badge { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 20px; border: 2px solid #fff; box-shadow: 0 4px 10px rgba(16,24,40,.12); }

.is-facebook { background: var(--fb); }
.is-instagram { background: var(--ig); }
.is-whatsapp { background: var(--wa); }
.is-meta { background: var(--meta); }

.ch-pill { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border-radius: 999px; font-size: 12.5px; font-weight: 700; }
.ch-pill.is-ok { background: #E8F8EE; color: #16A34A; }
.ch-pill.is-warn { background: #FFF6E5; color: #D97706; }
.ch-pill.is-muted { background: #F1F3F7; color: #64748B; }

.ch-steps { display: flex; flex-direction: column; gap: 10px; }
.ch-step { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 14px 16px; border: 1px solid var(--line); border-radius: 14px; background: #fff; }
.ch-step.is-primary { background: linear-gradient(180deg, #F7FAFF, #fff); border-color: #CFE0FF; }
.ch-step.is-off { background: #FAFBFC; }
.ch-step-main { display: flex; align-items: center; gap: 12px; min-width: 0; }
.ch-step-main strong { display: flex; align-items: center; gap: 8px; color: var(--ink); font-size: 14px; }
.ch-step-desc { display: block; color: var(--muted); font-size: 12.5px; margin-top: 2px; }
.ch-step-note { display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; color: #B45309; font-size: 12px; }
.ch-step-icon { width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0; display: grid; place-items: center; color: #fff; font-size: 19px; }
.ch-step-icon.sm { width: 32px; height: 32px; border-radius: 9px; font-size: 16px; }
.ch-mini-ok { display: inline-flex; align-items: center; gap: 2px; font-size: 11px; font-weight: 700; color: #16A34A; background: #E8F8EE; padding: 1px 7px; border-radius: 6px; }

.ch-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 40px; padding: 0 16px; border-radius: 11px; font-size: 13.5px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; white-space: nowrap; transition: transform .15s, box-shadow .15s, background .15s, color .15s, border-color .15s; }
.ch-btn.sm { height: 34px; padding: 0 12px; font-size: 12.5px; border-radius: 10px; }
.ch-btn-primary { background: var(--meta); color: #fff; box-shadow: 0 6px 16px rgba(8,102,255,.28); }
.ch-btn-primary:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 10px 22px rgba(8,102,255,.32); }
.ch-btn-ghost { background: #fff; border-color: var(--line); color: var(--text); }
.ch-btn-ghost:hover { border-color: #C9D7F5; color: #0866FF; }
.ch-btn-danger-ghost { background: #fff; border-color: var(--line); color: #B42318; }
.ch-btn-danger-ghost:hover { border-color: #F5C2C0; background: #FFF7F7; }
.ch-btn-danger { background: #DC2626; color: #fff; }
.ch-btn-disabled { background: #F1F3F7; color: var(--muted); cursor: default; }
.ch-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

.ch-empty { text-align: center; padding: 18px 12px 6px; }
.ch-empty-art { width: 64px; height: 64px; margin: 0 auto 12px; border-radius: 18px; display: grid; place-items: center; background: #EEF4FF; color: #0866FF; font-size: 30px; }
.ch-empty h3 { margin: 0 0 6px; padding: 0; font-size: 16px; font-weight: 700; line-height: 1.35; color: var(--ink); }
.ch-empty p { margin: 0 auto 14px; max-width: 520px; color: var(--muted); font-size: 13px; line-height: 1.55; }
.ch-gets { list-style: none; margin: 0 auto; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; max-width: 640px; text-align: left; }
.ch-gets li { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 12px; background: #F8FAFF; color: var(--text); font-size: 12.5px; }
.ch-gets i { color: #0866FF; font-size: 17px; }

.ch-conn { border-top: 1px solid var(--line-soft); padding-top: 18px; display: flex; flex-direction: column; gap: 14px; }
.ch-conn-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.ch-conn-id { display: flex; align-items: center; gap: 12px; }
.ch-conn-id strong { color: var(--ink); font-size: 14px; }
.ch-conn-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 3px; font-size: 12px; color: var(--muted); }
.ch-conn-meta i { font-size: 14px; vertical-align: -2px; }
.ch-conn-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ch-confirm { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; color: #B42318; font-weight: 600; }

.ch-status { display: inline-flex; align-items: center; gap: 5px; font-weight: 700; }
.ch-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; display: inline-block; }
.ch-status.is-ok { color: #16A34A; } .ch-status.is-warn { color: #D97706; } .ch-status.is-bad { color: #DC2626; } .ch-status.is-muted { color: #64748B; }
.ch-muted { color: var(--muted); font-size: 12px; }
.ch-warn-text { color: #D97706; font-weight: 600; }

.ch-alert { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 12px; font-size: 13px; }
.ch-alert i { font-size: 20px; }
.ch-alert div { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.ch-alert.is-warn { background: #FFF8EB; color: #92400E; }
.ch-alert.is-bad, .ch-alert.is-muted { background: #FDECEC; color: #991B1B; }

.ch-caps { display: flex; flex-wrap: wrap; gap: 8px; }
.ch-cap { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border-radius: 10px; background: #F4F5F9; color: var(--muted); font-size: 12.5px; font-weight: 600; }
.ch-cap.is-on { background: #EEF4FF; color: #0B4FD1; }
.ch-cap i { font-size: 15px; }
.ch-cap-up { margin-left: 4px; color: var(--brand); font-weight: 700; text-decoration: none; }
.ch-cap-up:hover { text-decoration: underline; }

.ch-group { display: flex; flex-direction: column; gap: 8px; }
.ch-group-head { display: flex; align-items: center; gap: 8px; }
.ch-group-head h4 { margin: 0; padding: 0; line-height: 1.35; display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; color: var(--ink); }
.ch-group-head h4 i { font-size: 16px; color: var(--muted); }
.ch-count { font-size: 11px; font-weight: 700; background: #F1F3F7; color: var(--text); padding: 1px 7px; border-radius: 6px; }
.ch-assets { display: flex; flex-direction: column; border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
.ch-asset { display: flex; align-items: center; gap: 12px; padding: 12px 14px; background: #fff; transition: background .15s, opacity .15s; }
.ch-asset + .ch-asset { border-top: 1px solid var(--line-soft); }
.ch-asset:hover { background: #FBFCFF; }
.ch-asset.is-off .ch-avatar, .ch-asset.is-off .ch-asset-id { opacity: .5; }
.ch-avatar { width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0; overflow: hidden; display: grid; place-items: center; color: #fff; font-size: 17px; }
.ch-avatar img { width: 100%; height: 100%; object-fit: cover; background: #fff; }
.ch-asset-id { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ch-asset-id strong { font-size: 13.5px; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ch-asset-id small { font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ch-asset-caps { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
.ch-toggle-pill { display: inline-flex; align-items: center; gap: 4px; height: 28px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: #fff; color: var(--muted); font-size: 12px; font-weight: 600; cursor: pointer; transition: all .15s; }
.ch-toggle-pill i { font-size: 14px; }
.ch-toggle-pill.is-on { background: #EEF4FF; border-color: #BFD3FF; color: #0B4FD1; }
.ch-toggle-pill:hover:not(:disabled) { border-color: #9DBBFF; }
.ch-toggle-pill:disabled { opacity: .6; cursor: progress; }

.ch-switch { position: relative; width: 40px; height: 22px; flex-shrink: 0; cursor: pointer; }
.ch-switch input { opacity: 0; width: 0; height: 0; }
.ch-switch span { position: absolute; inset: 0; border-radius: 999px; background: #D5D9E2; transition: background .15s; }
.ch-switch span::after { content: ""; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2); transition: transform .15s; }
.ch-switch input:checked + span { background: #0866FF; }
.ch-switch input:checked + span::after { transform: translateX(18px); }
.ch-switch input:disabled + span { opacity: .5; cursor: not-allowed; }
.ch-none { margin: 0; }

.ch-upcoming { background: #fff; border: 1px dashed #D5D9E2; border-radius: 18px; padding: 20px 24px; }
.ch-upcoming h3 { margin: 0 0 2px; padding: 0; font-size: 15px; font-weight: 700; line-height: 1.35; color: var(--ink); }
.ch-up-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; margin-top: 14px; }
.ch-up { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; background: #F8F9FB; }
.ch-up span:last-child { display: flex; flex-direction: column; }
.ch-up strong { font-size: 13px; color: var(--ink); }
.ch-up small { font-size: 11.5px; color: var(--muted); }
.ch-up-icon { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; color: #fff; font-size: 16px; flex-shrink: 0; }
.ch-up-icon.is-google { background: #4285F4; }
.ch-up-icon.is-x, .ch-up-icon.is-tiktok, .ch-up-icon.is-threads { background: #000; }
.ch-up-icon.is-linkedin { background: linear-gradient(180deg, #0A66C2 0%, #004182 100%); }
.ch-up-icon.is-snapchat { background: #FFFC00; color: #000; }
.ch-up-icon.is-pinterest { background: linear-gradient(180deg, #F0002A 0%, #BD001C 100%); }

[dir="rtl"] .ch-brand-stack .ch-badge + .ch-badge { margin-left: 0; margin-right: -10px; }
[dir="rtl"] .ch-flash-x { margin-left: 0; margin-right: auto; }
[dir="rtl"] .ch-switch span::after { left: auto; right: 3px; }
[dir="rtl"] .ch-switch input:checked + span::after { transform: translateX(-18px); }

@media (max-width: 767.98px) {
  .ch { padding: 14px; }
  .ch-card { padding: 16px; }
  .ch-step { flex-direction: column; align-items: stretch; }
  .ch-asset { flex-wrap: wrap; }
  .ch-asset-caps { width: 100%; justify-content: flex-start; padding-left: 50px; }
  .ch-gets { grid-template-columns: 1fr; }
}
</style>
