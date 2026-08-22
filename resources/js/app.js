import './bootstrap';

import Alpine from 'alpinejs';
import Vue from 'vue';

import PostsDashboard from './components/posts/PostsDashboard.vue';
import PostPreview from './components/posts/PostPreview.vue';
import FacebookCampaignBuilder from './components/ads/FacebookCampaignBuilder.vue';

window.Alpine = Alpine;

Alpine.start();

Vue.component('posts-dashboard', PostsDashboard);
Vue.component('post-preview', PostPreview);
Vue.component('facebook-campaign-builder', FacebookCampaignBuilder);

new Vue({
    el: '#app',
});