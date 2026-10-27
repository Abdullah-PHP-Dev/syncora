<template>
  <div class="gs-screen">
    <div class="gs-head">
      <div>
        <h1>{{ t('reports.title') }}</h1>
        <p class="gs-sub">{{ t('reports.subtitle') }}</p>
      </div>
    </div>

    <gs-state v-if="!suite.account.connected" kind="setup" icon="bxl-google" :title="t('states.first_time_title')" :text="t('states.first_time_text')">
      <a v-if="suite.canManage" :href="suite.urls.setup" class="gs-btn gs-btn-primary gs-btn-sm">{{ t('setup.button') }}</a>
    </gs-state>

    <template v-else>
      <section class="gs-card gs-pad gs-report-form">
        <div class="gs-field">
          <span class="gs-label" id="gs-src">{{ t('reports.source') }}</span>
          <div class="gs-seg" role="radiogroup" aria-labelledby="gs-src">
            <button v-for="s in ['ga4', 'search_console']" :key="s" type="button" role="radio" :aria-checked="service === s ? 'true' : 'false'" :class="{ active: service === s }" @click="setService(s)">{{ serviceName(s) }}</button>
          </div>
        </div>
        <div class="gs-field">
          <span class="gs-label">{{ t('reports.property') }}</span>
          <p v-if="prop" class="gs-field-value">{{ prop.name }} <span class="gs-mono gs-muted">{{ prop.external_id }}</span> · <a :href="suite.urls.properties + '#svc-' + service" class="gs-link">{{ t('common.change') }}</a></p>
          <p v-else class="gs-field-value gs-muted">{{ t('settings.no_property') }} · <a :href="suite.urls.properties + '#svc-' + service" class="gs-link">{{ t('overview.choose_property') }}</a></p>
        </div>
        <label class="gs-field">
          <span class="gs-label">{{ t('reports.report') }}</span>
          <select v-model="type" class="gs-input" @change="reset">
            <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
          </select>
        </label>
        <label v-if="service === 'search_console'" class="gs-field">
          <span class="gs-label">{{ t('search.search_type_label') }}</span>
          <select v-model="searchType" class="gs-input" @change="reset">
            <option v-for="st in ['web', 'image', 'video', 'news', 'discover', 'googleNews']" :key="st" :value="st">{{ t('search.types.' + st) }}</option>
          </select>
        </label>
        <div class="gs-field">
          <span class="gs-label">{{ t('ranges.label') }}</span>
          <gs-range :show-compare="false" :presets="['7d', '28d', '30d', '90d']" @change="reset" />
        </div>
        <div class="gs-row gs-report-actions">
          <button type="button" class="gs-btn gs-btn-ghost" :disabled="!prop || previewing" @click="preview"><i class="bx bx-show"></i>{{ previewing ? t('reports.previewing') : t('reports.preview') }}</button>
          <button type="button" class="gs-btn gs-btn-primary" :disabled="!prop || exporting" @click="exportCsv">
            <span v-if="exporting" class="gs-spinner sm light" aria-hidden="true"></span><i v-else class="bx bx-download"></i>{{ exporting ? t('reports.exporting') : t('reports.export_csv') }}
          </button>
        </div>
        <p class="gs-muted sm">{{ t('reports.formats_note') }}</p>
        <p v-if="exportMessage" :class="exportError ? 'gs-field-error' : 'gs-text-success'" role="status">{{ exportMessage }}</p>
      </section>

      <section class="gs-card gs-pad">
        <gs-state v-if="previewing" kind="loading" :title="t('reports.previewing')" compact />
        <gs-state v-else-if="error" kind="error" :error="error" compact @retry="preview" />
        <gs-state v-else-if="!result" kind="empty" icon="bx-table" :text="prop ? t('reports.pick') : t('states.no_property_text', { service: serviceName(service) })" compact />
        <gs-state v-else-if="!result.rows.length" kind="empty" :text="t('reports.empty')" compact />
        <template v-else>
          <p class="gs-muted sm">{{ t('reports.preview_note', { n: result.rows.length }) }}</p>
          <gs-table :rows="result.rows" :columns="previewColumns" />
        </template>
      </section>
    </template>
  </div>
</template>

<script>
import axios from 'axios';
import { suite, t, fmt, api, rangeParams, selected, serviceName, raw, normaliseErrorAsync } from '../store';
import GsRange from '../ui/GsRange.vue';
import GsState from '../ui/GsState.vue';
import GsTable from '../ui/GsTable.vue';

const FORMATS = {
  number: (v) => fmt.int(v), percent: (v) => fmt.pct(Number(v)), decimal: (v) => fmt.dec(Number(v)),
  seconds: (v) => fmt.duration(Number(v)), money: (v) => fmt.dec(Number(v), 2), date: (v) => v, text: (v) => (v === '' || v === null ? t('common.not_set') : v),
};

export default {
  name: 'GsReports',
  components: { GsRange, GsState, GsTable },
  data() {
    const q = new URLSearchParams(window.location.search);
    const service = q.get('service') === 'search_console' ? 'search_console' : 'ga4';
    const types = raw('reports.types.' + service) || {};
    return { suite, service, type: types[q.get('type')] ? q.get('type') : Object.keys(types)[0], searchType: 'web', previewing: false, exporting: false, result: null, error: null, exportMessage: '', exportError: false };
  },
  computed: {
    prop() { return selected(this.service); },
    types() { return raw('reports.types.' + this.service) || {}; },
    previewColumns() {
      return this.result.columns.map((c) => ({ key: c.key, label: t('columns.' + c.key), numeric: !['text', 'date'].includes(c.type), format: (v) => (typeof v === 'string' && c.type !== 'text' && c.type !== 'date' ? FORMATS[c.type](Number(v)) : FORMATS[c.type] ? FORMATS[c.type](v) : v), class: c.type === 'text' ? 'gs-ellipsis' : '' }));
    },
  },
  methods: {
    t, serviceName,
    setService(s) { this.service = s; this.type = Object.keys(this.types)[0]; this.reset(); },
    reset() { this.result = null; this.error = null; this.exportMessage = ''; },
    params() { return { ...rangeParams(), service: this.service, type: this.type, search_type: this.service === 'search_console' ? this.searchType : undefined }; },
    async preview() {
      this.previewing = true; this.error = null; this.result = null;
      try {
        const res = await api('get', suite.urls.api.report_preview, { params: this.params() });
        if (res.error) this.error = res.error; else this.result = res.data;
      } catch (e) { this.error = e; } finally { this.previewing = false; }
    },
    async exportCsv() {
      this.exporting = true; this.exportMessage = ''; this.exportError = false;
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
        this.exportMessage = t('reports.export_failed', { reason: err.message });
      } finally { this.exporting = false; }
    },
  },
};
</script>
