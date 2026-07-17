// public/js/app.js
(function() {
    'use strict';

    // Функция подсветки JSON (исправленная)
    function syntaxHighlight(data) {
        let json;
        if (typeof data === 'string') {
            try {
                json = JSON.stringify(JSON.parse(data), null, 2);
            } catch {
                json = data;
            }
        } else {
            json = JSON.stringify(data, null, 2);
        }

        json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        json = json.replace(/^(\s*)("(?:[^"\\]|\\.)*"):/gm, (match, spaces, key) => {
            return spaces + '<span class="key">' + key + '</span>:';
        });

        json = json.replace(/: (".*?")/g, (match, str) => {
            return ': <span class="string">' + str + '</span>';
        });

        json = json.replace(/\b(true|false)\b/g, match => '<span class="boolean">' + match + '</span>');
        json = json.replace(/\b(null)\b/g, match => '<span class="null">' + match + '</span>');
        json = json.replace(/(?<!["\w])(\d+)(?!["\w])/g, match => '<span class="number">' + match + '</span>');

        return json;
    }

    function showResult(elementId, content) {
        const el = document.getElementById(elementId);
        if (!el) return;
        el.innerHTML = content;
        el.classList.add('visible');
    }

    function showLoading(elementId) {
        const el = document.getElementById(elementId);
        if (!el) return;
        el.innerHTML = `<div class="loading-text"><span class="spinner"></span> Загрузка...</div>`;
        el.classList.add('visible');
    }

    function hideResult(elementId) {
        const el = document.getElementById(elementId);
        if (!el) return;
        el.classList.remove('visible');
        el.innerHTML = '';
    }

    async function toggleResult(elementId, url, options = {}) {
        const el = document.getElementById(elementId);
        if (el.classList.contains('visible') && el.innerHTML.trim() !== '') {
            hideResult(elementId);
            return;
        }

        showLoading(elementId);

        try {
            const response = await fetch(url, options);
            if (!response.ok) {
                let errorMessage = `HTTP ${response.status}`;
                try {
                    const errorData = await response.json();
                    if (errorData.message) errorMessage = errorData.message;
                } catch {}
                throw new Error(errorMessage);
            }
            const data = await response.json();
            showResult(elementId, syntaxHighlight(data));
        } catch (error) {
            showResult(elementId, `<div style="color: #f87171;">❌ Ошибка: ${error.message}</div>`);
        }
    }

    // Ждём полной загрузки DOM
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing handlers...');

        // Обработчики для Health и Metrics
        document.querySelectorAll('.action-btn[data-target]').forEach(btn => {
            btn.addEventListener('click', function() {
                const target = this.dataset.target;
                let url = '';
                let resultId = '';
                if (target === 'health') {
                    url = '/api/health';
                    resultId = 'health-result';
                } else if (target === 'metrics') {
                    url = '/api/metrics';
                    resultId = 'metrics-result';
                }
                if (url && resultId) {
                    toggleResult(resultId, url);
                }
            });
        });

        // Форма отправки заявки
        const form = document.getElementById('contact-form');
        const sendBtn = document.getElementById('send-btn');
        const contactResultId = 'contact-result';

        if (form) {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const name = document.getElementById('name').value.trim();
                const email = document.getElementById('email').value.trim();
                const phone = document.getElementById('phone').value.trim();
                const comment = document.getElementById('comment').value.trim();

                if (!name || !email || !phone || !comment) {
                    alert('Все поля обязательны.');
                    return;
                }

                const payload = { name, email, phone, comment };

                sendBtn.disabled = true;
                sendBtn.textContent = 'Отправка...';
                showLoading(contactResultId);

                try {
                    const response = await fetch('/api/contact', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });

                    if (!response.ok) {
                        let errorMessage = `HTTP ${response.status}`;
                        try {
                            const errorData = await response.json();
                            if (errorData.message) errorMessage = errorData.message;
                        } catch {}
                        throw new Error(errorMessage);
                    }

                    const data = await response.json();
                    showResult(contactResultId, syntaxHighlight(data));
                } catch (error) {
                    showResult(contactResultId, `<div style="color: #f87171;">❌ Ошибка: ${error.message}</div>`);
                } finally {
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'Отправить заявку';
                }
            });
        } else {
            console.warn('Form #contact-form not found');
        }
    });
})();
