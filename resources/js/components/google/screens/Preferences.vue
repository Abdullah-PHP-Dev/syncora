<template>
  <div class="gs-screen">
    <div class="gs-head">
      <div>
        <h1>{{ t('preferences.title') }}</h1>
        <p class="gs-sub">{{ t('preferences.subtitle') }}</p>
      </div>
      <a :href="suite.urls.health" class="gs-btn gs-btn-ghost"><i class="bx bx-pulse"></i>{{ t('health.title') }}</a>
    </div>

    <form class="gs-split" @submit.prevent="save">
      <div class="gs-stack">
        <section class="gs-card gs-pad">
          <h2>{{ t('preferences.reporting') }}</h2>
          <label class="gs-field">
            <span class="gs-label">{{ t('preferences.default_range') }}</span>
            <select v-model="form.default_range" class="gs-input">
              <option v-for="r in ['today', '7d', '28d', '30d', '90d']" :key="r" :value="r">{{ t('ranges.' + r) }}</option>
            </select>
          </label>
          <label class="gs-switch">
            <input v-model="form.compare_previous" type="checkbox">
            <span class="gs-switch-ui" aria-hidden="true"></span>
            <span><strong>{{ t('preferences.compare_previous') }}</strong><small>{{ t('preferences.compare_hint') }}</small></span>
          </label>
          <div class="gs-field">
            <span class="gs-label">{{ t('preferences.timezone') }}</span>
            <p class="gs-muted sm">{{ t('preferences.timezone_hint', { tz: (ga && ga.time_zone) || t('preferences.timezone_unknown') }) }}</p>
          </div>
        </section>

        <section class="gs-card gs-pad">
          <div class="gs-card-head"><h2>{{ t('preferences.default_properties') }}</h2><a :href="suite.urls.properties" class="gs-btn gs-btn-ghost gs-btn-sm">{{ t('common.change') }}</a></div>
          <p class="gs-muted sm">{{ t('preferences.default_properties_hint') }}</p>
          <dl class="gs-dl">
            <div v-for="s in ['ga4', 'search_console']" :key="s"><dt>{{ serviceName(s) }}</dt><dd>{{ selected(s) ? selected(s).name : t('settings.no_property') }}</dd></div>
          </dl>
        </section>

        <section class="gs-card gs-pad">
          <h2>{{ t('preferences.widgets') }}</h2>
          <p class="gs-muted sm">{{ t('preferences.widgets_hint') }}</p>
          <div class="gs-check-grid">
            <label v-for="w in widgets" :key="w" class="gs-check"><input v-model="form.widgets" type="checkbox" :value="w">{{ t('preferences.widget.' + w) }}</label>
          </div>
        </section>

        <section class="gs-card gs-pad">
          <h2>{{ t('preferences.sync') }}</h2>
          <fieldset class="gs-radios">
            <legend class="gs-label">{{ t('preferences.sync_frequency') }}</legend>
            <label v-for="(label, key) in syncOptions" :key="key" class="gs-check"><input v-model="form.sync_frequency" type="radio" :value="key">{{ label }}</label>
          </fieldset>
        </section>

        <p v-if="error" class="gs-field-error" role="alert">{{ error }}</p>
        <div class="gs-row">
          <button type="submit" class="gs-btn gs-btn-primary" :disabled="saving || !dirty">{{ saving ? t('common.saving') : t('common.save') }}</button>
        </div>
      </div>

      <aside class="gs-stack">
        <section class="gs-card gs-pad">
          <h2>{{ t('preferences.privacy') }}</h2>
          <ul class="gs-tips"><li v-for="(p, i) in privacy" :key="i"><i class="bx bx-shield-quarter"></i>{{ p }}</li></ul>
          <a :href="suite.urls.privacy" class="gs-link" target="_blank" rel="noopener">{{ t('setup.privacy_link') }}</a>
        </section>
        <section class="gs-card gs-pad">
          <h2>{{ t('preferences.manage') }}</h2>
          <ul class="gs-links">
            <li><a :href="suite.urls.integrations">{{ t('nav.integrations') }}</a></li>
            <li><a :href="suite.urls.settings_ga4">{{ t('settings.title', { service: serviceName('ga4') }) }}</a></li>
            <li><a :href="suite.urls.settings_search_console">{{ t('settings.title', { service: serviceName('search_console') }) }}</a></li>
            <li><a :href="suite.urls.hub">{{ t('nav.credentials') }} · Connection Hub</a></li>
          </ul>
        </section>
      </aside>
    </form>
  </div>
</template>

<script>
import { suite, t, api, selected, serviceName, toast, raw } from '../store';

const WIDGETS = ['services', 'insights', 'performance', 'attention', 'activity', 'acquisition', 'queries', 'landing_pages', 'audience'];
const pick = (p) => ({ default_range: p.default_range, compare_previous: !!p.compare_previous, widgets: [...(p.widgets || [])], sync_frequency: p.sync_frequency });

export default {
  name: 'GsPreferences',
  data: () => ({ suite, widgets: WIDGETS, form: pick(suite.preferences), saving: false, error: '' }),
  computed: {
    ga() { return selected('ga4'); },
    syncOptions() { return raw('preferences.sync_options') || {}; },
    privacy() { return raw('preferences.privacy_points') || []; },
    dirty() { return JSON.stringify(this.form) !== JSON.stringify(pick(suite.preferences)); },
  },
  methods: {
    t, selected, serviceName,
    async save() {
      this.saving = true; this.error = '';
      try {
        const res = await api('put', suite.urls.api.preferences, { data: this.form });
        suite.preferences = res.preferences;
        this.form = pick(res.preferences);
        toast(res.message);
      } catch (e) {
        this.error = e.code === 'forbidden' ? t('preferences.read_only') : e.message;
      } finally { this.saving = false; }
    },
  },
};
</script>
