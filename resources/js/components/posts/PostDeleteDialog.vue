<template>
  <div class="pmd-backdrop" role="dialog" aria-modal="true" aria-labelledby="pmd-delete-title" @click.self="!busy && $emit('close')">
    <div class="pmd-dialog">
      <div class="pmd-head">
        <span class="pmd-icon" style="background:#DC2626"><i class="far fa-trash-alt"></i></span>
        <div><h3 id="pmd-delete-title">{{ bulk ? tc('confirm.bulk_title', deletable.length, { count: deletable.length }) : t('confirm.delete_title') }}</h3></div>
      </div>

      <p v-if="!bulk" class="pmd-text">{{ singleText }}</p>
      <template v-else>
        <p class="pmd-text">{{ t('confirm.bulk_text') }}</p>
        <ul class="pmd-list">
          <li v-for="item in deletable.slice(0, 6)" :key="item.post_id"><i :class="item.icon" :style="{ color: item.color }"></i><bdi dir="auto">{{ item.label }}</bdi></li>
        </ul>
        <p v-if="skipped.length" class="pmd-warn">{{ tc('confirm.bulk_skipped', skipped.length, { count: skipped.length }) }}</p>
      </template>

      <p v-if="error" class="pmd-error" role="alert">{{ error }}</p>

      <div class="pmd-actions">
        <button ref="cancel" type="button" class="pmd-btn ghost" :disabled="busy" @click="$emit('close')">{{ t('confirm.cancel') }}</button>
        <button type="button" class="pmd-btn danger" :disabled="busy || !deletable.length" @click="run"><i :class="busy ? 'fas fa-spinner fa-spin' : 'far fa-trash-alt'"></i>{{ busy ? t('confirm.deleting') : t('confirm.delete') }}</button>
      </div>
    </div>
  </div>
</template>

<script>
/*
 * Confirm and delete one or several platform posts (PostController::destroy /
 * bulkDestroy). Targets whose platform can't delete (PostCapabilities) are
 * listed as skipped and never sent.
 */
export default {
  name: 'PostDeleteDialog',

  props: {
    i18n: { type: Object, required: true },
    // Platform entries: { post_id, key, name, icon, color, label, state, capabilities }
    targets: { type: Array, required: true },
    destroyUrlTemplate: { type: String, required: true },
    bulkUrl: { type: String, default: '' },
  },

  data() {
    return { busy: false, error: '' };
  },

  computed: {
    bulk() { return this.targets.length > 1; },
    deletable() { return this.targets.filter((x) => x.capabilities && x.capabilities.can_delete); },
    skipped() { return this.targets.filter((x) => !(x.capabilities && x.capabilities.can_delete)); },
    singleText() {
      const x = this.targets[0] || {};
      if (x.key === 'whatsapp' && x.state === 'published') return this.t('confirm.delete_whatsapp');
      return x.capabilities && x.capabilities.delete_remote ? this.t('confirm.delete_platform', { platform: x.name }) : this.t('confirm.delete_local', { platform: x.name });
    },
  },

  mounted() {
    this.$nextTick(() => this.$refs.cancel && this.$refs.cancel.focus());
  },

  methods: {
    t(key, params) { return this.i18n.t(key, params); },
    tc(key, count, params) { return this.i18n.tc(key, count, params); },

    run() {
      this.busy = true;
      this.error = '';
      const headers = { headers: { Accept: 'application/json' } };
      const request = this.bulk
        ? window.axios.post(this.bulkUrl, { ids: this.deletable.map((x) => x.post_id) }, headers)
        : window.axios.delete(this.destroyUrlTemplate.replace('__POST__', this.deletable[0].post_id), headers);

      request
        .then(({ data }) => this.$emit('done', data))
        .catch((e) => {
          const body = (e.response && e.response.data) || {};
          // A bulk run where nothing could be deleted still reports why.
          if (this.bulk && body.results) {
            this.$emit('done', body);
            return;
          }
          this.error = body.message || this.t('alerts.generic_error');
        })
        .finally(() => { this.busy = false; });
    },
  },
};
</script>
