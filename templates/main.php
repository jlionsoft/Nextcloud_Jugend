<?php
declare(strict_types=1);
/** @var array $_ */
/** @var \OCP\IL10N\IL10N $l */
$navigation = [
    ['label' => $l->t('Nachrichten'), 'url' => $_['homeUrl'], 'icon' => 'icon-comment', 'active' => true],
];
if (!empty($_['organizationUrl'])) {
    $navigation[] = ['label' => $l->t('Organisation'), 'url' => $_['organizationUrl'], 'icon' => 'icon-category-monitoring', 'active' => false];
}
if (!empty($_['groupsUrl'])) {
    $navigation[] = ['label' => $l->t('Arbeitsgruppen'), 'url' => $_['groupsUrl'], 'icon' => 'icon-group', 'active' => false];
}
if (!empty($_['settingsUrl'])) {
    $navigation[] = ['label' => $l->t('Einstellungen'), 'url' => $_['settingsUrl'], 'icon' => 'icon-settings', 'active' => false];
}
?>
<div id="jugend-vue-root" data-navigation="<?php p(json_encode($navigation, JSON_UNESCAPED_SLASHES)); ?>">
    <div id="jugend-vue-app"></div>
    <div class="jugend-server-fallback">
    <div id="app-navigation">
        <ul id="usergrouplist">
            <?php foreach ($navigation as $item) { ?>
                <li<?php if ($item['active']) { ?> class="active"<?php } ?>>
                    <a class="<?php p($item['icon']); ?>" href="<?php p($item['url']); ?>"<?php if ($item['active']) { ?> aria-current="page"<?php } ?>>
                        <?php p($item['label']); ?>
                    </a>
                </li>
            <?php } ?>
        </ul>
    </div>
    <div id="app-content" class="jugend-app-content">
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
                            <button id="jugend-send-button" type="submit" class="primary"><?php p($l->t('Nachricht an alle senden')); ?></button>
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
                </aside>
            </main>
        </div>
    </div>
    </div>
</div>
