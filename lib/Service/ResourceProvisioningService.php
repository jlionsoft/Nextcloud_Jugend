<?php
declare(strict_types=1);

namespace OCA\Jugend\Service;

use OCP\IDBConnection;

class ResourceProvisioningService {
    private const JOB_STALE_AFTER = 900;

    public function __construct(
        private IDBConnection $db,
        private OrganizationService $organization,
        private ProvisioningService $client,
        private AuthentikService $authentik,
    ) {
    }

    /**
     * Creates one private Talk room per active-period group, reconciles active
     * group membership, and grants every current department member access to
     * all archived Talk rooms.
     */
    public function provisionTalkRooms(int $periodId, string $actor): array {
        $period = $this->organization->getPeriod($periodId);
        if ($period['status'] !== 'active') {
            throw new \InvalidArgumentException('Talk-Räume können nur für die aktive Organisationsperiode bereitgestellt werden.');
        }

        $currentMembers = $this->currentDepartmentMembers();
        $result = ['created' => [], 'existing' => [], 'archivedSynced' => [], 'failed' => [], 'inProgress' => []];

        foreach ($this->organization->listGroups($periodId) as $group) {
            $groupId = (int)$group['id'];
            if (!$this->claimJob($periodId, $groupId, 'talk')) {
                $result['inProgress'][] = ['groupId' => $groupId, 'groupName' => $group['name']];
                continue;
            }

            $created = false;
            try {
                $resource = $this->findResource($groupId, 'talk');
                if ($resource === null) {
                    $roomName = mb_substr('Jugend ' . $period['title'] . ' – ' . $group['name'], 0, 255);
                    $room = $this->client->createTalkRoom($roomName);
                    $url = $this->client->getBaseUrl() . '/call/' . rawurlencode($room['token']);

                    $qb = $this->db->getQueryBuilder();
                    $qb->insert('jugend_resources')
                        ->setValue('group_id', $qb->createNamedParameter($groupId))
                        ->setValue('resource_type', $qb->createNamedParameter('talk'))
                        ->setValue('resource_id', $qb->createNamedParameter($room['token']))
                        ->setValue('resource_url', $qb->createNamedParameter($url))
                        ->setValue('created_at', $qb->createNamedParameter(time()));
                    $qb->executeStatement();

                    $resource = ['resource_id' => $room['token'], 'resource_url' => $url];
                    $created = true;
                    $this->audit($actor, 'resource.talk.created', (string)$groupId, ['periodId' => $periodId]);
                }

                $expected = [];
                foreach ($group['members'] as $member) {
                    $uid = trim((string)$member['user_id']);
                    if ($uid !== '' && isset($currentMembers[$uid])) {
                        $expected[$uid] = true;
                    }
                }
                $changes = $this->reconcileParticipants((string)$resource['resource_id'], $expected);
                $entry = [
                    'groupId' => $groupId,
                    'groupName' => $group['name'],
                    'token' => (string)$resource['resource_id'],
                    'url' => $resource['resource_url'],
                    'participantsAdded' => $changes['added'],
                    'participantsRemoved' => $changes['removed'],
                    'participantErrors' => $changes['errors'],
                ];
                if ($changes['errors'] !== []) {
                    $this->finishJob($periodId, $groupId, 'talk', 'failed', 'Mindestens eine Talk-Mitgliedschaft konnte nicht abgeglichen werden.');
                    $result['failed'][] = [
                        'groupId' => $groupId,
                        'groupName' => $group['name'],
                        'message' => 'Mindestens eine Talk-Mitgliedschaft konnte nicht abgeglichen werden.',
                        'participants' => $changes['errors'],
                    ];
                } else {
                    $this->finishJob($periodId, $groupId, 'talk', 'succeeded', null);
                    $result[$created ? 'created' : 'existing'][] = $entry;
                }
            } catch (\Throwable $e) {
                $this->finishJob($periodId, $groupId, 'talk', 'failed', $e->getMessage());
                $result['failed'][] = [
                    'groupId' => $groupId,
                    'groupName' => $group['name'],
                    'message' => $e->getMessage(),
                ];
            }
        }

        // Archived content is intentionally shared with the current department,
        // not with the historical membership snapshot of each archived group.
        foreach ($this->listArchivedTalkResources() as $resource) {
            $archivedPeriodId = (int)$resource['period_id'];
            $groupId = (int)$resource['group_id'];
            if (!$this->claimJob($archivedPeriodId, $groupId, 'talk')) {
                $result['inProgress'][] = ['groupId' => $groupId, 'groupName' => $resource['group_name'], 'archived' => true];
                continue;
            }
            try {
                $changes = $this->reconcileParticipants((string)$resource['resource_id'], $currentMembers);
                if ($changes['errors'] !== []) {
                    $this->finishJob($archivedPeriodId, $groupId, 'talk', 'failed', 'Archivzugriff konnte nicht vollständig abgeglichen werden.');
                    $result['failed'][] = [
                        'groupId' => $groupId,
                        'groupName' => $resource['group_name'],
                        'archived' => true,
                        'message' => 'Archivzugriff konnte nicht vollständig abgeglichen werden.',
                        'participants' => $changes['errors'],
                    ];
                } else {
                    $this->finishJob($archivedPeriodId, $groupId, 'talk', 'succeeded', null);
                    $result['archivedSynced'][] = [
                        'groupId' => $groupId,
                        'groupName' => $resource['group_name'],
                        'periodTitle' => $resource['period_title'],
                        'participantsAdded' => $changes['added'],
                        'participantsRemoved' => $changes['removed'],
                        'url' => $resource['resource_url'],
                    ];
                }
            } catch (\Throwable $e) {
                $this->finishJob($archivedPeriodId, $groupId, 'talk', 'failed', $e->getMessage());
                $result['failed'][] = [
                    'groupId' => $groupId,
                    'groupName' => $resource['group_name'],
                    'archived' => true,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * Creates a private Groupfolder per active work group. The provisioning
     * account remains the only native group member on the folder; actual
     * access is granted through managed user shares. Archived folders are
     * shared to the Authentik-synchronised department group.
     */
    public function provisionGroupFolders(int $periodId, string $actor): array {
        $period = $this->organization->getPeriod($periodId);
        if ($period['status'] !== 'active') {
            throw new \InvalidArgumentException('Groupfolders können nur für die aktive Organisationsperiode bereitgestellt werden.');
        }
        $serviceGroup = $this->client->getProvisioningGroupId();
        $departmentGroup = $this->client->getDepartmentGroupId();
        if ($serviceGroup === '' || $departmentGroup === '') {
            throw new \RuntimeException('Bitte Dienstgruppe und Abteilungs-Zugriffsgruppe in den Jugendeinstellungen konfigurieren.');
        }

        $currentMembers = $this->currentDepartmentMembers();
        $result = ['created' => [], 'existing' => [], 'archivedSynced' => [], 'failed' => [], 'inProgress' => []];

        foreach ($this->organization->listGroups($periodId) as $group) {
            $groupId = (int)$group['id'];
            if (!$this->claimJob($periodId, $groupId, 'groupfolder')) {
                $result['inProgress'][] = ['groupId' => $groupId, 'groupName' => $group['name']];
                continue;
            }
            $created = false;
            try {
                $mountpoint = $this->groupFolderMountpoint($periodId, (string)$group['slug'], $groupId);
                $folder = $this->client->findGroupFolder($mountpoint);
                if ($folder === null) {
                    $folderId = $this->client->createGroupFolder($mountpoint);
                    $folder = ['id' => $folderId, 'mount_point' => $mountpoint, 'groups' => []];
                    $created = true;
                }
                $this->client->ensureGroupFolderServiceGroup($folder, $serviceGroup);
                $this->storeResource($groupId, 'groupfolder', (string)$folder['id'], $this->groupFolderUrl($mountpoint));
                if ($created) {
                    $this->audit($actor, 'resource.groupfolder.created', (string)$groupId, ['periodId' => $periodId, 'folderId' => (int)$folder['id']]);
                }

                $expected = [];
                foreach ($group['members'] as $member) {
                    $uid = trim((string)$member['user_id']);
                    if ($uid !== '' && isset($currentMembers[$uid])) {
                        $expected['0:' . $uid] = ['type' => 0, 'with' => $uid];
                    }
                }
                $changes = $this->reconcileGroupFolderShares('/' . $mountpoint, $expected);
                $entry = [
                    'groupId' => $groupId,
                    'groupName' => $group['name'],
                    'folderId' => (int)$folder['id'],
                    'mountpoint' => $mountpoint,
                    'url' => $this->groupFolderUrl($mountpoint),
                    'sharesAdded' => $changes['added'],
                    'sharesRemoved' => $changes['removed'],
                ];
                if ($changes['errors'] !== []) {
                    $this->finishJob($periodId, $groupId, 'groupfolder', 'failed', 'Mindestens eine Groupfolder-Freigabe konnte nicht abgeglichen werden.');
                    $result['failed'][] = ['groupId' => $groupId, 'groupName' => $group['name'], 'message' => 'Mindestens eine Groupfolder-Freigabe konnte nicht abgeglichen werden.', 'shares' => $changes['errors']];
                } else {
                    $this->finishJob($periodId, $groupId, 'groupfolder', 'succeeded', null);
                    $result[$created ? 'created' : 'existing'][] = $entry;
                }
            } catch (\Throwable $e) {
                $this->finishJob($periodId, $groupId, 'groupfolder', 'failed', $e->getMessage());
                $result['failed'][] = ['groupId' => $groupId, 'groupName' => $group['name'], 'message' => $e->getMessage()];
            }
        }

        foreach ($this->listArchivedGroupFolderResources() as $resource) {
            $archivedPeriodId = (int)$resource['period_id'];
            $groupId = (int)$resource['group_id'];
            if (!$this->claimJob($archivedPeriodId, $groupId, 'groupfolder')) {
                $result['inProgress'][] = ['groupId' => $groupId, 'groupName' => $resource['group_name'], 'archived' => true];
                continue;
            }
            try {
                $mountpoint = $this->groupFolderMountpoint($archivedPeriodId, (string)$resource['group_slug'], $groupId);
                $expected = ['1:' . $departmentGroup => ['type' => 1, 'with' => $departmentGroup]];
                $changes = $this->reconcileGroupFolderShares('/' . $mountpoint, $expected);
                if ($changes['errors'] !== []) {
                    $this->finishJob($archivedPeriodId, $groupId, 'groupfolder', 'failed', 'Archivzugriff konnte nicht vollständig abgeglichen werden.');
                    $result['failed'][] = ['groupId' => $groupId, 'groupName' => $resource['group_name'], 'archived' => true, 'message' => 'Archivzugriff konnte nicht vollständig abgeglichen werden.', 'shares' => $changes['errors']];
                } else {
                    $this->finishJob($archivedPeriodId, $groupId, 'groupfolder', 'succeeded', null);
                    $result['archivedSynced'][] = [
                        'groupId' => $groupId,
                        'groupName' => $resource['group_name'],
                        'periodTitle' => $resource['period_title'],
                        'sharesAdded' => $changes['added'],
                        'sharesRemoved' => $changes['removed'],
                        'url' => $this->groupFolderUrl($mountpoint),
                    ];
                }
            } catch (\Throwable $e) {
                $this->finishJob($archivedPeriodId, $groupId, 'groupfolder', 'failed', $e->getMessage());
                $result['failed'][] = ['groupId' => $groupId, 'groupName' => $resource['group_name'], 'archived' => true, 'message' => $e->getMessage()];
            }
        }
        return $result;
    }

    private function reconcileGroupFolderShares(string $path, array $expected): array {
        $existing = $this->client->getManagedFileShares($path);
        $existingByKey = [];
        foreach ($existing as $share) {
            $key = (int)($share['share_type'] ?? -1) . ':' . (string)($share['share_with'] ?? '');
            $existingByKey[$key] = (int)($share['id'] ?? 0);
        }
        $added = [];
        $removed = [];
        $errors = [];
        foreach ($expected as $key => $share) {
            if (isset($existingByKey[$key])) {
                unset($existingByKey[$key]);
                continue;
            }
            try {
                $this->client->createFileShare($path, $share['type'], $share['with'], 31);
                $added[] = $key;
            } catch (\Throwable $e) {
                $errors[] = ['share' => $key, 'action' => 'add', 'message' => $e->getMessage()];
            }
        }
        foreach ($existingByKey as $key => $shareId) {
            if ($shareId <= 0) {
                continue;
            }
            try {
                $this->client->deleteFileShare($shareId);
                $removed[] = $key;
            } catch (\Throwable $e) {
                $errors[] = ['share' => $key, 'action' => 'remove', 'message' => $e->getMessage()];
            }
        }
        return ['added' => $added, 'removed' => $removed, 'errors' => $errors];
    }

    private function listArchivedGroupFolderResources(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('r.group_id', 'g.slug AS group_slug', 'g.name AS group_name', 'p.id AS period_id', 'p.title AS period_title')
            ->from('jugend_resources', 'r')
            ->innerJoin('r', 'jugend_groups', 'g', $qb->expr()->eq('r.group_id', 'g.id'))
            ->innerJoin('g', 'jugend_periods', 'p', $qb->expr()->eq('g.period_id', 'p.id'))
            ->where($qb->expr()->eq('r.resource_type', $qb->createNamedParameter('groupfolder')))
            ->andWhere($qb->expr()->eq('p.status', $qb->createNamedParameter('archived')));
        return $qb->executeQuery()->fetchAll();
    }

    private function groupFolderMountpoint(int $periodId, string $slug, int $groupId): string {
        $slug = preg_replace('/[^a-z0-9_-]/i', '-', $slug) ?: 'group';
        return 'jugend-' . $periodId . '-' . substr($slug, 0, 40) . '-' . $groupId;
    }

    private function groupFolderUrl(string $mountpoint): string {
        return $this->client->getBaseUrl() . '/apps/files/?dir=%2F' . rawurlencode($mountpoint);
    }

    private function storeResource(int $groupId, string $type, string $resourceId, string $url): void {
        $existing = $this->findResource($groupId, $type);
        if ($existing !== null) {
            return;
        }
        $qb = $this->db->getQueryBuilder();
        $qb->insert('jugend_resources')
            ->setValue('group_id', $qb->createNamedParameter($groupId))
            ->setValue('resource_type', $qb->createNamedParameter($type))
            ->setValue('resource_id', $qb->createNamedParameter($resourceId))
            ->setValue('resource_url', $qb->createNamedParameter($url))
            ->setValue('created_at', $qb->createNamedParameter(time()));
        $qb->executeStatement();
    }

    /**
     * Creates one CalDAV calendar per active work group. Active calendars are
     * shared with current group members; archived calendars are shared read-only
     * with the current department access group.
     */
    public function provisionCalendars(int $periodId, string $actor): array {
        $period = $this->organization->getPeriod($periodId);
        if ($period['status'] !== 'active') {
            throw new \InvalidArgumentException('Kalender können nur für die aktive Organisationsperiode bereitgestellt werden.');
        }
        $departmentGroup = $this->client->getDepartmentGroupId();
        if ($departmentGroup === '') {
            throw new \RuntimeException('Bitte die Nextcloud-Zugriffsgruppe der Abteilung in den Jugendeinstellungen konfigurieren.');
        }
        $currentMembers = $this->currentDepartmentMembers();
        $result = ['created' => [], 'existing' => [], 'archivedSynced' => [], 'failed' => [], 'inProgress' => []];

        foreach ($this->organization->listGroups($periodId) as $group) {
            $groupId = (int)$group['id'];
            if (!$this->claimJob($periodId, $groupId, 'calendar')) {
                $result['inProgress'][] = ['groupId' => $groupId, 'groupName' => $group['name']];
                continue;
            }
            $created = false;
            try {
                $uri = $this->calendarUri($periodId, $groupId);
                $name = 'Jugend ' . $period['title'] . ' – ' . $group['name'];
                $existing = $this->findResource($groupId, 'calendar');
                $url = $this->client->ensureCalendar($uri, $name);
                if ($existing === null) {
                    $created = true;
                    $this->storeResource($groupId, 'calendar', $uri, $url);
                    $this->audit($actor, 'resource.calendar.created', (string)$groupId, ['periodId' => $periodId, 'calendarUri' => $uri]);
                }

                $expected = [];
                foreach ($group['members'] as $member) {
                    $uid = trim((string)$member['user_id']);
                    if ($uid !== '' && isset($currentMembers[$uid])) {
                        $expected[] = 'principal:principals/users/' . rawurlencode($uid);
                    }
                }
                $changes = $this->reconcileCalendarShares($uri, $expected, false);
                if ($changes['errors'] !== []) {
                    $this->finishJob($periodId, $groupId, 'calendar', 'failed', 'Mindestens eine Kalenderfreigabe konnte nicht abgeglichen werden.');
                    $result['failed'][] = ['groupId' => $groupId, 'groupName' => $group['name'], 'message' => 'Mindestens eine Kalenderfreigabe konnte nicht abgeglichen werden.', 'shares' => $changes['errors']];
                } else {
                    $this->finishJob($periodId, $groupId, 'calendar', 'succeeded', null);
                    $result[$created ? 'created' : 'existing'][] = [
                        'groupId' => $groupId,
                        'groupName' => $group['name'],
                        'calendarUri' => $uri,
                        'url' => $url,
                        'sharesAdded' => $changes['added'],
                        'sharesRemoved' => $changes['removed'],
                    ];
                }
            } catch (\Throwable $e) {
                $this->finishJob($periodId, $groupId, 'calendar', 'failed', $e->getMessage());
                $result['failed'][] = ['groupId' => $groupId, 'groupName' => $group['name'], 'message' => $e->getMessage()];
            }
        }

        foreach ($this->listArchivedCalendarResources() as $resource) {
            $archivedPeriodId = (int)$resource['period_id'];
            $groupId = (int)$resource['group_id'];
            if (!$this->claimJob($archivedPeriodId, $groupId, 'calendar')) {
                $result['inProgress'][] = ['groupId' => $groupId, 'groupName' => $resource['group_name'], 'archived' => true];
                continue;
            }
            try {
                $uri = $this->calendarUri($archivedPeriodId, $groupId);
                $principal = 'principal:principals/groups/' . rawurlencode($departmentGroup);
                $changes = $this->reconcileCalendarShares($uri, [$principal], true);
                if ($changes['errors'] !== []) {
                    $this->finishJob($archivedPeriodId, $groupId, 'calendar', 'failed', 'Archivzugriff auf den Kalender konnte nicht vollständig abgeglichen werden.');
                    $result['failed'][] = ['groupId' => $groupId, 'groupName' => $resource['group_name'], 'archived' => true, 'message' => 'Archivzugriff auf den Kalender konnte nicht vollständig abgeglichen werden.', 'shares' => $changes['errors']];
                } else {
                    $this->finishJob($archivedPeriodId, $groupId, 'calendar', 'succeeded', null);
                    $result['archivedSynced'][] = [
                        'groupId' => $groupId,
                        'groupName' => $resource['group_name'],
                        'periodTitle' => $resource['period_title'],
                        'sharesAdded' => $changes['added'],
                        'sharesRemoved' => $changes['removed'],
                        'url' => $resource['resource_url'],
                    ];
                }
            } catch (\Throwable $e) {
                $this->finishJob($archivedPeriodId, $groupId, 'calendar', 'failed', $e->getMessage());
                $result['failed'][] = ['groupId' => $groupId, 'groupName' => $resource['group_name'], 'archived' => true, 'message' => $e->getMessage()];
            }
        }
        return $result;
    }

    private function reconcileCalendarShares(string $calendarUri, array $expectedPrincipals, bool $readOnly): array {
        $existing = $this->client->getCalendarShares($calendarUri);
        $expected = array_values(array_unique($expectedPrincipals));
        $toAdd = array_values(array_diff($expected, $existing));
        $toRemove = array_values(array_diff($existing, $expected));
        $added = [];
        $removed = [];
        $errors = [];
        if ($toAdd !== []) {
            try {
                $this->client->shareCalendar($calendarUri, $toAdd, $readOnly);
                $added = $toAdd;
            } catch (\Throwable $e) {
                $errors[] = ['action' => 'add', 'principals' => $toAdd, 'message' => $e->getMessage()];
            }
        }
        if ($toRemove !== []) {
            try {
                $this->client->unshareCalendar($calendarUri, $toRemove);
                $removed = $toRemove;
            } catch (\Throwable $e) {
                $errors[] = ['action' => 'remove', 'principals' => $toRemove, 'message' => $e->getMessage()];
            }
        }
        return ['added' => $added, 'removed' => $removed, 'errors' => $errors];
    }

    private function listArchivedCalendarResources(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('r.group_id', 'r.resource_url', 'g.name AS group_name', 'p.id AS period_id', 'p.title AS period_title')
            ->from('jugend_resources', 'r')
            ->innerJoin('r', 'jugend_groups', 'g', $qb->expr()->eq('r.group_id', 'g.id'))
            ->innerJoin('g', 'jugend_periods', 'p', $qb->expr()->eq('g.period_id', 'p.id'))
            ->where($qb->expr()->eq('r.resource_type', $qb->createNamedParameter('calendar')))
            ->andWhere($qb->expr()->eq('p.status', $qb->createNamedParameter('archived')));
        return $qb->executeQuery()->fetchAll();
    }

    private function calendarUri(int $periodId, int $groupId): string {
        return 'jugend-p' . $periodId . '-g' . $groupId;
    }

    /** Returns persisted provisioning state for the selected period. */
    public function getTalkProvisioningStatus(int $periodId): array {
        $period = $this->organization->getPeriod($periodId);
        $qb = $this->db->getQueryBuilder();
        $qb->select('group_id', 'status', 'attempts', 'last_error', 'updated_at')
            ->from('jugend_provisioning')
            ->where($qb->expr()->eq('period_id', $qb->createNamedParameter($periodId)))
            ->andWhere($qb->expr()->eq('resource_type', $qb->createNamedParameter('talk')));
        $rows = $qb->executeQuery()->fetchAll();
        $byGroup = [];
        foreach ($rows as $row) {
            $byGroup[(int)$row['group_id']] = $row;
        }

        $groups = [];
        foreach ($this->organization->listGroups($periodId) as $group) {
            $job = $byGroup[(int)$group['id']] ?? null;
            $groups[] = [
                'groupId' => (int)$group['id'],
                'groupName' => $group['name'],
                'status' => $job['status'] ?? 'not_started',
                'attempts' => (int)($job['attempts'] ?? 0),
                'lastError' => $job['last_error'] ?? null,
                'updatedAt' => isset($job['updated_at']) ? (int)$job['updated_at'] : null,
            ];
        }
        $archivedQuery = $this->db->getQueryBuilder();
        $archivedQuery->select(
            'jp.period_id',
            'jp.status',
            'jp.attempts',
            'jp.last_error',
            'jp.updated_at',
            'g.name AS group_name',
            'p.title AS period_title'
        )
            ->from('jugend_provisioning', 'jp')
            ->innerJoin('jp', 'jugend_groups', 'g', $archivedQuery->expr()->eq('jp.group_id', 'g.id'))
            ->innerJoin('g', 'jugend_periods', 'p', $archivedQuery->expr()->eq('g.period_id', 'p.id'))
            ->where($archivedQuery->expr()->eq('jp.resource_type', $archivedQuery->createNamedParameter('talk')))
            ->andWhere($archivedQuery->expr()->eq('p.status', $archivedQuery->createNamedParameter('archived')))
            ->orderBy('p.starts_on', 'DESC')
            ->addOrderBy('g.sort_order', 'ASC');
        $archived = [];
        foreach ($archivedQuery->executeQuery()->fetchAll() as $row) {
            $archived[] = [
                'periodTitle' => $row['period_title'],
                'groupName' => $row['group_name'],
                'status' => $row['status'],
                'attempts' => (int)$row['attempts'],
                'lastError' => $row['last_error'],
                'updatedAt' => (int)$row['updated_at'],
            ];
        }

        return ['period' => $period, 'groups' => $groups, 'archivedGroups' => $archived];
    }

    private function currentDepartmentMembers(): array {
        $members = [];
        foreach ($this->authentik->getRecipients() as $recipient) {
            $uid = trim((string)($recipient['username'] ?? ''));
            if ($uid !== '') {
                $members[$uid] = true;
            }
        }
        return $members;
    }

    /**
     * Reconciles Talk attendees with an allow-list. The provisioning account is
     * the room owner and is never removed.
     */
    private function reconcileParticipants(string $token, array $expectedMembers): array {
        $participants = $this->client->getTalkParticipants($token);
        $serviceUid = $this->client->getServiceAccountUid();
        $existing = [];
        foreach ($participants as $participant) {
            if (($participant['actorType'] ?? '') === 'users' && !empty($participant['actorId'])) {
                $existing[(string)$participant['actorId']] = (int)($participant['attendeeId'] ?? 0);
            }
        }

        $added = [];
        $removed = [];
        $errors = [];
        foreach (array_keys($expectedMembers) as $uid) {
            if (isset($existing[$uid])) {
                continue;
            }
            try {
                $this->client->addTalkParticipant($token, $uid);
                $added[] = $uid;
            } catch (\Throwable $e) {
                $errors[] = ['userId' => $uid, 'action' => 'add', 'message' => $e->getMessage()];
            }
        }

        foreach ($existing as $uid => $attendeeId) {
            if (isset($expectedMembers[$uid]) || $uid === $serviceUid || $attendeeId <= 0) {
                continue;
            }
            try {
                $this->client->removeTalkParticipant($token, $attendeeId);
                $removed[] = $uid;
            } catch (\Throwable $e) {
                $errors[] = ['userId' => $uid, 'action' => 'remove', 'message' => $e->getMessage()];
            }
        }

        return ['added' => $added, 'removed' => $removed, 'errors' => $errors];
    }

    private function listArchivedTalkResources(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select(
            'r.group_id',
            'r.resource_id',
            'r.resource_url',
            'g.name AS group_name',
            'p.id AS period_id',
            'p.title AS period_title'
        )
            ->from('jugend_resources', 'r')
            ->innerJoin('r', 'jugend_groups', 'g', $qb->expr()->eq('r.group_id', 'g.id'))
            ->innerJoin('g', 'jugend_periods', 'p', $qb->expr()->eq('g.period_id', 'p.id'))
            ->where($qb->expr()->eq('r.resource_type', $qb->createNamedParameter('talk')))
            ->andWhere($qb->expr()->eq('p.status', $qb->createNamedParameter('archived')));
        return $qb->executeQuery()->fetchAll();
    }

    /**
     * Claims a unique target row. The conditional update prevents concurrent
     * requests from provisioning the same group simultaneously. A running
     * claim is recoverable after the stale timeout if the request was killed.
     */
    private function claimJob(int $periodId, int $groupId, string $type): bool {
        $now = time();
        $row = $this->findJob($periodId, $groupId, $type);
        if ($row === null) {
            try {
                $qb = $this->db->getQueryBuilder();
                $qb->insert('jugend_provisioning')
                    ->setValue('period_id', $qb->createNamedParameter($periodId))
                    ->setValue('group_id', $qb->createNamedParameter($groupId))
                    ->setValue('resource_type', $qb->createNamedParameter($type))
                    ->setValue('status', $qb->createNamedParameter('pending'))
                    ->setValue('attempts', $qb->createNamedParameter(0))
                    ->setValue('created_at', $qb->createNamedParameter($now))
                    ->setValue('updated_at', $qb->createNamedParameter($now));
                $qb->executeStatement();
            } catch (\Throwable $e) {
                // A concurrent request may have inserted the unique target row.
            }
            $row = $this->findJob($periodId, $groupId, $type);
        }
        if ($row === null) {
            throw new \RuntimeException('Der Provisionierungsstatus konnte nicht angelegt werden.');
        }

        $qb = $this->db->getQueryBuilder();
        $qb->update('jugend_provisioning')
            ->set('status', $qb->createNamedParameter('running'))
            ->set('attempts', $qb->createNamedParameter((int)$row['attempts'] + 1))
            ->set('last_error', $qb->createNamedParameter(null))
            ->set('updated_at', $qb->createNamedParameter($now))
            ->set('started_at', $qb->createNamedParameter($now))
            ->set('finished_at', $qb->createNamedParameter(null))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$row['id'])))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->neq('status', $qb->createNamedParameter('running')),
                $qb->expr()->lt('updated_at', $qb->createNamedParameter($now - self::JOB_STALE_AFTER))
            ));
        return $qb->executeStatement() === 1;
    }

    private function findJob(int $periodId, int $groupId, string $type): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'status', 'attempts')
            ->from('jugend_provisioning')
            ->where($qb->expr()->eq('period_id', $qb->createNamedParameter($periodId)))
            ->andWhere($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
            ->andWhere($qb->expr()->eq('resource_type', $qb->createNamedParameter($type)));
        $row = $qb->executeQuery()->fetch();
        return $row ?: null;
    }

    private function finishJob(int $periodId, int $groupId, string $type, string $status, ?string $error): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('jugend_provisioning')
            ->set('status', $qb->createNamedParameter($status))
            ->set('last_error', $qb->createNamedParameter($error === null ? null : mb_substr($error, 0, 4000)))
            ->set('updated_at', $qb->createNamedParameter(time()))
            ->set('finished_at', $qb->createNamedParameter(time()))
            ->where($qb->expr()->eq('period_id', $qb->createNamedParameter($periodId)))
            ->andWhere($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
            ->andWhere($qb->expr()->eq('resource_type', $qb->createNamedParameter($type)));
        $qb->executeStatement();
    }

    private function findResource(int $groupId, string $type): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('resource_id', 'resource_url')->from('jugend_resources')
            ->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
            ->andWhere($qb->expr()->eq('resource_type', $qb->createNamedParameter($type)));
        $row = $qb->executeQuery()->fetch();
        return $row ?: null;
    }

    private function audit(string $actor, string $action, string $subjectId, array $details): void {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('jugend_audit')
            ->setValue('actor_uid', $qb->createNamedParameter($actor))
            ->setValue('action', $qb->createNamedParameter($action))
            ->setValue('subject_type', $qb->createNamedParameter('group'))
            ->setValue('subject_id', $qb->createNamedParameter($subjectId))
            ->setValue('details', $qb->createNamedParameter(json_encode($details, JSON_THROW_ON_ERROR)))
            ->setValue('created_at', $qb->createNamedParameter(time()));
        $qb->executeStatement();
    }
}
