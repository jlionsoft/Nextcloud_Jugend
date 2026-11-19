<?php
declare(strict_types=1);
namespace OCA\\Jugend\\Controller;

use OCA\\Jugend\\Service\\AuthentikService;
use OCA\\Jugend\\Service\\NextcloudTalkService;
use OCA\\Jugend\\Service\\SmsService;
use OCP\\AppFramework\\Controller;
use OCP\\AppFramework\\Http\\JSONResponse;
use OCP\\AppFramework\\Http\\TemplateResponse;
use OCP\\IAppConfig;
use OCP\\IRequest;
use OCP\\Util;

class SettingsController extends Controller {
    private const SECRET_KEYS = ['authentik_token', 'talk_bot_secret', 'sms_password'];

    public function __construct(
        string $appName,
        IRequest $request,
        private IAppConfig $appConfig,
        private AuthentikService $authentik,
        private NextcloudTalkService $talk,
        private SmsService $sms,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @AdminRequired
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
        return new TemplateResponse($this->appName, 'settings', ['values' => $values, 'configured' => $configured]);
    }

    /**
     * @AdminRequired
     */
    public function save(
        string $authentik_url = '', string $authentik_group_id = '', string $authentik_token = '',
        string $talk_url = '', string $talk_room_token = '', string $talk_bot_secret = '',
        string $sms_gateway_url = '', string $sms_username = '', string $sms_password = '',
        string $sms_interface = 'lte1',
    ): JSONResponse {
        $plain = [
            'authentik_url' => trim($authentik_url), 'authentik_group_id' => trim($authentik_group_id),
            'talk_url' => trim($talk_url), 'talk_room_token' => trim($talk_room_token),
            'sms_gateway_url' => trim($sms_gateway_url), 'sms_username' => trim($sms_username),
            'sms_interface' => trim($sms_interface) ?: 'lte1',
        ];
        foreach ($plain as $key => $value) {
            $this->appConfig->setValueString($this->appName, $key, $value);
        }
        foreach (['authentik_token' => $authentik_token, 'talk_bot_secret' => $talk_bot_secret, 'sms_password' => $sms_password] as $key => $value) {
            if ($value !== '') {
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
                default => throw new \\InvalidArgumentException('Unbekannter Verbindungstyp.'),
            };
            return new JSONResponse($result);
        } catch (\\Throwable $e) {
            return new JSONResponse(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
