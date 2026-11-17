# Copybara Browser Extension

## English

Chrome, Firefox, and Edge extension: send the current page to the Copybara AI editor for parsing and rewriting in one click.

### Install (development)

1. Open `chrome://extensions` (Chromium) or `about:debugging` (Firefox).
2. Enable **Developer mode**.
3. Click **Load unpacked** and select this folder (`browser-extension` at the add-ons repo root).

### Usage

1. Open any normal web page (article, post).
2. Click the extension icon.
3. Click **Parse** (or the main action in the popup).
4. A new tab opens the Copybara AI editor; parsing starts automatically.

### Build for stores

```bash
./package.sh
```

Produces `copybara-extension-v1.0.0.zip` for Chrome Web Store, Firefox Add-ons, or Edge Add-ons.

### Publishing

See [store/README.md](store/README.md) for store requirements, listing text, and privacy policy.

---

## Русский

Расширение для Chrome, Firefox и Edge. Позволяет отправить текущую страницу в AI-редактор Copybara для парсинга и переписывания одним кликом.

### Установка (разработка)

1. Откройте `chrome://extensions` (или `about:debugging` в Firefox)
2. Включите «Режим разработчика»
3. Нажмите «Загрузить распакованное расширение»
4. Выберите папку этого расширения (`browser-extension` в корне репозитория аддонов)

### Использование

1. Откройте любую веб-страницу (статью, пост)
2. Кликните по иконке расширения
3. Нажмите «Спарсить»
4. Откроется новая вкладка с AI-редактором Copybara — парсинг запустится автоматически

### Сборка для публикации

```bash
./package.sh
```

Создаётся `copybara-extension-v1.0.0.zip` — загрузите его в Chrome Web Store, Firefox Add-ons или Edge Add-ons.

### Публикация

См. [store/README.md](store/README.md) — требования по магазинам, тексты описаний, политика конфиденциальности.
