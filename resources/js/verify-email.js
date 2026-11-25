// Email verification entry - loaded only on auth/verify-email (like register.js).
import Vue from 'vue';
import EmailVerification from './components/onboarding/EmailVerification.vue';

const el = document.getElementById('email-verification');
if (el) {
    const props = JSON.parse(el.dataset.props || '{}');
    new Vue({ render: (h) => h(EmailVerification, { props }) }).$mount(el);
}
