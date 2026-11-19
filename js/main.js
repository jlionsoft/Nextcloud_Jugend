(function () {
    'use strict';
    const form = document.getElementById('jugend-message-form');
    if (!form) return;
    const message = document.getElementById('jugend-message');
    const count = document.getElementById('jugend-character-count');
    const estimate = document.getElementById('jugend-sms-estimate');
    const feedback = document.getElementById('jugend-feedback');
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
    }
    message.addEventListener('input', update);
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!message.value.trim()) return;
        if (!window.confirm('Nachricht jetzt an alle konfigurierten Empfänger und den Talk-Raum senden?')) return;
        button.disabled = true;
        feedback.textContent = 'Versand läuft …';
        feedback.className = 'jugend-feedback';
        try {
            const response = await fetch(OC.generateUrl('/apps/jugend/api/v1/messages'), {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded', requesttoken: OC.requestToken},
                body: new URLSearchParams(new FormData(form))
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Versand fehlgeschlagen.');
            const talk = result.talk?.ok ? 'Talk: erfolgreich' : 'Talk: fehlgeschlagen (' + (result.talk?.error || 'unbekannt') + ')';
            const sms = 'SMS: ' + (result.sms?.sent || 0) + '/' + (result.sms?.total || 0) + ' erfolgreich';
            const failed = (result.sms?.failed || []).map(x => x.recipient + ': ' + x.error).join('\n');
            const smsError = result.sms?.error ? '\n' + result.sms.error : '';
            feedback.textContent = [talk, sms, smsError, failed].filter(Boolean).join('\n');
            feedback.classList.toggle('success', !!result.success);
            feedback.classList.toggle('warning', !result.success);
            if (result.success) { message.value = ''; update(); }
        } catch (error) {
            feedback.textContent = error.message || 'Unbekannter Fehler beim Versand.';
            feedback.classList.add('warning');
        } finally { button.disabled = false; }
    });
    update();
}());
