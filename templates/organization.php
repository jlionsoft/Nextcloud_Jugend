<?php
declare(strict_types=1);
/** @var \OCP\IL10N\IL10N $l */
?>
<div id="app-content" class="jugend-app-content">
    <div id="jugend-organization" class="jugend-page jugend-organization">
        <header class="jugend-page__header">
            <div>
                <h1><?php p($l->t('Organisationsverwaltung')); ?></h1>
                <p><?php p($l->t('Plane Organisationsperioden und stelle Arbeitsgruppen für den nächsten Zeitraum zusammen.')); ?></p>
            </div>
            <a id="jugend-organization-back" class="button" href="#"><?php p($l->t('Zur Nachrichtenübersicht')); ?></a>
        </header>

        <div id="jugend-organization-feedback" class="jugend-organization-feedback" role="status" aria-live="polite"></div>

        <main class="jugend-organization-layout">
            <section class="jugend-panel">
                <h2><?php p($l->t('Organisationsperioden')); ?></h2>
                <p class="jugend-muted"><?php p($l->t('Aktive und geplante Organisationsjahre. Änderungen sind zunächst Entwürfe und beeinflussen die aktive Organisation nicht.')); ?></p>
                <div id="jugend-period-list" class="jugend-period-list">
                    <p class="jugend-muted"><?php p($l->t('Perioden werden geladen …')); ?></p>
                </div>

                <section id="jugend-rollover-preview" class="jugend-rollover-preview" hidden>
                    <h3>Jahreswechsel prüfen</h3>
                    <p id="jugend-rollover-status" class="jugend-muted">Die Vorschau prüft Gruppen, Mitgliedschaften und Gruppenleitungen.</p>
                    <div id="jugend-rollover-errors" role="alert"></div>
                    <div id="jugend-rollover-summary" class="jugend-period-list"></div>
                    <div class="jugend-form-actions">
                        <button type="button" id="jugend-rollover-refresh" class="secondary">Vorschau aktualisieren</button>
                        <button type="button" id="jugend-rollover-activate" class="primary" disabled>Periode aktivieren</button>
                    </div>
                    <p class="jugend-muted">Die Aktivierung archiviert die bisher aktive Periode. Externe Talk-, Datei- und Kalenderressourcen werden in dieser Entwicklungsstufe noch nicht provisioniert.</p>
                </section>

                <section id="jugend-resource-provisioning" class="jugend-organization-form" hidden>
                    <h3>Ressourcen der aktiven Periode</h3>
                    <p class="jugend-muted">Erstellt je Arbeitsgruppe einen eigenen Talk-Raum und lädt die aktuell eingetragenen Gruppenmitglieder ein. Bereits vorhandene Räume werden weiterverwendet.</p>
                    <div class="jugend-form-actions">
                        <button type="button" id="jugend-provision-talk" class="primary">Talk-Räume bereitstellen / abgleichen</button>
                    </div>
                    <div id="jugend-provision-talk-result" class="jugend-period-list" role="status" aria-live="polite"></div>
                </section>

                <form id="jugend-period-form" class="jugend-organization-form">
                    <h3><?php p($l->t('Neue Periode planen')); ?></h3>
                    <div class="jugend-fields">
                        <div class="jugend-field">
                            <label for="jugend-period-key"><?php p($l->t('Schlüssel')); ?></label>
                            <input id="jugend-period-key" name="key" required maxlength="32" placeholder="2027">
                        </div>
                        <div class="jugend-field">
                            <label for="jugend-period-title"><?php p($l->t('Bezeichnung')); ?></label>
                            <input id="jugend-period-title" name="title" required maxlength="128" placeholder="Organisationsjahr 2027">
                        </div>
                        <div class="jugend-field">
                            <label for="jugend-period-start"><?php p($l->t('Beginn')); ?></label>
                            <input id="jugend-period-start" name="startsOn" type="date">
                        </div>
                        <div class="jugend-field">
                            <label for="jugend-period-end"><?php p($l->t('Ende')); ?></label>
                            <input id="jugend-period-end" name="endsOn" type="date">
                        </div>
                        <div class="jugend-field jugend-field--wide">
                            <label for="jugend-period-template"><?php p($l->t('Vorlage')); ?></label>
                            <select id="jugend-period-template" name="sourcePeriodId">
                                <option value="">Leere Periode erstellen</option>
                            </select>
                            <small class="jugend-muted"><?php p($l->t('Optional: Gruppen, Mitglieder und Gruppenleitungen aus einer bestehenden Periode übernehmen.')); ?></small>
                        </div>
                    </div>
                    <button type="submit" class="primary"><?php p($l->t('Entwurf erstellen')); ?></button>
                </form>
            </section>

            <section class="jugend-panel">
                <h2><?php p($l->t('Arbeitsgruppen')); ?></h2>
                <p id="jugend-selected-period" class="jugend-muted"><?php p($l->t('Wähle eine geplante Periode aus.')); ?></p>
                <div id="jugend-group-list" class="jugend-group-list">
                    <p class="jugend-muted"><?php p($l->t('Noch keine Periode ausgewählt.')); ?></p>
                </div>
                <form id="jugend-group-form" class="jugend-organization-form" hidden>
                    <h3><?php p($l->t('Arbeitsgruppe hinzufügen')); ?></h3>
                    <div class="jugend-fields">
                        <div class="jugend-field">
                            <label for="jugend-group-slug"><?php p($l->t('Technischer Schlüssel')); ?></label>
                            <input id="jugend-group-slug" name="slug" required maxlength="64" placeholder="technik">
                        </div>
                        <div class="jugend-field">
                            <label for="jugend-group-name"><?php p($l->t('Gruppenname')); ?></label>
                            <input id="jugend-group-name" name="name" required maxlength="128" placeholder="Technik">
                        </div>
                        <div class="jugend-field jugend-field--wide">
                            <label for="jugend-group-description"><?php p($l->t('Beschreibung')); ?></label>
                            <input id="jugend-group-description" name="description" maxlength="500">
                        </div>
                    </div>
                    <button type="submit" class="primary"><?php p($l->t('Gruppe anlegen')); ?></button>
                </form>
                <section id="jugend-membership-editor" class="jugend-organization-form" hidden>
                    <h3><?php p($l->t('Mitglieder und Gruppenleitung')); ?>: <span id="jugend-selected-group"></span></h3>
                    <p class="jugend-muted"><?php p($l->t('Wähle die Mitglieder und markiere genau zwei davon als Gruppenleitung.')); ?></p>
                    <form id="jugend-membership-form">
                        <div id="jugend-membership-list" class="jugend-membership-list"></div>
                        <button type="submit" class="primary"><?php p($l->t('Mitgliedschaften speichern')); ?></button>
                    </form>
                </section>
            </section>
        </main>

        <section id="jugend-app-admin-panel" class="jugend-panel" hidden>
            <h2><?php p($l->t('App-Administratoren')); ?></h2>
            <p class="jugend-muted"><?php p($l->t('App-Administratoren dürfen die Organisationsstruktur verwalten, benötigen dafür aber keine globalen Nextcloud-Administratorrechte. Zuweisungen können nur globale Nextcloud-Administratoren ändern.')); ?></p>
            <div id="jugend-app-admin-list" class="jugend-period-list"></div>
            <form id="jugend-app-admin-form" class="jugend-organization-form">
                <h3><?php p($l->t('App-Administrator hinzufügen')); ?></h3>
                <div class="jugend-field">
                    <label for="jugend-app-admin-user"><?php p($l->t('Nextcloud-Benutzer')); ?></label>
                    <select id="jugend-app-admin-user" name="userId" required>
                        <option value=""><?php p($l->t('Benutzer auswählen …')); ?></option>
                    </select>
                </div>
                <button type="submit" class="primary"><?php p($l->t('Berechtigung vergeben')); ?></button>
            </form>
        </section>

        <section id="jugend-role-management" class="jugend-panel" hidden>
            <h2><?php p($l->t('Rollen und Berechtigungen')); ?></h2>
            <p class="jugend-muted"><?php p($l->t('Erstelle eigene Rollen, kombiniere Berechtigungen und weise sie Personen global, für eine Periode oder für eine Arbeitsgruppe zu. Systemrollen bleiben geschützt.')); ?></p>
            <div class="jugend-organization-layout">
                <div>
                    <h3><?php p($l->t('Vorhandene Rollen')); ?></h3>
                    <div id="jugend-role-list" class="jugend-period-list"></div>
                    <form id="jugend-role-form" class="jugend-organization-form">
                        <h3 id="jugend-role-form-title"><?php p($l->t('Neue Rolle erstellen')); ?></h3>
                        <input type="hidden" name="roleId" id="jugend-role-id" value="0">
                        <div class="jugend-fields">
                            <div class="jugend-field">
                                <label for="jugend-role-key"><?php p($l->t('Technischer Schlüssel')); ?></label>
                                <input id="jugend-role-key" name="roleKey" required maxlength="64" pattern="[a-z][a-z0-9_.-]{1,63}" placeholder="gruppenleitung">
                            </div>
                            <div class="jugend-field">
                                <label for="jugend-role-name"><?php p($l->t('Rollenname')); ?></label>
                                <input id="jugend-role-name" name="name" required maxlength="128" placeholder="Gruppenleitung">
                            </div>
                            <div class="jugend-field jugend-field--wide">
                                <label for="jugend-role-description"><?php p($l->t('Beschreibung')); ?></label>
                                <input id="jugend-role-description" name="description" maxlength="2000">
                            </div>
                        </div>
                        <fieldset class="jugend-role-permissions">
                            <legend><?php p($l->t('Berechtigungen')); ?></legend>
                            <div id="jugend-role-permission-list"></div>
                        </fieldset>
                        <div class="jugend-form-actions">
                            <button type="submit" class="primary"><?php p($l->t('Rolle speichern')); ?></button>
                            <button type="button" id="jugend-role-cancel" class="secondary"><?php p($l->t('Abbrechen')); ?></button>
                        </div>
                    </form>
                </div>
                <div>
                    <h3><?php p($l->t('Rolle zuweisen')); ?></h3>
                    <form id="jugend-role-assignment-form" class="jugend-organization-form">
                        <div class="jugend-field">
                            <label for="jugend-assignment-user"><?php p($l->t('Person')); ?></label>
                            <select id="jugend-assignment-user" name="userId" required><option value=""><?php p($l->t('Person auswählen …')); ?></option></select>
                        </div>
                        <div class="jugend-field">
                            <label for="jugend-assignment-role"><?php p($l->t('Rolle')); ?></label>
                            <select id="jugend-assignment-role" name="roleId" required><option value=""><?php p($l->t('Rolle auswählen …')); ?></option></select>
                        </div>
                        <div class="jugend-field">
                            <label for="jugend-assignment-scope"><?php p($l->t('Geltungsbereich')); ?></label>
                            <select id="jugend-assignment-scope" name="scopeType">
                                <option value="global"><?php p($l->t('Global')); ?></option>
                                <option value="period"><?php p($l->t('Organisationsperiode')); ?></option>
                                <option value="group"><?php p($l->t('Arbeitsgruppe')); ?></option>
                            </select>
                        </div>
                        <div class="jugend-field" id="jugend-assignment-scope-id-wrap" hidden>
                            <label for="jugend-assignment-scope-id"><?php p($l->t('ID des Geltungsbereichs')); ?></label>
                            <select id="jugend-assignment-scope-id" name="scopeId" disabled><option value="">Geltungsbereich auswählen …</option></select>
                            <small class="jugend-muted"><?php p($l->t('Die ID findest du in der jeweiligen Organisationsperiode bzw. Arbeitsgruppe.')); ?></small>
                        </div>
                        <button type="submit" class="primary"><?php p($l->t('Rolle zuweisen')); ?></button>
                    </form>
                    <h3><?php p($l->t('Aktive Zuweisungen')); ?></h3>
                    <div id="jugend-role-assignment-list" class="jugend-period-list"></div>
                </div>
            </div>
        </section>
    </div>
</div>
