export type PortalNavIcon =
  | 'home'
  | 'lessons'
  | 'progress'
  | 'routes'
  | 'learn'
  | 'money'
  | 'practice'
  | 'test'
  | 'journey'
  | 'profile'

export type PortalNavItem = {
  to: string
  labelKey: string
  icon: PortalNavIcon
  match?: (path: string) => boolean
}

export const portalPrimaryNav: PortalNavItem[] = [
  { to: '/portal', labelKey: 'nav.home', icon: 'home', match: p => p === '/portal' },
  { to: '/portal/lessons', labelKey: 'nav.lessons', icon: 'lessons', match: p => p.startsWith('/portal/lessons') || p.startsWith('/portal/recap') },
  { to: '/portal/progress', labelKey: 'nav.progress', icon: 'progress', match: p => p.startsWith('/portal/progress') || p.startsWith('/portal/skills') },
  { to: '/portal/routes', labelKey: 'nav.routes', icon: 'routes', match: p => p.startsWith('/portal/routes') },
  { to: '/portal/learn', labelKey: 'nav.learn', icon: 'learn', match: p => p.startsWith('/portal/learn') },
  { to: '/portal/money', labelKey: 'nav.money', icon: 'money', match: p => p.startsWith('/portal/money') },
]

export const portalSecondaryNav: PortalNavItem[] = [
  { to: '/portal/practice', labelKey: 'nav.practice', icon: 'practice', match: p => p.startsWith('/portal/practice') },
  { to: '/portal/profile', labelKey: 'nav.profile', icon: 'profile', match: p => p.startsWith('/portal/profile') },
]

export const portalMobilePrimaryNav: PortalNavItem[] = [
  { to: '/portal', labelKey: 'nav.home', icon: 'home', match: p => p === '/portal' },
  { to: '/portal/lessons', labelKey: 'nav.lessons', icon: 'lessons', match: p => p.startsWith('/portal/lessons') || p.startsWith('/portal/recap') },
  { to: '/portal/progress', labelKey: 'nav.progress', icon: 'progress', match: p => p.startsWith('/portal/progress') || p.startsWith('/portal/skills') },
  { to: '/portal/learn', labelKey: 'nav.learn', icon: 'learn', match: p => p.startsWith('/portal/learn') },
]

export const portalMobileMoreNav: PortalNavItem[] = [
  { to: '/portal/routes', labelKey: 'nav.routes', icon: 'routes' },
  { to: '/portal/money', labelKey: 'nav.money', icon: 'money' },
  { to: '/portal/journey', labelKey: 'nav.journey', icon: 'journey' },
  { to: '/portal/practice', labelKey: 'nav.practice', icon: 'practice' },
  { to: '/portal/test', labelKey: 'nav.test', icon: 'test' },
  { to: '/portal/profile', labelKey: 'nav.profile', icon: 'profile' },
]

export function isPortalNavActive(item: PortalNavItem, path: string): boolean {
  if (item.match) return item.match(path)
  if (item.to === '/portal') return path === '/portal'
  return path === item.to || path.startsWith(`${item.to}/`)
}
