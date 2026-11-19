<?php
declare(strict_types=1);
namespace OCA\Jugend\Service;

use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use RuntimeException;

class MatrixService {
    public function __construct(private IClientService $http, private IAppConfig $config) {}

    public function testConnection(): array {
        $identity = $this->requestJson('GET', '/_matrix/client/v3/account/whoami');
        $configuredUser = $this->required('matrix_bot_user_id');
        $actualUser = (string)($identity['user_id'] ?? '');
        if ($actualUser === '') {
            throw new RuntimeException('Matrix hat keine Bot-Identität zurückgegeben.');
        }
        if ($actualUser !== $configuredUser) {
            throw new RuntimeException('Der Matrix-Token gehört zu ' . $actualUser . ', konfiguriert ist aber ' . $configuredUser . '.');
        }
        return ['ok' => true, 'message' => 'Matrix-Verbindung erfolgreich als ' . $actualUser . '.'];
    }

    public function sendToUsers(array $users, string $message): array {
        $result = ['sent' => 0, 'total' => count($users), 'failed' => [], 'error' => null];
        try {
            $this->required('matrix_homeserver');
            $this->required('matrix_server_name');
            $this->required('matrix_bot_user_id');
            $this->required('matrix_bot_token');
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            foreach ($users as $user) {
                $result['failed'][] = ['recipient' => (string)($user['username'] ?? 'unbekannt'), 'error' => $e->getMessage()];
            }
            return $result;
        }

        foreach ($users as $user) {
            $username = trim((string)($user['username'] ?? ''));
            if ($username === '') {
                $result['failed'][] = ['recipient' => 'unbekannt', 'error' => 'Authentik-Benutzername fehlt.'];
                continue;
            }
            $matrixUserId = '@' . $username . ':' . $this->required('matrix_server_name');
            try {
                if (!$this->userExists($matrixUserId)) {
                    throw new RuntimeException('Matrix-Konto existiert nicht oder wurde noch nicht angelegt.');
                }
                $roomId = $this->ensureDirectRoom($matrixUserId);
                $this->sendMessage($roomId, $message);
                $result['sent']++;
            } catch (\Throwable $e) {
                $result['failed'][] = ['recipient' => $matrixUserId, 'error' => $e->getMessage()];
            }
        }
        return $result;
    }

    private function userExists(string $userId): bool {
        $response = $this->request('GET', '/_matrix/client/v3/profile/' . rawurlencode($userId), null, true);
        if ($response->getStatusCode() === 404) {
            return false;
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw $this->responseError($response, 'Matrix-Profil konnte nicht abgefragt werden');
        }
        return true;
    }

    private function ensureDirectRoom(string $matrixUserId): string {
        $botUserId = $this->required('matrix_bot_user_id');
        $directPath = '/_matrix/client/v3/user/' . rawurlencode($botUserId) . '/account_data/m.direct';
        $directResponse = $this->request('GET', $directPath, null, true);
        $direct = [];
        if ($directResponse->getStatusCode() >= 200 && $directResponse->getStatusCode() < 300) {
            $direct = $this->decode((string)$directResponse->getBody());
        } elseif ($directResponse->getStatusCode() !== 404) {
            throw $this->responseError($directResponse, 'Matrix-Direktnachrichten konnten nicht geladen werden');
        }

        $rooms = $direct[$matrixUserId] ?? [];
        if (!is_array($rooms)) { $rooms = []; }

        $joinedResponse = $this->request('GET', '/_matrix/client/v3/joined_rooms');
        $joined = $this->decode((string)$joinedResponse->getBody())['joined_rooms'] ?? [];
        $joinedSet = array_fill_keys(is_array($joined) ? $joined : [], true);
        foreach ($rooms as $roomId) {
            if (is_string($roomId) && isset($joinedSet[$roomId])) {
                return $roomId;
            }
        }

        $created = $this->requestJson('POST', '/_matrix/client/v3/createRoom', [
            'preset' => 'trusted_private_chat',
            'invite' => [$matrixUserId],
            'is_direct' => true,
        ]);
        $roomId = (string)($created['room_id'] ?? '');
        if ($roomId === '') {
            throw new RuntimeException('Matrix hat beim Erstellen des Direktnachrichtenraums keine Raum-ID geliefert.');
        }

        if (!isset($direct[$matrixUserId]) || !is_array($direct[$matrixUserId])) {
            $direct[$matrixUserId] = [];
        }
        if (!in_array($roomId, $direct[$matrixUserId], true)) {
            $direct[$matrixUserId][] = $roomId;
        }
        $this->request('PUT', $directPath, $direct);
        return $roomId;
    }

    private function sendMessage(string $roomId, string $message): void {
        $txnId = bin2hex(random_bytes(16));
        $path = '/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/send/m.room.message/' . $txnId;
        $this->request('PUT', $path, ['msgtype' => 'm.text', 'body' => $message]);
    }

    private function requestJson(string $method, string $path, ?array $payload = null): array {
        $response = $this->request($method, $path, $payload);
        return $this->decode((string)$response->getBody());
    }

    private function request(string $method, string $path, ?array $payload = null, bool $allowError = false) {
        $base = rtrim($this->required('matrix_homeserver'), '/');
        $token = $this->required('matrix_bot_token');
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => 20,
        ];
        if ($payload !== null) {
            $options['body'] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        $client = $this->http->newClient();
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $url = $base . $path;
            $response = match (strtoupper($method)) {
                'GET' => $client->get($url, $options),
                'POST' => $client->post($url, $options),
                'PUT' => $client->put($url, $options),
                default => throw new RuntimeException('Nicht unterstützte Matrix-HTTP-Methode.'),
            };
            $status = $response->getStatusCode();
            if ($status === 429 && $attempt < 3) {
                usleep((int)(1000000 * (2 ** $attempt)));
                continue;
            }
            if (!$allowError && ($status < 200 || $status >= 300)) {
                throw $this->responseError($response, 'Matrix-Anfrage fehlgeschlagen');
            }
            return $response;
        }
        throw new RuntimeException('Matrix-Anfrage wurde wiederholt durch Rate-Limiting abgewiesen.');
    }

    private function responseError($response, string $context): RuntimeException {
        $data = $this->decode((string)$response->getBody());
        $detail = trim((string)($data['error'] ?? $data['errcode'] ?? ''));
        return new RuntimeException($context . ' (HTTP ' . $response->getStatusCode() . ')' . ($detail !== '' ? ': ' . $detail : '.'));
    }

    private function decode(string $json): array {
        if ($json === '') { return []; }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private function required(string $key): string {
        $value = trim($this->config->getValueString('jugend', $key));
        if ($value === '') {
            throw new RuntimeException('Matrix-Einstellung fehlt: ' . $key . '.');
        }
        return $value;
    }
}
