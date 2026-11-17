import { test as base, type Page } from '@playwright/test'
import { mkdir, writeFile } from 'node:fs/promises'
import { execSync } from 'node:child_process'
import path from 'node:path'

type BrowserLogBuckets = {
  console: string[]
  pageErrors: string[]
  requestFailures: string[]
}

function sanitizeName(value: string) {
  return value.replace(/[^a-zA-Z0-9._-]+/g, '_').slice(0, 180)
}

async function writeBrowserLogs(
  page: Page,
  testTitle: string,
  logsDir: string
) {
  const buckets: BrowserLogBuckets = {
    console: [],
    pageErrors: [],
    requestFailures: []
  }

  page.on('console', (message) => {
    buckets.console.push(`[${message.type()}] ${message.text()}`)
  })
  page.on('pageerror', (error) => {
    buckets.pageErrors.push(error.stack || error.message || String(error))
  })
  page.on('requestfailed', (request) => {
    const failure = request.failure()
    buckets.requestFailures.push(
      `${request.method()} ${request.url()} => ${failure?.errorText || 'unknown'}`
    )
  })

  return async () => {
    const file = path.join(logsDir, `${sanitizeName(testTitle)}.browser.log`)
    const content = [
      '# console',
      ...buckets.console,
      '',
      '# pageerror',
      ...buckets.pageErrors,
      '',
      '# requestfailed',
      ...buckets.requestFailures,
      ''
    ].join('\n')
    await writeFile(file, content, 'utf8')
  }
}

function collectDockerLogs(sinceIso: string) {
  try {
    const namesRaw = execSync(
      'docker ps --filter label=copybara.service --format "{{.Names}}"',
      { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }
    )
    const names = namesRaw.split('\n').map((n) => n.trim()).filter(Boolean)
    const result: Record<string, string> = {}

    for (const name of names) {
      try {
        result[name] = execSync(
          `docker logs --since "${sinceIso}" ${name} 2>&1`,
          { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }
        )
      } catch (error: unknown) {
        const err = error as { stdout?: string; stderr?: string; message?: string }
        result[name] = [err.stdout || '', err.stderr || '', err.message || '']
          .filter(Boolean)
          .join('\n')
      }
    }

    if (!names.length) {
      result.__meta__ = 'No running containers with label copybara.service were found.'
    }
    return result
  } catch {
    return {
      __meta__: 'Docker logs unavailable. Ensure docker CLI is installed and daemon is running.'
    }
  }
}

export const test = base.extend({
  page: async ({ page }, use, testInfo) => {
    const logsDir = path.join(process.cwd(), 'test-results', 'logs')
    await mkdir(logsDir, { recursive: true })

    const finalizeBrowserLogs = await writeBrowserLogs(page, testInfo.title, logsDir)
    const startedAt = new Date().toISOString()

    await use(page)

    await finalizeBrowserLogs()
    const dockerLogs = collectDockerLogs(startedAt)
    const serviceLogFile = path.join(
      logsDir,
      `${sanitizeName(testInfo.title)}.services.log`
    )

    const content = Object.entries(dockerLogs)
      .map(([name, chunk]) => `## ${name}\n${chunk || '(empty)'}\n`)
      .join('\n')
    await writeFile(serviceLogFile, content, 'utf8')
  }
})

export { expect } from '@playwright/test'
