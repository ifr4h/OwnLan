export type InstructorNavIcon =
  | 'today'
  | 'pupils'
  | 'diary'
  | 'accounts'
  | 'lesson'
  | 'services'
  | 'settings'

export type InstructorNavItem = {
  to: string
  label: string
  icon: InstructorNavIcon
  match?: (path: string) => boolean
}

/** Full desktop sidebar order — daily tools first, setup last */
export const instructorPrimaryNav: InstructorNavItem[] = [
  { to: '/today', label: 'Today', icon: 'today' },
  { to: '/pupils', label: 'Pupils', icon: 'pupils', match: p => p.startsWith('/pupils') },
  { to: '/lessons', label: 'Diary', icon: 'diary', match: p => p.startsWith('/lessons') || p.startsWith('/booking-requests') },
  { to: '/teaching', label: 'Teaching', icon: 'lesson', match: p => p.startsWith('/teaching') },
  { to: '/accounts', label: 'Accounts', icon: 'accounts', match: p => p.startsWith('/accounts') || p === '/money' },
  { to: '/services', label: 'Services', icon: 'services', match: p => p.startsWith('/services') },
]

/** Mobile bottom tabs — keep four daily areas + More */
export const instructorMobilePrimaryNav: InstructorNavItem[] = [
  { to: '/today', label: 'Today', icon: 'today' },
  { to: '/pupils', label: 'Pupils', icon: 'pupils', match: p => p.startsWith('/pupils') },
  { to: '/lessons', label: 'Diary', icon: 'diary', match: p => p.startsWith('/lessons') || p.startsWith('/booking-requests') },
  { to: '/accounts', label: 'Accounts', icon: 'accounts', match: p => p.startsWith('/accounts') || p === '/money' },
]

export const instructorMobileMoreNav: InstructorNavItem[] = [
  { to: '/teaching', label: 'Teaching', icon: 'lesson', match: p => p.startsWith('/teaching') },
  { to: '/services', label: 'Services', icon: 'services', match: p => p.startsWith('/services') },
  { to: '/settings', label: 'Settings', icon: 'settings', match: p => p.startsWith('/settings') },
]

export function isInstructorNavActive(item: InstructorNavItem, path: string): boolean {
  if (item.match) return item.match(path)
  if (item.to === '/today') return path === '/today'
  return path === item.to || path.startsWith(`${item.to}/`)
}
