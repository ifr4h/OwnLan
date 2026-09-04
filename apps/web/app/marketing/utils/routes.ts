export const PUBLIC_MARKETING_ROUTES = [
  '/',
  '/instructors',
  '/learners',
  '/features',
  '/pricing',
  '/about',
] as const

export function isPublicMarketingRoute(path: string): boolean {
  if (path === '/') return true
  return PUBLIC_MARKETING_ROUTES.some(
    route => route !== '/' && (path === route || path.startsWith(`${route}/`)),
  )
}

export const MARKETING_SITEMAP_PATHS = [
  '/',
  '/instructors',
  '/learners',
  '/features',
  '/features/diary',
  '/features/pupils',
  '/features/progress',
  '/features/teaching',
  '/features/money',
  '/pricing',
  '/about',
] as const
