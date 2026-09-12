// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  modules: ['@vite-pwa/nuxt'],

  css: [
    '~/assets/css/tokens.css',
    '~/assets/css/base.css',
    '~/assets/css/components.css',
    '~/assets/css/portal-layout.css',
    '~/assets/css/marketing.css',
  ],

  app: {
    head: {
      title: 'OwnLane',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'OwnLane — run your driving school. Keep your independence.' },
        { name: 'theme-color', content: '#168B55' },
        { name: 'mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-status-bar-style', content: 'default' },
        { name: 'apple-mobile-web-app-title', content: 'OwnLane' },
      ],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap',
        },
        { rel: 'icon', type: 'image/png', href: '/pwa-192.png' },
        { rel: 'apple-touch-icon', href: '/pwa-192.png' },
      ],
      // Blocking inline script — must run before first paint.
      script: [
        {
          key: 'ownlane-theme-init',
          tagPriority: 'critical',
          innerHTML:
            "(function(){try{var k='ownlane-theme';var p=localStorage.getItem(k)||'system';var d=p==='dark'||(p!=='light'&&window.matchMedia('(prefers-color-scheme: dark)').matches);var t=d?'dark':'light';document.documentElement.setAttribute('data-theme',t);document.documentElement.style.colorScheme=t;}catch(e){}})();",
        },
      ],
    },
  },

  runtimeConfig: {
    public: {
      appName: 'OwnLane',
      apiBase: '/api',
      appVersion: process.env.NUXT_PUBLIC_APP_VERSION || 'beta',
      feedbackEmail: process.env.NUXT_PUBLIC_FEEDBACK_EMAIL || 'feedback@ownlane.co.uk',
    },
  },

  // Same-origin /api/* → Yii2, so cookie auth stays simple.
  // API responses are never cached by the service worker — operational
  // offline data lives in IndexedDB instead.
  routeRules: {
    '/api/**': { proxy: 'http://127.0.0.1:8080/**' },
  },

  pwa: {
    registerType: 'autoUpdate',
    manifest: {
      name: 'OwnLane',
      short_name: 'OwnLane',
      description: 'Instructor day — lessons, pupils, and progress.',
      theme_color: '#168B55',
      background_color: '#fffefb',
      display: 'standalone',
      orientation: 'portrait-primary',
      start_url: '/today',
      lang: 'en-GB',
      categories: ['business', 'education'],
      icons: [
        {
          src: '/pwa-192.png',
          sizes: '192x192',
          type: 'image/png',
        },
        {
          src: '/pwa-512.png',
          sizes: '512x512',
          type: 'image/png',
        },
        {
          src: '/pwa-512.png',
          sizes: '512x512',
          type: 'image/png',
          purpose: 'maskable',
        },
      ],
    },
    workbox: {
      navigateFallback: '/',
      globPatterns: ['**/*.{js,css,html,png,svg,ico,woff2}'],
      // Avoid workbox+terser early-exit flakiness in CI/sandbox builds.
      mode: 'development',
      runtimeCaching: [
        {
          urlPattern: ({ url }) => url.pathname.startsWith('/api/'),
          handler: 'NetworkOnly',
        },
        {
          urlPattern: ({ url }) => url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com',
          handler: 'CacheFirst',
          options: {
            cacheName: 'ownlane-fonts',
            expiration: {
              maxEntries: 12,
              maxAgeSeconds: 60 * 60 * 24 * 365,
            },
          },
        },
      ],
    },
    client: {
      installPrompt: true,
      periodicSyncForUpdates: 3600,
    },
    // Keep SW off in `nuxt dev` — vite-plugin-pwa often races and leaves
    // `.nuxt/dev-sw-dist/sw.js` missing (ENOENT / /dev-sw.js 404s).
    // Production builds still generate the full PWA. Offline teaching data
    // uses IndexedDB and does not depend on the SW in development.
    devOptions: {
      enabled: false,
    },
  },

  typescript: {
    strict: true,
    typeCheck: false,
  },
})
