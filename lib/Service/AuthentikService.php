<?php
declare(strict_types=1);
namespace OCA\Jugend\Service;

use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use RuntimeException;

class AuthentikService {
    public function __construct(private IClientService $http, private IAppConfig $config) {}

    public function getRecipients(): array {
        $base = rtrim($this->config->getValueString('jugend', 'authentik_url'), '/');
        $group = trim($this->config->getValueString('jugend', 'authentik_group_id'));
        $token = $this->config->getValueString('jugend', 'authentik_token');
        if ($base === '' || $group === '' || $token === '') {
            throw new RuntimeException('Authentik-URL, Gruppen-ID und API-Token müssen konfiguriert sein.');
        }
        $url = $base . '/api/v3/core/groups/' . rawurlencode($group) . '/';
        $response = $this->http->newClient()->get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
            'timeout' => 10,
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Authentik antwortete mit HTTP ' . $response->getStatusCode() . '.');
        }
        $data = json_decode($this->body($response->getBody()), true, 512, JSON_THROW_ON_ERROR);
        $users = $data['users_obj'] ?? [];
        if (!is_array($users)) {
            throw new RuntimeException('Authentik lieferte keine gültige Benutzerliste.');
        }
        $recipients = [];
        foreach ($users as $user) {
            $attributes = $user['attributes'] ?? [];
            $phone = trim((string)($attributes['PhoneNumber'] ?? $attributes['phoneNumber'] ?? ''));
            $sms = filter_var($attributes['Sms'] ?? $attributes['sms'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($sms && $phone !== '') {
                $recipients[] = ['username' => (string)($user['username'] ?? ''), 'phone' => $phone];
            }
        }
        return $recipients;
    }

    private function body(mixed $body): string {
        return is_resource($body) ? (string)stream_get_contents($body) : (string)$body;
    }

    public function testConnection(): array {
        $recipients = $this->getRecipients();
        return ['ok' => true, 'message' => 'Authentik-Verbindung erfolgreich; ' . count($recipients) . ' SMS-Empfänger mit aktivierter SMS-Option gefunden.'];
    }
}
