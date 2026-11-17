import type { Page } from '@playwright/test'

const DENY_TEXT = [
  'удал',
  'delete',
  'оплат',
  'payment',
  'publish',
  'публик',
  'выйти',
  'logout',
  'log out',
  'rewrite',
  'рерайт',
  'ре-райт',
  'parse',
  'парс',
  'generate',
  'генер',
  'cover',
  'облож',
  'buy',
  'purchase'
]

export type ClickReport = {
  clicked: string[]
  skipped: Array<{ label: string; reason: string }>
}

function isDenied(label: string) {
  const normalized = label.toLowerCase()
  return DENY_TEXT.some((token) => normalized.includes(token))
}

function isExternal(href: string | null, baseUrl: string) {
  if (!href) return false
  if (href.startsWith('/')) return false
  return !href.startsWith(baseUrl)
}

export async function clickSafeControls(page: Page): Promise<ClickReport> {
  const report: ClickReport = { clicked: [], skipped: [] }
  const baseUrl = page.url().split('/').slice(0, 3).join('/')
  const controls = page.locator('button, [role="button"], a')
  const total = await controls.count()
  const cap = Math.min(total, 40)

  for (let i = 0; i < cap; i++) {
    const el = controls.nth(i)
    if (!(await el.isVisible())) continue

    const role = await el.getAttribute('role')
    const href = await el.getAttribute('href')
    const ariaDisabled = await el.getAttribute('aria-disabled')
    const disabled = await el.getAttribute('disabled')
    const rawLabel = ((await el.innerText()) || '').trim().replace(/\s+/g, ' ')
    const label = rawLabel || `${role || 'control'}#${i + 1}`

    if (disabled !== null || ariaDisabled === 'true') {
      report.skipped.push({ label, reason: 'disabled' })
      continue
    }
    if (isDenied(label)) {
      report.skipped.push({ label, reason: 'denylist' })
      continue
    }
    if (isExternal(href, baseUrl)) {
      report.skipped.push({ label, reason: 'external-link' })
      continue
    }

    try {
      await el.click({ timeout: 2000 })
      report.clicked.push(label)
    } catch {
      report.skipped.push({ label, reason: 'click-failed' })
    }
  }

  return report
}
