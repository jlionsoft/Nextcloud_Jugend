<?php
declare(strict_types=1);

namespace OCA\Jugend\Service;

use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use RuntimeException;

class ProvisioningService {
    private const USER_KEY = 'provisioning_user';
    private const URL_KEY = 'provisioning_url';
    private const PASSWORD_KEY = 'provisioning_app_password';

    public function __construct(
        private IAppConfig $config,
        private IClientService $httpClient,
    ) {
    }

    public function getBaseUrl(): string {
        return $this->baseUrl();
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

    /** Creates a private Talk group room; participant sync is a separate step. */
    public function createTalkRoom(string $name): array {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new \InvalidArgumentException('Der Talk-Raumname ist ungültig.');
        }
        $response = $this->request('POST', '/ocs/v2.php/apps/spreed/api/v4/room', [
            'roomType' => 2,
            'roomName' => $name,
        ]);
        $payload = json_decode((string)$response->getBody(), true);
        $data = $payload['ocs']['data'] ?? [];
        $token = (string)($data['token'] ?? '');
        if (!in_array((int)($payload['ocs']['meta']['statuscode'] ?? 0), [100, 200, 201], true) || $token === '') {
            throw new RuntimeException('Nextcloud Talk hat keinen gültigen Raum zurückgegeben.');
        }
        return ['token' => $token, 'roomId' => isset($data['id']) ? (int)$data['id'] : null];
    }

    public function getTalkParticipants(string $token): array {
        $response = $this->request('GET', '/ocs/v2.php/apps/spreed/api/v4/room/' . rawurlencode($token) . '/participants');
        $payload = json_decode((string)$response->getBody(), true);
        if (!in_array((int)($payload['ocs']['meta']['statuscode'] ?? 0), [100, 200], true)) {
            throw new RuntimeException('Die Talk-Teilnehmer konnten nicht gelesen werden.');
        }
        $participants = $payload['ocs']['data'] ?? [];
        return is_array($participants) ? $participants : [];
    }

    public function getTalkParticipantIds(string $token): array {
        $ids = [];
        foreach ($this->getTalkParticipants($token) as $participant) {
            if (($participant['actorType'] ?? '') === 'users' && !empty($participant['actorId'])) {
                $ids[] = (string)$participant['actorId'];
            }
        }
        return $ids;
    }

    public function getServiceAccountUid(): string {
        return trim($this->config->getValueString('jugend', self::USER_KEY));
    }

    /** Removes an attendee by the attendeeId returned by the participants endpoint. */
    public function removeTalkParticipant(string $token, int $attendeeId): void {
        if ($token === '' || $attendeeId <= 0) {
            throw new \InvalidArgumentException('Talk-Raumtoken und Teilnehmer-ID müssen gültig sein.');
        }
        $response = $this->request(
            'DELETE',
            '/ocs/v2.php/apps/spreed/api/v4/room/' . rawurlencode($token) . '/attendees',
            ['attendeeId' => $attendeeId],
        );
        $payload = json_decode((string)$response->getBody(), true);
        if (!in_array((int)($payload['ocs']['meta']['statuscode'] ?? 0), [100, 200], true)) {
            throw new RuntimeException('Talk konnte einen nicht mehr berechtigten Teilnehmer nicht entfernen.');
        }
    }

    public function addTalkParticipant(string $token, string $userId): void {
        $token = trim($token);
        $userId = trim($userId);
        if ($token === '' || $userId === '') {
            throw new \InvalidArgumentException('Talk-Raumtoken und Benutzer-ID müssen gesetzt sein.');
        }
        $response = $this->request(
            'POST',
            '/ocs/v2.php/apps/spreed/api/v4/room/' . rawurlencode($token) . '/participants',
            ['newParticipant' => $userId, 'source' => 'users'],
        );
        $payload = json_decode((string)$response->getBody(), true);
        if (!in_array((int)($payload['ocs']['meta']['statuscode'] ?? 0), [100, 200, 201], true)) {
            throw new RuntimeException('Talk konnte den Benutzer ' . $userId . ' nicht zum Raum hinzufügen.');
        }
    }

    public function getProvisioningGroupFolderRoot(): string {
        $root = trim($this->config->getValueString('jugend', 'provisioning_groupfolder_root'));
        if ($root === '' || str_contains($root, '/') || in_array($root, ['.', '..'], true)) {
            throw new RuntimeException('Der Groupfolder-Stammordner ist nicht korrekt konfiguriert.');
        }
        return $root;
    }

