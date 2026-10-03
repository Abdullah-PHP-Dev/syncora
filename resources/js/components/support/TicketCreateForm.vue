<template>

  <div class="tc">

    <!-- Header -->
    <div class="tc-head">
      <a :href="indexUrl" class="tc-back"><i class="bx bx-arrow-back"></i> Support tickets</a>
      <div class="tc-head-title">
        <div class="tc-head-icon"><i class="bx bx-support"></i></div>
        <div>
          <h4>New support ticket</h4>
          <p>Tell us what's going on and our support team will get back to you here.</p>
        </div>
      </div>
    </div>

    <div class="tc-grid">

      <!-- Form -->
      <form class="tc-card" @submit.prevent="submit" novalidate>

        <div class="tc-section">
          <label class="tc-label" for="tc-subject">Subject <span>*</span></label>
          <input id="tc-subject" type="text" v-model="form.subject" class="tc-input" :class="{ 'is-invalid': errors.subject }"
                 placeholder="e.g. My Instagram posts are not publishing" maxlength="200" required autocomplete="off">
          <div class="tc-hint-row">
            <span v-if="errors.subject" class="tc-error">{{ errors.subject[0] }}</span>
            <span v-else class="tc-hint">A short summary of the problem.</span>
            <span class="tc-count">{{ form.subject.length }}/200</span>
          </div>
        </div>

        <div class="tc-section">
          <div class="tc-label">Category</div>
          <div class="tc-categories" role="radiogroup" aria-label="Category">
            <button v-for="c in categories" :key="c.value" type="button" role="radio" :aria-checked="form.category === c.value"
                    class="tc-category" :class="{ 'is-active': form.category === c.value }" @click="form.category = c.value">
              <span class="tc-category-icon"><i class="bx" :class="c.icon"></i></span>
              <span class="tc-category-name">{{ c.value }}</span>
              <i class="bx bxs-check-circle tc-check"></i>
            </button>
          </div>
          <span v-if="errors.category" class="tc-error">{{ errors.category[0] }}</span>
        </div>

        <div class="tc-section">
          <div class="tc-label">Priority <span>*</span></div>
          <div class="tc-priorities" role="radiogroup" aria-label="Priority">
            <button v-for="p in priorities" :key="p.value" type="button" role="radio" :aria-checked="form.priority === p.value"
                    class="tc-priority" :class="['is-' + p.value, { 'is-active': form.priority === p.value }]" @click="form.priority = p.value">
              <span class="tc-priority-name"><span class="tc-dot"></span>{{ p.label }}</span>
              <span class="tc-priority-desc">{{ p.desc }}</span>
            </button>
          </div>
          <span v-if="errors.priority" class="tc-error">{{ errors.priority[0] }}</span>
        </div>

        <div class="tc-section">
          <label class="tc-label" for="tc-body">Describe the issue <span>*</span></label>
          <textarea id="tc-body" v-model="form.body" class="tc-input tc-textarea" :class="{ 'is-invalid': errors.body }" rows="7" required
                    placeholder="What were you trying to do, what happened instead, and when did it start? Include the account or page involved and any error message you saw."></textarea>
          <div class="tc-hint-row">
            <span v-if="errors.body" class="tc-error">{{ errors.body[0] }}</span>
            <span v-else class="tc-hint">The more detail you share, the faster we can help.</span>
            <span class="tc-count">{{ form.body.length }} characters</span>
          </div>
        </div>

        <div v-if="generalError" class="tc-alert"><i class="bx bx-error-circle"></i> {{ generalError }}</div>

        <div class="tc-actions">
          <a :href="indexUrl" class="tc-btn tc-btn-ghost">Cancel</a>
          <button type="submit" class="tc-btn tc-btn-brand" :disabled="submitting">
            <span v-if="submitting" class="spinner-border spinner-border-sm"></span>
            <i v-else class="bx bx-send"></i>
            {{ submitting ? 'Submitting…' : 'Submit ticket' }}
          </button>
        </div>
      </form>

      <!-- Side panel -->
      <aside class="tc-side">
        <div class="tc-side-card tc-help">
          <div class="tc-help-icon"><i class="bx bx-book-open"></i></div>
          <h6>Looking for a quick answer?</h6>
          <p>Many common questions are already answered in the Help Center.</p>
          <a :href="helpCenterUrl" class="tc-btn tc-btn-outline w-100"><i class="bx bx-search"></i> Search the Help Center</a>
        </div>

        <div class="tc-side-card">
          <h6>What happens next</h6>
          <ol class="tc-steps">
            <li><span>1</span><div><strong>Ticket created</strong>You get a ticket number to track it.</div></li>
            <li><span>2</span><div><strong>Our team reviews it</strong>Tickets are handled in order of priority.</div></li>
            <li><span>3</span><div><strong>Reply in this thread</strong>Follow up and chat with the agent on the ticket page.</div></li>
          </ol>
        </div>

        <div class="tc-side-card">
          <h6>Tips for a faster resolution</h6>
          <ul class="tc-tips">
            <li><i class="bx bx-check"></i> Name the social account, page or campaign involved.</li>
            <li><i class="bx bx-check"></i> Copy the exact error message, if there is one.</li>
            <li><i class="bx bx-check"></i> List the steps that lead to the problem.</li>
            <li><i class="bx bx-shield-quarter"></i> Never share passwords or access tokens.</li>
          </ul>
        </div>
      </aside>

    </div>
  </div>

