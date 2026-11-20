(function () {
    'use strict';

    const form = document.getElementById('jugend-settings-form');
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

    const feedback = document.getElementById('jugend-settings-feedback');
    const saveButton = document.getElementById('jugend-save-settings');
    const secretNames = ['authentik_token', 'talk_bot_secret', 'sms_password', 'matrix_bot_token', 'provisioning_app_password'];

    function setFeedback(message, type) {
        feedback.textContent = message || '';
        feedback.className = 'jugend-feedback' + (type ? ' ' + type : '');
    }

    function setSecretState(name, configured, removing) {
        const status = form.querySelector('[data-secret-status="' + name + '"]');
        const clearButton = form.querySelector('[data-secret="' + name + '"]');
        const clearInput = form.elements['clear_' + name];
        if (!status || !clearButton || !clearInput) return;

        status.dataset.configured = configured ? '1' : '0';
        status.dataset.removing = removing ? '1' : '0';
        status.textContent = removing
            ? strings.secretRemovePending
            : (configured ? strings.secretStored : strings.secretNone);
        clearInput.value = removing ? '1' : '0';
        clearButton.textContent = removing ? strings.undoRemove : strings.removeCredentials;
        clearButton.disabled = !configured && !removing;
        clearButton.classList.toggle('warning', removing);
    }

    secretNames.forEach((name) => {
        const field = form.elements[name];
        const clearButton = form.querySelector('[data-secret="' + name + '"]');
        const status = form.querySelector('[data-secret-status="' + name + '"]');
        clearButton?.addEventListener('click', () => {
            const configured = status?.dataset.configured === '1';
            const removing = form.elements['clear_' + name].value === '1';
            setSecretState(name, configured, !removing);
            if (removing) field.focus();
        });
        field?.addEventListener('input', () => {
            if (field.value !== '') {
                const configured = status?.dataset.configured === '1';
                setSecretState(name, configured, false);
            }
        });
    });

    async function request(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                requesttoken: requestToken(),
                Accept: 'application/json'
            },
            body: new URLSearchParams(data)
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(result.message || format(strings.requestFailed, {status: response.status}));
        }
        return result;
    }

    async function saveSettings(showMessage) {
        saveButton.disabled = true;
        if (showMessage) setFeedback(strings.saving);
        try {
            const result = await request(urls.save, Object.fromEntries(new FormData(form)));
            if (!result.saved) throw new Error(result.message || strings.saveFailed);

            secretNames.forEach((name) => {
                form.elements[name].value = '';
                const configured = result.configured?.[name] === true;
                setSecretState(name, configured, false);
            });
            if (showMessage) setFeedback(strings.saved, 'success');
            return true;
        } catch (error) {
            if (showMessage) setFeedback(error.message || strings.saveFailed, 'error');
            throw error;
        } finally {
            saveButton.disabled = false;
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        try { await saveSettings(true); } catch (_) { /* Feedback is already visible. */ }
    });

    form.querySelectorAll('.test-connection').forEach((button) => {
        button.addEventListener('click', async () => {
            const channel = button.dataset.channel;
            const resultLabel = form.querySelector('[data-test-result="' + channel + '"]');
            button.disabled = true;
            resultLabel.textContent = strings.testing;
            resultLabel.className = 'jugend-test-result';
            try {
                await saveSettings(false);
                const result = await request(urls.test, {channel});
                if (!result.ok) throw new Error(result.message || strings.testFailed);
                resultLabel.textContent = result.message || strings.testOk;
                resultLabel.classList.add('success');
                setFeedback(strings.savedAndTestOk, 'success');
            } catch (error) {
                resultLabel.textContent = error.message || strings.testFailed;
                resultLabel.classList.add('error');
                setFeedback(resultLabel.textContent, 'error');
            } finally {
                button.disabled = false;
            }
        });
    });

    document.getElementById('jugend-copy-webhook')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const value = document.getElementById('jugend-webhook-url')?.textContent || '';
        try {
            await navigator.clipboard.writeText(value);
            button.textContent = strings.copied;
        } catch (_) {
            setFeedback(strings.copyFailed, 'warning');
        }
    });
}());
