<?php
declare(strict_types=1);
/** @var array $_ */
/** @var \OCP\IL10N\IL10N $l */
?>
<?php print_unescaped($this->inc('_navigation')); ?>
<div id="app-content" class="jugend-app-content">
<div id="app-content-wrapper">
<div id="jugend-app" class="jugend-page">
    <header class="jugend-page__header">
        <div>
            <h1><?php p($l->t('Jugend-Nachricht versenden')); ?></h1>
            <p><?php p($l->t('Erstelle eine Nachricht und versende sie über Matrix, Nextcloud Talk und SMS.')); ?></p>
        </div>
    </header>

    <main class="jugend-main-layout">
        <section class="jugend-panel jugend-composer" aria-labelledby="jugend-composer-title">
            <div class="jugend-panel__heading">
                <div>
                    <h2 id="jugend-composer-title"><?php p($l->t('Neue Nachricht')); ?></h2>
                    <p><?php p($l->t('Die Nachricht wird an alle Matrix-Mitglieder der Jugendgruppe, den Talk-Raum und SMS-Opt-in-Empfänger gesendet.')); ?></p>
                </div>
            </div>
            <form id="jugend-message-form">
                <label class="jugend-label" for="jugend-message"><?php p($l->t('Nachrichtentext')); ?></label>
                <textarea id="jugend-message" name="message" rows="12" maxlength="5000" required
                    placeholder="<?php p($l->t('Was möchtest du der Jugendgruppe mitteilen?')); ?>"></textarea>
                <div class="jugend-editor-footer">
                    <span id="jugend-sms-estimate" class="jugend-muted"><?php p($l->t('SMS: 0 Segmente')); ?></span>
                    <span id="jugend-character-count" class="jugend-muted" aria-live="polite">0 / 5000</span>
                </div>
                <div class="jugend-form-actions">
                    <button id="jugend-send-button" type="submit" class="primary">
                        <?php p($l->t('Nachricht an alle senden')); ?>
                    </button>
                </div>
            </form>
        </section>

        <aside class="jugend-panel jugend-help-panel" aria-labelledby="jugend-channels-title">
            <h2 id="jugend-channels-title"><?php p($l->t('Versand')); ?></h2>
            <ul class="jugend-channel-list">
                <li><strong>Matrix</strong><small><?php p($l->t('Persönliche Direktnachricht an jedes Gruppenmitglied mit Matrix-Konto')); ?></small></li>
                <li><strong>Nextcloud Talk</strong><small><?php p($l->t('Nachricht im konfigurierten Gruppenraum')); ?></small></li>
                <li><strong>SMS</strong><small><?php p($l->t('Nur Empfänger mit aktivierter SMS-Option')); ?></small></li>
            </ul>
            <p class="jugend-muted"><?php p($l->t('Vor dem Versand wirst du um eine Bestätigung gebeten.')); ?></p>
        </aside>
    </main>

    <section id="jugend-result-panel" class="jugend-panel jugend-result" hidden aria-live="polite">
        <h2><?php p($l->t('Versandergebnis')); ?></h2>
        <p id="jugend-feedback" class="jugend-feedback"></p>
    </section>

    <dialog id="jugend-confirm-dialog" class="jugend-dialog" aria-labelledby="jugend-confirm-title" aria-describedby="jugend-confirm-text">
        <form method="dialog" class="jugend-dialog__form">
            <h2 id="jugend-confirm-title"><?php p($l->t('Nachricht senden?')); ?></h2>
            <p id="jugend-confirm-text" class="jugend-dialog__text"></p>
            <div class="jugend-dialog__actions">
                <button type="submit" class="button" value="cancel"><?php p($l->t('Abbrechen')); ?></button>
                <button type="submit" class="primary" value="confirm"><?php p($l->t('Jetzt senden')); ?></button>
            </div>
        </form>
    </dialog>
</div>
</div>
</div>
