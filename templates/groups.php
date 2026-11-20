<?php
declare(strict_types=1);
/** @var \OCP\IL10N\IL10N $l */
?>
<div id="app-content" class="jugend-app-content">
    <div id="jugend-groups" class="jugend-page jugend-organization">
        <header class="jugend-page__header">
            <div>
                <h1><?php p($l->t('Meine Arbeitsgruppen')); ?></h1>
                <p><?php p($l->t('Verwalte die Mitglieder und die beiden Gruppenleitungen deiner aktiven Arbeitsgruppen.')); ?></p>
            </div>
            <a class="button" href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('jugend.page.index')); ?>"><?php p($l->t('Zurück')); ?></a>
        </header>

        <div id="jugend-groups-feedback" class="jugend-organization-feedback" role="status" aria-live="polite"></div>

        <main class="jugend-organization-layout">
            <section class="jugend-panel">
                <h2><?php p($l->t('Arbeitsgruppen')); ?></h2>
                <p class="jugend-muted"><?php p($l->t('Es werden nur aktive Arbeitsgruppen angezeigt, in denen du aktuell als Gruppenleitung eingetragen bist.')); ?></p>
                <div id="jugend-leader-group-list" class="jugend-group-list">
                    <p class="jugend-muted"><?php p($l->t('Arbeitsgruppen werden geladen …')); ?></p>
                </div>
            </section>

            <section class="jugend-panel">
                <h2><?php p($l->t('Mitglieder verwalten')); ?></h2>
                <p id="jugend-leader-selected-group" class="jugend-muted"><?php p($l->t('Wähle links eine Arbeitsgruppe aus.')); ?></p>
                <div id="jugend-leader-out-of-scope" class="jugend-muted" role="status"></div>
                <form id="jugend-leader-membership-form" class="jugend-organization-form" hidden>
                    <p class="jugend-muted"><?php p($l->t('Wähle die Mitglieder und markiere genau zwei Personen als gleichberechtigte Gruppenleitung. Änderungen an Talk, Dateiordner und Kalender werden innerhalb des nächsten Synchronisationslaufs übernommen.')); ?></p>
                    <div id="jugend-leader-membership-list" class="jugend-membership-list"></div>
                    <button type="submit" class="primary"><?php p($l->t('Mitgliedschaften speichern')); ?></button>
                </form>
            </section>
        </main>
    </div>
</div>
