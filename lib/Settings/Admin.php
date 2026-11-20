<?php
declare(strict_types=1);
namespace OCA\Jugend\Settings;

use OCA\Jugend\AppInfo\Application;
use OCA\Jugend\Controller\SettingsController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;
use OCP\Util;

/**
 * Admin settings form, shown in Nextcloud's administration settings.
 */
class Admin implements ISettings {
    private const PLAIN_KEYS = [
        'authentik_url', 'authentik_group_id',
        'talk_url', 'talk_room_token',
        'sms_gateway_url', 'sms_username', 'sms_interface',
        'matrix_homeserver', 'matrix_server_name', 'matrix_bot_user_id',
        'provisioning_url', 'provisioning_user',
    ];

    public function __construct(
        private IAppConfig $appConfig,
        private IURLGenerator $urlGenerator,
        private IInitialState $initialState,
        private IL10N $l,
    ) {
    }

    public function getForm(): TemplateResponse {
        Util::addScript(Application::APP_ID, 'settings');
        Util::addStyle(Application::APP_ID, 'main');

        $configured = [];
        foreach (SettingsController::SECRET_KEYS as $key) {
            $configured[$key] = $this->appConfig->getValueString(Application::APP_ID, $key) !== '';
        }
        $values = [];
        foreach (self::PLAIN_KEYS as $key) {
            $values[$key] = $this->appConfig->getValueString(Application::APP_ID, $key);
        }

        $this->initialState->provideInitialState('urls', [
            'save' => $this->urlGenerator->linkToRoute('jugend.settings.save'),
            'test' => $this->urlGenerator->linkToRoute('jugend.settings.test'),
        ]);
        $this->initialState->provideInitialState('strings', [
            'secretRemovePending' => $this->l->t('Wird beim Speichern entfernt'),
            'secretStored' => $this->l->t('Zugangsdaten sind gespeichert'),
            'secretNone' => $this->l->t('Keine Zugangsdaten gespeichert'),
            'undoRemove' => $this->l->t('Entfernen rückgängig'),
            'removeCredentials' => $this->l->t('Zugangsdaten entfernen'),
            'requestFailed' => $this->l->t('Die Anfrage ist fehlgeschlagen (HTTP {status}).'),
            'saving' => $this->l->t('Einstellungen werden gespeichert …'),
            'saveFailed' => $this->l->t('Speichern fehlgeschlagen.'),
            'saved' => $this->l->t('Alle Einstellungen wurden gespeichert.'),
            'testing' => $this->l->t('Speichere Einstellungen und prüfe Verbindung …'),
            'testFailed' => $this->l->t('Verbindungstest fehlgeschlagen.'),
            'testOk' => $this->l->t('Verbindung erfolgreich.'),
            'savedAndTestOk' => $this->l->t('Einstellungen gespeichert. Verbindungstest erfolgreich.'),
            'copied' => $this->l->t('Kopiert'),
            'copyFailed' => $this->l->t('Kopieren nicht möglich. Bitte die Webhook-URL manuell markieren.'),
        ]);

        return new TemplateResponse(Application::APP_ID, 'settings', [
            'values' => $values,
            'configured' => $configured,
            'talkWebhookUrl' => $this->urlGenerator->linkToRouteAbsolute('jugend.talkwebhook.webhook'),
        ], '');
    }

    public function getSection(): string {
        return AdminSection::SECTION_ID;
    }

    public function getPriority(): int {
        return 50;
    }
}
