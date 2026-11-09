<template>
  <div class="auth-form-container reg-wizard">
    <div class="auth-form-header">
      <div class="auth-form-badge auth-form-badge-purple"><i class="bi bi-stars" aria-hidden="true"></i></div>
      <h2 id="auth-form-title">{{ t('title') }}</h2>
      <p>{{ t('subtitle') }}</p>
    </div>

    <ol class="reg-steps" :aria-label="t('steps.progress', { current: step, total: 2 })">
      <li v-for="(label, i) in stepLabels" :key="label"
          :class="{ 'is-active': step === i + 1, 'is-done': step > i + 1 }"
          :aria-current="step === i + 1 ? 'step' : null">
        <span class="reg-step-dot" aria-hidden="true">
          <i v-if="step > i + 1" class="bi bi-check-lg"></i><template v-else>{{ i + 1 }}</template>
        </span>
        <span class="reg-step-label">{{ label }}</span>
      </li>
    </ol>

    <div v-if="serverError" class="auth-alert auth-alert-danger" role="alert">
      <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
      <div><strong>{{ t('check_details') }}</strong><div>{{ serverError }}</div></div>
    </div>

    <form ref="form" method="POST" :action="action" class="auth-form" novalidate @submit="submit">
      <input type="hidden" name="_token" :value="csrf">
      <!-- Step 1's value travels with the step 2 submit. -->
      <input type="hidden" name="organization_name" :value="form.organization_name">

      <transition :name="transitionName" mode="out-in" @after-enter="focusStep">
        <div v-if="step === 1" key="organization" ref="step1" class="reg-step-panel">
          <h3 class="reg-step-heading">{{ t('organization.heading') }}</h3>
          <div class="auth-field">
            <label for="organization_name">{{ t('fields.organization_name') }}</label>
            <div class="auth-input-wrap" :class="{ 'is-invalid': errors.organization_name }">
              <i class="bi bi-buildings" aria-hidden="true"></i>
              <input id="organization_name" v-model="form.organization_name" type="text"
                     maxlength="150" autocomplete="organization" required
                     :placeholder="t('fields.organization_placeholder')"
                     :aria-invalid="errors.organization_name ? 'true' : 'false'"
                     aria-describedby="organization_name_help organization_name_error"
                     @blur="validate('organization_name')" @input="clear('organization_name')">
            </div>
            <p v-if="errors.organization_name" id="organization_name_error" class="reg-error" role="alert">{{ errors.organization_name }}</p>
            <p id="organization_name_help" class="auth-password-hint">{{ t('organization.help') }}</p>
          </div>
          <button type="button" class="auth-submit" @click="next">
            <span>{{ t('next') }}</span><i class="bi bi-arrow-right" aria-hidden="true"></i>
          </button>
        </div>

        <div v-else key="account" ref="step2" class="reg-step-panel">
          <h3 class="reg-step-heading">{{ t('account.heading') }}</h3>
          <p class="reg-step-help">{{ t('account.help', { organization: form.organization_name.trim() }) }}</p>

          <div class="auth-field">
            <label for="email">{{ t('fields.email') }}</label>
            <div class="auth-input-wrap" :class="{ 'is-invalid': errors.email }">
              <i class="bi bi-envelope" aria-hidden="true"></i>
              <input id="email" v-model="form.email" name="email" type="email" maxlength="255"
                     autocomplete="email" required placeholder="you@company.com"
                     :aria-invalid="errors.email ? 'true' : 'false'" aria-describedby="email_error"
                     @blur="validate('email')" @input="clear('email')">
            </div>
            <p v-if="errors.email" id="email_error" class="reg-error" role="alert">{{ errors.email }}</p>
          </div>

          <div v-for="field in passwordFields" :key="field" class="auth-field">
            <label :for="field">{{ t('fields.' + field) }}</label>
            <div class="auth-input-wrap" :class="{ 'is-invalid': errors[field] }">
              <i class="bi" :class="field === 'password' ? 'bi-lock' : 'bi-shield-check'" aria-hidden="true"></i>
              <input :id="field" v-model="form[field]" :name="field" :type="visible[field] ? 'text' : 'password'"
                     autocomplete="new-password" required :placeholder="t('fields.' + field + '_placeholder')"
                     :aria-invalid="errors[field] ? 'true' : 'false'" :aria-describedby="field + '_error'"
                     @blur="validate(field)" @input="clear(field)">
              <button type="button" class="auth-password-toggle" :aria-pressed="visible[field] ? 'true' : 'false'"
                      :aria-label="visible[field] ? t('hide_password') : t('show_password')"
                      @click="visible[field] = !visible[field]">
                <i class="bi" :class="visible[field] ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
              </button>
            </div>
            <p v-if="errors[field]" :id="field + '_error'" class="reg-error" role="alert">{{ errors[field] }}</p>
            <p v-else-if="field === 'password'" class="auth-password-hint">{{ t('password_hint') }}</p>
          </div>

          <div class="auth-terms">
            <label class="auth-checkbox auth-checkbox-top">
              <input v-model="form.terms" type="checkbox" name="terms" value="1"
                     :aria-invalid="errors.terms ? 'true' : 'false'" aria-describedby="terms_error" @change="clear('terms')">
              <span></span>
            </label>
            <div>
              {{ t('terms_prefix') }} <a :href="urls.terms" target="_blank" rel="noopener">{{ t('terms') }}</a>
              {{ t('and') }} <a :href="urls.privacy" target="_blank" rel="noopener">{{ t('privacy') }}</a>.
              <p v-if="errors.terms" id="terms_error" class="reg-error" role="alert">{{ errors.terms }}</p>
            </div>
          </div>

          <div class="reg-actions">
            <button type="button" class="reg-back" :disabled="submitting" @click="previous">
              <i class="bi bi-arrow-left" aria-hidden="true"></i><span>{{ t('previous') }}</span>
            </button>
            <button type="submit" class="auth-submit" :disabled="submitting" :aria-busy="submitting ? 'true' : 'false'">
              <span v-if="submitting" class="reg-spinner" aria-hidden="true"></span>
              <span>{{ submitting ? t('registering') : t('register') }}</span>
              <i v-if="!submitting" class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
          </div>
        </div>
      </transition>
    </form>

    <div class="auth-bottom-text">{{ t('have_account') }} <a :href="urls.login">{{ t('sign_in') }}</a></div>
    <div class="auth-legal">{{ t('legal') }}</div>
  </div>
