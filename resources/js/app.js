import './bootstrap';

import Alpine from 'alpinejs';
import Vue from 'vue';

import PostsDashboard from './components/posts/PostsDashboard.vue';
import PostPreview from './components/posts/PostPreview.vue';
import SubscriptionPlans from './components/subscription/SubscriptionPlans.vue';
import CheckoutData from './components/subscription/CheckoutData.vue';
import NotificationCenter from './components/notifications/NotificationCenter.vue';

window.Alpine = Alpine;

Alpine.start();

Vue.component('posts-dashboard', PostsDashboard);
Vue.component('post-preview', PostPreview);
Vue.component('subscription-plans', SubscriptionPlans);
Vue.component('subscription-checkout', CheckoutData);
Vue.component('notification-center', NotificationCenter);

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