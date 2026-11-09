// Registration wizard entry - loaded only on auth/register so the marketing
// bundle (apps.js) stays Vue-free. The server-rendered fallback form inside
// the mount point is replaced once Vue mounts.
import Vue from 'vue';
import RegisterWizard from './components/onboarding/RegisterWizard.vue';

const el = document.getElementById('register-wizard');
if (el) {
    const props = JSON.parse(el.dataset.props || '{}');
    new Vue({ render: (h) => h(RegisterWizard, { props }) }).$mount(el);
}
