<?php
declare(strict_types=1);
namespace OCA\Jugend\Service;

use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use RuntimeException;

class NextcloudTalkService {
    public function __construct(private IClientService $http, private IAppConfig $config) {}

    public function testConnection(): array {
        $this->send('Verbindungstest der Jugend-App.');
        return ['ok' => true, 'message' => 'Nextcloud-Talk-Bot hat die Testnachricht erfolgreich gesendet.'];
    }

    public function send(string $message): void {
        $this->sendToRoom($this->required('talk_room_token'), $message);
    }

    public function sendToRoom(string $room, string $message): void {
        $base = rtrim($this->required('talk_url'), '/') . '/';
        $secret = $this->required('talk_bot_secret');
        $random = bin2hex(random_bytes(32));
        $signature = hash_hmac('sha256', $random . $message, $secret);
        $url = $base . 'ocs/v2.php/apps/spreed/api/v1/bot/' . rawurlencode($room) . '/message?format=json';
        $response = $this->http->newClient()->post($url, [
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Nextcloud-Talk-Bot-Random' => $random,
                'X-Nextcloud-Talk-Bot-Signature' => $signature,
            ],
            'body' => json_encode(['message' => $message], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'timeout' => 15,
        ]);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException('Nextcloud Talk antwortete mit HTTP ' . $response->getStatusCode() . '.');
        }
        $data = json_decode(is_resource($response->getBody()) ? (string)stream_get_contents($response->getBody()) : (string)$response->getBody(), true);
        if (isset($data['ocs']['meta']['statuscode']) && (int)$data['ocs']['meta']['statuscode'] !== 100) {
            throw new RuntimeException('Nextcloud Talk meldet: ' . (string)($data['ocs']['meta']['message'] ?? 'unbekannter Fehler'));
        }
    }

    private function required(string $key): string {
        $value = trim($this->config->getValueString('jugend', $key));
        if ($value === '') { throw new RuntimeException('Einstellung fehlt: ' . $key); }
        return $value;
    }
}
