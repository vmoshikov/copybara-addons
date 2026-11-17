(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var cfg = window.cbaiAiRewrite || {};
    var btn = document.getElementById('cbai-ai-rewrite-btn');
    if (!btn) return;

    var statusEl = document.getElementById('cbai-ai-rewrite-status');
    var preview = document.getElementById('cbai-ai-rewrite-preview');
    var resultEl = document.getElementById('cbai-ai-rewrite-result');
    var applyBtn = document.getElementById('cbai-ai-apply-btn');
    var cancelBtn = document.getElementById('cbai-ai-cancel-btn');
    var instructionEl = document.getElementById('cbai-ai-instruction');
    var i18n = cfg.i18n || {};

    function setStatus(msg) {
      if (statusEl) statusEl.textContent = msg || '';
    }

    function applyToEditor(html) {
      if (window.wp && wp.data && wp.data.dispatch) {
        try {
          wp.data.dispatch('core/editor').editPost({ content: html });
          return true;
        } catch (e) { /* fall through */ }
      }
      if (window.tinymce && tinymce.get('content')) {
        tinymce.get('content').setContent(html);
        return true;
      }
      var ta = document.getElementById('content');
      if (ta) {
        ta.value = html;
        return true;
      }
      return false;
    }

    btn.addEventListener('click', function () {
      if (!cfg.available) {
        setStatus(i18n.needKey || 'Connectors required');
        return;
      }
      btn.disabled = true;
      setStatus(i18n.working || '…');
      if (preview) preview.style.display = 'none';

      var body = new FormData();
      body.append('action', cfg.action);
      body.append('nonce', cfg.nonce);
      body.append('post_id', btn.getAttribute('data-post-id') || '');
      body.append('instruction', instructionEl ? instructionEl.value : '');

      fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          btn.disabled = false;
          if (!json || !json.success) {
            var msg = (json && json.data && json.data.message) || i18n.error || 'Error';
            setStatus(msg);
            return;
          }
          var content = (json.data && json.data.content) || '';
          if (resultEl) resultEl.value = content;
          if (preview) preview.style.display = 'block';
          setStatus(i18n.done || 'OK');
        })
        .catch(function () {
          btn.disabled = false;
          setStatus(i18n.error || 'Error');
        });
    });

    if (applyBtn) {
      applyBtn.addEventListener('click', function () {
        var html = resultEl ? resultEl.value : '';
        if (!html) return;
        if (applyToEditor(html)) {
          setStatus(i18n.applied || 'Applied');
          if (preview) preview.style.display = 'none';
        }
      });
    }
    if (cancelBtn) {
      cancelBtn.addEventListener('click', function () {
        if (preview) preview.style.display = 'none';
        setStatus('');
      });
    }
  });
})();
