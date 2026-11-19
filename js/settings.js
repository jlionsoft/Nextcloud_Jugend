(function () {
    'use strict';

    const form = document.getElementById('jugend-settings-form');
    if (!form) return;

    const feedback = document.getElementById('jugend-settings-feedback');
    const saveButton = document.getElementById('jugend-save-settings');
    const secretNames = ['authentik_token', 'talk_bot_secret', 'sms_password'];

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
        status.textContent = removing
            ? 'Wird beim Speichern entfernt'
            : (configured ? 'Zugangsdaten sind gespeichert' : 'Keine Zugangsdaten gespeichert');
        clearInput.value = removing ? '1' : '0';
        clearButton.textContent = removing ? 'Entfernen rückgängig' : 'Zugangsdaten entfernen';
        clearButton.disabled = !configured && !removing;
        clearButton.classList.toggle('warning', removing);
    }

    secretNames.forEach((name) => {
        const field = form.elements[name];
        const clearButton = form.querySelector('[data-secret="' + name + '"]');
        const status = form.querySelector('[data-secret-status="' + name + '"]');
        const configured = status?.dataset.configured === '1';

        clearButton?.addEventListener('click', () => {
            const removing = form.elements['clear_' + name].value === '1';
            setSecretState(name, configured, !removing);
            if (removing) field.focus();
        });
        field?.addEventListener('input', () => {
            if (field.value !== '') setSecretState(name, configured, false);
        });
    });

    async function request(url, data) {
        const response = await fetch(OC.generateUrl(url), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                requesttoken: OC.requestToken,
                Accept: 'application/json'
            },
            body: new URLSearchParams(data)
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(result.message || 'Die Anfrage ist fehlgeschlagen (HTTP ' + response.status + ').');
        }
        return result;
    }

    async function saveSettings(showMessage) {
        saveButton.disabled = true;
        if (showMessage) setFeedback('Einstellungen werden gespeichert …');
        try {
            const result = await request('/apps/jugend/api/v1/settings', Object.fromEntries(new FormData(form)));
            if (!result.saved) throw new Error(result.message || 'Speichern fehlgeschlagen.');

            secretNames.forEach((name) => {
                form.elements[name].value = '';
                const configured = result.configured?.[name] === true;
                setSecretState(name, configured, false);
            });
            if (showMessage) setFeedback('Alle Einstellungen wurden gespeichert.', 'success');
            return true;
        } catch (error) {
            if (showMessage) setFeedback(error.message || 'Speichern fehlgeschlagen.', 'warning');
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
            resultLabel.textContent = 'Speichere Einstellungen und prüfe Verbindung …';
            resultLabel.className = 'jugend-test-result';
            try {
                await saveSettings(false);
                const result = await request('/apps/jugend/api/v1/settings/test', {channel});
                if (!result.ok) throw new Error(result.message || 'Verbindungstest fehlgeschlagen.');
                resultLabel.textContent = result.message || 'Verbindung erfolgreich.';
                resultLabel.classList.add('success');
                setFeedback('Einstellungen gespeichert. Verbindungstest erfolgreich.', 'success');
            } catch (error) {
                resultLabel.textContent = error.message || 'Verbindungstest fehlgeschlagen.';
                resultLabel.classList.add('error');
                setFeedback(resultLabel.textContent, 'warning');
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
            button.textContent = 'Kopiert';
        } catch (_) {
            setFeedback('Kopieren nicht möglich. Bitte die Webhook-URL manuell markieren.', 'warning');
        }
    });
}());
