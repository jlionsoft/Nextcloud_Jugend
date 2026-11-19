<?php
declare(strict_types=1);
/** @var array $_ */
/** @var \OCP\IL10N\IL10N $l */
$values = $_['values']; $configured = $_['configured']; $backUrl = $_['backUrl']; $talkWebhookUrl = $_['talkWebhookUrl'];
?>
<div id="jugend-settings" class="section">
    <h1><?php p($l->t('Jugend – Einstellungen')); ?></h1>
    <p><?php p($l->t('Verbindungsdaten und Zugangsdaten der Nachrichtenkanäle verwalten.')); ?></p>
    <form id="jugend-settings-form" class="jugend-card">
        <h2>Authentik – Empfänger</h2>
        <label for="authentik_url">Authentik-URL</label><input id="authentik_url" name="authentik_url" type="url" value="<?php p($values['authentik_url']); ?>" placeholder="https://auth.example.org">
        <label for="authentik_group_id">Gruppen-ID</label><input id="authentik_group_id" name="authentik_group_id" value="<?php p($values['authentik_group_id']); ?>">
        <label for="authentik_token">API-Token</label><input id="authentik_token" name="authentik_token" type="password" autocomplete="new-password" placeholder="<?php p($configured['authentik_token'] ? 'Token gespeichert – leer lassen zum Beibehalten' : 'Noch kein Token gespeichert'); ?>"><label><input type="checkbox" name="clear_authentik_token" value="1"> Gespeichertes Token löschen</label>
        <button type="button" class="button test-connection" data-channel="authentik">Authentik-Verbindung testen</button>
        <hr>
        <h2>Nextcloud Talk</h2>
        <label for="talk_url">Nextcloud-URL</label><input id="talk_url" name="talk_url" type="url" value="<?php p($values['talk_url']); ?>" placeholder="https://cloud.example.org">
        <label for="talk_room_token">Talk-Raum-Token</label><input id="talk_room_token" name="talk_room_token" value="<?php p($values['talk_room_token']); ?>">
        <label for="talk_bot_secret">Talk-Bot-Secret</label><input id="talk_bot_secret" name="talk_bot_secret" type="password" autocomplete="new-password" placeholder="<?php p($configured['talk_bot_secret'] ? 'Secret gespeichert – leer lassen zum Beibehalten' : 'Noch kein Secret gespeichert'); ?>"><label><input type="checkbox" name="clear_talk_bot_secret" value="1"> Gespeichertes Secret löschen</label>
        <button type="button" class="button test-connection" data-channel="talk">Talk-Testnachricht senden</button>
        <p>Webhook-URL für die Talk-Bot-Konfiguration:</p><code><?php p($talkWebhookUrl); ?></code>
        <hr>
        <h2>MikroTik SMS-Gateway</h2>
        <label for="sms_gateway_url">Gateway-URL</label><input id="sms_gateway_url" name="sms_gateway_url" type="url" value="<?php p($values['sms_gateway_url']); ?>" placeholder="http://192.168.178.100">
        <label for="sms_username">RouterOS-Benutzername</label><input id="sms_username" name="sms_username" value="<?php p($values['sms_username']); ?>" autocomplete="username">
        <label for="sms_password">RouterOS-Passwort</label><input id="sms_password" name="sms_password" type="password" autocomplete="new-password" placeholder="<?php p($configured['sms_password'] ? 'Passwort gespeichert – leer lassen zum Beibehalten' : 'Noch kein Passwort gespeichert'); ?>"><label><input type="checkbox" name="clear_sms_password" value="1"> Gespeichertes Passwort löschen</label>
        <label for="sms_interface">LTE-Interface</label><input id="sms_interface" name="sms_interface" value="<?php p($values['sms_interface'] ?: 'lte1'); ?>">
        <button type="button" class="button test-connection" data-channel="sms">SMS-Gateway-Verbindung testen</button>
        <div class="jugend-actions"><button type="submit" class="primary">Einstellungen speichern</button><a class="button" href="<?php p($backUrl); ?>">Zurück zu Nachrichten</a></div>
        <p id="jugend-settings-feedback" role="status" aria-live="polite"></p>
    </form>
    <p class="settings-hint">Zugangsdaten werden nicht erneut angezeigt. Leere Passwortfelder behalten gespeicherte Werte. Verbindungstests setzen gespeicherte Einstellungen voraus.</p>
</div>
