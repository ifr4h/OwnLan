import { chromium } from 'playwright-core'
import { mkdirSync } from 'fs'
import { join } from 'path'

const OUT = '/Users/ifrahvermeer/Documents/OwnLane/.tmp/darkmode-shots'
mkdirSync(OUT, { recursive: true })

const BASE = 'http://127.0.0.1:3000'

const pages = [
  { path: '/today', name: '01-today' },
  { path: '/pupils', name: '02-pupils' },
  { path: '/teaching', name: '03-teaching' },
  { path: '/lessons', name: '04-diary' },
  { path: '/services', name: '05-services' },
  { path: '/accounts', name: '06-accounts' },
  { path: '/accounts/payments', name: '07-payments' },
  { path: '/accounts/expenses', name: '08-expenses' },
  { path: '/accounts/reports', name: '09-reports' },
  { path: '/accounts/vehicles', name: '10-vehicles' },
  { path: '/settings', name: '11-settings' },
  { path: '/settings/profile', name: '12-settings-profile' },
  { path: '/settings/data', name: '13-settings-data' },
  { path: '/money', name: '14-money' },
  { path: '/learners', name: '15-learners' },
]

async function shot(page, name) {
  await page.waitForTimeout(800)
  await page.screenshot({ path: join(OUT, `${name}.png`), fullPage: true })
  console.log('shot', name)
}

const browser = await chromium.launch({
  channel: 'chrome',
  headless: true,
})
const context = await browser.newContext({
  viewport: { width: 1280, height: 900 },
  colorScheme: 'dark',
})
const page = await context.newPage()

page.on('console', (msg) => {
  if (msg.type() === 'error') console.log('console.error', msg.text())
})

await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' })
await page.fill('input[autocomplete="username"]', 'instructor')
await page.fill('input[autocomplete="current-password"]', 'instructor')
await page.click('button[type="submit"]')
await page.waitForURL(/\/(today|onboarding)/, { timeout: 15000 })
console.log('logged in', page.url())

// Force dark via localStorage + reload
await page.evaluate(() => {
  localStorage.setItem('ownlane-theme', 'dark')
  document.documentElement.setAttribute('data-theme', 'dark')
  document.documentElement.style.colorScheme = 'dark'
})
await page.reload({ waitUntil: 'networkidle' })
await shot(page, '00-today-after-dark')

for (const item of pages) {
  try {
    await page.goto(`${BASE}${item.path}`, { waitUntil: 'networkidle', timeout: 20000 })
    // ensure dark still applied
    await page.evaluate(() => {
      localStorage.setItem('ownlane-theme', 'dark')
      document.documentElement.setAttribute('data-theme', 'dark')
      document.documentElement.style.colorScheme = 'dark'
    })
    await shot(page, item.name)

    // Diary: open first lesson if present
    if (item.path === '/lessons') {
      const lesson = page.locator('.cal-lesson, .diary-lesson, [data-lesson], .appointment').first()
      if (await lesson.count()) {
        await lesson.click({ timeout: 3000 }).catch(() => {})
        await page.waitForTimeout(1000)
        await shot(page, '04b-diary-lesson-open')
      }
      // week/day toggles if any
      const dayBtn = page.getByRole('button', { name: /Day/i }).first()
      if (await dayBtn.count()) {
        await dayBtn.click().catch(() => {})
        await page.waitForTimeout(600)
        await shot(page, '04c-diary-day')
      }
    }

    // Pupils: open first pupil
    if (item.path === '/pupils') {
      const link = page.locator('a[href^="/pupils/"]').first()
      if (await link.count()) {
        await link.click()
        await page.waitForLoadState('networkidle')
        await page.evaluate(() => {
          document.documentElement.setAttribute('data-theme', 'dark')
        })
        await shot(page, '02b-pupil-detail')
      }
    }
  } catch (e) {
    console.log('FAIL', item.name, e.message)
    await shot(page, `${item.name}-error`)
  }
}

await browser.close()
console.log('done', OUT)
