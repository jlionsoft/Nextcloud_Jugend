<?php
declare(strict_types=1);
namespace OCA\Jugend\Service;

use OCA\Jugend\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use RuntimeException;

class AuthentikService {
    public function __construct(private IClientService $http, private IAppConfig $config) {}

    /**
     * Returns every active member of the configured youth group.
     * Matrix targets all members; SMS is restricted to explicit opt-ins.
     */
    public function getRecipients(): array {
        $base = rtrim($this->required('authentik_url'), '/');
        $group = $this->required('authentik_group_id');
        $token = $this->required('authentik_token');
        $url = $base . '/api/v3/core/groups/' . rawurlencode($group) . '/';
        $response = $this->http->newClient()->get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
            'timeout' => 15,
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
            if (!is_array($user) || (array_key_exists('is_active', $user) && !$user['is_active'])) {
                continue;
            }
            $attributes = is_array($user['attributes'] ?? null) ? $user['attributes'] : [];
            $phone = trim((string)($attributes['phone_number'] ?? $attributes['PhoneNumber'] ?? $attributes['phoneNumber'] ?? ''));
            $smsValue = $attributes['sms'] ?? $attributes['Sms'] ?? false;
            $smsEnabled = filter_var($smsValue, FILTER_VALIDATE_BOOLEAN);
            $recipients[] = [
                'username' => trim((string)($user['username'] ?? '')),
                'phone' => $phone,
                'sms_enabled' => $smsEnabled,
            ];
        }
        return $recipients;
    }

    private function required(string $key): string {
        $value = trim($this->config->getValueString(Application::APP_ID, $key));
        if ($value === '') { throw new RuntimeException('Authentik-Einstellung fehlt: ' . $key . '.'); }
        return $value;
    }

    private function body(mixed $body): string {
        return is_resource($body) ? (string)stream_get_contents($body) : (string)$body;
    }

    public function testConnection(): array {
        $recipients = $this->getRecipients();
        $smsCount = count(array_filter($recipients, static fn(array $user): bool => $user['sms_enabled'] && $user['phone'] !== ''));
        return ['ok' => true, 'message' => 'Authentik-Verbindung erfolgreich; ' . count($recipients) . ' aktive Gruppenmitglieder, davon ' . $smsCount . ' mit SMS-Freigabe.'];
    }
}
