import teachingEnGB from '~/locales/en-GB/teaching'

type Dict = Record<string, unknown>

const locales: Record<string, Dict> = {
  'en-GB': teachingEnGB as unknown as Dict,
}

function getByPath(obj: Dict, path: string): unknown {
  const parts = path.split('.')
  let cur: unknown = obj
  for (const part of parts) {
    if (cur === null || cur === undefined || typeof cur !== 'object') return undefined
    cur = (cur as Dict)[part]
  }
  return cur
}

function interpolate(template: string, vars?: Record<string, string | number>): string {
  if (!vars) return template
  return template.replace(/\{(\w+)\}/g, (_, key: string) => {
    const v = vars[key]
    return v === undefined || v === null ? `{${key}}` : String(v)
  })
}

export function useTeachingI18n(locale = 'en-GB') {
  const messages = computed(() => locales[locale] ?? locales['en-GB'])

  function t(path: string, vars?: Record<string, string | number>): string {
    const value = getByPath(messages.value, path)
    if (typeof value === 'string') {
      return interpolate(value, vars)
    }
    return path
  }

  return { t, locale, messages }
}
