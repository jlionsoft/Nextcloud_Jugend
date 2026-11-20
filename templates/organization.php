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

    </div>
</div>
