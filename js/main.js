(function () {
    'use strict';

    const form = document.getElementById('jugend-message-form');
    if (!form) {
        return;
    }

    const message = document.getElementById('jugend-message');
    const count = document.getElementById('jugend-character-count');
    const smsEstimate = document.getElementById('jugend-sms-estimate');
    const feedback = document.getElementById('jugend-feedback');
    const sendButton = document.getElementById('jugend-send-button');

    function smsSegments(value) {
        if (value.length === 0) {
            return 0;
        }

        // Conservative estimate matching the legacy client for ordinary GSM text.
        return value.length <= 160 ? 1 : Math.ceil(value.length / 153);
    }

    function updateCounters() {
        const value = message.value;
        count.textContent = value.length + ' / 5000';
        smsEstimate.textContent = 'SMS: ' + smsSegments(value) + ' Segmente (Schätzung)';
    }

    message.addEventListener('input', updateCounters);

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        feedback.textContent = 'Der Versand wird in einem folgenden Entwicklungsschritt aktiviert.';
        feedback.classList.add('warning');
    });

    updateCounters();
    sendButton.disabled = true;
    sendButton.title = 'Nachrichtenkanäle werden noch eingerichtet';
}());
