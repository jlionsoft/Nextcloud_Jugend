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
    let selectedGroup = null;
    let availableUsers = [];

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
            const card = element('button', 'jugend-period-item', group.name + ' · ' + group.members.length + ' Mitglieder');
            card.type = 'button';
            card.addEventListener('click', () => selectGroup(group));
            groupList.append(card);
        });
    }

    async function selectGroup(group) {
        selectedGroup = group;
        const editor = document.getElementById('jugend-membership-editor');
        editor.hidden = selectedPeriod.status !== 'draft';
        document.getElementById('jugend-selected-group').textContent = group.name;
        const memberIds = new Set(group.members.map((member) => member.user_id));
        const leaderIds = new Set(group.members.filter((member) => member.is_leader === true || member.is_leader === 1 || member.is_leader === '1').map((member) => member.user_id));
        const list = document.getElementById('jugend-membership-list');
        list.replaceChildren();
        availableUsers.forEach((user) => {
            const row = element('div', 'jugend-member-row');
            const memberLabel = element('label', 'jugend-member-label');
            const member = element('input'); member.type = 'checkbox'; member.value = user.uid; member.checked = memberIds.has(user.uid); member.className = 'jugend-member-check';
            memberLabel.append(member, document.createTextNode(' ' + user.displayName + ' (' + user.uid + ')'));
            const leaderLabel = element('label', 'jugend-leader-label');
            const leader = element('input'); leader.type = 'checkbox'; leader.value = user.uid; leader.checked = leaderIds.has(user.uid); leader.disabled = !member.checked; leader.className = 'jugend-leader-check';
            leaderLabel.append(leader, document.createTextNode(' Gruppenleitung'));
            member.addEventListener('change', () => { leader.disabled = !member.checked; if (!member.checked) leader.checked = false; });
            row.append(memberLabel, leaderLabel); list.append(row);
        });
    }

    document.getElementById('jugend-membership-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!selectedGroup) return;
        const params = new URLSearchParams();
        params.set('groupId', selectedGroup.id);
        document.querySelectorAll('.jugend-member-check:checked').forEach((input) => params.append('members[]', input.value));
        document.querySelectorAll('.jugend-leader-check:checked').forEach((input) => params.append('leaders[]', input.value));
        try {
            const response = await fetch(urls.setMembers, {method:'PUT', headers:{'Content-Type':'application/x-www-form-urlencoded', requesttoken:token(), Accept:'application/json'}, body:params});
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || 'Mitgliedschaften konnten nicht gespeichert werden.');
            showFeedback('Mitgliedschaften für „' + selectedGroup.name + '“ gespeichert.');
            await loadGroups();
            await selectGroup(payload.group);
        } catch (error) { showFeedback(error.message, true); }
    });


    async function loadAppAdmins() {
        if (!urls.manageAppAdmins) return;
        const panel = document.getElementById('jugend-app-admin-panel');
        panel.hidden = false;
        const [adminPayload, userPayload] = await Promise.all([
            request(urls.appAdmins),
            request(urls.users)
        ]);
        const list = document.getElementById('jugend-app-admin-list');
        list.replaceChildren();
        const select = document.getElementById('jugend-app-admin-user');
        select.replaceChildren(new Option('Benutzer auswählen …', ''));
        const assigned = new Set(adminPayload.admins.map((admin) => admin.user_id));
        userPayload.users.forEach((user) => {
            if (!assigned.has(user.uid)) {
                select.add(new Option(user.displayName + ' (' + user.uid + ')', user.uid));
            }
        });
        if (!adminPayload.admins.length) {
            list.append(element('p', 'jugend-muted', 'Noch keine App-Administratoren zugewiesen.'));
        }
        adminPayload.admins.forEach((admin) => {
            const row = element('div', 'jugend-member-row');
            const info = element('span', '', admin.user_id + (admin.granted_by ? ' · vergeben durch ' + admin.granted_by : ''));
            const revoke = element('button', 'secondary', 'Entziehen');
            revoke.type = 'button';
            revoke.addEventListener('click', async () => {
                if (!window.confirm('App-Administratorrechte für „' + admin.user_id + '“ wirklich entziehen?')) return;
                try {
                    await request(urls.revokeAppAdmin, 'DELETE', {userId: admin.user_id});
                    showFeedback('App-Administratorrechte für „' + admin.user_id + '“ wurden entzogen.');
                    await loadAppAdmins();
                } catch (error) { showFeedback(error.message, true); }
            });
            row.append(info, revoke);
            list.append(row);
        });
    }

    document.getElementById('jugend-app-admin-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const userId = document.getElementById('jugend-app-admin-user').value;
        if (!userId) return;
        try {
            await request(urls.grantAppAdmin, 'POST', {userId});
            showFeedback('„' + userId + '“ ist jetzt App-Administrator.');
            await loadAppAdmins();
        } catch (error) { showFeedback(error.message, true); }
    });

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

    Promise.all([request(urls.users).then((payload) => { availableUsers = payload.users; }), loadPeriods(), loadAppAdmins()]).catch((error) => showFeedback(error.message, true));
}());
