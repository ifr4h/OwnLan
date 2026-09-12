export type ThemePreference = 'system' | 'light' | 'dark'
export type ResolvedTheme = 'light' | 'dark'

export const THEME_STORAGE_KEY = 'ownlane-theme'

const THEME_OPTIONS: Array<{ value: ThemePreference; label: string; hint: string }> = [
  {
    value: 'system',
    label: 'Match phone',
    hint: 'Follows your device light or dark setting.',
  },
  {
    value: 'light',
    label: 'Light',
    hint: 'Warm cream notebook.',
  },
  {
    value: 'dark',
    label: 'Dark',
    hint: 'Easier in low light.',
  },
]

function isThemePreference(value: unknown): value is ThemePreference {
  return value === 'system' || value === 'light' || value === 'dark'
}

function systemPrefersDark(): boolean {
  if (!import.meta.client) return false
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

function resolveTheme(preference: ThemePreference): ResolvedTheme {
  if (preference === 'light' || preference === 'dark') return preference
  return systemPrefersDark() ? 'dark' : 'light'
}

function readStoredPreference(): ThemePreference {
  if (!import.meta.client) return 'system'
  try {
    const raw = localStorage.getItem(THEME_STORAGE_KEY)
    return isThemePreference(raw) ? raw : 'system'
  } catch {
    return 'system'
  }
}

function writeStoredPreference(preference: ThemePreference) {
  if (!import.meta.client) return
  try {
    localStorage.setItem(THEME_STORAGE_KEY, preference)
  } catch {
    // Private mode / quota — preference still applies for this session.
  }
}

function applyDomTheme(resolved: ResolvedTheme) {
  if (!import.meta.client) return
  const root = document.documentElement
  root.setAttribute('data-theme', resolved)
  root.style.colorScheme = resolved

  const meta = document.querySelector('meta[name="theme-color"]')
  if (meta) {
    meta.setAttribute('content', resolved === 'dark' ? '#14110f' : '#168B55')
  }
}

export function useTheme() {
  const preference = useState<ThemePreference>('theme-preference', () => 'system')
  const resolved = useState<ResolvedTheme>('theme-resolved', () => 'light')
  const ready = useState<boolean>('theme-ready', () => false)

  function apply(next: ThemePreference) {
    preference.value = next
    const theme = resolveTheme(next)
    resolved.value = theme
    applyDomTheme(theme)
    writeStoredPreference(next)
  }

  function setPreference(next: ThemePreference) {
    apply(next)
  }

  function init() {
    if (!import.meta.client || ready.value) return

    apply(readStoredPreference())
    ready.value = true

    const media = window.matchMedia('(prefers-color-scheme: dark)')
    const onSystemChange = () => {
      if (preference.value === 'system') apply('system')
    }
    media.addEventListener('change', onSystemChange)
  }

  return {
    preference,
    resolved,
    ready,
    options: THEME_OPTIONS,
    setPreference,
    init,
  }
}