    /**
     * Creates nested directories inside the pre-created Groupfolder root via
     * WebDAV MKCOL. This avoids Groupfolders admin endpoints that require an
     * interactive password-confirmation session and cannot be called with an
     * app password.
     */
    public function ensureGroupFolderPath(string $relativePath): void {
        $parts = array_values(array_filter(explode('/', trim($relativePath, '/')), static fn(string $part): bool => $part !== ''));
        if ($parts === []) {
            throw new \InvalidArgumentException('Der Groupfolder-Pfad darf nicht leer sein.');
        }
        $path = '';
        foreach ($parts as $part) {
            if ($part === '.' || $part === '..' || !preg_match('/^[\pL\pN _.-]{1,64}$/u', $part)) {
                throw new \InvalidArgumentException('Der Groupfolder-Pfad enthält einen ungültigen Abschnitt.');
            }
            $path .= '/' . rawurlencode($part);
            $davPath = '/remote.php/dav/files/' . rawurlencode($this->getServiceAccountUid()) . $path;
            $response = $this->requestRaw('MKCOL', $davPath, '', [], [201, 405]);
            if ($response->getStatusCode() === 405) {
                $probe = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>';
                $this->requestRaw('PROPFIND', $davPath, $probe, [
                    'Depth' => '0',
                    'Content-Type' => 'application/xml; charset=utf-8',
                ], [207]);
            }
        }
    }

    /** Returns direct file shares for a path created by this service account. */
    public function getManagedFileShares(string $path): array {
        $url = '/ocs/v2.php/apps/files_sharing/api/v1/shares?format=json&reshares=false&path=' . rawurlencode($path);
        $data = $this->ocsData($this->request('GET', $url));
        $shares = is_array($data) ? $data : [];
        $owner = $this->getServiceAccountUid();
        return array_values(array_filter($shares, static function (array $share) use ($owner): bool {
            return (string)($share['uid_owner'] ?? '') === $owner
                && in_array((int)($share['share_type'] ?? -1), [0, 1], true);
        }));
    }

