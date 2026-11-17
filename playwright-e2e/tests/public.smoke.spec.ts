import { test, expect } from './fixtures/logging'
import { clickSafeControls } from './utils/clickSafeControls'

const publicRoutes = ['/', '/login', '/docs', '/docs/ai-editor']

for (const route of publicRoutes) {
  test(`public route smoke: ${route}`, async ({ page }) => {
    await page.goto(route)
    await expect(page.locator('body')).toBeVisible()

    const report = await clickSafeControls(page)
    test.info().annotations.push({
      type: 'safe-click-report',
      description: `clicked=${report.clicked.length}, skipped=${report.skipped.length}`
    })
  })
}
