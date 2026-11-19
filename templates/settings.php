<?php
declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N\IL10N $l */
$values = $_['values'];
$configured = $_['configured'];
?>

<div id="jugend-settings" class="section">
    <h1><?php p($l->t('Jugend – Einstellungen')); ?></h1>
    <p><?php p($l->t('Verbindungsdaten und Zugangsdaten der Nachrichtenkanäle verwalten.')); ?></p>

    <form id="jugend-settings-form" class="jugend-card">
        <h2><?php p($l->t('Authentik – Empfänger')); ?></h2>
        <label for="authentik_url"><?php p($l->t('Authentik-URL')); ?></label>
        <input id="authentik_url" name="authentik_url" type="url" value="<?php p($values['authentik_url']); ?>" placeholder="https://auth.example.org" />
        <label for="authentik_group_id"><?php p($l->t('Gruppen-ID')); ?></label>
        <input id="authentik_group_id" name="authentik_group_id" type="text" value="<?php p($values['authentik_group_id']); ?>" />
        <label for="authentik_token"><?php p($l->t('API-Token')); ?></label>
        <input id="authentik_token" name="authentik_token" type="password" autocomplete="new-password" placeholder="<?php p($configured['authentik_token'] ? $l->t('Token ist gespeichert – leer lassen, um ihn beizubehalten') : $l->t('Noch kein Token gespeichert')); ?>" />

        <hr />
        <h2><?php p($l->t('Nextcloud Talk')); ?></h2>
        <label for="talk_url"><?php p($l->t('Nextcloud-URL')); ?></label>
        <input id="talk_url" name="talk_url" type="url" value="<?php p($values['talk_url']); ?>" placeholder="https://cloud.example.org" />
        <label for="talk_room_token"><?php p($l->t('Talk-Raum-Token')); ?></label>
        <input id="talk_room_token" name="talk_room_token" type="text" value="<?php p($values['talk_room_token']); ?>" />
        <label for="talk_bot_secret"><?php p($l->t('Talk-Bot-Secret')); ?></label>
        <input id="talk_bot_secret" name="talk_bot_secret" type="password" autocomplete="new-password" placeholder="<?php p($configured['talk_bot_secret'] ? $l->t('Secret ist gespeichert – leer lassen, um es beizubehalten') : $l->t('Noch kein Secret gespeichert')); ?>" />

        <hr />
        <h2><?php p($l->t('MikroTik SMS-Gateway')); ?></h2>
        <label for="sms_gateway_url"><?php p($l->t('Gateway-URL')); ?></label>
        <input id="sms_gateway_url" name="sms_gateway_url" type="url" value="<?php p($values['sms_gateway_url']); ?>" placeholder="http://192.168.178.100" />
        <label for="sms_username"><?php p($l->t('RouterOS-Benutzername')); ?></label>
        <input id="sms_username" name="sms_username" type="text" value="<?php p($values['sms_username']); ?>" autocomplete="username" />
        <label for="sms_password"><?php p($l->t('RouterOS-Passwort')); ?></label>
        <input id="sms_password" name="sms_password" type="password" autocomplete="new-password" placeholder="<?php p($configured['sms_password'] ? $l->t('Passwort ist gespeichert – leer lassen, um es beizubehalten') : $l->t('Noch kein Passwort gespeichert')); ?>" />
        <label for="sms_interface"><?php p($l->t('LTE-Interface')); ?></label>
        <input id="sms_interface" name="sms_interface" type="text" value="<?php p($values['sms_interface'] ?: 'lte1'); ?>" />

        <div class="jugend-actions">
            <button type="submit" class="primary"><?php p($l->t('Einstellungen speichern')); ?></button>
            <a class="button" href="<?php p(\OC\Util::linkToRoute('jugend.page.index')); ?>"><?php p($l->t('Zurück zu Nachrichten')); ?></a>
        </div>
        <p id="jugend-settings-feedback" role="status" aria-live="polite"></p>
    </form>
    <p class="settings-hint"><?php p($l->t('Zugangsdaten werden nicht erneut im Formular angezeigt. Leere Passwortfelder lassen bereits gespeicherte Zugangsdaten unverändert.')); ?></p>
</div>