</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  storeUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
  helpCenterUrl: { type: String, required: true },
  // Prefilled from the Help Center's "Ask AI" low-confidence escalation
  // link (see HelpCenterBrowser.vue) - both default to '' so arriving
  // here directly still shows the exact same empty form as before.
  initialSubject: { type: String, default: '' },
  initialBody: { type: String, default: '' },
});

// Values are stored as-is on the ticket (tickets.category).
const categories = [
  { value: 'Account & Billing', icon: 'bx-credit-card' },
  { value: 'Publishing & Posting', icon: 'bx-calendar-edit' },
  { value: 'Integrations & API', icon: 'bx-plug' },
  { value: 'Bug Report', icon: 'bx-bug' },
  { value: 'Other', icon: 'bx-dots-horizontal-rounded' },
];

const priorities = [
  { value: 'low', label: 'Low', desc: 'General question' },
  { value: 'medium', label: 'Medium', desc: 'Something isn\'t working right' },
  { value: 'high', label: 'High', desc: 'A key feature is blocked' },
  { value: 'urgent', label: 'Urgent', desc: 'Business is stopped' },
];

const form = ref({ subject: props.initialSubject, category: 'Account & Billing', priority: 'medium', body: props.initialBody });
const errors = ref({});
const generalError = ref('');
const submitting = ref(false);

const canSubmit = computed(() => form.value.subject.trim() !== '' && form.value.body.trim() !== '');

async function submit() {
  if (!canSubmit.value) {
    errors.value = {
      ...(form.value.subject.trim() ? {} : { subject: ['Please add a subject.'] }),
      ...(form.value.body.trim() ? {} : { body: ['Please describe the issue.'] }),
    };
    return;
  }

  submitting.value = true;
  errors.value = {};
  generalError.value = '';

  try {
    const { data } = await window.axios.post(props.storeUrl, form.value);
    window.location.href = data.redirect_url;
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      generalError.value = error.response?.data?.message || 'Failed to submit ticket. Please try again.';
    }
    submitting.value = false;
  }
}
</script>

