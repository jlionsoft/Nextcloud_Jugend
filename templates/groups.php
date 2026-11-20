<?php
declare(strict_types=1);
/** @var \OCP\IL10N\IL10N $l */
?>
<div id="app">
    <nav id="app-navigation" aria-label="<?php p($l->t('App-Navigation')); ?>">
        <ul>
            <li class="app-navigation-entry"><a href="<?php p($_['navigation']['home']); ?>"><span class="icon icon-home" aria-hidden="true"></span><span><?php p($l->t('Nachrichten')); ?></span></a></li>
            <?php if (!empty($_['navigation']['organization'])) { ?><li class="app-navigation-entry"><a href="<?php p($_['navigation']['organization']); ?>"><span class="icon icon-category-monitoring" aria-hidden="true"></span><span><?php p($l->t('Organisation')); ?></span></a></li><?php } ?>
            <li class="app-navigation-entry active"><a href="<?php p($_['navigation']['groups']); ?>"><span class="icon icon-group" aria-hidden="true"></span><span><?php p($l->t('Arbeitsgruppen')); ?></span></a></li>
            <?php if (!empty($_['navigation']['settings'])) { ?><li class="app-navigation-entry"><a href="<?php p($_['navigation']['settings']); ?>"><span class="icon icon-settings" aria-hidden="true"></span><span><?php p($l->t('Einstellungen')); ?></span></a></li><?php } ?>
        </ul>
    </nav>
    <div id="app-content" class="jugend-app-content">
    <div id="jugend-groups" class="jugend-page jugend-organization">
        <header class="jugend-page__header">
            <div>
                <h1><?php p($l->t('Meine Arbeitsgruppen')); ?></h1>
                <p><?php p($l->t('Verwalte die Mitglieder und die beiden Gruppenleitungen deiner aktiven und geplanten Arbeitsgruppen.')); ?></p>
            </div>
            <a class="button" href="<?php p($_['homeUrl']); ?>"><?php p($l->t('Zurück')); ?></a>
        </header>

        <div id="jugend-groups-feedback" class="jugend-organization-feedback" role="status" aria-live="polite"></div>

        <main class="jugend-organization-layout">
            <section class="jugend-panel">
                <h2><?php p($l->t('Arbeitsgruppen')); ?></h2>
                <p class="jugend-muted"><?php p($l->t('Aktive Arbeitsgruppen und gleichnamige Entwürfe werden angezeigt, soweit du sie aufgrund deiner Leitungsrolle oder einer Gruppenberechtigung verwalten darfst.')); ?></p>
                <div id="jugend-leader-group-list" class="jugend-group-list">
                    <p class="jugend-muted"><?php p($l->t('Arbeitsgruppen werden geladen …')); ?></p>
                </div>
            </section>

            <section class="jugend-panel">
                <h2><?php p($l->t('Mitglieder verwalten')); ?></h2>
                <p id="jugend-leader-selected-group" class="jugend-muted"><?php p($l->t('Wähle links eine Arbeitsgruppe aus.')); ?></p>
                <div id="jugend-leader-resource-links" class="jugend-form-actions"></div>
                <div id="jugend-leader-out-of-scope" class="jugend-muted" role="status"></div>
                <form id="jugend-leader-membership-form" class="jugend-organization-form" hidden>
                    <p class="jugend-muted"><?php p($l->t('Wähle die Mitglieder und markiere genau zwei Personen als gleichberechtigte Gruppenleitung. Bei aktiven Gruppen werden Änderungen im nächsten Synchronisationslauf übernommen. Geplante Gruppen werden für den nächsten Organisationswechsel vorbereitet.')); ?></p>
                    <div id="jugend-leader-membership-list" class="jugend-membership-list"></div>
                    <button type="submit" class="primary"><?php p($l->t('Mitgliedschaften speichern')); ?></button>
                </form>
            </section>
        </main>
    </div>
</div>
</div>