</template>

<script>
// Client checks mirror RegisteredUserController::store for fast feedback;
// the server stays the authority and its errors are shown on the right step.
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const STEP_ONE = ['organization_name'];
const STEP_TWO = ['email', 'password', 'password_confirmation', 'terms'];

export default {
  name: 'RegisterWizard',
  props: {
    action: { type: String, required: true },
    csrf: { type: String, required: true },
    old: { type: Object, default: () => ({}) },
    serverErrors: { type: Object, default: () => ({}) },
    urls: { type: Object, required: true },
    messages: { type: Object, required: true },
  },
  data() {
    const errors = {};
    Object.keys(this.serverErrors).forEach((key) => { errors[key] = [].concat(this.serverErrors[key])[0]; });
    const hasStepTwoState = STEP_TWO.some((key) => errors[key]) || !!this.old.email || !!errors.registration;
    return {
      step: errors.organization_name || !this.old.organization_name || !hasStepTwoState ? 1 : 2,
      direction: 'forward',
      submitting: false,
      serverError: errors.registration || null,
      errors,
      visible: { password: false, password_confirmation: false },
      passwordFields: ['password', 'password_confirmation'],
      form: {
        organization_name: this.old.organization_name || '',
        email: this.old.email || '',
        password: '',
        password_confirmation: '',
        terms: !!this.old.terms,
      },
    };
  },
  computed: {
    stepLabels() { return [this.t('steps.organization'), this.t('steps.account')]; },
    transitionName() { return this.direction === 'forward' ? 'reg-forward' : 'reg-back'; },
  },
  methods: {
    t(key, params = {}) {
      const value = key.split('.').reduce((node, part) => (node && node[part] !== undefined ? node[part] : null), this.messages);
      return String(value ?? key).replace(/:(\w+)/g, (match, name) => (params[name] !== undefined ? params[name] : match));
    },
    rule(field) {
      const f = this.form;
      const v = this.t.bind(this);
      switch (field) {
        case 'organization_name': {
          const name = f.organization_name.trim();
          if (!name) return v('validation.organization_required');
          if (name.length < 2) return v('validation.organization_min');
          if (name.length > 150) return v('validation.organization_max');
          return /[<>]/.test(name) ? v('validation.organization_chars') : null;
        }
        case 'email':
          if (!f.email.trim()) return v('validation.email_required');
          return EMAIL.test(f.email.trim()) ? null : v('validation.email_invalid');
        case 'password':
          if (!f.password) return v('validation.password_required');
          return f.password.length < 8 ? v('validation.password_min') : null;
        case 'password_confirmation':
          return f.password_confirmation === f.password ? null : v('validation.password_mismatch');
        case 'terms':
          return f.terms ? null : v('validation.terms');
        default:
          return null;
      }
    },
    validate(field) {
      const message = this.rule(field);
      this.$set(this.errors, field, message);
      return !message;
    },
    validateAll(fields) {
      // Run every rule so all problems show at once.
      return fields.map((field) => this.validate(field)).every(Boolean);
    },
    clear(field) {
      if (this.errors[field]) this.$set(this.errors, field, null);
      this.serverError = null;
    },
    next() {
      if (!this.validateAll(STEP_ONE)) { this.focusFirstError(); return; }
      this.form.organization_name = this.form.organization_name.trim();
      this.direction = 'forward';
      this.step = 2;
    },
    previous() {
      this.direction = 'back';
      this.step = 1;
    },
    submit(event) {
      if (this.step === 1) { event.preventDefault(); this.next(); return; }
      if (this.submitting || !this.validateAll(STEP_ONE)) {
        event.preventDefault();
        if (!this.submitting) this.previous();
        return;
      }
      if (!this.validateAll(STEP_TWO)) { event.preventDefault(); this.focusFirstError(); return; }
      this.submitting = true; // native submit continues; button is disabled against double clicks
    },
    focusStep() {
      const panel = this.step === 1 ? this.$refs.step1 : this.$refs.step2;
      const target = panel && (panel.querySelector('[aria-invalid="true"]') || panel.querySelector('input'));
      if (target) target.focus();
    },
    focusFirstError() {
      this.$nextTick(() => {
        const target = this.$el.querySelector('input[aria-invalid="true"]');
        if (target) target.focus();
      });
    },
  },
  mounted() {
    // Land focus on the step that needs attention after a server round trip.
    if (Object.keys(this.serverErrors).length) this.$nextTick(this.focusStep);
  },
};
</script>
