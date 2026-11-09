<template>
  <div class="wp" :dir="i18n.dir">
    <main class="wp-main">
      <div v-if="flash" class="wp-flash" role="status">{{ flash }}</div>

      <div class="wp-head">
        <div>
          <h1>{{ t('welcome', { name: picker.user.first_name || picker.user.name }) }}</h1>
          <p>{{ t('subtitle') }}</p>
        </div>
        <div class="wp-head-actions">
          <label class="wp-search">
            <i class="bx bx-search" aria-hidden="true"></i>
            <span class="sr-only">{{ t('search') }}</span>
            <input v-model="query" type="search" :placeholder="t('search')">
          </label>
          <button v-if="picker.can_create" type="button" class="wp-btn wp-btn-primary" @click="openCreate"><i class="bx bx-plus"></i> {{ t('new') }}</button>
        </div>
      </div>

      <section v-for="inv in picker.invitations" :key="inv.id" class="wp-invite">
        <span class="wp-invite-icon"><i class="bx bx-envelope"></i></span>
        <div class="wp-invite-text">
          <strong>{{ t('invitation.title', { workspace: inv.workspace }) }}</strong>
          <small>{{ t('invitation.body', { time: ago(inv.invited_at) }) }}</small>
        </div>
        <form method="POST" :action="inv.decline_url"><input type="hidden" name="_token" :value="csrf"><button type="submit" class="wp-btn wp-btn-secondary">{{ t('invitation.decline') }}</button></form>
        <form method="POST" :action="inv.accept_url"><input type="hidden" name="_token" :value="csrf"><button type="submit" class="wp-btn wp-btn-dark">{{ t('invitation.accept') }}</button></form>
      </section>

      <div class="wp-section-head">
        <h2>{{ t('yours', { count: picker.workspaces.length }) }}</h2>
        <span>{{ t('sorted') }}</span>
      </div>

      <div class="wp-grid">
        <article v-for="w in visible" :key="w.id" class="wp-card" :class="{ 'is-current': w.current }">
          <div class="wp-card-head">
            <span class="wp-avatar" :class="{ 'has-logo': w.logo_url }" :style="w.logo_url ? {} : { background: color(w.name) }"><img v-if="w.logo_url" :src="w.logo_url" alt=""><template v-else>{{ initials(w.name) }}</template></span>
            <div class="wp-card-title">
              <h3 :title="w.name"><span class="wp-name">{{ w.name }}</span><span v-if="w.current" class="wp-tag">{{ t('current') }}</span><span v-else-if="w.id === lastUsedId" class="wp-tag">{{ t('last_used') }}</span></h3>
              <p>{{ t('kinds.' + w.kind) }} · {{ t('roles.' + w.role) }}</p>
            </div>
            <button v-if="w.edit_url" type="button" class="wp-icon-btn wp-edit" :title="t('edit.button')" :aria-label="t('edit.button') + ': ' + w.name" @click="openEdit(w)"><i class="bx bx-pencil"></i></button>
          </div>
          <div v-if="w.platforms.length" class="wp-platforms">
            <span v-for="p in w.platforms.slice(0, 7)" :key="p" class="wp-pf" :style="pfStyle(p)" :title="p">{{ pfLabel(p) }}</span>
            <span v-if="w.platforms.length > 7" class="wp-pf is-more">{{ t('more_platforms', { count: w.platforms.length - 7 }) }}</span>
          </div>
          <p v-else class="wp-empty">{{ t('no_platforms') }}</p>
          <div class="wp-card-foot">
            <span class="wp-health" :class="w.platforms.length ? (w.attention ? 'is-warn' : 'is-ok') : 'is-muted'">
              {{ !w.platforms.length ? '' : (w.attention ? tc('attention', w.attention) : t('healthy')) }}
            </span>
            <form method="POST" :action="w.open_url">
              <input type="hidden" name="_token" :value="csrf">
              <!-- Owners finish setup on Connections; team members can't manage connections. -->
              <input v-if="needsSetup(w)" type="hidden" name="to" value="setup">
              <button type="submit" class="wp-btn" :class="needsSetup(w) ? 'wp-btn-primary' : 'wp-btn-secondary'">
                {{ needsSetup(w) ? t('finish_setup') : t('open') }}
              </button>
            </form>
          </div>
        </article>

        <button v-if="picker.can_create && !query" type="button" class="wp-card wp-create" @click="openCreate">
          <span class="wp-create-icon"><i class="bx bx-plus"></i></span>
          <strong>{{ t('create.card_title') }}</strong>
          <small>{{ atLimit ? tc('limit', picker.limit) : t('create.card_body') }}</small>
        </button>
        <p v-if="query && !visible.length" class="wp-none">{{ t('none_found', { query }) }}</p>
      </div>

      <p v-if="picker.can_create" class="wp-usage">
        <i class="bx bx-layer"></i>
        {{ picker.limit === -1 ? t('usage_unlimited') : t('usage', { used: picker.owned, limit: picker.limit }) }}
        <a v-if="picker.limit !== -1" :href="picker.upgrade_url">{{ t('upgrade') }}</a>
      </p>
    </main>

    <!-- Create a workspace -->
    <transition name="wp-fade">
      <div v-if="creating" class="wp-backdrop" @click.self="closeCreate">
        <form class="wp-modal" role="dialog" aria-modal="true" :aria-label="editing ? t('edit.title') : t('create.title')" novalidate @submit.prevent="submit" @keydown.esc="closeCreate">
          <div class="wp-modal-main">
            <header class="wp-modal-head">
              <div>
                <h2>{{ editing ? t('edit.title') : t('create.title') }}</h2>
                <p>{{ editing ? t('edit.subtitle') : t('create.subtitle') }}</p>
              </div>
              <button type="button" class="wp-icon-btn" :aria-label="t('create.cancel')" @click="closeCreate"><i class="bx bx-x"></i></button>
            </header>

            <div v-if="!editing && (atLimit || errors.workspace)" class="wp-alert" role="alert">
              <i class="bx bx-crown"></i>
              <div><strong>{{ t('upgrade_title') }}</strong><span>{{ errors.workspace || tc('limit', picker.limit) }}</span></div>
              <a :href="picker.upgrade_url" class="wp-btn wp-btn-primary sm">{{ t('upgrade') }}</a>
            </div>
            <p v-if="errors.general" class="wp-alert is-bad" role="alert">{{ errors.general }}</p>

            <label class="wp-field">
              <span>{{ t('create.name') }}</span>
              <input ref="name" v-model="form.name" type="text" maxlength="150" :placeholder="t('create.name_placeholder')" :aria-invalid="errors.name ? 'true' : 'false'" @input="errors.name = null">
              <small v-if="errors.name" class="wp-error">{{ errors.name }}</small>
            </label>

            <div class="wp-field">
              <span>{{ t('create.logo') }} <small class="wp-optional">{{ t('create.logo_optional') }}</small></span>
              <div class="wp-logo">
                <span class="wp-avatar lg" :class="{ 'has-logo': logoPreview }" :style="logoPreview ? {} : { background: color(form.name || '?') }">
                  <img v-if="logoPreview" :src="logoPreview" alt=""><template v-else>{{ initials(form.name || '?') }}</template>
                </span>
                <div class="wp-logo-actions">
                  <div class="wp-logo-buttons">
                    <label class="wp-btn wp-btn-secondary sm wp-upload">
                      <input ref="logo" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="pickLogo">
                      <i class="bx bx-upload" aria-hidden="true"></i> {{ logoPreview ? t('create.logo_change') : t('create.logo_upload') }}
                    </label>
                    <button v-if="logoPreview" type="button" class="wp-btn wp-btn-ghost sm" @click="clearLogo">{{ t('create.logo_remove') }}</button>
                  </div>
                  <small class="wp-help">{{ t('create.logo_help') }}</small>
                </div>
              </div>
              <small v-if="errors.logo" class="wp-error">{{ errors.logo }}</small>
            </div>

            <fieldset class="wp-field">
              <legend>{{ t('create.for') }}</legend>
              <div class="wp-choices">
                <label v-for="k in ['own', 'client']" :key="k" class="wp-choice" :class="{ 'is-picked': form.kind === k }">
                  <input v-model="form.kind" type="radio" name="kind" :value="k">
                  <span class="wp-radio" aria-hidden="true"></span>
                  <span><strong>{{ t('create.' + k) }}</strong><small>{{ t('create.' + k + '_text') }}</small></span>
                </label>
              </div>
            </fieldset>

            <div class="wp-row">
              <label class="wp-field">
                <span>{{ t('create.industry') }}</span>
                <select v-model="form.industry">
                  <option value="">{{ t('create.industry_placeholder') }}</option>
                  <option v-for="i in picker.options.industries" :key="i" :value="i">{{ t('industries.' + i) }}</option>
                </select>
              </label>
              <label class="wp-field">
                <span>{{ t('create.country') }}</span>
                <select v-model="form.country">
                  <option v-for="(cur, code) in picker.options.countries" :key="code" :value="code">{{ t('countries.' + code) }} · {{ cur }}</option>
                </select>
              </label>
            </div>
            <div class="wp-row">
              <label class="wp-field">
                <span>{{ t('create.timezone') }}</span>
                <select v-model="form.timezone">
                  <option v-for="tz in picker.options.timezones" :key="tz" :value="tz">{{ t('timezones.' + tz) }}</option>
                </select>
              </label>
              <fieldset class="wp-field">
                <legend>{{ t('create.language') }}</legend>
                <div class="wp-seg">
                  <label v-for="l in picker.options.languages" :key="l" :class="{ 'is-active': form.content_language === l }">
                    <input v-model="form.content_language" type="radio" name="content_language" :value="l">{{ t('create.languages.' + l) }}
                  </label>
                </div>
              </fieldset>
            </div>

            <fieldset class="wp-field">
              <legend>{{ t('create.goals') }}</legend>
              <small class="wp-help">{{ t('create.goals_help') }}</small>
              <div class="wp-goals">
                <label v-for="(platforms, g) in picker.options.goals" :key="g" class="wp-goal" :class="{ 'is-picked': form.goals.includes(g) }">
                  <input v-model="form.goals" type="checkbox" :value="g">
                  <span class="wp-check" aria-hidden="true"><i class="bx bx-check"></i></span>
                  {{ t('goal_labels.' + g) }}
                </label>
              </div>
            </fieldset>

            <footer class="wp-modal-foot">
              <small>{{ editing ? '' : t('create.invite_later') }}</small>
              <button type="button" class="wp-btn wp-btn-secondary" @click="closeCreate">{{ t('create.cancel') }}</button>
              <button type="submit" class="wp-btn wp-btn-primary" :disabled="saving || (!editing && atLimit)">
                <i v-if="saving" class="bx bx-loader-alt bx-spin"></i>
                <template v-if="editing">{{ saving ? t('edit.saving') : t('edit.submit') }}</template>
                <template v-else>{{ saving ? t('create.creating') : t('create.submit') }}</template>
                <i v-if="!saving && !editing" class="bx bx-right-arrow-alt wp-flip"></i>
              </button>
            </footer>
          </div>

          <aside class="wp-modal-side">
            <p class="wp-side-label">{{ t('create.preview') }}</p>
            <div class="wp-preview">
              <span class="wp-avatar" :class="{ 'has-logo': logoPreview }" :style="logoPreview ? {} : { background: color(form.name || '?') }"><img v-if="logoPreview" :src="logoPreview" alt=""><template v-else>{{ initials(form.name || '?') }}</template></span>
              <div>
                <strong>{{ form.name || t('create.name_placeholder') }}</strong>
                <small>{{ [t('kinds.' + form.kind), form.industry ? t('industries.' + form.industry) : null, currency].filter(Boolean).join(' · ') }}</small>
              </div>
            </div>
            <p class="wp-side-label">{{ t('create.recommended') }}</p>
            <ul v-if="recommended.length" class="wp-recs">
              <li v-for="p in recommended" :key="p">
                <span class="wp-pf lg" :style="pfStyle(p)">{{ pfLabel(p) }}</span>
                <span><strong>{{ t('platforms.' + p + '.name') }}</strong><small>{{ t('platforms.' + p + '.why') }}</small></span>
              </li>
            </ul>
            <p v-else class="wp-help">{{ t('create.pick_goals') }}</p>
            <div v-if="!editing" class="wp-next">
              <p class="wp-side-label">{{ t('create.next') }}</p>
              <ol>
                <li v-for="(s, i) in nextSteps" :key="i"><span>{{ i + 1 }}</span> {{ s }}</li>
              </ol>
            </div>
          </aside>
        </form>
      </div>
    </transition>
  </div>
