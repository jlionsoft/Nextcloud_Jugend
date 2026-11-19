(function () {
    'use strict';
    const form = document.getElementById('jugend-settings-form');
    if (!form) return;
    const feedback = document.getElementById('jugend-settings-feedback');
    const secrets = ['authentik_token', 'talk_bot_secret', 'sms_password'];

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        feedback.textContent = 'Speichere …';
        try {
            const response = await fetch(OC.generateUrl('/apps/jugend/api/v1/settings'), {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded', requesttoken: OC.requestToken},
                body: new URLSearchParams(new FormData(form))
            });
            const result = await response.json();
            if (!response.ok || !result.saved) throw new Error(result.message || 'Speichern fehlgeschlagen.');
            secrets.forEach(name => { form.elements[name].value = ''; });
            feedback.textContent = 'Einstellungen gespeichert.';
        } catch (error) { feedback.textContent = error.message || 'Fehler beim Speichern.'; }
    });

    form.querySelectorAll('.test-connection').forEach(button => {
        button.addEventListener('click', async () => {
            button.disabled = true;
            feedback.textContent = 'Verbindung wird geprüft …';
            try {
                const response = await fetch(OC.generateUrl('/apps/jugend/api/v1/settings/test'), {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded', requesttoken: OC.requestToken},
                    body: new URLSearchParams({channel: button.dataset.channel})
                });
                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.message || 'Verbindungstest fehlgeschlagen.');
                feedback.textContent = result.message;
            } catch (error) { feedback.textContent = error.message || 'Verbindungstest fehlgeschlagen.'; }
            finally { button.disabled = false; }
        });
    });
}());
