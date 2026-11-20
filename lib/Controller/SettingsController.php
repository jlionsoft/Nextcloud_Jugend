<?php
declare(strict_types=1);
namespace OCA\Jugend\Controller;

use OCA\Jugend\Service\AuthentikService;
use OCA\Jugend\Service\MatrixService;
use OCA\Jugend\Service\NextcloudTalkService;
use OCA\Jugend\Service\ProvisioningService;
use OCA\Jugend\Service\SmsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;

class SettingsController extends Controller {
    public const SECRET_KEYS = ['authentik_token', 'talk_bot_secret', 'sms_password', 'matrix_bot_token', 'provisioning_app_password'];

    public function __construct(
        string $appName,
        IRequest $request,
        private IAppConfig $appConfig,
        private AuthentikService $authentik,
        private MatrixService $matrix,
        private NextcloudTalkService $talk,
        private SmsService $sms,
        private ProvisioningService $provisioning,
        private IL10N $l,
    ) {
        parent::__construct($appName, $request);
    }

    public function save(
        string $authentik_url = '', string $authentik_group_id = '', string $authentik_token = '',
        string $talk_url = '', string $talk_room_token = '', string $talk_bot_secret = '',
        string $sms_gateway_url = '', string $sms_username = '', string $sms_password = '',
        string $sms_interface = 'lte1',
        string $matrix_homeserver = '', string $matrix_server_name = '', string $matrix_bot_user_id = '',
        string $matrix_bot_token = '',
        string $provisioning_url = '', string $provisioning_user = '', string $provisioning_app_password = '',
        string $provisioning_group_id = '', string $department_group_id = '',
        bool $clear_provisioning_app_password = false,
        bool $clear_authentik_token = false,
        bool $clear_talk_bot_secret = false,
        bool $clear_sms_password = false,
        bool $clear_matrix_bot_token = false,
    ): JSONResponse {
        $urls = [
            'authentik_url' => $authentik_url,
            'talk_url' => $talk_url,
            'sms_gateway_url' => $sms_gateway_url,
            'matrix_homeserver' => $matrix_homeserver,
            'provisioning_url' => $provisioning_url,
        ];
        foreach ($urls as $key => $url) {
            $url = trim($url);
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            if ($url !== '' && (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true))) {
                return new JSONResponse(['saved' => false, 'message' => $this->l->t('Ungültige URL in %s.', [$key])], 400);
            }
        }
        if (trim($provisioning_url) !== '' && strtolower((string)parse_url(trim($provisioning_url), PHP_URL_SCHEME)) !== 'https') {
            return new JSONResponse(['saved' => false, 'message' => 'Die Provisioning-Verbindung muss HTTPS verwenden.'], 400);
        }
        $plain = [
            'authentik_url' => trim($authentik_url),
            'authentik_group_id' => trim($authentik_group_id),
            'talk_url' => trim($talk_url),
            'talk_room_token' => trim($talk_room_token),
            'sms_gateway_url' => trim($sms_gateway_url),
            'sms_username' => trim($sms_username),
            'sms_interface' => trim($sms_interface) ?: 'lte1',
            'matrix_homeserver' => rtrim(trim($matrix_homeserver), '/'),
            'matrix_server_name' => trim($matrix_server_name),
            'matrix_bot_user_id' => trim($matrix_bot_user_id),
            'provisioning_url' => rtrim(trim($provisioning_url), '/'),
            'provisioning_user' => trim($provisioning_user),
            'provisioning_group_id' => trim($provisioning_group_id),
            'department_group_id' => trim($department_group_id),
        ];
        foreach ($plain as $key => $value) {
            $this->appConfig->setValueString($this->appName, $key, $value);
        }
        $secrets = [
            'authentik_token' => [$authentik_token, $clear_authentik_token],
            'talk_bot_secret' => [$talk_bot_secret, $clear_talk_bot_secret],
            'sms_password' => [$sms_password, $clear_sms_password],
            'matrix_bot_token' => [$matrix_bot_token, $clear_matrix_bot_token],
            'provisioning_app_password' => [$provisioning_app_password, $clear_provisioning_app_password],
        ];
        foreach ($secrets as $key => [$value, $clear]) {
            if ($clear) {
                $this->appConfig->deleteKey($this->appName, $key);
            } elseif ($value !== '') {
                $this->appConfig->setValueString($this->appName, $key, $value, false, true);
            }
        }
        $configured = [];
        foreach (self::SECRET_KEYS as $key) {
            $configured[$key] = $this->appConfig->getValueString($this->appName, $key) !== '';
        }
        return new JSONResponse(['saved' => true, 'configured' => $configured]);
    }

    public function test(string $channel = ''): JSONResponse {
        try {
            $result = match ($channel) {
                'authentik' => $this->authentik->testConnection(),
                'matrix' => $this->matrix->testConnection(),
                'sms' => $this->sms->testConnection(),
                'talk' => $this->talk->testConnection(),
                'provisioning' => $this->provisioning->testConnection(),
                default => throw new \InvalidArgumentException($this->l->t('Unbekannter Verbindungstyp.')),
            };
            return new JSONResponse($result);
        } catch (\Throwable $e) {
            return new JSONResponse(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
