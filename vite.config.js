import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue2'

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/apps.css',
                'resources/js/app.js',
                'resources/js/apps.js',
            ],
            refresh: true,
        }),

        vue(),
    ],

    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm.js',
        },
    },
})
