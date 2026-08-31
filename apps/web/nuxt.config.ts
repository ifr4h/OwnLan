// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  css: [
    '~/assets/css/tokens.css',
    '~/assets/css/base.css',
  ],

  app: {
    head: {
      title: 'OwnLane',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'OwnLane — run your driving school. Keep your independence.' },
      ],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400&family=Manrope:wght@400&display=swap',
        },
      ],
    },
  },

  runtimeConfig: {
    public: {
      appName: 'OwnLane',
      apiBase: '/api',
    },
  },

  // Same-origin /api/* → Yii2, so cookie auth in step 2 stays simple.
  routeRules: {
    '/api/**': { proxy: 'http://127.0.0.1:8080/**' },
  },

  typescript: {
    strict: true,
    typeCheck: false,
  },
})
