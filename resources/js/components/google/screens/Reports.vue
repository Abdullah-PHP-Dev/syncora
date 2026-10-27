<template>
  <div class="gs-screen">
    <div class="gs-head">
      <div>
        <p class="gs-eyebrow">{{ t('title') }} / {{ t('nav.reports') }}</p>
        <h1>{{ t('reports.title') }}</h1>
        <p class="gs-sub">{{ t('reports.subtitle') }}</p>
      </div>
    </div>

    <gs-state v-if="!suite.account.connected" kind="setup" icon="bxl-google" :title="t('states.first_time_title')" :text="t('states.first_time_text')">
      <a v-if="suite.canManage" :href="suite.urls.setup" class="gs-btn gs-btn-primary gs-btn-sm">{{ t('setup.button') }}</a>
    </gs-state>

    <div v-else class="gs-reports">
      <!-- Builder -->
      <aside class="gs-card gs-rb" :aria-label="t('reports.builder')">
        <div class="gs-rb-step">
          <p class="gs-rb-title"><span class="gs-rb-n">1</span>{{ t('reports.source') }}</p>
          <div class="gs-rb-sources" role="radiogroup" :aria-label="t('reports.source')">
            <button v-for="s in ['ga4', 'search_console']" :key="s" type="button" role="radio" class="gs-rb-source" :class="{ active: service === s }" :aria-checked="service === s ? 'true' : 'false'" @click="setService(s)">
              <gs-service-icon :service="s" />
              <span class="gs-rb-source-main">
                <strong>{{ serviceName(s) }}</strong>
                <small v-if="propFor(s)" :title="propFor(s).external_id">{{ propFor(s).name }}</small>
                <small v-else class="gs-text-warning">{{ t('settings.no_property') }}</small>
              </span>
              <i class="bx" :class="service === s ? 'bxs-check-circle' : 'bx-circle'" aria-hidden="true"></i>
            </button>
          </div>
          <a :href="suite.urls.properties + '#svc-' + service" class="gs-link sm">{{ prop ? t('common.change') + ' · ' + t('reports.property') : t('overview.choose_property') }}</a>
        </div>

        <div class="gs-rb-step">
          <p class="gs-rb-title" id="gs-rb-type"><span class="gs-rb-n">2</span>{{ t('reports.report') }}</p>
          <div class="gs-rb-types" role="radiogroup" aria-labelledby="gs-rb-type">
            <button v-for="(label, key) in types" :key="key" type="button" role="radio" class="gs-rb-type" :class="{ active: type === key }" :aria-checked="type === key ? 'true' : 'false'" @click="setType(key)">
              <i class="bx" :class="ICONS[key] || 'bx-table'" aria-hidden="true"></i>
              <span><strong>{{ label }}</strong><small>{{ t('reports.hints.' + service + '.' + key) }}</small></span>
            </button>
          </div>
        </div>

        <div class="gs-rb-step">
          <p class="gs-rb-title"><span class="gs-rb-n">3</span>{{ t('reports.options') }}</p>
          <div class="gs-field">
            <span class="gs-label">{{ t('ranges.label') }}</span>
            <gs-range :show-compare="false" :presets="['7d', '28d', '30d', '90d']" @change="preview" />
          </div>
          <label v-if="service === 'search_console'" class="gs-field">
            <span class="gs-label">{{ t('search.search_type_label') }}</span>
            <select v-model="searchType" class="gs-input" @change="preview">
              <option v-for="st in ['web', 'image', 'video', 'news', 'discover', 'googleNews']" :key="st" :value="st">{{ t('search.types.' + st) }}</option>
            </select>
          </label>
        </div>
      </aside>

      <!-- Preview -->
      <section class="gs-card gs-rp" :aria-busy="previewing ? 'true' : 'false'">
        <header class="gs-rp-head">
          <div class="gs-rp-id">
            <span class="gs-rp-icon" :class="'svc-' + service" aria-hidden="true"><i class="bx" :class="ICONS[type] || 'bx-table'"></i></span>
            <div>
              <h2>{{ types[type] }}</h2>
              <p class="gs-rp-meta">
                <span>{{ serviceName(service) }}</span>
                <span v-if="prop" class="gs-mono">{{ prop.name }}</span>
                <span><i class="bx bx-calendar"></i>{{ periodLabel }}</span>
                <span v-if="result && result.rows.length">{{ t('reports.rows', { n: fmt.int(result.rows.length) }) }}</span>
              </p>
            </div>
          </div>
          <div class="gs-rp-actions">
            <button type="button" class="gs-btn gs-btn-ghost gs-btn-icon" :disabled="!prop || previewing" :aria-label="t('common.refresh')" :title="t('common.refresh')" @click="preview">
              <i class="bx bx-refresh" :class="{ 'bx-spin': previewing }"></i>
            </button>
            <button v-if="suite.extra.can_export" type="button" class="gs-btn gs-btn-primary" :disabled="!prop || exporting" @click="exportCsv">
              <span v-if="exporting" class="gs-spinner sm light" aria-hidden="true"></span><i v-else class="bx bx-download"></i>{{ exporting ? t('reports.exporting') : t('reports.export_csv') }}
            </button>
            <a v-else :href="suite.urls.upgrade" class="gs-btn gs-btn-ghost"><i class="bx bx-lock-alt"></i>{{ t('reports.export_csv') }}</a>
          </div>
        </header>

        <div v-if="!suite.extra.can_export" class="gs-banner is-brand" role="note">
          <i class="bx bx-lock-alt"></i>
          <div><strong>{{ t('reports.locked_title') }}</strong><p>{{ t('reports.locked_text') }}</p></div>
          <a :href="suite.urls.upgrade" class="gs-btn gs-btn-primary gs-btn-sm">{{ t('reports.upgrade') }}</a>
        </div>
        <div v-if="exportMessage" class="gs-banner" :class="exportError ? 'is-danger' : 'is-success'" role="status">
          <i class="bx" :class="exportError ? 'bx-error-circle' : 'bx-check-circle'"></i>
          <div><p>{{ exportMessage }}</p></div>
          <a v-if="exportError && exportCode === 'package'" :href="suite.urls.upgrade" class="gs-link">{{ t('reports.upgrade') }}</a>
        </div>

        <div class="gs-rp-body">
          <div v-if="previewing" class="gs-skel-table" aria-hidden="true">
            <span v-for="n in 7" :key="n" class="gs-skel" :style="{ width: (100 - (n % 3) * 12) + '%' }"></span>
          </div>
          <gs-state v-else-if="!prop" kind="empty" icon="bx-list-check" :title="t('states.no_property_title')" :text="t('states.no_property_text', { service: serviceName(service) })" compact>
            <a :href="suite.urls.properties + '#svc-' + service" class="gs-btn gs-btn-primary gs-btn-sm">{{ t('overview.choose_property') }}</a>
          </gs-state>
          <gs-state v-else-if="error" kind="error" :error="error" compact @retry="preview" />
          <gs-state v-else-if="result && !result.rows.length" kind="empty" icon="bx-table" :title="t('reports.empty_title')" :text="t('reports.empty')" compact />
          <gs-table v-else-if="result" :rows="result.rows" :columns="previewColumns" :limit="15" />
        </div>

        <footer class="gs-rp-foot">
          <span><i class="bx bx-info-circle"></i>{{ result && result.rows.length ? t('reports.preview_note', { n: result.rows.length }) : t('reports.formats_note') }}</span>
          <span v-if="fetchedAt">{{ t('common.last_synced') }} {{ fmt.ago(fetchedAt) }}</span>
        </footer>
      </section>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { suite, t, fmt, api, rangeParams, selected, serviceName, raw, normaliseErrorAsync } from '../store';
