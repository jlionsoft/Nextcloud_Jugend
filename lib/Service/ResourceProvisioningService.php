<?php
declare(strict_types=1);

namespace OCA\Jugend\Service;

use OCP\DB\IDBConnection;

class ResourceProvisioningService {
    public function __construct(
        private IDBConnection $db,
        private OrganizationService $organization,
        private ProvisioningService $client,
    ) {
    }

    /**
     * Creates one private Talk room per active-period group and reconciles
     * current group members. Existing room tokens are reused on retries.
     */
    public function provisionTalkRooms(int $periodId, string $actor): array {
        $period = $this->organization->getPeriod($periodId);
        if ($period['status'] !== 'active') {
            throw new \InvalidArgumentException('Talk-Räume können nur für die aktive Organisationsperiode bereitgestellt werden.');
        }

        $result = ['created' => [], 'existing' => [], 'failed' => []];
        foreach ($this->organization->listGroups($periodId) as $group) {
            $groupId = (int)$group['id'];
            $resource = $this->findResource($groupId, 'talk');
            $created = false;

            try {
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
                    $this->audit($actor, 'resource.talk.created', (string)$groupId, ['periodId' => $periodId, 'token' => $room['token']]);
                }

                $token = (string)$resource['resource_id'];
                $existingParticipants = $this->client->getTalkParticipantIds($token);
                $added = [];
                $participantErrors = [];
                foreach ($group['members'] as $member) {
                    $uid = (string)$member['user_id'];
                    if (in_array($uid, $existingParticipants, true)) {
                        continue;
                    }
                    try {
                        $this->client->addTalkParticipant($token, $uid);
                        $added[] = $uid;
                    } catch (\Throwable $e) {
                        $participantErrors[] = ['userId' => $uid, 'message' => $e->getMessage()];
                    }
                }

                $entry = [
                    'groupId' => $groupId,
                    'groupName' => $group['name'],
                    'token' => $token,
                    'url' => $resource['resource_url'],
                    'participantsAdded' => $added,
                    'participantErrors' => $participantErrors,
                ];
                if ($created) {
                    $result['created'][] = $entry;
                } else {
                    $result['existing'][] = $entry;
                }
                if ($participantErrors !== []) {
                    $result['failed'][] = [
                        'groupId' => $groupId,
                        'groupName' => $group['name'],
                        'message' => 'Einige Gruppenmitglieder konnten nicht zum Talk-Raum hinzugefügt werden.',
                        'participants' => $participantErrors,
                    ];
                }
            } catch (\Throwable $e) {
                $result['failed'][] = [
                    'groupId' => $groupId,
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
