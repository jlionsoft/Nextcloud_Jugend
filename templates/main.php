<?php
declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N\IL10N $l */
?>

<div id="jugend-app" class="section">
    <div class="jugend-header">
        <h1><?php p($l->t('Jugend-Nachricht versenden')); ?></h1>
        <p><?php p($l->t('Verfasse eine Nachricht für die Jugendgruppe.')); ?></p>
    </div>

    <p><a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('jugend.settings.index')); ?>"><?php p($l->t('Einstellungen (nur Nextcloud-Admins)')); ?></a></p>

    <form id="jugend-message-form" class="jugend-card">
        <label for="jugend-message"><?php p($l->t('Nachricht')); ?></label>
        <textarea
            id="jugend-message"
            name="message"
            rows="8"
            maxlength="5000"
            required
            placeholder="<?php p($l->t('Deine Nachricht …')); ?>"
        ></textarea>

        <div class="jugend-message-meta">
            <span id="jugend-sms-estimate"><?php p($l->t('SMS: 0 Segmente')); ?></span>
            <span id="jugend-character-count">0 / 5000</span>
        </div>

        <div class="jugend-actions">
            <button id="jugend-send-button" type="submit" class="primary">
                <?php p($l->t('An alle senden')); ?>
            </button>
        </div>
        <p id="jugend-feedback" class="jugend-feedback" role="status" aria-live="polite"></p>
    </form>
</div>
