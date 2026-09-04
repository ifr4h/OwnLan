/**
 * Broad Companion UI — DEFERRED / NOT CURRENTLY EXPOSED.
 * See docs/17-companion-access-decision.md.
 */
export default defineNuxtRouteMiddleware((to) => {
  if (!to.path.startsWith('/companion')) {
    return
  }

  return navigateTo('/login')
})
