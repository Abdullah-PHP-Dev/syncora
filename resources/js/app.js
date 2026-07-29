import './bootstrap';

import Alpine from 'alpinejs';
import Vue from 'vue';

import PostsDashboard from './components/posts/PostsDashboard.vue';
import PostPreview from './components/posts/PostPreview.vue';

window.Alpine = Alpine;

Alpine.start();

Vue.component('posts-dashboard', PostsDashboard);
Vue.component('post-preview', PostPreview);

new Vue({
    el: '#app',
});