</template>

<script>
import { createTranslator } from '../ads/facebook/i18n';

const COLORS = ['#720AFF', '#064BFF', '#0F766E', '#9A3412', '#BE185D', '#0369A1', '#4338CA', '#B45309'];
// Short platform marks (social_accounts.platform and Connections card keys).
const MARKS = {
  facebook: ['f', '#1877F2', '#fff'], instagram: ['IG', '#C13584', '#fff'], whatsapp: ['WA', '#075E54', '#fff'],
  threads: ['@', '#000', '#fff'], linkedin: ['in', '#0A66C2', '#fff'], x: ['X', '#000', '#fff'], twitter: ['X', '#000', '#fff'],
  youtube: ['YT', '#CC0000', '#fff'], tiktok: ['TT', '#000', '#fff'], snapchat: ['SC', '#FFFC00', '#000'],
  google: ['G', '#1A73E8', '#fff'], business_profile: ['G', '#1A73E8', '#fff'], pinterest: ['P', '#E60023', '#fff'],
  meta_ads: ['Ad', '#0866FF', '#fff'], meta: ['Ad', '#0866FF', '#fff'], openai: ['AI', '#10A37F', '#fff']
};
const PREFERRED = ['facebook', 'instagram', 'whatsapp', 'threads', 'linkedin', 'x', 'youtube', 'tiktok', 'snapchat', 'google', 'pinterest'];

