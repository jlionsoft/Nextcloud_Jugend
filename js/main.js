(function () {
    'use strict';
    const form = document.getElementById('jugend-message-form');
    if (!form) return;

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
        estimate.textContent = 'SMS: ca. ' + segments(message.value) + ' Segmente';
        button.disabled = !message.value.trim();
    }

    message.addEventListener('input', update);
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!message.value.trim()) return;
        if (!window.confirm('Nachricht jetzt an alle Matrix-Mitglieder, den Talk-Raum und SMS-Opt-in-Empfänger senden?')) return;

        button.disabled = true;
        resultPanel.hidden = false;
        feedback.textContent = 'Nachricht wird versendet …';
        feedback.className = 'jugend-feedback';
        try {
            const response = await fetch(OC.generateUrl('/apps/jugend/api/v1/messages'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    requesttoken: OC.requestToken,
                    Accept: 'application/json'
                },
                body: new URLSearchParams(new FormData(form))
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'Versand fehlgeschlagen (HTTP ' + response.status + ').');

            const talk = result.talk?.ok
                ? 'Talk: erfolgreich'
                : 'Talk: fehlgeschlagen (' + (result.talk?.error || 'unbekannt') + ')';
            const matrix = 'Matrix: ' + (result.matrix?.sent || 0) + '/' + (result.matrix?.total || 0) + ' erfolgreich';
            const matrixFailed = (result.matrix?.failed || []).map((item) => item.recipient + ': ' + item.error).join('\n');
            const matrixError = result.matrix?.error || '';
            const sms = 'SMS: ' + (result.sms?.sent || 0) + '/' + (result.sms?.total || 0) + ' erfolgreich';
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
            feedback.textContent = error.message || 'Unbekannter Fehler beim Versand.';
            feedback.classList.add('warning');
        } finally {
            button.disabled = !message.value.trim();
        }
    });
    update();
}());
