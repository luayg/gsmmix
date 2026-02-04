import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [
    laravel({
      input: [
        // Default Laravel assets
        'resources/css/app.css',
        'resources/js/app.js',

        // Admin panel assets
        'resources/css/bundle.css',
        'resources/css/admin-theme.css',
        'resources/css/admin.css',
        'resources/js/admin.js',

        // ✅ Orders IMEI isolated entry (لا يعتمد على admin.js)
        'resources/js/orders-imei-edit.js',
      ],
      refresh: true,
    }),
    tailwindcss(),
  ],
  server: {
    watch: {
      ignored: ['**/storage/framework/views/**'],
    },
  },
});
