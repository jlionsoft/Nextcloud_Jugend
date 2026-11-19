<?php
declare(strict_types=1);
/** @var array $_ */
/** @var \OCP\IL10N\IL10N $l */
$values = $_['values'];
$configured = $_['configured'];
$backUrl = $_['backUrl'];
$talkWebhookUrl = $_['talkWebhookUrl'];
?>
<div id="app-content">
<div id="jugend-settings" class="jugend-page">
    <header class="jugend-page__header">
        <div>
            <h1><?php p($l->t('Jugend – Einstellungen')); ?></h1>
            <p><?php p($l->t('Verwalte die Verbindungen und Zugangsdaten für den Nachrichtenversand.')); ?></p>
        </div>
        <a class="button" href="<?php p($backUrl); ?>"><?php p($l->t('Zurück zur Nachricht')); ?></a>
    </header>

    <form id="jugend-settings-form" class="jugend-settings-form" novalidate>
        <section class="jugend-panel jugend-settings-section">
            <div class="jugend-panel__heading">
                <div><h2>Authentik</h2><p><?php p($l->t('Empfängergruppe und SMS-Freigaben')); ?></p></div>
                <span class="jugend-service-mark">AUTH</span>
            </div>
            <div class="jugend-fields">
                <div class="jugend-field">
                    <label class="jugend-label" for="authentik_url"><?php p($l->t('Authentik-URL')); ?></label>
                    <input class="input-field" id="authentik_url" name="authentik_url" type="url" value="<?php p($values['authentik_url']); ?>" placeholder="https://auth.example.org">
                </div>
                <div class="jugend-field">
                    <label class="jugend-label" for="authentik_group_id"><?php p($l->t('Gruppen-ID')); ?></label>
                    <input class="input-field" id="authentik_group_id" name="authentik_group_id" value="<?php p($values['authentik_group_id']); ?>">
                </div>
                <div class="jugend-field jugend-field--wide">
                    <label class="jugend-label" for="authentik_token"><?php p($l->t('API-Token')); ?></label>
                    <input class="input-field" id="authentik_token" name="authentik_token" type="password" autocomplete="new-password" placeholder="<?php p($configured['authentik_token'] ? $l->t('Neues Token eingeben, um es zu ersetzen') : $l->t('Noch kein Token hinterlegt')); ?>">
                    <div class="jugend-secret-controls">
                        <span class="jugend-secret-status" data-secret-status="authentik_token" data-configured="<?php p($configured['authentik_token'] ? '1' : '0'); ?>"><?php p($configured['authentik_token'] ? $l->t('Token ist gespeichert') : $l->t('Kein Token gespeichert')); ?></span>
                        <button type="button" class="button jugend-secret-clear" data-secret="authentik_token" <?php if (!$configured['authentik_token']) { ?>disabled<?php } ?>><?php p($l->t('Token entfernen')); ?></button>
                        <input type="hidden" name="clear_authentik_token" value="0">
                    </div>
                    <p class="jugend-field-hint"><?php p($l->t('Das gespeicherte Token wird aus Sicherheitsgründen nicht angezeigt. Leer lassen behält den bisherigen Wert.')); ?></p>
                </div>
            </div>
            <div class="jugend-section-actions">
                <button type="button" class="button test-connection" data-channel="authentik"><?php p($l->t('Authentik-Verbindung testen')); ?></button>
                <span class="jugend-test-result" data-test-result="authentik"></span>
            </div>
        </section>

        <section class="jugend-panel jugend-settings-section">
            <div class="jugend-panel__heading">
                <div><h2>Nextcloud Talk</h2><p><?php p($l->t('Gruppenraum und Bot-Anbindung')); ?></p></div>
                <span class="jugend-service-mark">TALK</span>
            </div>
            <div class="jugend-fields">
                <div class="jugend-field">
                    <label class="jugend-label" for="talk_url"><?php p($l->t('Nextcloud-URL')); ?></label>
                    <input class="input-field" id="talk_url" name="talk_url" type="url" value="<?php p($values['talk_url']); ?>" placeholder="https://cloud.example.org">
                </div>
                <div class="jugend-field">
                    <label class="jugend-label" for="talk_room_token"><?php p($l->t('Talk-Raum-Token')); ?></label>
                    <input class="input-field" id="talk_room_token" name="talk_room_token" value="<?php p($values['talk_room_token']); ?>">
                </div>
                <div class="jugend-field jugend-field--wide">
                    <label class="jugend-label" for="talk_bot_secret"><?php p($l->t('Talk-Bot-Secret')); ?></label>
                    <input class="input-field" id="talk_bot_secret" name="talk_bot_secret" type="password" autocomplete="new-password" placeholder="<?php p($configured['talk_bot_secret'] ? $l->t('Neues Secret eingeben, um es zu ersetzen') : $l->t('Noch kein Secret hinterlegt')); ?>">
                    <div class="jugend-secret-controls">
                        <span class="jugend-secret-status" data-secret-status="talk_bot_secret" data-configured="<?php p($configured['talk_bot_secret'] ? '1' : '0'); ?>"><?php p($configured['talk_bot_secret'] ? $l->t('Secret ist gespeichert') : $l->t('Kein Secret gespeichert')); ?></span>
                        <button type="button" class="button jugend-secret-clear" data-secret="talk_bot_secret" <?php if (!$configured['talk_bot_secret']) { ?>disabled<?php } ?>><?php p($l->t('Secret entfernen')); ?></button>
                        <input type="hidden" name="clear_talk_bot_secret" value="0">
                    </div>
                    <p class="jugend-field-hint"><?php p($l->t('Leer lassen behält das bisherige Secret.')); ?></p>
                </div>
            </div>
            <div class="jugend-webhook">
                <label class="jugend-label"><?php p($l->t('Talk-Webhook-URL')); ?></label>
                <div class="jugend-copy-row"><code id="jugend-webhook-url"><?php p($talkWebhookUrl); ?></code><button type="button" class="button" id="jugend-copy-webhook"><?php p($l->t('Kopieren')); ?></button></div>
            </div>
            <div class="jugend-section-actions">
                <button type="button" class="button test-connection" data-channel="talk"><?php p($l->t('Talk-Testnachricht senden')); ?></button>
                <span class="jugend-test-result" data-test-result="talk"></span>
            </div>
        </section>

        <section class="jugend-panel jugend-settings-section">
            <div class="jugend-panel__heading">
                <div><h2>Matrix</h2><p><?php p($l->t('Persönliche Direktnachrichten an alle Mitglieder der Jugendgruppe')); ?></p></div>
                <span class="jugend-service-mark">MATRIX</span>
            </div>
            <div class="jugend-fields">
                <div class="jugend-field">
                    <label class="jugend-label" for="matrix_homeserver"><?php p($l->t('Homeserver-URL')); ?></label>
                    <input class="input-field" id="matrix_homeserver" name="matrix_homeserver" type="url" value="<?php p($values['matrix_homeserver']); ?>" placeholder="https://matrix.example.org">
                </div>
                <div class="jugend-field">
                    <label class="jugend-label" for="matrix_server_name"><?php p($l->t('Matrix-Servername')); ?></label>
                    <input class="input-field" id="matrix_server_name" name="matrix_server_name" value="<?php p($values['matrix_server_name']); ?>" placeholder="matrix.example.org">
                    <p class="jugend-field-hint"><?php p($l->t('Domainanteil der Matrix-Benutzer-IDs, ohne @ und Doppelpunkt.')); ?></p>
                </div>
                <div class="jugend-field jugend-field--wide">
                    <label class="jugend-label" for="matrix_bot_user_id"><?php p($l->t('Bot-Benutzer-ID')); ?></label>
                    <input class="input-field" id="matrix_bot_user_id" name="matrix_bot_user_id" value="<?php p($values['matrix_bot_user_id']); ?>" placeholder="@nachrichten:matrix.example.org">
                </div>
                <div class="jugend-field jugend-field--wide">
                    <label class="jugend-label" for="matrix_bot_token"><?php p($l->t('Bot-Access-Token')); ?></label>
                    <input class="input-field" id="matrix_bot_token" name="matrix_bot_token" type="password" autocomplete="new-password" placeholder="<?php p($configured['matrix_bot_token'] ? $l->t('Neues Token eingeben, um es zu ersetzen') : $l->t('Noch kein Token hinterlegt')); ?>">
                    <div class="jugend-secret-controls">
                        <span class="jugend-secret-status" data-secret-status="matrix_bot_token" data-configured="<?php p($configured['matrix_bot_token'] ? '1' : '0'); ?>"><?php p($configured['matrix_bot_token'] ? $l->t('Token ist gespeichert') : $l->t('Kein Token gespeichert')); ?></span>
                        <button type="button" class="button jugend-secret-clear" data-secret="matrix_bot_token" <?php if (!$configured['matrix_bot_token']) { ?>disabled<?php } ?>><?php p($l->t('Token entfernen')); ?></button>
                        <input type="hidden" name="clear_matrix_bot_token" value="0">
                    </div>
                    <p class="jugend-field-hint"><?php p($l->t('Das Token wird nicht angezeigt. Leer lassen behält den bisherigen Wert.')); ?></p>
                </div>
            </div>
            <div class="jugend-section-actions">
                <button type="button" class="button test-connection" data-channel="matrix"><?php p($l->t('Matrix-Verbindung testen')); ?></button>
                <span class="jugend-test-result" data-test-result="matrix"></span>
            </div>
        </section>

        <section class="jugend-panel jugend-settings-section">
            <div class="jugend-panel__heading">
                <div><h2>MikroTik SMS-Gateway</h2><p><?php p($l->t('RouterOS REST API und LTE-Schnittstelle')); ?></p></div>
                <span class="jugend-service-mark">SMS</span>
            </div>
            <div class="jugend-fields">
                <div class="jugend-field">
                    <label class="jugend-label" for="sms_gateway_url"><?php p($l->t('Gateway-URL')); ?></label>
                    <input class="input-field" id="sms_gateway_url" name="sms_gateway_url" type="url" value="<?php p($values['sms_gateway_url']); ?>" placeholder="http://192.168.178.100">
                </div>
                <div class="jugend-field">
                    <label class="jugend-label" for="sms_interface"><?php p($l->t('LTE-Interface')); ?></label>
                    <input class="input-field" id="sms_interface" name="sms_interface" value="<?php p($values['sms_interface'] ?: 'lte1'); ?>" placeholder="lte1">
                </div>
                <div class="jugend-field">
                    <label class="jugend-label" for="sms_username"><?php p($l->t('RouterOS-Benutzername')); ?></label>
                    <input class="input-field" id="sms_username" name="sms_username" value="<?php p($values['sms_username']); ?>" autocomplete="username">
                </div>
                <div class="jugend-field">
                    <label class="jugend-label" for="sms_password"><?php p($l->t('RouterOS-Passwort')); ?></label>
                    <input class="input-field" id="sms_password" name="sms_password" type="password" autocomplete="new-password" placeholder="<?php p($configured['sms_password'] ? $l->t('Neues Passwort eingeben, um es zu ersetzen') : $l->t('Noch kein Passwort hinterlegt')); ?>">
                    <div class="jugend-secret-controls">
                        <span class="jugend-secret-status" data-secret-status="sms_password" data-configured="<?php p($configured['sms_password'] ? '1' : '0'); ?>"><?php p($configured['sms_password'] ? $l->t('Passwort ist gespeichert') : $l->t('Kein Passwort gespeichert')); ?></span>
                        <button type="button" class="button jugend-secret-clear" data-secret="sms_password" <?php if (!$configured['sms_password']) { ?>disabled<?php } ?>><?php p($l->t('Passwort entfernen')); ?></button>
                        <input type="hidden" name="clear_sms_password" value="0">
                    </div>
                </div>
            </div>
            <div class="jugend-section-actions">
                <button type="button" class="button test-connection" data-channel="sms"><?php p($l->t('SMS-Gateway-Verbindung testen')); ?></button>
                <span class="jugend-test-result" data-test-result="sms"></span>
            </div>
        </section>

        <div class="jugend-settings-footer">
            <p id="jugend-settings-feedback" class="jugend-feedback" role="status" aria-live="polite"></p>
            <button type="submit" class="primary" id="jugend-save-settings"><?php p($l->t('Alle Einstellungen speichern')); ?></button>
        </div>
    </form>
</div>
</div>
