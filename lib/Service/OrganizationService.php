<?php
declare(strict_types=1);

namespace OCA\Jugend\Service;

use OCP\DB\Exception;
use OCP\IDBConnection;
use OCP\IUserManager;
use RuntimeException;

class OrganizationService {
    public function __construct(
        private IDBConnection $db,
        private IUserManager $users,
    ) {
    }

    public function listPeriods(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('jugend_periods')->orderBy('starts_on', 'DESC')->addOrderBy('id', 'DESC');
        return $qb->executeQuery()->fetchAll();
    }

    public function createPeriod(string $key, string $title, ?string $startsOn, ?string $endsOn, string $actor): array {
        $key = strtolower(trim($key));
        $title = trim($title);
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $key)) {
            throw new \InvalidArgumentException('Der Periodenschlüssel darf nur Kleinbuchstaben, Zahlen, Bindestriche und Unterstriche enthalten.');
        }
        if ($title === '' || mb_strlen($title) > 128) {
            throw new \InvalidArgumentException('Bitte gib einen gültigen Namen für die Organisationsperiode an.');
        }
        $this->validateDateRange($startsOn, $endsOn);
        $now = time();
        $qb = $this->db->getQueryBuilder();
        $qb->insert('jugend_periods')
            ->setValue('period_key', $qb->createNamedParameter($key))
            ->setValue('title', $qb->createNamedParameter($title))
            ->setValue('status', $qb->createNamedParameter('draft'))
            ->setValue('starts_on', $startsOn === null ? $qb->createNamedParameter(null) : $qb->createNamedParameter($startsOn))
            ->setValue('ends_on', $endsOn === null ? $qb->createNamedParameter(null) : $qb->createNamedParameter($endsOn))
            ->setValue('created_by', $qb->createNamedParameter($actor))
            ->setValue('created_at', $qb->createNamedParameter($now))
            ->setValue('updated_at', $qb->createNamedParameter($now));
        $qb->executeStatement();
        $id = (int)$this->db->lastInsertId('*PREFIX*jugend_periods');
        $this->audit($actor, 'period.created', 'period', (string)$id, ['key' => $key]);
        return $this->getPeriod($id);
    }

    public function getPeriod(int $id): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('jugend_periods')->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        $row = $qb->executeQuery()->fetch();
        if (!$row) {
            throw new \InvalidArgumentException('Die Organisationsperiode wurde nicht gefunden.');
        }
        return $row;
    }

    public function listGroups(int $periodId): array {
        $this->getPeriod($periodId);
        $qb = $this->db->getQueryBuilder();
        $qb->select('g.*')->from('jugend_groups', 'g')
            ->where($qb->expr()->eq('g.period_id', $qb->createNamedParameter($periodId)))
            ->orderBy('g.sort_order', 'ASC')->addOrderBy('g.name', 'ASC');
        $groups = $qb->executeQuery()->fetchAll();
        foreach ($groups as &$group) {
            $group['members'] = $this->listMembers((int)$group['id']);
        }
        unset($group);
        return $groups;
    }

    public function createGroup(int $periodId, string $slug, string $name, string $description, string $actor): array {
        $period = $this->getPeriod($periodId);
        if ($period['status'] !== 'draft') {
            throw new \InvalidArgumentException('Arbeitsgruppen können nur in einer geplanten Organisationsperiode angelegt werden.');
        }
        $slug = strtolower(trim($slug));
        $name = trim($name);
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug)) {
            throw new \InvalidArgumentException('Der Gruppenschlüssel darf nur Kleinbuchstaben, Zahlen, Bindestriche und Unterstriche enthalten.');
        }
        if ($name === '' || mb_strlen($name) > 128) {
            throw new \InvalidArgumentException('Bitte gib einen gültigen Gruppennamen an.');
        }
        $now = time();
        $qb = $this->db->getQueryBuilder();
        $qb->insert('jugend_groups')
            ->setValue('period_id', $qb->createNamedParameter($periodId))
            ->setValue('slug', $qb->createNamedParameter($slug))
            ->setValue('name', $qb->createNamedParameter($name))
            ->setValue('description', $qb->createNamedParameter(trim($description) ?: null))
            ->setValue('sort_order', $qb->createNamedParameter(0))
            ->setValue('created_at', $qb->createNamedParameter($now))
            ->setValue('updated_at', $qb->createNamedParameter($now));
        $qb->executeStatement();
        $id = (int)$this->db->lastInsertId('*PREFIX*jugend_groups');
        $this->audit($actor, 'group.created', 'group', (string)$id, ['periodId' => $periodId, 'slug' => $slug]);
        return $this->getGroup($id);
    }

    public function getGroup(int $id): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('jugend_groups')->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        $row = $qb->executeQuery()->fetch();
        if (!$row) {
            throw new \InvalidArgumentException('Die Arbeitsgruppe wurde nicht gefunden.');
        }
        $row['members'] = $this->listMembers($id);
        return $row;
    }

    public function listMembers(int $groupId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('user_id', 'is_leader', 'joined_at', 'left_at')
            ->from('jugend_members')
            ->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
            ->andWhere($qb->expr()->isNull('left_at'))
            ->orderBy('is_leader', 'DESC')->addOrderBy('user_id', 'ASC');
        return $qb->executeQuery()->fetchAll();
    }

    /**
     * Replaces the planned membership snapshot. Exactly two distinct leaders are mandatory.
     * Historical rows are closed, never deleted.
     */
    public function setMembers(int $groupId, array $memberUids, array $leaderUids, string $actor): array {
        $group = $this->getGroup($groupId);
        $period = $this->getPeriod((int)$group['period_id']);
        if ($period['status'] !== 'draft') {
            throw new \InvalidArgumentException('Mitgliedschaften können nur in einer geplanten Organisationsperiode geändert werden.');
        }
        $members = array_values(array_unique(array_filter(array_map('trim', $memberUids), static fn(string $uid): bool => $uid !== '')));
        $leaders = array_values(array_unique(array_filter(array_map('trim', $leaderUids), static fn(string $uid): bool => $uid !== '')));
        if (count($leaders) !== 2) {
            throw new \InvalidArgumentException('Jede Arbeitsgruppe benötigt genau zwei Gruppenleiter.');
        }
        if (array_diff($leaders, $members) !== []) {
            throw new \InvalidArgumentException('Beide Gruppenleiter müssen auch Mitglieder der Arbeitsgruppe sein.');
        }
        foreach ($members as $uid) {
            if ($this->users->get($uid) === null) {
                throw new \InvalidArgumentException('Der Nextcloud-Benutzer „' . $uid . '“ existiert nicht.');
            }
        }

        $now = time();
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->update('jugend_members')->set('left_at', $qb->createNamedParameter($now))
                ->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
                ->andWhere($qb->expr()->isNull('left_at'));
            $qb->executeStatement();

            foreach ($members as $uid) {
                $qb = $this->db->getQueryBuilder();
                $qb->insert('jugend_members')
                    ->setValue('group_id', $qb->createNamedParameter($groupId))
                    ->setValue('user_id', $qb->createNamedParameter($uid))
                    ->setValue('is_leader', $qb->createNamedParameter(in_array($uid, $leaders, true)))
                    ->setValue('joined_at', $qb->createNamedParameter($now))
                    ->setValue('left_at', $qb->createNamedParameter(null));
                $qb->executeStatement();
            }
            $this->audit($actor, 'group.members_replaced', 'group', (string)$groupId, [
                'members' => $members,
                'leaders' => $leaders,
            ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $this->getGroup($groupId);
    }

    public function listNextcloudUsers(string $search = ''): array {
        $result = [];
        foreach ($this->users->search(trim($search), 200) as $user) {
            $result[] = [
                'uid' => $user->getUID(),
                'displayName' => $user->getDisplayName(),
            ];
        }
        return $result;
    }

    private function validateDateRange(?string $startsOn, ?string $endsOn): void {
        foreach (['Beginn' => $startsOn, 'Ende' => $endsOn] as $label => $date) {
            if ($date !== null && $date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new \InvalidArgumentException('Ungültiges Datum für ' . $label . '.');
            }
        }
        if ($startsOn && $endsOn && $endsOn < $startsOn) {
            throw new \InvalidArgumentException('Das Ende darf nicht vor dem Beginn liegen.');
        }
    }

    private function audit(string $actor, string $action, string $type, string $id, array $details): void {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('jugend_audit')
            ->setValue('actor_uid', $qb->createNamedParameter($actor))
            ->setValue('action', $qb->createNamedParameter($action))
            ->setValue('subject_type', $qb->createNamedParameter($type))
            ->setValue('subject_id', $qb->createNamedParameter($id))
            ->setValue('details', $qb->createNamedParameter(json_encode($details, JSON_THROW_ON_ERROR)))
            ->setValue('created_at', $qb->createNamedParameter(time()));
        $qb->executeStatement();
    }
}