    /** Create a direct user or group share for a Groupfolder path. */
    public function createFileShare(string $path, int $shareType, string $shareWith, int $permissions = 31): int {
        if (!in_array($shareType, [0, 1], true) || trim($shareWith) === '' || $permissions < 1 || $permissions > 31) {
            throw new \InvalidArgumentException('Die Dateifreigabe ist ungültig.');
        }
        $data = $this->ocsData($this->request('POST', '/ocs/v2.php/apps/files_sharing/api/v1/shares', [
            'path' => $path,
            'shareType' => $shareType,
            'shareWith' => $shareWith,
            'permissions' => $permissions,
        ]));
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Nextcloud hat keine gültige Freigabe-ID zurückgegeben.');
        }
        return $id;
    }

    public function deleteFileShare(int $shareId): void {
        if ($shareId <= 0) {
            throw new \InvalidArgumentException('Die Freigabe-ID ist ungültig.');
        }
        $this->ocsData($this->request('DELETE', '/ocs/v2.php/apps/files_sharing/api/v1/shares/' . $shareId));
    }

    /**
     * Create or reuse a calendar owned by the provisioning account.
     * CalDAV MKCALENDAR is used instead of creating a calendar in the app's
     * own database, so the resource remains a normal user calendar.
     */
    public function ensureCalendar(string $calendarUri, string $displayName): string {
        $calendarUri = trim($calendarUri);
        $displayName = trim($displayName);
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $calendarUri) || $displayName === '') {
            throw new \InvalidArgumentException('Kalenderkennung oder Kalendername ist ungültig.');
        }
        $url = $this->calendarPath($calendarUri);
        $probe = $this->requestRaw('PROPFIND', $url, '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>', [
            'Depth' => '0',
            'Content-Type' => 'application/xml; charset=utf-8',
        ], [207, 404]);
        if ($probe->getStatusCode() === 207) {
            return $this->baseUrl() . $url;
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:mkcalendar xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">'
            . '<d:set><d:prop><d:displayname>' . htmlspecialchars($displayName, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</d:displayname>'
            . '<c:supported-calendar-component-set><c:comp name="VEVENT"/><c:comp name="VTODO"/></c:supported-calendar-component-set>'
            . '</d:prop></d:set></d:mkcalendar>';
        $response = $this->requestRaw('MKCALENDAR', $url, $xml, ['Content-Type' => 'application/xml; charset=utf-8'], [201, 200]);
        return $this->baseUrl() . $url;
    }

    /** Returns the current principal URIs shared to a calendar owned by the service account. */
    public function getCalendarShares(string $calendarUri): array {
        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><oc:invite/></d:prop></d:propfind>';
        $response = $this->requestRaw('PROPFIND', $this->calendarPath($calendarUri), $xml, [
            'Depth' => '0',
            'Content-Type' => 'application/xml; charset=utf-8',
        ], [207]);
        $body = (string)$response->getBody();
        $document = new \DOMDocument();
        if (!$document->loadXML($body)) {
            throw new RuntimeException('Die Kalenderfreigaben konnten nicht gelesen werden.');
        }
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('d', 'DAV:');
        $xpath->registerNamespace('oc', 'http://owncloud.org/ns');
        $nodes = $xpath->query('//oc:invite/oc:user/d:href');
        $principals = [];
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $principal = trim($node->textContent);
                if (str_starts_with($principal, 'principal:principals/')) {
                    $principals[] = $principal;
                }
            }
        }
        return array_values(array_unique($principals));
    }

    /** Adds or updates a calendar share using Nextcloud's CalDAV sharing extension. */
    public function shareCalendar(string $calendarUri, array $principals, bool $readOnly = false): void {
        $principals = array_values(array_unique(array_filter(array_map('trim', $principals))));
        if ($principals === []) {
            return;
        }
        $items = '';
        foreach ($principals as $principal) {
            if (!preg_match('#^principal:principals/(users|groups)/[^/]+$#', $principal)) {
                throw new \InvalidArgumentException('Ungültiger Kalender-Share-Principal.');
            }
            $items .= '<oc:set><d:href>' . htmlspecialchars($principal, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</d:href>'
                . ($readOnly ? '<oc:read-only/>' : '<oc:read-write/>')
                . '</oc:set>';
        }
        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            . '<oc:share xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">' . $items . '</oc:share>';
        $this->requestRaw('POST', $this->calendarPath($calendarUri), $xml, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ], [200, 201, 204]);
    }

    /** Removes specific calendar shares without touching any other principal. */
    public function unshareCalendar(string $calendarUri, array $principals): void {
        $principals = array_values(array_unique(array_filter(array_map('trim', $principals))));
        if ($principals === []) {
            return;
        }
        $items = '';
        foreach ($principals as $principal) {
            if (!preg_match('#^principal:principals/(users|groups)/[^/]+$#', $principal)) {
                throw new \InvalidArgumentException('Ungültiger Kalender-Share-Principal.');
            }
            $items .= '<oc:remove><d:href>' . htmlspecialchars($principal, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</d:href></oc:remove>';
        }
        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            . '<oc:share xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">' . $items . '</oc:share>';
        $this->requestRaw('POST', $this->calendarPath($calendarUri), $xml, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ], [200, 201, 204]);
    }

    private function calendarPath(string $calendarUri): string {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $calendarUri)) {
            throw new \InvalidArgumentException('Kalenderkennung ist ungültig.');
        }
        return '/remote.php/dav/calendars/' . rawurlencode($this->getServiceAccountUid()) . '/' . rawurlencode($calendarUri) . '/';
    }

    private function ocsData(\Psr\Http\Message\ResponseInterface $response): array {
        $payload = json_decode((string)$response->getBody(), true);
        $meta = $payload['ocs']['meta'] ?? [];
        if (!in_array((int)($meta['statuscode'] ?? 0), [100, 200], true)) {
            $message = trim((string)($meta['message'] ?? ''));
            throw new RuntimeException($message !== '' ? $message : 'Die Nextcloud-OCS-Anfrage ist fehlgeschlagen.');
        }
        $data = $payload['ocs']['data'] ?? [];
        return is_array($data) ? $data : [];
    }

    private function requestRaw(string $method, string $path, string $body, array $headers, array $acceptedStatusCodes): \Psr\Http\Message\ResponseInterface {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Das Nextcloud-Provisioning-Dienstkonto ist noch nicht vollständig eingerichtet.');
        }
        $response = $this->httpClient->newClient()->request($method, $this->baseUrl() . $path, [
            'auth' => [
                $this->config->getValueString('jugend', self::USER_KEY),
                $this->config->getValueString('jugend', self::PASSWORD_KEY),
            ],
            'headers' => $headers + ['Accept' => 'application/xml'],
            'body' => $body,
            'timeout' => 20,
        ]);
        if (!in_array($response->getStatusCode(), $acceptedStatusCodes, true)) {
            throw new RuntimeException('Nextcloud antwortet auf ' . $method . ' mit HTTP ' . $response->getStatusCode() . '.');
        }
        return $response;
    }

    private function request(string $method, string $path, array $form = []): \Psr\Http\Message\ResponseInterface {
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
        $response = $this->httpClient->newClient()->request($method, $this->baseUrl() . $path, $options);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException('Nextcloud-API antwortet mit HTTP ' . $response->getStatusCode() . '.');
        }
        return $response;
    }

    private function baseUrl(): string {
        return rtrim($this->config->getValueString('jugend', self::URL_KEY), '/');
    }
}
