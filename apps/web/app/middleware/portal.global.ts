export default defineNuxtRouteMiddleware(async (to) => {
  if (!to.path.startsWith('/portal')) {
    return
  }

  const { ready, isAuthenticated, fetchMe } = usePortalAuth()
  if (!ready.value) {
    await fetchMe()
  }

  const isPublic
    = to.path === '/portal/login'
      || to.path === '/portal/join'
      || to.path === '/portal/forgot-password'
      || to.path === '/portal/reset-password'

  if (isAuthenticated.value && isPublic) {
    return navigateTo('/portal')
  }

  if (!isAuthenticated.value && !isPublic) {
    return navigateTo({
      path: '/login',
      query: { redirect: to.fullPath },
    })
  }
})
