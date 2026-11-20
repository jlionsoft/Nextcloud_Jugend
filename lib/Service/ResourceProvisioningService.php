<?php
declare(strict_types=1);

namespace OCA\Jugend\Service;

use OCP\DB\IDBConnection;
use RuntimeException;

class ResourceProvisioningService {
    public function __construct(
        private IDBConnection $db,
        private OrganizationService $organization,
        private ProvisioningService $client,
    ) {
    }

    /**
     * Idempotently creates one private Talk room for each group in an active
     * period. Persisting each token immediately makes retries safe after a
     * partial failure.
     */
    public function provisionTalkRooms(int $periodId, string $actor): array {
        $period = $this->organization->getPeriod($periodId);
        if ($period['status'] !== 'active') {
            throw new \InvalidArgumentException('Talk-Räume können nur für die aktive Organisationsperiode bereitgestellt werden.');
        }

        $result = ['created' => [], 'existing' => [], 'failed' => []];
        foreach ($this->organization->listGroups($periodId) as $group) {
            $existing = $this->findResource((int)$group['id'], 'talk');
            if ($existing !== null) {
                $result['existing'][] = ['groupId' => (int)$group['id'], 'groupName' => $group['name'], 'token' => $existing['resource_id']];
                continue;
            }

            try {
                $roomName = mb_substr('Jugend ' . $period['title'] . ' – ' . $group['name'], 0, 255);
                $room = $this->client->createTalkRoom($roomName);
                $baseUrl = rtrim((string)$this->getProvisioningUrl(), '/');
                $url = $baseUrl . '/call/' . rawurlencode($room['token']);

                $qb = $this->db->getQueryBuilder();
                $qb->insert('jugend_resources')
                    ->setValue('group_id', $qb->createNamedParameter((int)$group['id']))
                    ->setValue('resource_type', $qb->createNamedParameter('talk'))
                    ->setValue('resource_id', $qb->createNamedParameter($room['token']))
                    ->setValue('resource_url', $qb->createNamedParameter($url))
                    ->setValue('created_at', $qb->createNamedParameter(time()));
                $qb->executeStatement();

                $result['created'][] = [
                    'groupId' => (int)$group['id'],
                    'groupName' => $group['name'],
                    'token' => $room['token'],
                    'url' => $url,
                ];
                $this->audit($actor, 'resource.talk.created', (string)$group['id'], ['periodId' => $periodId, 'token' => $room['token']]);
            } catch (\Throwable $e) {
                $result['failed'][] = [
                    'groupId' => (int)$group['id'],
                    'groupName' => $group['name'],
                    'message' => $e->getMessage(),
                ];
            }
        }
        return $result;
    }

    private function findResource(int $groupId, string $type): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('resource_id', 'resource_url')->from('jugend_resources')
            ->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
            ->andWhere($qb->expr()->eq('resource_type', $qb->createNamedParameter($type)));
        $row = $qb->executeQuery()->fetch();
        return $row ?: null;
    }

    private function getProvisioningUrl(): string {
        // The configured URL is exposed by the client only through its validated
        // connection; the stored resource URL is reconstructed by the caller.
        return '';
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