export default {
  props: {
    picker: { type: Object, required: true },
    flashText: { type: String, default: '' }
  },

  data() {
    const zone = (Intl.DateTimeFormat().resolvedOptions().timeZone) || 'Asia/Riyadh';
    return {
      query: '',
      creating: false,
      // The card being edited, or null when creating.
      editing: null,
      // Logo: the picked file, what the dialog shows, and "take the current one off".
      logoFile: null,
      logoPreview: null,
      removeLogo: false,
      saving: false,
      flash: this.flashText,
      errors: {},
      zone: this.picker.options.timezones.includes(zone) ? zone : 'Asia/Riyadh',
      form: this.blankForm(this.picker.options.timezones.includes(zone) ? zone : 'Asia/Riyadh'),
      csrf: (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
    };
  },

  computed: {
    i18n() { return createTranslator(this.picker.messages, this.picker.locale); },
    visible() {
      const q = this.query.trim().toLocaleLowerCase();
      return q ? this.picker.workspaces.filter(w => w.name.toLocaleLowerCase().includes(q)) : this.picker.workspaces;
    },
    lastUsedId() {
      const opened = this.picker.workspaces.filter(w => w.role === 'owner' && w.last_opened_at);
      return opened.length ? opened.reduce((a, b) => (a.last_opened_at > b.last_opened_at ? a : b)).id : null;
    },
    atLimit() { return this.picker.limit !== -1 && this.picker.owned >= this.picker.limit; },
    currency() { return this.picker.options.countries[this.form.country] || null; },
    recommended() {
      const picked = new Set();
      this.form.goals.forEach(g => (this.picker.options.goals[g] || []).forEach(p => picked.add(p)));
      if (picked.has('meta_ads')) picked.delete('facebook');
      return this.picker.options.platform_order.filter(p => picked.has(p)).slice(0, 4);
    },
    nextSteps() {
      const steps = (this.picker.messages.create || {}).next_steps;
      return Array.isArray(steps) ? steps : Object.values(steps || {});
    }
  },

  methods: {
    t(key, params) { return this.i18n.t(key, params); },
    tc(key, n, params) { return this.i18n.tc(key, n, params); },
    initials(name) {
      const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
      return ((parts[0] || '?')[0] + (parts.length > 1 ? parts[1][0] : (parts[0] || '').slice(1, 2))).toUpperCase();
    },
    color(name) {
      const seed = String(name).split('').reduce((n, c) => n + c.charCodeAt(0), 0);
      return COLORS[seed % COLORS.length];
    },
    needsSetup(w) { return w.role === 'owner' && !w.platforms.length; },
    pfLabel(p) { return (MARKS[p] || [String(p).slice(0, 2).toUpperCase()])[0]; },
    pfStyle(p) {
      const m = MARKS[p] || [null, '#64748B', '#fff'];
      return { background: m[1], color: m[2] };
    },
    sortPlatforms(list) {
      return list.slice().sort((a, b) => (PREFERRED.indexOf(a) + 99) % 99 - (PREFERRED.indexOf(b) + 99) % 99);
    },
    ago(iso) {
      if (!iso) return '';
      const s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
      if (s < 60) return this.t('ago.now');
      if (s < 3600) return this.t('ago.minutes', { count: Math.round(s / 60) });
      if (s < 86400) return this.t('ago.hours', { count: Math.round(s / 3600) });
      return this.t('ago.days', { count: Math.round(s / 86400) });
    },
    blankForm(zone) {
      return {
        name: '', kind: 'client', industry: '', country: 'SA', timezone: zone || this.zone,
        content_language: this.picker.locale === 'ar' ? 'ar' : 'both', goals: []
      };
    },
    openCreate() {
      if (this.editing) { this.editing = null; this.form = this.blankForm(); }
      this.resetLogo(null);
      this.creating = true;
      this.errors = {};
      document.body.style.overflow = 'hidden';
      this.$nextTick(() => this.$refs.name && this.$refs.name.focus());
    },
    openEdit(w) {
      const d = w.details || {};
      const blank = this.blankForm();
      this.editing = w;
      // Older workspaces may have no country, time zone or language yet: offer the defaults.
      this.form = {
        name: d.name || w.name, kind: d.kind || w.kind, industry: d.industry || '',
        country: d.country || blank.country, timezone: d.timezone || blank.timezone,
        content_language: d.content_language || blank.content_language, goals: (d.goals || []).slice()
      };
      this.resetLogo(d.logo_url || w.logo_url || null);
      this.creating = true;
      this.errors = {};
      document.body.style.overflow = 'hidden';
      this.$nextTick(() => this.$refs.name && this.$refs.name.focus());
    },
    resetLogo(url) {
      if (this.logoPreview && this.logoPreview.startsWith('blob:')) URL.revokeObjectURL(this.logoPreview);
      this.logoFile = null;
      this.logoPreview = url;
      this.removeLogo = false;
      if (this.$refs.logo) this.$refs.logo.value = '';
    },
    // Checked here for a quick answer; the server checks again (type, size, dimensions).
    pickLogo(event) {
      const file = event.target.files && event.target.files[0];
      if (!file) return;
      if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
        this.$set(this.errors, 'logo', this.t('create.logo_type'));
        event.target.value = '';
        return;
      }
      if (file.size > 2 * 1024 * 1024) {
        this.$set(this.errors, 'logo', this.t('create.logo_size'));
        event.target.value = '';
        return;
      }
      this.resetLogo(URL.createObjectURL(file));
      this.logoFile = file;
      this.$set(this.errors, 'logo', null);
    },
    clearLogo() {
      const hadSaved = this.editing && !this.logoFile && this.logoPreview;
      this.resetLogo(null);
      this.removeLogo = !!hadSaved || !!this.editing;
    },
    closeCreate() {
      if (this.saving) return;
      this.creating = false;
      document.body.style.overflow = '';
    },
    submit() {
      if (this.saving) return;
      if (!this.form.name.trim()) { this.errors = { name: this.t('create.name_required') }; return; }
      this.saving = true;
      this.errors = {};
      // Multipart, so the logo travels with the details (PATCH is sent as POST + _method).
      const payload = new FormData();
      ['name', 'kind', 'country', 'timezone', 'content_language'].forEach(k => payload.append(k, this.form[k] || ''));
      if (this.form.industry) payload.append('industry', this.form.industry);
      this.form.goals.forEach(g => payload.append('goals[]', g));
      if (this.logoFile) payload.append('logo', this.logoFile);
      if (this.removeLogo) payload.append('remove_logo', '1');
      const card = this.editing;
      if (card) payload.append('_method', 'PATCH');
      const request = window.axios.post(card ? card.edit_url : this.picker.store_url, payload);
      request
        .then(({ data }) => {
          if (!card) { window.location.href = data.redirect; return; }
          // Saved: update the card in place.
          Object.assign(card, { name: data.workspace.name, logo_url: data.workspace.logo_url, kind: data.workspace.kind, industry: data.workspace.industry, details: data.workspace });
          this.saving = false;
          this.closeCreate();
          this.editing = null;
          this.flash = data.message;
          // The sidebar switcher is server-rendered: follow the new name of the open workspace.
          const sidebar = card.current && document.querySelector('.ov-ws-text strong');
          if (sidebar) {
            sidebar.textContent = data.workspace.name;
            const avatar = document.querySelector('.ov-ws-switch .ov-ws-avatar');
            if (avatar) {
              avatar.textContent = '';
              if (data.workspace.logo_url) {
                const img = document.createElement('img');
                img.src = data.workspace.logo_url;
                img.alt = '';
                avatar.appendChild(img);
                avatar.classList.add('has-logo');
              } else {
                avatar.textContent = this.initials(data.workspace.name);
                avatar.classList.remove('has-logo');
              }
            }
          }
        })
        .catch((error) => {
          this.saving = false;
          const bag = (error.response && error.response.status === 422 && error.response.data.errors) || null;
          if (bag) {
            const flat = {};
            Object.keys(bag).forEach(k => { flat[k.split('.')[0]] = [].concat(bag[k])[0]; });
            this.errors = flat;
          } else {
            this.errors = { general: this.t(card ? 'edit.failed' : 'create.failed') };
          }
        });
    }
  },

  created() {
    this.picker.workspaces.forEach(w => { w.platforms = this.sortPlatforms(w.platforms || []); });
  },

  mounted() {
    // Sidebar "New workspace" links here with ?create=1.
    if (this.picker.can_create && new URLSearchParams(window.location.search).get('create') === '1') {
      this.openCreate();
      history.replaceState(null, '', window.location.pathname);
    }
  }
};
</script>

