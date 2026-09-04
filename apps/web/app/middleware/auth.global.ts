import { isPublicMarketingRoute } from '~/marketing/utils/routes'

export default defineNuxtRouteMiddleware(async (to) => {
  if (to.path.startsWith('/portal') || to.path.startsWith('/join') || to.path.startsWith('/companion')) {
    return
  }

  // Guest payment links — scoped token only, no instructor session.
  if (to.path.startsWith('/pay/')) {
    return
  }

  // Public share links — token scoped.
  if (to.path.startsWith('/share/')) {
    return
  }

  // Public instructor profile pages (/instructors/{slug}) — not the instructor app.
  if (/^\/instructors\/[^/]+$/.test(to.path)) {
    return
  }

  if (isPublicMarketingRoute(to.path)) {
    const { ready, isAuthenticated, fetchMe } = useAuth()
    if (!ready.value) {
      await fetchMe()
    }
    if (isAuthenticated.value && to.path === '/') {
      return navigateTo('/today')
    }
    return
  }

  const { ready, isAuthenticated, fetchMe } = useAuth()

  if (!ready.value) {
    await fetchMe()
  }

  const isAuthRoute = to.path === '/login'
    || to.path === '/register'
    || to.path === '/forgot-password'
    || to.path === '/reset-password'

  if (isAuthenticated.value && isAuthRoute) {
    return navigateTo('/today')
  }

  if (!isAuthenticated.value && !isAuthRoute) {
    return navigateTo('/login')
  }
})
