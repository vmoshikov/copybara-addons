const BASE_URL = 'https://copybara.ai';

document.getElementById('parseBtn').addEventListener('click', async () => {
  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (!tab?.url) {
      alert('Не удалось получить URL страницы');
      return;
    }
    if (!tab.url.startsWith('http://') && !tab.url.startsWith('https://')) {
      alert('Откройте обычную веб-страницу (http/https)');
      return;
    }
    const editorUrl = `${BASE_URL}/dashboard/ai-editor?url=${encodeURIComponent(tab.url)}`;
    chrome.tabs.create({ url: editorUrl });
    window.close();
  } catch (err) {
    console.error('Copybara extension error:', err);
    alert('Произошла ошибка. Попробуйте ещё раз.');
  }
});
