# copybara.ai Integration (WordPress)

**Service:** [copybara.ai](https://copybara.ai)  
**Text domain / slug:** `copybara-ai-integration` — use this string as the second argument to `__()`, `_e()`, `esc_html__()`, etc. (always a literal string; Plugin Check disallows a variable).  
**Version:** 1.1.0 · **License:** GPL v2 or later · **Requires:** WordPress 5.8+, PHP 7.4+ (WordPress 7.0+ for AI Client / Connectors)

Official WordPress integration for **copybara.ai**:

1. **Copybara → WordPress** — receive / update posts via REST or admin-ajax (API key `cb_…`).
2. **WordPress AI (7.0+)** — Copybara as AI provider (OpenRouter proxy); key `cbk_live_…` in **Settings → Connectors**; tokens billed on the Copybara account.

## Two keys (do not mix)

| Key | Where | Purpose |
|-----|--------|---------|
| `cb_…` | WordPress **Settings → copybara.ai** → Copybara **Settings → Publishing** | Inbound publish/update |
| `cbk_live_…` | Copybara **Settings → API** → WordPress **Settings → Connectors** | AI Client / chat completions |

## Setup (publishing)

- **API key (`cb_`)** — copy into the copybara.ai app (**Settings → Publishing**).
- **Endpoint URL** — from WordPress **Settings → copybara.ai** (copy button). Prefer admin-ajax.
- **Post type, status, author, image placement** — on the same settings page.
- **Image placement** can also be set in Copybara (**Settings → Publishing**): `featured` (default), `body`, or `both` (explicit opt-in only).

Re-publishing the same Copybara material updates the same WordPress post (`copybara_id` meta).

## Setup (AI / WordPress 7+)

1. Create an API key in Copybara (**Settings → API**), scope at least `write`.
2. In WordPress **Settings → Connectors**, configure **copybara.ai** with that `cbk_live_…` key.
3. Use the **copybara.ai — Переписать** metabox on the post editor, or any plugin that calls `wp_ai_client_prompt()->using_provider('copybara')`.
4. Each completion deducts Copybara tokens (`wordpress_ai`). Insufficient balance → HTTP 402.

Staging: filter `cbai_ai_base_url` to point at your gateway (default `https://api.copybara.ai/v1/ai`).

## API (publishing)

| | |
|--|--|
| REST | `POST /wp-json/copybara-ai/v1/posts` (legacy: `copybara/v1`) |
| admin-ajax | `action=copybara_ai_receive_post` (legacy: `copybara_receive_post`) |

**Auth:** `X-API-Key` or `Authorization: Bearer …` with the plugin key (`cb_…`).

**Useful body fields:** `title`, `content`, `content_type`, `status`, `featured_image`, `image_placement` (`featured`\|`body`\|`both`), `copybara_id` (UUID for upsert).

## WordPress.org packaging

Ship the plugin ZIP as **`copybara-ai-integration.zip`** (folder inside the archive: **`copybara-ai-integration/`**). Do **not** ship directory-only PNG banners or icons inside the plugin ZIP; use SVN `assets/` after approval.

`readme.txt` follows the WordPress.org plugin readme format.
