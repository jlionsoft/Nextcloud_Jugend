(function () {
    'use strict';
    const root = document.getElementById('jugend-groups');
    if (!root) return;

    function loadState(key) {
        const element = document.getElementById('initial-state-jugend-' + key);
        if (!element) throw new Error('Missing initial state: ' + key);
        const bytes = Uint8Array.from(atob(element.value), (char) => char.charCodeAt(0));
        return JSON.parse(new TextDecoder().decode(bytes));
    }

    const urls = loadState('groupUrls');
    const token = () => document.head.dataset.requesttoken || '';
    const groupList = document.getElementById('jugend-leader-group-list');
    const selectedLabel = document.getElementById('jugend-leader-selected-group');
    const editor = document.getElementById('jugend-leader-membership-form');
    const memberList = document.getElementById('jugend-leader-membership-list');
    const outOfScope = document.getElementById('jugend-leader-out-of-scope');
    const resourceLinks = document.getElementById('jugend-leader-resource-links');
    const feedback = document.getElementById('jugend-groups-feedback');
    let groups = [];
    let users = [];
    let selectedGroup = null;

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

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function showFeedback(message, isError = false) {
        feedback.textContent = message;
        feedback.classList.toggle('error', isError);
        feedback.classList.toggle('success', !isError && !!message);
    }

    function renderGroups() {
        groupList.replaceChildren();
        if (!groups.length) {
            groupList.append(element('p', 'jugend-muted', 'Du bist aktuell in keiner Arbeitsgruppe als Leitung eingetragen.'));
            return;
        }
        groups.forEach((group) => {
            const periodState = group.period_status === 'draft' ? 'Geplant' : 'Aktiv';
            const button = element('button', 'jugend-period-item',
                periodState + ' · ' + (group.period_title ? group.period_title + ' · ' : '') + group.name + ' · ' + group.members.length + ' Mitglieder');
            button.type = 'button';
            button.setAttribute('aria-pressed', String(selectedGroup?.id === group.id));
            button.addEventListener('click', () => selectGroup(group));
            groupList.append(button);
        });
    }

    function selectGroup(group) {
        selectedGroup = group;
        selectedLabel.textContent = (group.period_title ? group.period_title + ' · ' : '') + group.name;
        editor.hidden = false;
        memberList.replaceChildren();
        resourceLinks.replaceChildren();
        const resourceLabels = {talk: 'Talk öffnen', groupfolder: 'Dateiordner öffnen', calendar: 'Kalender öffnen'};
        Object.entries(resourceLabels).forEach(([key, label]) => {
            const url = group.resources?.[key];
            if (url) {
                const link = element('a', 'button', label);
                link.href = url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                resourceLinks.append(link);
            } else {
                resourceLinks.append(element('span', 'jugend-muted', label + ': noch nicht bereitgestellt'));
            }
        });

        const available = new Set(users.map((user) => user.uid));
        const currentMembers = new Set(group.members.map((member) => member.user_id));
        const currentLeaders = new Set(group.members
            .filter((member) => member.is_leader === true || member.is_leader === 1 || member.is_leader === '1')
            .map((member) => member.user_id));
        const unavailableMembers = [...currentMembers].filter((uid) => !available.has(uid));
        outOfScope.textContent = unavailableMembers.length
            ? 'Nicht mehr in der aktuellen App-Zugriffsgruppe: ' + unavailableMembers.join(', ') +
                '. Diese Personen werden beim Speichern aus der aktiven Gruppenmitgliedschaft entfernt.'
            : '';

        users.forEach((user) => {
            const row = element('div', 'jugend-member-row');
            const memberLabel = element('label', 'jugend-member-label');
            const member = element('input');
            member.type = 'checkbox';
            member.value = user.uid;
            member.checked = currentMembers.has(user.uid);
            member.className = 'jugend-leader-member-check';

            const leaderLabel = element('label', 'jugend-leader-label');
            const leader = element('input');
            leader.type = 'checkbox';
            leader.value = user.uid;
            leader.checked = currentLeaders.has(user.uid);
            leader.disabled = !member.checked;
            leader.className = 'jugend-leader-role-check';

            memberLabel.append(member, document.createTextNode(' ' + user.displayName + ' (' + user.uid + ')'));
            leaderLabel.append(leader, document.createTextNode(' Gruppenleitung'));
            member.addEventListener('change', () => {
                leader.disabled = !member.checked;
                if (!member.checked) leader.checked = false;
            });
            row.append(memberLabel, leaderLabel);
            memberList.append(row);
        });
    }

    async function load() {
        groupList.replaceChildren(element('p', 'jugend-muted', 'Arbeitsgruppen werden geladen …'));
        try {
            const [groupPayload, userPayload] = await Promise.all([
                request(urls.groups),
                request(urls.users)
            ]);
            groups = groupPayload.groups || [];
            users = userPayload.users || [];
            renderGroups();
            if (selectedGroup) {
                const refreshed = groups.find((group) => Number(group.id) === Number(selectedGroup.id));
                if (refreshed) selectGroup(refreshed);
                else {
                    selectedGroup = null;
                    editor.hidden = true;
                    selectedLabel.textContent = 'Wähle links eine Arbeitsgruppe aus.';
                }
            }
        } catch (error) {
            groupList.replaceChildren(element('p', 'error', error.message));
            showFeedback(error.message, true);
        }
    }

    editor.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!selectedGroup) return;

        const params = new URLSearchParams();
        params.set('groupId', selectedGroup.id);
        const leaderInputs = [...document.querySelectorAll('.jugend-leader-role-check:checked')];
        if (leaderInputs.length !== 2) {
            showFeedback('Bitte markiere genau zwei Personen als Gruppenleitung.', true);
            return;
        }
        document.querySelectorAll('.jugend-leader-member-check:checked').forEach((input) => params.append('members[]', input.value));
        leaderInputs.forEach((input) => params.append('leaders[]', input.value));

        const saveButton = editor.querySelector('button[type="submit"]');
        saveButton.disabled = true;
        try {
            const payload = await request(urls.setMembers, 'PUT', params);
            showFeedback(payload.message || 'Mitgliedschaften gespeichert.');
            await load();
        } catch (error) {
            showFeedback(error.message, true);
        } finally {
            saveButton.disabled = false;
        }
    });

    load();
}());
