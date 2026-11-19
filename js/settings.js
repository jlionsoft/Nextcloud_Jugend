(function () {
    'use strict';

    const form = document.getElementById('jugend-settings-form');
    if (!form) {
        return;
    }

    const feedback = document.getElementById('jugend-settings-feedback');

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        feedback.textContent = 'Speichere …';

        const body = new URLSearchParams(new FormData(form));
        try {
            const response = await fetch(OC.generateUrl('/apps/jugend/api/v1/settings'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    requesttoken: OC.requestToken
                },
                body
            });
            const result = await response.json();
            if (!response.ok || !result.saved) {
                throw new Error(result.message || 'Die Einstellungen konnten nicht gespeichert werden.');
            }

            for (const field of ['authentik_token', 'talk_bot_secret', 'sms_password']) {
                form.elements[field].value = '';
            }
            feedback.textContent = 'Einstellungen gespeichert.';
        } catch (error) {
            feedback.textContent = error.message || 'Fehler beim Speichern.';
        }
    });
}());