<style scoped>
.tc { --ln: #e7e9f0; --ln-soft: #f1f3f7; --ink: #161b2b; --ink2: #545d70; --muted: #8a92a3; --brand: #6d4aff; --brand-2: #8f6bff; --brand-soft: #f2eeff; --danger: #d92d20; color: var(--ink2); max-width: 1160px; margin: 0 auto; }

/* Header */
.tc-head { margin-bottom: 20px; }
.tc-back { display: inline-flex; align-items: center; gap: 4px; font-size: .8rem; font-weight: 600; color: var(--muted); text-decoration: none; margin-bottom: 12px; }
.tc-back:hover { color: var(--brand); }
[dir="rtl"] .tc-back i { transform: scaleX(-1); }
.tc-head-title { display: flex; gap: 14px; align-items: center; }
.tc-head-icon { width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; font-size: 24px; color: #fff; background: linear-gradient(135deg, var(--brand), var(--brand-2)); box-shadow: 0 8px 18px rgba(109, 74, 255, .28); flex-shrink: 0; }
.tc-head h4 { color: var(--ink); font-weight: 700; font-size: 1.35rem; letter-spacing: -.01em; margin: 0 0 3px; }
.tc-head p { margin: 0; font-size: .87rem; }

/* Layout */
.tc-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 20px; align-items: start; }
.tc-card { background: #fff; border: 1px solid var(--ln); border-radius: 16px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 12px 32px rgba(16, 24, 40, .04); padding: 8px 24px 24px; }
.tc-section { padding: 18px 0; border-bottom: 1px solid var(--ln-soft); }
.tc-section:last-of-type { border-bottom: none; }

.tc-label { display: block; font-size: .84rem; font-weight: 700; color: var(--ink); margin-bottom: 8px; }
.tc-label span { color: var(--danger); }
.tc-input { width: 100%; border: 1px solid var(--ln); border-radius: 10px; padding: 10px 13px; font-size: .9rem; color: var(--ink); background: #fff; outline: none; transition: border-color .15s, box-shadow .15s; }
.tc-input::placeholder { color: #a5abb8; }
.tc-input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
.tc-input.is-invalid { border-color: #fda29b; }
.tc-input.is-invalid:focus { box-shadow: 0 0 0 3px rgba(217, 45, 32, .12); }
.tc-textarea { resize: vertical; min-height: 160px; line-height: 1.55; }
.tc-hint-row { display: flex; justify-content: space-between; gap: 12px; margin-top: 6px; font-size: .76rem; }
.tc-hint { color: var(--muted); }
.tc-count { color: var(--muted); white-space: nowrap; font-variant-numeric: tabular-nums; }
.tc-error { color: var(--danger); font-weight: 500; font-size: .76rem; display: block; }

/* Category tiles */
.tc-categories { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; }
.tc-category { position: relative; display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px 8px 12px; border: 1px solid var(--ln); border-radius: 12px; background: #fff; text-align: center; transition: border-color .15s, box-shadow .15s, background .15s; }
.tc-category:hover { border-color: #cfd4de; }
.tc-category-icon { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; font-size: 20px; background: var(--ln-soft); color: var(--ink2); transition: background .15s, color .15s; }
.tc-category-name { font-size: .76rem; font-weight: 600; color: var(--ink); line-height: 1.3; }
.tc-check { position: absolute; top: 7px; inset-inline-end: 7px; font-size: 1rem; color: var(--brand); opacity: 0; transition: opacity .15s; }
.tc-category.is-active { border-color: var(--brand); background: #fbfaff; box-shadow: 0 0 0 3px rgba(109, 74, 255, .1); }
.tc-category.is-active .tc-category-icon { background: var(--brand); color: #fff; }
.tc-category.is-active .tc-check { opacity: 1; }

/* Priority options */
.tc-priorities { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.tc-priority { --pc: #079455; display: flex; flex-direction: column; align-items: flex-start; gap: 3px; padding: 11px 12px; border: 1px solid var(--ln); border-radius: 12px; background: #fff; text-align: start; transition: border-color .15s, box-shadow .15s, background .15s; }
.tc-priority.is-medium { --pc: #b54708; }
.tc-priority.is-high { --pc: #e04f16; }
.tc-priority.is-urgent { --pc: #d92d20; }
.tc-priority:hover { border-color: #cfd4de; }
.tc-priority-name { display: inline-flex; align-items: center; gap: 7px; font-size: .84rem; font-weight: 700; color: var(--ink); }
.tc-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--pc); }
.tc-priority-desc { font-size: .73rem; color: var(--muted); line-height: 1.35; }
.tc-priority.is-active { border-color: var(--pc); background: color-mix(in srgb, var(--pc) 6%, #fff); box-shadow: 0 0 0 3px color-mix(in srgb, var(--pc) 14%, transparent); }

/* Actions */
.tc-alert { display: flex; align-items: center; gap: 8px; margin-top: 4px; padding: 10px 14px; border-radius: 10px; background: #fef3f2; border: 1px solid #fecdca; color: var(--danger); font-size: .84rem; font-weight: 500; }
.tc-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 18px; border-top: 1px solid var(--ln-soft); }
.tc-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; height: 42px; padding: 0 18px; border-radius: 10px; font-size: .86rem; font-weight: 600; border: 1px solid transparent; text-decoration: none; cursor: pointer; transition: background .15s, border-color .15s, box-shadow .15s, transform .15s, opacity .15s; }
.tc-btn i { font-size: 1.1rem; }
.tc-btn-brand { background: linear-gradient(135deg, var(--brand), var(--brand-2)); color: #fff; box-shadow: 0 4px 12px rgba(109, 74, 255, .25); min-width: 160px; }
.tc-btn-brand:hover:not(:disabled) { color: #fff; box-shadow: 0 6px 16px rgba(109, 74, 255, .35); transform: translateY(-1px); }
.tc-btn-brand:disabled { opacity: .55; cursor: not-allowed; box-shadow: none; }
.tc-btn-ghost { background: transparent; color: var(--ink2); }
.tc-btn-ghost:hover { background: var(--ln-soft); color: var(--ink); }
.tc-btn-outline { background: #fff; border-color: var(--ln); color: var(--ink); }
.tc-btn-outline:hover { background: #fafbfd; border-color: #cfd4de; color: var(--ink); }

/* Side panel */
.tc-side { display: flex; flex-direction: column; gap: 14px; position: sticky; top: 90px; }
.tc-side-card { background: #fff; border: 1px solid var(--ln); border-radius: 16px; padding: 18px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
.tc-side-card h6 { color: var(--ink); font-weight: 700; font-size: .9rem; margin-bottom: 12px; }
.tc-help { background: linear-gradient(180deg, #f8f6ff 0%, #fff 100%); border-color: #e4dcff; }
.tc-help h6 { margin-bottom: 4px; }
.tc-help p { font-size: .8rem; margin-bottom: 14px; }
.tc-help-icon { width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center; font-size: 20px; color: var(--brand); background: var(--brand-soft); margin-bottom: 12px; }

.tc-steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
.tc-steps li { display: flex; gap: 12px; font-size: .79rem; line-height: 1.45; color: var(--muted); }
.tc-steps li > span { width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; font-size: .72rem; font-weight: 700; color: var(--brand); background: var(--brand-soft); }
.tc-steps strong { display: block; color: var(--ink); font-size: .82rem; }

.tc-tips { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 9px; }
.tc-tips li { display: flex; gap: 8px; font-size: .79rem; line-height: 1.45; }
.tc-tips i { color: #079455; font-size: 1.05rem; flex-shrink: 0; margin-top: 1px; }
.tc-tips li:last-child i { color: var(--brand); }

@media (max-width: 1199.98px) {
  .tc-categories { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .tc-priorities { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 991.98px) {
  .tc-grid { grid-template-columns: 1fr; }
  .tc-side { position: static; }
}
@media (max-width: 575.98px) {
  .tc-card { padding: 4px 16px 16px; }
  .tc-categories { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .tc-actions { flex-direction: column-reverse; }
  .tc-btn-brand { width: 100%; }
}
</style>
