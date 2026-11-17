# Copybara Playwright E2E (addons)

Standalone Playwright suite for UI smoke checks and service log collection.

This project lives in `components/addons/playwright-e2e` and is intentionally
not connected to production deploy stacks.

## What it covers

- Public UI smoke: `/`, `/login`, `/docs`, `/docs/ai-editor`
- Authenticated smoke: key dashboard routes
- Safe click pass over visible controls (with denylist)
- Browser logs (`console`, `pageerror`, failed requests)
- Docker service logs (`docker logs --since`) into test artifacts

## Prerequisites

- Running local Copybara stack (for example via root `docker-compose.yml`)
- Node.js 20+
- Docker CLI available locally for service log capture (optional)

## Setup

```bash
cd components/addons/playwright-e2e
cp .env.example .env
npm install
npx playwright install chromium
```

Configure `.env`:

- `E2E_BASE_URL` - default `http://localhost:3010`
- `E2E_EMAIL` and `E2E_PASSWORD` - required only for authenticated smoke

## Run

```bash
npm test
npm run test:ui
npm run test:headed
```

After execution:

- HTML report: `playwright-report/`
- Artifacts and logs: `test-results/`

## Notes

- If auth credentials are missing, authenticated suites are skipped.
- Docker log collection is best-effort and does not fail tests if unavailable.
- Deep business flows are included as `skip` placeholders and should be filled
  while implementing related product functionality.
