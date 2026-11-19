(function () {
    'use strict';
    const form = document.getElementById('jugend-message-form');
    if (!form) return;

    // Server-provided state (Nextcloud "initial state": base64-encoded JSON in a hidden input).
    function loadState(key) {
        const element = document.getElementById('initial-state-jugend-' + key);
        if (!element) throw new Error('Missing initial state: ' + key);
        const bytes = Uint8Array.from(atob(element.value), (char) => char.charCodeAt(0));
        return JSON.parse(new TextDecoder().decode(bytes));
    }
    const urls = loadState('urls');
    const strings = loadState('strings');
    const format = (text, values = {}) => text.replace(/\{(\w+)\}/g, (match, name) => (name in values ? String(values[name]) : match));
    const requestToken = () => document.head.dataset.requesttoken || '';

    const message = document.getElementById('jugend-message');
    const count = document.getElementById('jugend-character-count');
    const estimate = document.getElementById('jugend-sms-estimate');
    const feedback = document.getElementById('jugend-feedback');
    const resultPanel = document.getElementById('jugend-result-panel');
    const button = document.getElementById('jugend-send-button');

    function segments(value) {
        if (!value) return 0;
        const extended = '^{}\\\\[~]|€';
        let slots = 0;
        for (const char of value) slots += extended.includes(char) ? 2 : 1;
        return slots <= 160 ? 1 : Math.ceil(slots / 153);
    }

    function update() {
        count.textContent = message.value.length + ' / 5000';
        estimate.textContent = format(strings.smsEstimate, {count: segments(message.value)});
        button.disabled = !message.value.trim();
    }

    message.addEventListener('input', update);
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!message.value.trim()) return;
        if (!window.confirm(strings.confirmSend)) return;

        button.disabled = true;
        resultPanel.hidden = false;
        feedback.textContent = strings.sending;
        feedback.className = 'jugend-feedback';
        try {
            const response = await fetch(urls.send, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    requesttoken: requestToken(),
                    Accept: 'application/json'
                },
                body: new URLSearchParams(new FormData(form))
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || format(strings.sendFailedHttp, {status: response.status}));

            const talk = result.talk?.ok
                ? strings.talkOk
                : format(strings.talkFailed, {error: result.talk?.error || strings.unknown});
            const matrix = format(strings.matrixSummary, {sent: result.matrix?.sent || 0, total: result.matrix?.total || 0});
            const matrixFailed = (result.matrix?.failed || []).map((item) => item.recipient + ': ' + item.error).join('\n');
            const matrixError = result.matrix?.error || '';
            const sms = format(strings.smsSummary, {sent: result.sms?.sent || 0, total: result.sms?.total || 0});
            const failed = (result.sms?.failed || []).map((item) => item.recipient + ': ' + item.error).join('\n');
            const smsError = result.sms?.error || '';
            feedback.textContent = [talk, matrix, matrixError, matrixFailed, sms, smsError, failed].filter(Boolean).join('\n');
            feedback.classList.toggle('success', !!result.success);
            feedback.classList.toggle('warning', !result.success);
            if (result.success) {
                message.value = '';
                update();
            }
        } catch (error) {
            feedback.textContent = error.message || strings.unknownError;
            feedback.classList.add('warning');
        } finally {
            button.disabled = !message.value.trim();
        }
    });
    update();
}());
