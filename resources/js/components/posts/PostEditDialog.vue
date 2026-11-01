<template>
  <div class="pmd-backdrop" role="dialog" aria-modal="true" aria-labelledby="pmd-edit-title" @click.self="$emit('close')">
    <form class="pmd-dialog" @submit.prevent="save">
      <div class="pmd-head">
        <span class="pmd-icon" :style="{ background: target.color || '#004CFF' }"><i :class="target.icon || 'fas fa-pen'"></i></span>
        <div>
          <h3 id="pmd-edit-title">{{ local ? t('edit.title') : t('edit.title_on', { platform: target.name }) }}</h3>
          <p dir="auto">{{ target.page }}</p>
        </div>
      </div>

      <p class="pmd-note"><i class="fas fa-info-circle"></i>{{ note }}</p>

      <label v-if="fields.includes('title')" class="pmd-field">
        <span>{{ t('edit.field_title') }}</span>
        <input ref="first" v-model="form.title" type="text" maxlength="255" dir="auto">
      </label>
      <label v-if="fields.includes('content')" class="pmd-field">
        <span>{{ t('edit.field_content') }}</span>
        <textarea :ref="fields.includes('title') ? null : 'first'" v-model="form.content" rows="7" dir="auto"></textarea>
        <small>{{ form.content.length }}</small>
      </label>
      <label v-if="fields.includes('schedule_at')" class="pmd-field">
        <span>{{ t('edit.field_schedule') }}</span>
        <input v-model="form.schedule_at" type="datetime-local" required>
        <small>{{ t('edit.timezone', { zone: timezone }) }}</small>
      </label>

      <p v-if="error" class="pmd-error" role="alert">{{ error }}</p>

      <div class="pmd-actions">
        <button type="button" class="pmd-btn ghost" @click="$emit('close')">{{ t('edit.cancel') }}</button>
        <button type="submit" class="pmd-btn primary" :disabled="saving"><i :class="saving ? 'fas fa-spinner fa-spin' : 'fas fa-check'"></i>{{ saving ? t('edit.saving') : t('edit.save') }}</button>
      </div>
    </form>
  </div>
</template>

<script>
/*
 * Edit one platform post (PostController::update). Offers only the fields
 * PostCapabilities allows: a scheduled or draft post's title, text and time
 * here; a published post's text (and YouTube title) on the platform.
 */
export default {
  name: 'PostEditDialog',

  props: {
    i18n: { type: Object, required: true },
    // A platform entry: { post_id, key, name, icon, color, page, title, content, schedule_at, state, capabilities }
    target: { type: Object, required: true },
    updateUrlTemplate: { type: String, required: true },
  },

  data() {
    return {
      form: { title: this.target.title || '', content: this.target.content || '', schedule_at: this.toLocalInput(this.target.schedule_at) },
      saving: false,
      error: '',
      timezone: (window.Intl && Intl.DateTimeFormat().resolvedOptions().timeZone) || '',
    };
  },

  computed: {
    fields() { return (this.target.capabilities && this.target.capabilities.edit_fields) || []; },
    local() { return !(this.target.capabilities && this.target.capabilities.edit_remote); },
    note() {
      if (this.local) return this.t('edit.note_local');
      const key = 'edit.note_' + this.target.key;
      const note = this.t(key);
      return note === key ? '' : note;
    },
  },

  mounted() {
    this.$nextTick(() => this.$refs.first && this.$refs.first.focus());
  },

  methods: {
    t(key, params) { return this.i18n.t(key, params); },

    // ISO time -> the browser's local "YYYY-MM-DDTHH:mm" for <input type=datetime-local>.
    toLocalInput(iso) {
      if (!iso) return '';
      const d = new Date(iso);
      const pad = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    },

    save() {
      this.saving = true;
      this.error = '';
      const payload = {};
      this.fields.forEach((field) => { payload[field] = field === 'schedule_at' ? this.form.schedule_at.replace('T', ' ') + ':00' : this.form[field]; });
      if (this.fields.includes('schedule_at')) payload.timezone = this.timezone;

      window.axios.patch(this.updateUrlTemplate.replace('__POST__', this.target.post_id), payload, { headers: { Accept: 'application/json' } })
        .then(({ data }) => this.$emit('saved', data))
        .catch((e) => {
          const body = (e.response && e.response.data) || {};
          const first = body.errors ? Object.values(body.errors)[0] : null;
          this.error = (first && first[0]) || body.message || this.t('alerts.generic_error');
        })
        .finally(() => { this.saving = false; });
    },
  },
};
</script>

<style>
.pmd-backdrop { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 16px; background: rgba(16, 24, 40, .45); }
.pmd-dialog { width: min(560px, 100%); max-height: calc(100vh - 32px); overflow-y: auto; padding: 22px; border-radius: 16px; background: #fff; box-shadow: 0 24px 60px rgba(16, 24, 40, .3); color: #4B5263; }
.pmd-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.pmd-head h3 { margin: 0; font-size: 1.05rem; font-weight: 700; line-height: 1.35; color: #161B2B; }
.pmd-head p { margin: 2px 0 0; font-size: .8rem; color: #8A92A3; }
.pmd-icon { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; flex-shrink: 0; color: #fff; font-size: 1rem; }
.pmd-note { display: flex; gap: 8px; align-items: flex-start; margin: 0 0 14px; padding: 10px 12px; border-radius: 10px; background: #F5F8FF; border: 1px solid #DCE5FB; font-size: .8rem; line-height: 1.45; }
.pmd-note i { color: #004CFF; margin-top: 2px; }
.pmd-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; font-size: .8rem; font-weight: 600; color: #4B5263; }
.pmd-field input, .pmd-field textarea { width: 100%; border: 1px solid #E7E9F0; border-radius: 10px; padding: 9px 11px; font: inherit; font-weight: 400; font-size: .88rem; color: #161B2B; background: #fff; resize: vertical; }
.pmd-field input:focus, .pmd-field textarea:focus { outline: none; border-color: #B9CCFF; box-shadow: 0 0 0 3px #EEF3FF; }
.pmd-field small { font-weight: 400; font-size: .72rem; color: #8A92A3; text-align: end; }
.pmd-text { margin: 0 0 6px; font-size: .88rem; line-height: 1.5; overflow-wrap: anywhere; }
.pmd-list { margin: 8px 0 12px; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 6px; font-size: .8rem; }
.pmd-list li { display: flex; gap: 8px; align-items: center; }
.pmd-error { margin: 0 0 10px; padding: 9px 11px; border-radius: 10px; background: #FEF3F2; color: #B42318; font-size: .8rem; }
.pmd-warn { margin: 0 0 10px; padding: 9px 11px; border-radius: 10px; background: #FFF6E5; color: #B45309; font-size: .8rem; }
.pmd-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 6px; }
.pmd-btn { display: inline-flex; align-items: center; gap: 7px; height: 38px; padding: 0 16px; border-radius: 10px; border: 1px solid transparent; font-size: .85rem; font-weight: 600; cursor: pointer; }
.pmd-btn.primary { background: #004CFF linear-gradient(105deg, #004CFF, #5235FF); color: #fff; }
.pmd-btn.danger { background: #DC2626; color: #fff; }
.pmd-btn.ghost { background: #fff; border-color: #E7E9F0; color: #161B2B; }
.pmd-btn:disabled { opacity: .6; cursor: not-allowed; }
</style>
