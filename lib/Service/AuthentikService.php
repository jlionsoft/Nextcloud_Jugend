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
                'pk' => (int)($user['pk'] ?? 0),
                'phone' => $phone,
                'sms_enabled' => $smsEnabled,
            ];
        }
        return $recipients;
    }

    /**
     * Creates or reconciles a dedicated Authentik group for one app work group.
     * The group is marked with app-owned attributes so unrelated groups are
     * never adopted or modified accidentally.
     *
     * @param list<int> $expectedUserPks
     */
    public function syncWorkgroupGroup(string $name, int $periodId, int $groupId, array $expectedUserPks): array {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 150 || $periodId <= 0 || $groupId <= 0) {
            throw new \InvalidArgumentException('Authentik-Gruppenname oder Organisationsreferenz ist ungültig.');
        }
        $expected = array_values(array_unique(array_filter(array_map('intval', $expectedUserPks), static fn(int $pk): bool => $pk > 0)));
        $group = $this->findGroupByName($name);
        $created = false;
        if ($group === null) {
            $group = $this->requestJson('POST', '/api/v3/core/groups/', [
                'name' => $name,
                'is_superuser' => false,
                'users' => $expected,
                'attributes' => [
                    'managed_by' => 'nextcloud-jugend',
                    'period_id' => $periodId,
                    'group_id' => $groupId,
                ],
            ]);
            $created = true;
        } else {
            $attributes = is_array($group['attributes'] ?? null) ? $group['attributes'] : [];
            if (($attributes['managed_by'] ?? '') !== 'nextcloud-jugend'
                || (int)($attributes['period_id'] ?? 0) !== $periodId
                || (int)($attributes['group_id'] ?? 0) !== $groupId) {
                throw new RuntimeException('Eine Authentik-Gruppe mit dem Namen „' . $name . '“ existiert bereits, gehört aber nicht dieser App.');
            }
        }

        $uuid = (string)($group['pk'] ?? '');
        if ($uuid === '') {
            throw new RuntimeException('Authentik hat keine Gruppen-UUID zurückgegeben.');
        }
        $current = array_values(array_unique(array_filter(array_map('intval', $group['users'] ?? []), static fn(int $pk): bool => $pk > 0)));
        $added = [];
        $removed = [];
        foreach (array_diff($expected, $current) as $pk) {
            $this->requestJson('POST', '/api/v3/core/groups/' . rawurlencode($uuid) . '/add_user/', ['pk' => (int)$pk], [200, 204]);
            $added[] = (int)$pk;
        }
        foreach (array_diff($current, $expected) as $pk) {
            $this->requestJson('POST', '/api/v3/core/groups/' . rawurlencode($uuid) . '/remove_user/', ['pk' => (int)$pk], [200, 204]);
            $removed[] = (int)$pk;
        }
        return [
            'uuid' => $uuid,
            'name' => $name,
            'created' => $created,
            'added' => $added,
            'removed' => $removed,
        ];
    }

    private function findGroupByName(string $name): ?array {
        $path = '/api/v3/core/groups/?search=' . rawurlencode($name) . '&include_users=true';
        $data = $this->requestJson('GET', $path);
        $results = $data['results'] ?? [];
        if (!is_array($results)) {
            throw new RuntimeException('Authentik lieferte keine gültige Gruppenliste.');
        }
        $matches = array_values(array_filter($results, static fn(array $group): bool => (string)($group['name'] ?? '') === $name));
        if (count($matches) > 1) {
            throw new RuntimeException('Authentik lieferte mehrere Gruppen mit demselben Namen.');
        }
        return $matches[0] ?? null;
    }

    private function requestJson(string $method, string $path, ?array $body = null, array $acceptedStatusCodes = [200, 201]): array {
        $base = rtrim($this->required('authentik_url'), '/');
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->required('authentik_token'),
                'Accept' => 'application/json',
            ],
            'timeout' => 20,
        ];
        if ($body !== null) {
            $options['json'] = $body;
        }
        $response = $this->http->newClient()->request($method, $base . $path, $options);
        if (!in_array($response->getStatusCode(), $acceptedStatusCodes, true)) {
            throw new RuntimeException('Authentik-API antwortet mit HTTP ' . $response->getStatusCode() . '.');
        }
        $raw = $this->body($response->getBody());
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
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
