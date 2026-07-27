import './bootstrap';

import Alpine from 'alpinejs';
import Vue from 'vue';

import PostsDashboard from './components/posts/PostsDashboard.vue';

window.Alpine = Alpine;

Alpine.start();

Vue.component('posts-dashboard', PostsDashboard);

new Vue({
    el: '#app',
});