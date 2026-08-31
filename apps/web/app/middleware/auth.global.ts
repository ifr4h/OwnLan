export default defineNuxtRouteMiddleware(async (to) => {
  const { ready, isAuthenticated, fetchMe } = useAuth()

  if (!ready.value) {
    await fetchMe()
  }

  const isAuthRoute = to.path === '/login' || to.path === '/register'

  if (!isAuthenticated.value && !isAuthRoute && to.path !== '/') {
    return navigateTo('/login')
  }

  if (isAuthenticated.value && isAuthRoute) {
    return navigateTo('/today')
  }
})
