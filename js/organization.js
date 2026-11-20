(function () {
    'use strict';
    const root = document.getElementById('jugend-organization');
    if (!root) return;

    function loadState(key) {
        const element = document.getElementById('initial-state-jugend-' + key);
        if (!element) throw new Error('Missing initial state: ' + key);
        const bytes = Uint8Array.from(atob(element.value), (char) => char.charCodeAt(0));
        return JSON.parse(new TextDecoder().decode(bytes));
    }
    const urls = loadState('organizationUrls');
    const token = () => document.head.dataset.requesttoken || '';
    const feedback = document.getElementById('jugend-organization-feedback');
    const periodList = document.getElementById('jugend-period-list');
    const groupList = document.getElementById('jugend-group-list');
    const selectedLabel = document.getElementById('jugend-selected-period');
    const groupForm = document.getElementById('jugend-group-form');
    let selectedPeriod = null;

    async function request(url, method = 'GET', data = null) {
        const options = {method, headers: {Accept: 'application/json'}};
        if (data !== null) {
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            options.headers.requesttoken = token();
            options.body = new URLSearchParams(data);
        }
        const response = await fetch(url, options);
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'Anfrage fehlgeschlagen (HTTP ' + response.status + ').');
        return payload;
    }

    function showFeedback(message, isError = false) {
        feedback.textContent = message;
        feedback.classList.toggle('error', isError);
        feedback.classList.toggle('success', !isError && !!message);
    }

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    async function loadPeriods() {
        const payload = await request(urls.periods);
        periodList.replaceChildren();
        if (!payload.periods.length) {
            periodList.append(element('p', 'jugend-muted', 'Noch keine Organisationsperioden vorhanden.'));
            return;
        }
        payload.periods.forEach((period) => {
            const button = element('button', 'jugend-period-item', period.title + ' · ' + period.status);
            button.type = 'button';
            button.addEventListener('click', () => selectPeriod(period));
            periodList.append(button);
        });
    }

    async function selectPeriod(period) {
        selectedPeriod = period;
        selectedLabel.textContent = period.title + ' (' + period.status + ')';
        groupForm.hidden = period.status !== 'draft';
        await loadGroups();
    }

    async function loadGroups() {
        if (!selectedPeriod) return;
        const payload = await request(urls.groups + '?periodId=' + encodeURIComponent(selectedPeriod.id));
        groupList.replaceChildren();
        if (!payload.groups.length) {
            groupList.append(element('p', 'jugend-muted', 'In dieser Periode gibt es noch keine Arbeitsgruppen.'));
            return;
        }
        payload.groups.forEach((group) => {
            const card = element('article', 'jugend-group-card');
            const heading = element('h3', '', group.name);
            const members = element('p', 'jugend-muted', group.members.length + ' Mitglieder · ' + group.members.filter((m) => m.is_leader === true || m.is_leader === 1 || m.is_leader === '1').length + ' Gruppenleiter');
            card.append(heading, members);
            groupList.append(card);
        });
    }

    document.getElementById('jugend-organization-back').href = urls.home;

    document.getElementById('jugend-period-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget).entries());
        try {
            const payload = await request(urls.createPeriod, 'POST', data);
            event.currentTarget.reset();
            showFeedback('Organisationsperiode „' + payload.period.title + '“ wurde als Entwurf erstellt.');
            await loadPeriods();
            await selectPeriod(payload.period);
        } catch (error) { showFeedback(error.message, true); }
    });

    groupForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!selectedPeriod) return;
        const data = Object.fromEntries(new FormData(event.currentTarget).entries());
        data.periodId = selectedPeriod.id;
        try {
            const payload = await request(urls.createGroup, 'POST', data);
            event.currentTarget.reset();
            showFeedback('Arbeitsgruppe „' + payload.group.name + '“ wurde angelegt.');
            await loadGroups();
        } catch (error) { showFeedback(error.message, true); }
    });

    loadPeriods().catch((error) => showFeedback(error.message, true));
}());