<style scoped>
.wp {
  /* Socialeaz brand (brand-theme.css, loaded by layouts.app). */
  --p600: var(--theme-purple, #720aff); --p700: var(--theme-hover, #6008ff); --p50: var(--theme-soft, #f1eaff); --p100: #E4D4FF;
  --grad: var(--theme-gradient, linear-gradient(110deg, #004cff, #710bff));
  --ink: var(--theme-ink, #09072d); --ink5: var(--theme-muted, #626498); --line: var(--theme-border, #edf0fa);
  --canvas: #F7F8FD; --ok: #15803D; --warn: #B45309;
  color: var(--ink);
}
.wp *, .wp *::before, .wp *::after { box-sizing: border-box; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }
[dir="rtl"] .wp-flip { transform: scaleX(-1); }

.wp-main { max-width: 1040px; margin: 0 auto; padding: 8px 0 40px; }
.wp-flash { margin-bottom: 16px; padding: 12px 16px; border-radius: 12px; background: #E8F8EE; color: #166534; font-size: 14px; }
.wp-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 24px; }
.wp-head h1 { margin: 0 0 6px; font-size: 32px; line-height: 40px; font-weight: 800; letter-spacing: -.02em; }
.wp-head p { margin: 0; color: var(--ink5); font-size: 15px; }
.wp-head-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.wp-search { display: flex; align-items: center; gap: 8px; width: 260px; height: 42px; padding: 0 14px; border: 1px solid var(--line); border-radius: 12px; background: #fff; color: var(--ink5); }
.wp-search input { flex: 1; min-width: 0; border: 0; outline: none; background: none; font: inherit; font-size: 14px; color: var(--ink); }
.wp-search:focus-within { border-color: var(--p600); box-shadow: 0 0 0 3px rgba(114,10,255,.14); }

.wp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 42px; padding: 0 16px; border: 1px solid transparent; border-radius: 12px; font: inherit; font-size: 14px; font-weight: 600; white-space: nowrap; cursor: pointer; text-decoration: none; }
.wp-btn.sm { height: 36px; padding: 0 12px; font-size: 13px; border-radius: 10px; }
.wp-btn i { font-size: 18px; }
.wp-btn-primary { background: var(--grad); color: #fff; box-shadow: 0 8px 20px rgba(54, 11, 255, .18); }
.wp-btn-primary:hover { color: #fff; box-shadow: 0 10px 24px rgba(54, 11, 255, .28); }
.wp-btn-secondary { background: #fff; border-color: var(--line); color: var(--ink); }
.wp-btn-secondary:hover { border-color: #CBD5E1; color: var(--ink); }
.wp-btn-dark { background: var(--ink); color: #fff; }
.wp-btn-dark:hover { color: #fff; background: #1d1a4a; }
.wp-btn:disabled { opacity: .55; cursor: not-allowed; }
.wp-btn:focus-visible, .wp-icon-btn:focus-visible, .wp-create:focus-visible { outline: 3px solid rgba(114,10,255,.35); outline-offset: 2px; }
.wp-icon-btn { display: grid; place-items: center; width: 40px; height: 40px; border: 1px solid var(--line); border-radius: 12px; background: #fff; color: var(--ink); font-size: 20px; cursor: pointer; }

.wp-invite { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; padding: 16px 18px; border: 1px solid var(--line); border-radius: 18px; background: #fff; }
.wp-invite form { margin: 0; }
.wp-invite-icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px; background: var(--p50); color: var(--p600); font-size: 20px; }
.wp-invite-text { flex: 1; min-width: 200px; display: flex; flex-direction: column; }
.wp-invite-text strong { font-size: 15px; }
.wp-invite-text small { color: var(--ink5); font-size: 13px; }

.wp-section-head { display: flex; justify-content: space-between; align-items: center; margin: 28px 0 12px; }
.wp-section-head h2 { margin: 0; color: var(--ink5); font-size: 12.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.wp-section-head span { color: var(--ink5); font-size: 13px; }

.wp-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
.wp-card { display: flex; flex-direction: column; gap: 16px; min-width: 0; min-height: 210px; padding: 20px; border: 1px solid var(--line); border-radius: 20px; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
.wp-card.is-current { border-color: #C9A8FF; box-shadow: 0 0 0 3px var(--p50); }
.wp-card-head { display: flex; align-items: center; gap: 12px; }
.wp-avatar { display: grid; place-items: center; flex-shrink: 0; width: 44px; height: 44px; border-radius: 12px; color: #fff; font-size: 15px; font-weight: 700; overflow: hidden; }
.wp-avatar.lg { width: 64px; height: 64px; border-radius: 16px; font-size: 20px; }
.wp-avatar.has-logo { background: #fff; border: 1px solid var(--line); }
.wp-avatar img { width: 100%; height: 100%; object-fit: contain; }
.wp-logo { display: flex; align-items: center; gap: 16px; }
.wp-logo-actions { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.wp-logo-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.wp-logo-actions .wp-help { margin: 0; }
.wp-upload { position: relative; cursor: pointer; }
.wp-upload:focus-within { outline: 3px solid rgba(114, 10, 255, .35); outline-offset: 2px; }
.wp-optional { color: var(--ink5); font-weight: 500; }
.wp-btn-ghost { background: transparent; color: var(--ink5); }
.wp-btn-ghost:hover { background: #F5F6FB; color: #B91C1C; }
.wp-card-title { min-width: 0; flex: 1; }
.wp-edit { flex-shrink: 0; align-self: flex-start; width: 34px; height: 34px; border-radius: 10px; font-size: 17px; color: var(--ink5); }
.wp-edit:hover { color: var(--p600); border-color: #D9C6FF; background: var(--p50); }
.wp-card-title h3 { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 6px; margin: 0; font-size: 16px; font-weight: 650; }
.wp-name { min-width: 0; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wp-card-title h3 .wp-tag { margin-inline-start: 0; }
.wp-card-title p { margin: 2px 0 0; color: var(--ink5); font-size: 13px; }
.wp-tag { display: inline-block; margin-inline-start: 6px; padding: 2px 8px; border-radius: 999px; background: var(--p50); color: var(--p600); font-size: 11px; font-weight: 600; vertical-align: middle; }
.wp-platforms { display: flex; flex-wrap: wrap; gap: 6px; }
.wp-pf { display: inline-grid; place-items: center; min-width: 26px; height: 26px; padding: 0 5px; border-radius: 7px; font-size: 10.5px; font-weight: 800; }
.wp-pf.lg { min-width: 30px; height: 30px; border-radius: 8px; }
.wp-pf.is-more { background: #F1F5F9; color: var(--ink5); }
.wp-empty { margin: 0; color: var(--ink5); font-size: 13.5px; }
.wp-card-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: auto; padding-top: 14px; border-top: 1px solid var(--line); }
.wp-card-foot form { margin: 0; }
.wp-health { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; }
.wp-health::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.wp-health.is-ok { color: var(--ok); }
.wp-health.is-warn { color: var(--warn); }
.wp-health.is-muted::before { display: none; }
.wp-create { align-items: center; justify-content: center; gap: 8px; border-style: dashed; background: transparent; box-shadow: none; text-align: center; font: inherit; color: var(--ink); cursor: pointer; }
.wp-create:hover { background: #fff; border-color: #CBD5E1; }
.wp-create-icon { display: grid; place-items: center; width: 44px; height: 44px; margin-bottom: 4px; border-radius: 12px; background: var(--grad); color: #fff; font-size: 22px; }
.wp-create strong { font-size: 15px; }
.wp-create small { max-width: 220px; color: var(--ink5); font-size: 13px; line-height: 1.5; }
.wp-none { grid-column: 1 / -1; color: var(--ink5); }
.wp-usage { display: flex; align-items: center; gap: 8px; margin: 20px 0 0; color: var(--ink5); font-size: 13px; }
.wp-usage a { color: var(--p600); font-weight: 600; text-decoration: none; }

/* Modal */
.wp-backdrop { position: fixed; inset: 0; z-index: 1090; display: grid; place-items: center; padding: 24px; background: rgba(15,23,42,.5); overflow-y: auto; }
/* Each column is capped at the modal's height and scrolls on its own; a grid row sized to its content
   would otherwise outgrow the modal and get clipped (footer buttons unreachable on short screens). */
.wp-modal { --wp-modal-max: calc(100vh - 48px); display: grid; grid-template-columns: minmax(0, 1fr) 300px; width: min(940px, 100%); max-height: var(--wp-modal-max); margin: 0; border-radius: 24px; background: #fff; box-shadow: 0 24px 60px rgba(15,23,42,.25); overflow: hidden; }
@supports (height: 100dvh) { .wp-modal { --wp-modal-max: calc(100dvh - 48px); } }
.wp-modal-main { --wp-pad-y: 28px; --wp-pad-x: 32px; display: flex; flex-direction: column; gap: 16px; max-height: var(--wp-modal-max); padding: var(--wp-pad-y) var(--wp-pad-x); overflow-y: auto; overscroll-behavior: contain; }
.wp-modal-head { display: flex; justify-content: space-between; gap: 12px; }
.wp-modal-head h2 { margin: 0 0 4px; font-size: 22px; font-weight: 700; }
.wp-modal-head p { margin: 0; color: var(--ink5); font-size: 14px; }
.wp-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; margin: 0; padding: 0; border: 0; }
.wp-field > span, .wp-field legend { margin-bottom: 6px; padding: 0; font-size: 13.5px; font-weight: 600; }
.wp-field input[type=text], .wp-field select { height: 44px; padding: 0 12px; border: 1px solid var(--line); border-radius: 12px; background: #fff; font: inherit; font-size: 14px; color: var(--ink); }
.wp-field input[type=text]:focus, .wp-field select:focus { outline: none; border-color: var(--p600); box-shadow: 0 0 0 3px rgba(114,10,255,.14); }
.wp-field input[aria-invalid=true] { border-color: #F87171; }
.wp-error { color: #B91C1C; font-size: 12.5px; }
.wp-help { margin: -4px 0 4px; color: var(--ink5); font-size: 13px; }
.wp-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.wp-choices { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.wp-choice { position: relative; display: flex; align-items: flex-start; gap: 10px; padding: 14px; border: 1.5px solid var(--line); border-radius: 14px; cursor: pointer; }
.wp-choice.is-picked { border-color: var(--p600); background: var(--p50); }
.wp-choice input, .wp-goal input, .wp-seg input { position: absolute; opacity: 0; pointer-events: none; }
.wp-choice > span:last-child { display: flex; flex-direction: column; }
.wp-choice strong { font-size: 14.5px; }
.wp-choice small { color: var(--ink5); font-size: 12.5px; }
.wp-radio { flex-shrink: 0; width: 18px; height: 18px; margin-top: 2px; border: 2px solid #CBD5E1; border-radius: 50%; }
.is-picked .wp-radio { border: 5px solid var(--p600); }
.wp-choice input:focus-visible ~ .wp-radio, .wp-goal input:focus-visible ~ .wp-check { box-shadow: 0 0 0 3px rgba(114,10,255,.3); }
.wp-seg { display: flex; padding: 4px; border: 1px solid var(--line); border-radius: 12px; background: var(--canvas); }
.wp-seg label { position: relative; flex: 1; padding: 8px 6px; border-radius: 9px; text-align: center; font-size: 13.5px; font-weight: 500; color: var(--ink5); cursor: pointer; }
.wp-seg label.is-active { background: #fff; color: var(--ink); font-weight: 600; box-shadow: 0 1px 3px rgba(15,23,42,.1); }
.wp-seg label:focus-within { outline: 2px solid rgba(114,10,255,.35); }
.wp-goals { display: flex; flex-wrap: wrap; gap: 8px; }
.wp-goal { position: relative; display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border: 1.5px solid var(--line); border-radius: 999px; font-size: 13.5px; font-weight: 500; cursor: pointer; }
.wp-goal.is-picked { border-color: var(--p600); background: var(--p50); }
.wp-check { display: grid; place-items: center; width: 18px; height: 18px; border: 2px solid #CBD5E1; border-radius: 5px; color: transparent; font-size: 14px; }
.is-picked .wp-check { border-color: var(--p600); background: var(--p600); color: #fff; }
/* Sticky action bar: stays at the bottom of the scrolling form, full-bleed over the column padding.
   Sticky offsets are measured inside the scroller's padding, hence the negative bottom on desktop. */
.wp-modal-foot { position: sticky; bottom: calc(-1 * var(--wp-pad-y)); z-index: 2; display: flex; align-items: center; gap: 10px; margin: auto calc(-1 * var(--wp-pad-x)) calc(-1 * var(--wp-pad-y)); padding: 16px var(--wp-pad-x) 20px; border-top: 1px solid var(--line); background: #fff; box-shadow: 0 -10px 24px rgba(15,23,42,.05); }
.wp-modal-foot small { flex: 1; color: var(--ink5); font-size: 13px; }
.wp-alert { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 0; padding: 12px 14px; border: 1px solid #FDE68A; border-radius: 14px; background: #FFFBEB; font-size: 13.5px; color: #92400E; }
.wp-alert i { font-size: 20px; }
.wp-alert > div { flex: 1; min-width: 180px; display: flex; flex-direction: column; }
.wp-alert strong { color: var(--ink); }
.wp-alert.is-bad { border-color: #FECACA; background: #FEF2F2; color: #B91C1C; }
.wp-modal-side { display: flex; flex-direction: column; gap: 12px; max-height: var(--wp-modal-max); padding: 28px 24px; background: var(--canvas); border-inline-start: 1px solid var(--line); overflow-y: auto; overscroll-behavior: contain; }
.wp-side-label { margin: 6px 0 0; color: var(--ink5); font-size: 11.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.wp-preview { display: flex; align-items: center; gap: 12px; padding: 14px; border: 1px solid var(--line); border-radius: 14px; background: #fff; }
.wp-preview > div { min-width: 0; display: flex; flex-direction: column; }
.wp-preview strong { font-size: 15px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wp-preview small { color: var(--ink5); font-size: 12.5px; }
.wp-recs { display: flex; flex-direction: column; gap: 8px; margin: 0; padding: 0; list-style: none; }
.wp-recs li { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
.wp-recs li > span:last-child { display: flex; flex-direction: column; min-width: 0; }
.wp-recs strong { font-size: 14px; }
.wp-recs small { color: var(--ink5); font-size: 12px; }
.wp-next { margin-top: auto; }
.wp-next ol { display: flex; flex-direction: column; gap: 10px; margin: 8px 0 0; padding: 0; list-style: none; font-size: 13.5px; }
.wp-next li { display: flex; align-items: flex-start; gap: 10px; }
.wp-next li span { display: grid; place-items: center; flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%; background: #E2E8F0; font-size: 11.5px; font-weight: 700; }
.wp-next li:first-child span { background: var(--p600); color: #fff; }

.wp-fade-enter-active, .wp-fade-leave-active { transition: opacity .18s ease; }
.wp-fade-enter, .wp-fade-leave-to { opacity: 0; }
@media (prefers-reduced-motion: reduce) { .wp-fade-enter-active, .wp-fade-leave-active { transition: none; } }

@media (max-width: 991.98px) {
  .wp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  /* Stacked: the whole modal scrolls (form, then preview); the action bar still sticks to its bottom. */
  .wp-modal { grid-template-columns: minmax(0, 1fr); overflow-y: auto; overscroll-behavior: contain; }
  .wp-modal-main, .wp-modal-side { max-height: none; overflow: visible; }
  .wp-modal-foot { bottom: 0; } /* the scroller is now .wp-modal, which has no padding */
  .wp-modal-side { border-inline-start: 0; border-top: 1px solid var(--line); }
  .wp-next { margin-top: 8px; }
}
@media (max-width: 640px) {
  .wp-main { padding: 0 0 32px; }
  .wp-head h1 { font-size: 26px; line-height: 34px; }
  .wp-head-actions, .wp-search { width: 100%; }
  .wp-head-actions .wp-btn { flex: 1; }
  .wp-grid, .wp-row, .wp-choices { grid-template-columns: minmax(0, 1fr); }
  .wp-backdrop { padding: 0; place-items: end stretch; }
  .wp-modal { --wp-modal-max: 94vh; border-radius: 22px 22px 0 0; }
  @supports (height: 100dvh) { .wp-modal { --wp-modal-max: 94dvh; } }
  .wp-modal-main { --wp-pad-y: 22px; --wp-pad-x: 16px; }
  .wp-modal-side { padding: 20px 16px; }
  .wp-modal-foot { flex-wrap: wrap; padding-bottom: calc(14px + env(safe-area-inset-bottom)); }
  .wp-modal-foot small { flex-basis: 100%; }
  .wp-modal-foot .wp-btn { flex: 1; }
}
</style>