import GsRange from '../ui/GsRange.vue';
import GsState from '../ui/GsState.vue';
import GsTable from '../ui/GsTable.vue';
import GsServiceIcon from '../ui/GsServiceIcon.vue';

const FORMATS = {
  number: (v) => fmt.int(v), percent: (v) => fmt.pct(Number(v)), decimal: (v) => fmt.dec(Number(v)),
  seconds: (v) => fmt.duration(Number(v)), money: (v) => fmt.dec(Number(v), 2), date: (v) => v, text: (v) => (v === '' || v === null ? t('common.not_set') : v),
};

const ICONS = {
  daily: 'bx-calendar', channels: 'bx-git-branch', sources: 'bx-link-alt', landing_pages: 'bx-log-in-circle', pages: 'bx-file',
  countries: 'bx-world', devices: 'bx-devices', events: 'bx-pointer', queries: 'bx-search-alt', appearance: 'bx-star',
};

/*
 * Report builder: pick a source, a report and options; the preview loads
 * by itself. CSV export is a separate package module (data_export), so a
 * plan without it sees why up front instead of a failed download.
 */
export default {
  name: 'GsReports',
  components: { GsRange, GsState, GsTable, GsServiceIcon },
  data() {
    const q = new URLSearchParams(window.location.search);
    const service = q.get('service') === 'search_console' ? 'search_console' : 'ga4';
    const types = raw('reports.types.' + service) || {};
    return { suite, ICONS, service, type: types[q.get('type')] ? q.get('type') : Object.keys(types)[0], searchType: 'web', previewing: false, exporting: false, result: null, fetchedAt: null, error: null, exportMessage: '', exportError: false, exportCode: null };
  },
  computed: {
    fmt: () => fmt,
    prop() { return selected(this.service); },
    types() { return raw('reports.types.' + this.service) || {}; },
    periodLabel() {
      const r = suite.range;
      return r.key === 'custom' && r.start && r.end ? `${fmt.date(r.start)} – ${fmt.date(r.end)}` : t('ranges.' + r.key);
    },
    previewColumns() {
      return this.result.columns.map((c) => ({ key: c.key, label: t('columns.' + c.key), numeric: !['text', 'date'].includes(c.type), format: (v) => (typeof v === 'string' && c.type !== 'text' && c.type !== 'date' ? FORMATS[c.type](Number(v)) : FORMATS[c.type] ? FORMATS[c.type](v) : v), class: c.type === 'text' ? 'gs-ellipsis' : '' }));
    },
  },
  mounted() { if (suite.account.connected) this.preview(); },
  methods: {
    t, serviceName,
    propFor: (s) => selected(s),
    setService(s) {
      if (this.service === s) return;
      this.service = s;
      this.type = Object.keys(this.types)[0];
      this.preview();
    },
    setType(key) { if (this.type !== key) { this.type = key; this.preview(); } },
    params() { return { ...rangeParams(), service: this.service, type: this.type, search_type: this.service === 'search_console' ? this.searchType : undefined }; },
    async preview() {
      this.error = null; this.result = null; this.exportMessage = '';
      if (!this.prop) return;
      this.previewing = true;
      const ticket = (this.ticket = (this.ticket || 0) + 1);
      try {
        const res = await api('get', suite.urls.api.report_preview, { params: this.params() });
        if (ticket !== this.ticket) return; // a newer selection won
        if (res.error) this.error = res.error; else { this.result = res.data; this.fetchedAt = res.fetched_at || null; }
      } catch (e) {
        if (ticket === this.ticket) this.error = e;
      } finally {
        if (ticket === this.ticket) this.previewing = false;
      }
    },
    async exportCsv() {
      this.exporting = true; this.exportMessage = ''; this.exportError = false; this.exportCode = null;
      try {
        const response = await axios.get(suite.urls.api.report_export, { params: this.params(), responseType: 'blob' });
        const match = /filename="?([^";]+)"?/.exec(response.headers['content-disposition'] || '');
        const file = match ? match[1] : 'socialeaz-report.csv';
        const url = URL.createObjectURL(response.data);
        const a = document.createElement('a');
        a.href = url; a.download = file; document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        this.exportMessage = t('reports.exported', { file });
      } catch (e) {
        const err = await normaliseErrorAsync(e);
        this.exportError = true;
        this.exportCode = err.code;
        this.exportMessage = err.code === 'package' ? t('reports.locked_text') : t('reports.export_failed', { reason: err.message });
      } finally { this.exporting = false; }
    },
  },
};
</script>
