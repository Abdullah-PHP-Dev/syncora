import './bootstrap';

import Alpine from 'alpinejs';
import Vue from 'vue';

import PostsDashboard from './components/posts/PostsDashboard.vue';
import PostPreview from './components/posts/PostPreview.vue';
import SubscriptionPlans from './components/subscription/SubscriptionPlans.vue';
import CheckoutData from './components/subscription/CheckoutData.vue';

window.Alpine = Alpine;

Alpine.start();

Vue.component('posts-dashboard', PostsDashboard);
Vue.component('post-preview', PostPreview);
Vue.component('subscription-plans', SubscriptionPlans);
Vue.component('subscription-checkout', CheckoutData);

new Vue({
    el: '#app',
});