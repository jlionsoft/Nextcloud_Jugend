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

    function formatPeriodDate(date) {
        if (!date) return '';
        const parsed = new Date(date + 'T00:00:00');
        return Number.isNaN(parsed.getTime())
            ? date
            : new Intl.DateTimeFormat('de-DE', {day: '2-digit', month: '2-digit', year: 'numeric'}).format(parsed);
    }

    function periodDateLabel(period) {
        const start = formatPeriodDate(period.starts_on);
        const end = formatPeriodDate(period.ends_on);
        if (start && end) return start + ' bis ' + end;
        if (start) return 'ab ' + start;
        if (end) return 'bis ' + end;
        return 'ohne festgelegten Zeitraum';
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
        const templateSelect = document.getElementById('jugend-period-template');
        templateSelect.replaceChildren(new Option('Leere Periode erstellen', ''));
        payload.periods.forEach((period) => {
            templateSelect.add(new Option(period.title + ' · ' + period.status + ' · ' + periodDateLabel(period), period.id));
        });
        if (!payload.periods.length) {
            periodList.append(element('p', 'jugend-muted', 'Noch keine Organisationsperioden vorhanden.'));
            return [];
        }
        payload.periods.forEach((period) => {
            const button = element('button', 'jugend-period-item', period.title + ' · ' + period.status + ' · ' + periodDateLabel(period));
            button.type = 'button';
            button.addEventListener('click', () => selectPeriod(period));
            periodList.append(button);
        });
        return payload.periods;
    }

    async function selectPeriod(period) {
        selectedPeriod = period;
        selectedLabel.textContent = period.title + ' (' + period.status + ') · ' + periodDateLabel(period);
        groupForm.hidden = period.status !== 'draft';
        document.getElementById('jugend-rollover-preview').hidden = period.status !== 'draft';
        document.getElementById('jugend-resource-provisioning').hidden = period.status !== 'active';
        document.getElementById('jugend-rollover-activate').disabled = true;
        await loadGroups();
        if (period.status === 'draft') await loadRolloverPreview();
        if (period.status === 'active') await loadTalkStatus();
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


    const rolloverPanel = document.getElementById('jugend-rollover-preview');
    const rolloverErrors = document.getElementById('jugend-rollover-errors');
    const rolloverSummary = document.getElementById('jugend-rollover-summary');
    const rolloverStatus = document.getElementById('jugend-rollover-status');
    const activateButton = document.getElementById('jugend-rollover-activate');

    async function loadRolloverPreview() {
        if (!selectedPeriod || selectedPeriod.status !== 'draft') return;
        activateButton.disabled = true;
        rolloverStatus.textContent = 'Prüfe Organisationsperiode …';
        rolloverErrors.replaceChildren();
        rolloverSummary.replaceChildren();
        try {
            const preview = await request(urls.previewPeriod + '?periodId=' + encodeURIComponent(selectedPeriod.id));
            const summary = preview.summary;
            rolloverStatus.textContent = (preview.valid ? 'Die Periode ist aktivierbar.' : 'Die Periode enthält noch Fehler.')
                + ' Zeitraum: ' + periodDateLabel(preview.period) + '.'
                + ' ' + summary.groupCount + ' Arbeitsgruppen, ' + summary.memberCount + ' unterschiedliche Mitglieder.'
                + (preview.previousActivePeriod ? ' Bisher aktiv: ' + preview.previousActivePeriod.title + '.' : ' Es gibt bisher keine aktive Periode.');
            preview.errors.forEach((message) => rolloverErrors.append(element('p', 'error', message)));
            const addList = (heading, entries, render) => {
                if (!entries.length) return;
                rolloverSummary.append(element('strong', '', heading));
                entries.forEach((entry) => rolloverSummary.append(element('p', 'jugend-muted', render(entry))));
            };
            addList('Neue Arbeitsgruppen', summary.addedGroups, (entry) => '+ ' + entry.name);
            addList('Wegfallende Arbeitsgruppen', summary.removedGroups, (entry) => '− ' + entry.name);
            addList('Umbenannte Arbeitsgruppen', summary.renamedGroups, (entry) => entry.from + ' → ' + entry.to);
            addList('Mitgliedschaftsänderungen', summary.membershipChanges, (entry) => {
                const changes = [];
                if (entry.added.length) changes.push('neu: ' + entry.added.join(', '));
                if (entry.removed.length) changes.push('entfernt: ' + entry.removed.join(', '));
                if (entry.leadershipChanged.length) changes.push('Leitungswechsel: ' + entry.leadershipChanged.join(', '));
                return entry.group + ' — ' + changes.join('; ');
            });
            if (!summary.addedGroups.length && !summary.removedGroups.length && !summary.renamedGroups.length && !summary.membershipChanges.length) {
                rolloverSummary.append(element('p', 'jugend-muted', 'Keine Gruppen- oder Mitgliedschaftsänderungen gegenüber der aktiven Periode.'));
            }
            activateButton.disabled = !preview.valid;
        } catch (error) {
            rolloverStatus.textContent = 'Die Vorschau konnte nicht geladen werden.';
            rolloverErrors.append(element('p', 'error', error.message));
        }
    }


    async function loadTalkStatus() {
        if (!selectedPeriod || selectedPeriod.status !== 'active' || !urls.resourceProvisioningStatus) return;
        const target = document.getElementById('jugend-talk-status');
        target.replaceChildren(element('p', 'jugend-muted', 'Provisionierungsstatus wird geladen …'));
        try {
            const payload = await request(urls.resourceProvisioningStatus + '?periodId=' + encodeURIComponent(selectedPeriod.id));
            target.replaceChildren();
            const labels = {
                not_started: 'Noch nicht bereitgestellt',
                pending: 'Wartet',
                running: 'Wird verarbeitet',
                succeeded: 'Erfolgreich',
                failed: 'Fehler – erneuter Versuch möglich'
            };
            const resourceLabels = {talk: 'Talk', groupfolder: 'Dateiordner', calendar: 'Kalender'};
            const renderGroup = (group, prefix = '') => {
                const row = element('div', 'jugend-member-row');
                const parts = Object.entries(resourceLabels).map(([key, label]) => {
                    const state = group.resources?.[key];
                    return label + ': ' + (labels[state?.status || 'not_started'] || state?.status || 'Noch nicht bereitgestellt') +
                        (state ? ' (' + state.attempts + ' Versuche)' : '');
                });
                row.append(element('span', '', prefix + group.groupName + ' · ' + parts.join(' · ')));
                Object.values(group.resources || {}).forEach((state) => {
                    if (state.lastError) row.append(element('small', 'error', state.lastError));
                });
                target.append(row);
            };
            if (payload.groups.length) {
                payload.groups.forEach((group) => renderGroup(group));
            } else {
                target.append(element('p', 'jugend-muted', 'Keine Arbeitsgruppen in dieser Periode.'));
            }
            if ((payload.archivedGroups || []).length) {
                target.append(element('h4', '', 'Archivierte Ressourcen'));
                payload.archivedGroups.forEach((group) => renderGroup(group, group.periodTitle + ' · '));
            }
        } catch (error) {
            target.replaceChildren(element('p', 'error', error.message));
        }
    }

    document.getElementById('jugend-provision-talk').addEventListener('click', async () => {
        if (!selectedPeriod || selectedPeriod.status !== 'active') return;
        const button = document.getElementById('jugend-provision-talk');
        const output = document.getElementById('jugend-provision-talk-result');
        button.disabled = true;
        output.replaceChildren(element('p', 'jugend-muted', 'Talk-Räume werden bereitgestellt und Gruppenmitglieder abgeglichen …'));
        try {
            const result = await request(urls.provisionTalkRooms, 'POST', {periodId: selectedPeriod.id});
            output.replaceChildren();
            const renderEntries = (title, entries) => {
                if (!entries.length) return;
                output.append(element('h4', '', title));
                entries.forEach((entry) => {
                    const row = element('p', 'jugend-muted', entry.groupName + ': ' + (entry.participantsAdded?.length || 0) + ' Mitglieder hinzugefügt');
                    if (entry.url) {
                        const link = element('a', '', 'Talk-Raum öffnen');
                        link.href = entry.url;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        row.append(document.createTextNode(' · '), link);
                    }
                    output.append(row);
                    (entry.participantErrors || []).forEach((failure) => {
                        output.append(element('p', 'error', failure.userId + ': ' + failure.message));
                    });
                });
            };
            renderEntries('Neu erstellt', result.created || []);
            renderEntries('Bereits vorhanden / abgeglichen', result.existing || []);
            if ((result.archivedSynced || []).length) {
                output.append(element('h4', '', 'Archivzugriff abgeglichen'));
                result.archivedSynced.forEach((entry) => {
                    output.append(element('p', 'jugend-muted', entry.periodTitle + ' · ' + entry.groupName + ': ' +
                        (entry.participantsAdded?.length || 0) + ' hinzugefügt, ' + (entry.participantsRemoved?.length || 0) + ' entfernt'));
                });
            }
            (result.failed || []).forEach((failure) => {
                output.append(element('p', 'error', failure.groupName + ': ' + failure.message));
            });
            if (!(result.created || []).length && !(result.existing || []).length && !(result.failed || []).length) {
                output.append(element('p', 'jugend-muted', 'Keine Arbeitsgruppen für diese Periode vorhanden.'));
            }
            showFeedback('Talk-Ressourcen wurden verarbeitet. Bitte prüfe die Ergebnisse je Arbeitsgruppe.');
        } catch (error) {
            output.replaceChildren(element('p', 'error', error.message));
            showFeedback(error.message, true);
        } finally {
            button.disabled = false;
            await loadTalkStatus();
        }
    });

    document.getElementById('jugend-talk-status-refresh').addEventListener('click', loadTalkStatus);

    async function runResourceProvisioning(buttonId, outputId, endpoint, resourceLabel) {
        if (!selectedPeriod || selectedPeriod.status !== 'active') return;
        const button = document.getElementById(buttonId);
        const output = document.getElementById(outputId);
        button.disabled = true;
        output.replaceChildren(element('p', 'jugend-muted', resourceLabel + ' werden bereitgestellt und Berechtigungen abgeglichen …'));
        try {
            const result = await request(endpoint, 'POST', {periodId: selectedPeriod.id});
            output.replaceChildren();
            const render = (title, entries) => {
                if (!entries?.length) return;
                output.append(element('h4', '', title));
                entries.forEach((entry) => {
                    const counts = ' · ' + (entry.sharesAdded?.length || 0) + ' Freigaben hinzugefügt, ' +
                        (entry.sharesRemoved?.length || 0) + ' entfernt';
                    const row = element('p', 'jugend-muted', (entry.periodTitle ? entry.periodTitle + ' · ' : '') + entry.groupName + counts);
                    if (entry.url) {
                        const link = element('a', '', resourceLabel + ' öffnen');
                        link.href = entry.url;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        row.append(document.createTextNode(' · '), link);
                    }
                    output.append(row);
                });
            };
            render('Neu bereitgestellt', result.created || []);
            render('Bereits vorhanden / abgeglichen', result.existing || []);
            render('Archivzugriff abgeglichen', result.archivedSynced || []);
            (result.failed || []).forEach((failure) => output.append(element('p', 'error', failure.groupName + ': ' + failure.message)));
            (result.inProgress || []).forEach((item) => output.append(element('p', 'jugend-muted', item.groupName + ': Provisionierung läuft bereits.')));
            if (!(result.created || []).length && !(result.existing || []).length && !(result.archivedSynced || []).length &&
                !(result.failed || []).length && !(result.inProgress || []).length) {
                output.append(element('p', 'jugend-muted', 'Keine Arbeitsgruppen für diese Periode vorhanden.'));
            }
            showFeedback(resourceLabel + ' wurden verarbeitet. Bitte prüfe die Ergebnisse je Arbeitsgruppe.');
        } catch (error) {
            output.replaceChildren(element('p', 'error', error.message));
            showFeedback(error.message, true);
        } finally {
            button.disabled = false;
            await loadTalkStatus();
        }
    }

    document.getElementById('jugend-provision-groupfolders').addEventListener('click', () =>
        runResourceProvisioning('jugend-provision-groupfolders', 'jugend-provision-groupfolders-result', urls.provisionGroupFolders, 'Dateiordner'));
    document.getElementById('jugend-provision-calendars').addEventListener('click', () =>
        runResourceProvisioning('jugend-provision-calendars', 'jugend-provision-calendars-result', urls.provisionCalendars, 'Kalender'));


    document.getElementById('jugend-rollover-refresh').addEventListener('click', loadRolloverPreview);
    activateButton.addEventListener('click', async () => {
        if (!selectedPeriod || activateButton.disabled) return;
        const previous = document.querySelector('#jugend-rollover-status').textContent;
        if (!window.confirm('Organisationsperiode „' + selectedPeriod.title + '“ jetzt sofort aktivieren? Zeitraum: ' + periodDateLabel(selectedPeriod) + '. Die bisher aktive Periode wird archiviert.')) return;
        activateButton.disabled = true;
        try {
            const result = await request(urls.activatePeriod, 'POST', {periodId: selectedPeriod.id});
            showFeedback('„' + result.period.title + '“ ist jetzt die aktive Organisationsperiode.');
            const periods = await loadPeriods();
            const updated = periods.find((period) => Number(period.id) === Number(result.period.id));
            if (updated) await selectPeriod(updated);
        } catch (error) {
            rolloverStatus.textContent = previous;
            showFeedback(error.message, true);
            await loadRolloverPreview();
        }
    });

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


    const rolePanel = document.getElementById('jugend-role-management');
    const roleList = document.getElementById('jugend-role-list');
    const assignmentList = document.getElementById('jugend-role-assignment-list');
    const roleForm = document.getElementById('jugend-role-form');
    let rolePermissions = [];
    let managedRoles = [];

    function resetRoleForm() {
        roleForm.reset();
        document.getElementById('jugend-role-id').value = '0';
        document.getElementById('jugend-role-key').disabled = false;
        document.getElementById('jugend-role-form-title').textContent = 'Neue Rolle erstellen';
        roleForm.querySelectorAll('input[name="permissionKeys[]"]').forEach((input) => { input.checked = false; });
    }

    async function loadRoles() {
        if (!urls.manageRoles) return;
        rolePanel.hidden = false;
        const payload = await request(urls.roles);
        managedRoles = payload.roles;
        rolePermissions = payload.permissions;
        const permissionList = document.getElementById('jugend-role-permission-list');
        permissionList.replaceChildren();
        rolePermissions.forEach((permission) => {
            const label = element('label', 'jugend-role-permission');
            const checkbox = element('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'permissionKeys[]';
            checkbox.value = permission.permission_key;
            const description = element('span');
            description.append(element('strong', '', permission.name), element('small', '', permission.permission_key + ' · ' + (permission.description || '')));
            label.append(checkbox, description);
            permissionList.append(label);
        });

        roleList.replaceChildren();
        const roleSelect = document.getElementById('jugend-assignment-role');
        roleSelect.replaceChildren(new Option('Rolle auswählen …', ''));
        managedRoles.forEach((role) => {
            roleSelect.add(new Option(role.name + (role.is_system ? ' (System)' : ''), role.id));
            const row = element('div', 'jugend-member-row');
            const detail = element('div', 'jugend-role-detail');
            detail.append(element('strong', '', role.name), element('small', '', role.role_key + ' · ' + role.permissions.join(', ')));
            row.append(detail);
            if (!role.is_system) {
                const edit = element('button', 'secondary', 'Bearbeiten');
                edit.type = 'button';
                edit.addEventListener('click', () => {
                    document.getElementById('jugend-role-id').value = role.id;
                    document.getElementById('jugend-role-key').value = role.role_key;
                    document.getElementById('jugend-role-key').disabled = true;
                    document.getElementById('jugend-role-name').value = role.name;
                    document.getElementById('jugend-role-description').value = role.description || '';
                    document.getElementById('jugend-role-form-title').textContent = 'Rolle bearbeiten: ' + role.name;
                    permissionList.querySelectorAll('input').forEach((input) => { input.checked = role.permissions.includes(input.value); });
                    roleForm.scrollIntoView({behavior:'smooth', block:'nearest'});
                });
                const remove = element('button', 'secondary', 'Löschen');
                remove.type = 'button';
                remove.addEventListener('click', async () => {
                    if (!window.confirm('Rolle „' + role.name + '“ wirklich löschen?')) return;
                    try {
                        await request(urls.deleteRole, 'DELETE', {roleId: role.id});
                        showFeedback('Rolle „' + role.name + '“ wurde gelöscht.');
                        await loadRoles();
                    } catch (error) { showFeedback(error.message, true); }
                });
                row.append(edit, remove);
            } else {
                row.append(element('span', 'jugend-muted', 'Geschützte Systemrolle'));
            }
            roleList.append(row);
        });

        assignmentList.replaceChildren();
        if (!payload.assignments.length) {
            assignmentList.append(element('p', 'jugend-muted', 'Noch keine benutzerdefinierten Rollenzuweisungen.'));
        }
        payload.assignments.forEach((assignment) => {
            const row = element('div', 'jugend-member-row');
            const scope = assignment.scope_type === 'global' ? 'Global' : (assignment.scope_type === 'period' ? 'Periode #' : 'Gruppe #') + assignment.scope_id;
            row.append(element('span', '', assignment.user_id + ' · ' + assignment.name + ' · ' + scope));
            if (assignment.role_key !== 'app_admin') {
                const revoke = element('button', 'secondary', 'Entziehen');
                revoke.type = 'button';
                revoke.addEventListener('click', async () => {
                    try {
                        await request(urls.revokeRole, 'DELETE', {assignmentId: assignment.id});
                        showFeedback('Rollenzuweisung wurde entzogen.');
                        await loadRoles();
                    } catch (error) { showFeedback(error.message, true); }
                });
                row.append(revoke);
            }
            assignmentList.append(row);
        });
    }

    roleForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const params = new URLSearchParams();
        params.set('roleId', document.getElementById('jugend-role-id').value);
        params.set('roleKey', document.getElementById('jugend-role-key').value);
        params.set('name', document.getElementById('jugend-role-name').value);
        params.set('description', document.getElementById('jugend-role-description').value);
        roleForm.querySelectorAll('input[name="permissionKeys[]"]:checked').forEach((input) => params.append('permissionKeys[]', input.value));
        try {
            const response = await fetch(urls.saveRole, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded', requesttoken:token(), Accept:'application/json'}, body:params});
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || 'Rolle konnte nicht gespeichert werden.');
            showFeedback('Rolle „' + payload.role.name + '“ wurde gespeichert.');
            resetRoleForm();
            await loadRoles();
        } catch (error) { showFeedback(error.message, true); }
    });

    document.getElementById('jugend-role-cancel').addEventListener('click', resetRoleForm);
    const assignmentScope = document.getElementById('jugend-assignment-scope');
    const assignmentScopeIdWrap = document.getElementById('jugend-assignment-scope-id-wrap');
    async function updateAssignmentScopeOptions() {
        const scopeSelect = document.getElementById('jugend-assignment-scope-id');
        const scopeType = assignmentScope.value;
        assignmentScopeIdWrap.hidden = scopeType === 'global';
        scopeSelect.required = scopeType !== 'global';
        scopeSelect.disabled = scopeType === 'global';
        scopeSelect.replaceChildren(new Option(scopeType === 'group' ? 'Arbeitsgruppe auswählen …' : 'Periode auswählen …', ''));
        if (scopeType === 'global') return;
        try {
            const periodPayload = await request(urls.periods);
            if (scopeType === 'period') {
                periodPayload.periods.forEach((period) => scopeSelect.add(new Option(period.title + ' (' + period.status + ')', period.id)));
            } else {
                for (const period of periodPayload.periods) {
                    const groupPayload = await request(urls.groups + '?periodId=' + encodeURIComponent(period.id));
                    groupPayload.groups.forEach((group) => scopeSelect.add(new Option(period.title + ' — ' + group.name, group.id)));
                }
            }
        } catch (error) {
            showFeedback(error.message, true);
        }
    }
    assignmentScope.addEventListener('change', updateAssignmentScopeOptions);
    document.getElementById('jugend-role-assignment-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = {
            userId: document.getElementById('jugend-assignment-user').value,
            roleId: document.getElementById('jugend-assignment-role').value,
            scopeType: assignmentScope.value
        };
        const scopeId = document.getElementById('jugend-assignment-scope-id').value;
        if (assignmentScope.value !== 'global') data.scopeId = scopeId;
        try {
            await request(urls.assignRole, 'POST', data);
            showFeedback('Rollenzuweisung wurde gespeichert.');
            event.currentTarget.reset();
            await updateAssignmentScopeOptions();
            await loadRoles();
        } catch (error) { showFeedback(error.message, true); }
    });

    async function loadRoleUsers() {
        if (!urls.manageRoles) return;
        const payload = await request(urls.users);
        const select = document.getElementById('jugend-assignment-user');
        select.replaceChildren(new Option('Person auswählen …', ''));
        payload.users.forEach((user) => select.add(new Option(user.displayName + ' (' + user.uid + ')', user.uid)));
    }

    document.getElementById('jugend-organization-back').href = urls.home;

    document.getElementById('jugend-period-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget).entries());
        try {
            const sourcePeriodId = data.sourcePeriodId;
            delete data.sourcePeriodId;
            if (sourcePeriodId) {
                data.sourcePeriodId = sourcePeriodId;
            }
            const payload = await request(sourcePeriodId ? urls.clonePeriod : urls.createPeriod, 'POST', data);
            event.currentTarget.reset();
            showFeedback(sourcePeriodId
                ? 'Organisationsperiode „' + payload.period.title + '“ wurde aus der Vorlage übernommen.'
                : 'Organisationsperiode „' + payload.period.title + '“ wurde als Entwurf erstellt.');
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

    Promise.all([request(urls.users).then((payload) => { availableUsers = payload.users; }), loadPeriods(), loadAppAdmins(), loadRoles(), loadRoleUsers()]).catch((error) => showFeedback(error.message, true));
}());
