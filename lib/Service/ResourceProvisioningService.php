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
