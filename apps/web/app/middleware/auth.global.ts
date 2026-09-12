import { isPublicMarketingRoute } from '~/marketing/utils/routes'
import { safeAppRedirect } from '~/composables/useApi'

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

  const { ready, isAuthenticated, fetchMe } = useAuth()
  const clientChecked = useState<boolean>('auth-client-checked', () => false)

  if (!ready.value) {
    await fetchMe()
  } else if (import.meta.client && !clientChecked.value) {
    // One client re-check: SSR can miss the session cookie; do not trust a
    // server-side "logged out" without verifying in the browser.
    clientChecked.value = true
    if (!isAuthenticated.value) {
      await fetchMe()
    }
  }

  if (isPublicMarketingRoute(to.path)) {
    if (isAuthenticated.value && to.path === '/') {
      return navigateTo('/today')
    }
    return
  }

  const isAuthRoute = to.path === '/login'
    || to.path === '/register'
    || to.path === '/forgot-password'
    || to.path === '/reset-password'

  if (isAuthenticated.value && isAuthRoute) {
    const redirect = safeAppRedirect(to.query.redirect)
    return navigateTo(redirect || '/today')
  }

  if (!isAuthenticated.value && !isAuthRoute) {
    return navigateTo({
      path: '/login',
      query: { redirect: to.fullPath },
    })
  }
})
