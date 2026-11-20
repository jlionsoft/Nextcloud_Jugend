<?php
declare(strict_types=1);

namespace OCA\\Jugend\\Service;

use OCP\\Http\\Client\\IClientService;
use OCP\\IAppConfig;
use RuntimeException;

/**
 * Uses a dedicated Nextcloud service account for provisioning resources.
 * The app password is stored using Nextcloud's encrypted app-config value.
 */
class ProvisioningService {
    private const USER_KEY = 'provisioning_user';
    private const URL_KEY = 'provisioning_url';
    private const PASSWORD_KEY = 'provisioning_app_password';

    public function __construct(
        private IAppConfig $config,
        private IClientService $httpClient,
    ) {
    }

    public function isConfigured(): bool {
        return $this->baseUrl() !== ''
            && $this->config->getValueString('jugend', self::USER_KEY) !== ''
            && $this->config->getValueString('jugend', self::PASSWORD_KEY) !== '';
    }

    public function testConnection(): array {
        $response = $this->request('GET', '/ocs/v2.php/cloud/capabilities?format=json');
        $payload = json_decode((string)$response->getBody(), true);
        $meta = $payload['ocs']['meta'] ?? [];
        if ((int)($meta['statuscode'] ?? 0) !== 100) {
            throw new RuntimeException('Nextcloud hat die OCS-Anfrage nicht erfolgreich beantwortet.');
        }
        return [
            'ok' => true,
            'message' => 'Dienstkonto erfolgreich authentifiziert.',
            'serverVersion' => $payload['ocs']['data']['version']['string'] ?? null,
        ];
    }

    /**
     * Creates a private Talk group room. Participants are deliberately added
     * separately by the resource orchestrator after membership reconciliation.
     *
     * @return array{token:string,roomId?:int}
     */
    public function createTalkRoom(string $name): array {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Der Talk-Raumname ist ungültig.');
        }
        $response = $this->request('POST', '/ocs/v2.php/apps/spreed/api/v4/room', [
            'roomType' => 2,
            'roomName' => $name,
        ]);
        $payload = json_decode((string)$response->getBody(), true);
        $data = $payload['ocs']['data'] ?? [];
        $token = (string)($data['token'] ?? '');
        if ((int)($payload['ocs']['meta']['statuscode'] ?? 0) !== 100 || $token === '') {
            throw new RuntimeException('Nextcloud Talk hat keinen gültigen Raum zurückgegeben.');
        }
        return ['token' => $token, 'roomId' => isset($data['id']) ? (int)$data['id'] : null];
    }

    private function request(string $method, string $path, array $form = []): \\\\Psr\\\\Http\\\\Message\\\\ResponseInterface {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Das Nextcloud-Provisioning-Dienstkonto ist noch nicht vollständig eingerichtet.');
        }
        $options = [
            'auth' => [
                $this->config->getValueString('jugend', self::USER_KEY),
                $this->config->getValueString('jugend', self::PASSWORD_KEY),
            ],
            'headers' => ['OCS-APIRequest' => 'true', 'Accept' => 'application/json'],
            'timeout' => 20,
        ];
        if ($form !== []) {
            $options['form_params'] = $form;
        }
        $client = $this->httpClient->newClient();
        $response = $client->request($method, $this->baseUrl() . $path, $options);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException('Nextcloud-API antwortet mit HTTP ' . $response->getStatusCode() . '.');
        }
        return $response;
    }

    private function baseUrl(): string {
        return rtrim($this->config->getValueString('jugend', self::URL_KEY), '/');
    }
}
