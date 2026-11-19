<?php
declare(strict_types=1);
namespace OCA\Jugend\Controller;

use OCA\Jugend\Service\AuthentikService;
use OCA\Jugend\Service\NextcloudTalkService;
use OCA\Jugend\Service\SmsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;

class SettingsController extends Controller {
    private const SECRET_KEYS = ['authentik_token', 'talk_bot_secret', 'sms_password'];

    public function __construct(
        string $appName,
        IRequest $request,
        private IAppConfig $appConfig,
        private AuthentikService $authentik,
        private NextcloudTalkService $talk,
        private SmsService $sms,
        private IURLGenerator $urlGenerator,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @AdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        Util::addScript($this->appName, 'settings');
        Util::addStyle($this->appName, 'main');
        $configured = [];
        foreach (self::SECRET_KEYS as $key) {
            $configured[$key] = $this->appConfig->getValueString($this->appName, $key) !== '';
        }
        $values = [];
        foreach (['authentik_url', 'authentik_group_id', 'talk_url', 'talk_room_token', 'sms_gateway_url', 'sms_username', 'sms_interface'] as $key) {
            $values[$key] = $this->appConfig->getValueString($this->appName, $key);
        }
        return new TemplateResponse($this->appName, 'settings', [
            'values' => $values,
            'configured' => $configured,
            'backUrl' => $this->urlGenerator->linkToRoute('jugend.page.index'),
            'talkWebhookUrl' => $this->urlGenerator->linkToRouteAbsolute('jugend.talkwebhook.webhook'),
        ]);
    }

    /**
     * @AdminRequired
     */
    public function save(
        string $authentik_url = '', string $authentik_group_id = '', string $authentik_token = '',
        string $talk_url = '', string $talk_room_token = '', string $talk_bot_secret = '',
        string $sms_gateway_url = '', string $sms_username = '', string $sms_password = '',
        string $sms_interface = 'lte1',
        bool $clear_authentik_token = false,
        bool $clear_talk_bot_secret = false,
        bool $clear_sms_password = false,
    ): JSONResponse {
        foreach (['authentik_url' => $authentik_url, 'talk_url' => $talk_url, 'sms_gateway_url' => $sms_gateway_url] as $key => $url) {
            $url = trim($url);
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            if ($url !== '' && (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true))) {
                return new JSONResponse(['saved' => false, 'message' => 'Ungültige URL in ' . $key . '.'], 400);
            }
        }
        $plain = [
            'authentik_url' => trim($authentik_url),
            'authentik_group_id' => trim($authentik_group_id),
            'talk_url' => trim($talk_url),
            'talk_room_token' => trim($talk_room_token),
            'sms_gateway_url' => trim($sms_gateway_url),
            'sms_username' => trim($sms_username),
            'sms_interface' => trim($sms_interface) ?: 'lte1',
        ];
        foreach ($plain as $key => $value) {
            $this->appConfig->setValueString($this->appName, $key, $value);
        }
        $secrets = [
            'authentik_token' => [$authentik_token, $clear_authentik_token],
            'talk_bot_secret' => [$talk_bot_secret, $clear_talk_bot_secret],
            'sms_password' => [$sms_password, $clear_sms_password],
        ];
        foreach ($secrets as $key => [$value, $clear]) {
            if ($clear) {
                $this->appConfig->deleteKey($this->appName, $key);
            } elseif ($value !== '') {
                $this->appConfig->setValueString($this->appName, $key, $value, false, true);
            }
        }
        return new JSONResponse(['saved' => true]);
    }

    /**
     * @AdminRequired
     */
    public function test(string $channel = ''): JSONResponse {
        try {
            $result = match ($channel) {
                'authentik' => $this->authentik->testConnection(),
                'sms' => $this->sms->testConnection(),
                'talk' => $this->talk->testConnection(),
                default => throw new \InvalidArgumentException('Unbekannter Verbindungstyp.'),
            };
            return new JSONResponse($result);
        } catch (\Throwable $e) {
            return new JSONResponse(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
