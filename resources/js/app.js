import './bootstrap';

import Alpine from 'alpinejs';
import Vue from 'vue';

import PostsDashboard from './components/posts/PostsDashboard.vue';
import PostPreview from './components/posts/PostPreview.vue';
import PostComposer from './components/posts/composer/PostComposer.vue';
import SubscriptionPlans from './components/subscription/SubscriptionPlans.vue';
import CheckoutData from './components/subscription/CheckoutData.vue';
import NotificationCenter from './components/notifications/NotificationCenter.vue';
import FaqManager from './components/support/FaqManager.vue';
import HelpCenterBrowser from './components/support/HelpCenterBrowser.vue';
import TicketsList from './components/support/TicketsList.vue';
import TicketCreateForm from './components/support/TicketCreateForm.vue';
import TicketThread from './components/support/TicketThread.vue';
import CopilotFindAnswer from './components/support/CopilotFindAnswer.vue';

window.Alpine = Alpine;
// Needed so admin/chats/dashboard.blade.php's plain inline <script> (a
// legacy jQuery file, not an ES module) can do `new Vue(...)` itself to
// mount copilot-find-answer fresh on every conversation switch - without
// this, Vue is only a module-local import here and that inline script
// would throw "Vue is not defined". Same pattern as window.axios below.
window.Vue = Vue;

Alpine.start();

Vue.component('posts-dashboard', PostsDashboard);
Vue.component('post-preview', PostPreview);
Vue.component('post-composer', PostComposer);
Vue.component('subscription-plans', SubscriptionPlans);
Vue.component('subscription-checkout', CheckoutData);
Vue.component('notification-center', NotificationCenter);
Vue.component('faq-manager', FaqManager);
Vue.component('help-center-browser', HelpCenterBrowser);
Vue.component('tickets-list', TicketsList);
Vue.component('ticket-create-form', TicketCreateForm);
Vue.component('ticket-thread', TicketThread);
Vue.component('copilot-find-answer', CopilotFindAnswer);

new Vue({
    el: '#app',
});

// Separate root: the navbar (and this notification center inside it)
// renders outside #app's DOM subtree - see layouts/app.blade.php, the
// navbar include comes before the <div id="app"> that wraps @yield('content').
// A tag placed in the navbar would never be compiled by the root above.
if (document.getElementById('notification-center-root')) {
    new Vue({
        el: '#notification-center-root',
    });
}