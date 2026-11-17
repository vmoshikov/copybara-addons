import { expect, test } from './fixtures/logging'
import type { Page } from '@playwright/test'
import { mkdir, writeFile } from 'node:fs/promises'
import path from 'node:path'

const hasAuth = Boolean(process.env.E2E_EMAIL && process.env.E2E_PASSWORD)

const MATERIAL_URLS = [
  'https://en.wikipedia.org/wiki/Content_marketing',
  'https://en.wikipedia.org/wiki/Prompt_engineering',
  'https://developer.mozilla.org/en-US/docs/Web/JavaScript'
]

type ScenarioReport = {
  presetName: string
  presetId: string
  promptPreview: string
  sourceUrl: string
  rewritePostId: string
  titlePreview: string
  contentLength: number
  runAt: string
}

async function clickSelectOption(page: Page, label: string, optionText: string) {
  const field = page.locator('div').filter({ has: page.locator(`label:has-text("${label}")`) }).first()
  const trigger = field.locator('button, input').first()
  await trigger.click()
  await page.getByRole('option', { name: new RegExp(optionText, 'i') }).first().click()
}

async function createPresetAndReadPrompt(page: Page, presetName: string) {
  await page.goto('/dashboard/ai-editor/presets/text')
  await expect(page.getByRole('heading', { name: 'Создание пресета для текста' })).toBeVisible()

  await page.getByPlaceholder('Мой пресет').fill(presetName)
  await page.getByPlaceholder('Краткое описание пресета').fill('E2E smoke preset for parse+rewrite')
  await page.getByPlaceholder('Например: перепиши в научном стиле, сохрани технические детали')
    .fill('Пиши структурировано, без воды, с четкими подзаголовками')

  await page.getByText('Соцсети', { exact: true }).first().click()
  await page.getByLabel('Повествование в реальном времени').check()
  await page.getByLabel('Использовать emoji').check()
  await page.getByLabel('Поиск и проверка фактов').check()

  await clickSelectOption(page, 'Длина статьи', 'Короткая')
  await clickSelectOption(page, 'Тон голоса', 'Профессиональный')

  const saveResponsePromise = page.waitForResponse((res) =>
    res.url().includes('/api/presets') && res.request().method() === 'POST' && res.status() === 200
  )

  await page.getByRole('button', { name: /^Сохранить$/ }).click()
  const saveResponse = await saveResponsePromise
  const body = await saveResponse.json()

  await expect(page.getByText('Пресет создан')).toBeVisible({ timeout: 20_000 })

  return {
    presetId: String(body.id),
    systemPrompt: String(body.system_prompt || '')
  }
}

async function parseRandomMaterial(page: Page) {
  const candidates = [...MATERIAL_URLS].sort(() => Math.random() - 0.5)
  let lastError = ''

  for (const sourceUrl of candidates) {
    await page.goto('/dashboard/ai-editor')
    await page.getByPlaceholder('https://example.com/article').fill(sourceUrl)
    await page.getByLabel('Спарсить').first().click()

    try {
      await page.waitForURL(/\/dashboard\/ai-editor\/edit\//, { timeout: 90_000 })
      return sourceUrl
    } catch {
      lastError = `parse timeout for ${sourceUrl}`
    }
  }

  throw new Error(`Failed to parse random source: ${lastError}`)
}

async function runRewriteByPreset(page: Page, presetName: string) {
  const presetButton = page.locator('button').filter({ hasText: presetName }).first()
  await expect(presetButton).toBeVisible({ timeout: 30_000 })
  await presetButton.click()

  await expect(page.getByText('Copybara переписывает текст')).toBeVisible({ timeout: 30_000 })
  await expect(page.getByText('Текст статьи сгенерирован')).toBeVisible({ timeout: 180_000 })

  const titleValue = await page.locator('textarea[placeholder="Заголовок"], input[placeholder="Заголовок"]').first().inputValue().catch(() => '')
  const content = await page.locator('[data-testid="notion-editor-content"], .ProseMirror').first().innerText().catch(() => '')
  const rewritePostId = page.url().split('/').pop() || ''

  return {
    rewritePostId,
    titleValue: titleValue.trim(),
    contentLength: content.trim().length
  }
}

async function deletePreset(page: Page, presetName: string) {
  await page.goto('/dashboard/ai-editor/presets/text')
  const deleteButton = page.getByRole('button', { name: new RegExp(`Удалить\\s+${presetName}`) })
  await expect(deleteButton).toBeVisible({ timeout: 30_000 })
  page.once('dialog', async (dialog) => {
    await dialog.accept()
  })
  await deleteButton.click()
  await expect(page.getByText('Пресет удалён')).toBeVisible({ timeout: 20_000 })
}

async function saveScenarioReport(report: ScenarioReport) {
  const outDir = path.join(process.cwd(), 'test-results', 'reports')
  await mkdir(outDir, { recursive: true })
  const filename = path.join(outDir, `preset-rewrite-${Date.now()}.json`)
  await writeFile(filename, `${JSON.stringify(report, null, 2)}\n`, 'utf8')
}

test.describe('future deep scenarios', () => {
  test.skip(!hasAuth, 'E2E_EMAIL/E2E_PASSWORD are not configured')

  test('preset create -> prompt check -> parse random -> rewrite -> report -> delete preset', async ({ page }) => {
    const presetName = `e2e-preset-${Date.now()}`
    let presetId = ''

    try {
      const presetResult = await createPresetAndReadPrompt(page, presetName)
      presetId = presetResult.presetId

      expect(presetResult.systemPrompt).toContain('Перепиши следующий текст')
      expect(presetResult.systemPrompt.length).toBeGreaterThan(40)

      const sourceUrl = await parseRandomMaterial(page)
      const rewriteResult = await runRewriteByPreset(page, presetName)

      await saveScenarioReport({
        presetName,
        presetId,
        promptPreview: presetResult.systemPrompt.slice(0, 400),
        sourceUrl,
        rewritePostId: rewriteResult.rewritePostId,
        titlePreview: rewriteResult.titleValue.slice(0, 200),
        contentLength: rewriteResult.contentLength,
        runAt: new Date().toISOString()
      })
    } finally {
      if (presetId) {
        await deletePreset(page, presetName)
      }
    }
  })
})
