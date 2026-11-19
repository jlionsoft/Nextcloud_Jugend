<?php
declare(strict_types=1);

namespace OCA\Jugend\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\Util;

class SettingsController extends Controller {
    private const SECRET_KEYS = [
        'authentik_token',
        'talk_bot_secret',
        'sms_password',
    ];

    public function __construct(
        string $appName,
        IRequest $request,
        private IAppConfig $appConfig,
    ) {
        parent::__construct($appName, $request);
    }

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
        ]);
    }

    public function save(
        string $authentik_url = '',
        string $authentik_group_id = '',
        string $authentik_token = '',
        string $talk_url = '',
        string $talk_room_token = '',
        string $talk_bot_secret = '',
        string $sms_gateway_url = '',
        string $sms_username = '',
        string $sms_password = '',
        string $sms_interface = 'lte1',
    ): JSONResponse {
        $plainValues = [
            'authentik_url' => $authentik_url,
            'authentik_group_id' => $authentik_group_id,
            'talk_url' => $talk_url,
            'talk_room_token' => $talk_room_token,
            'sms_gateway_url' => $sms_gateway_url,
            'sms_username' => $sms_username,
            'sms_interface' => $sms_interface,
        ];

        foreach ($plainValues as $key => $value) {
            $this->appConfig->setValueString($this->appName, $key, trim($value));
        }

        $secrets = [
            'authentik_token' => $authentik_token,
            'talk_bot_secret' => $talk_bot_secret,
            'sms_password' => $sms_password,
        ];

        foreach ($secrets as $key => $value) {
            if ($value !== '') {
                $this->appConfig->setValueString($this->appName, $key, $value, false, true);
            }
        }

        return new JSONResponse(['saved' => true]);
    }
}
