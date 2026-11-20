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
        private AuthentikService $authentik,
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
        $startsOn = $startsOn !== null && trim($startsOn) !== '' ? trim($startsOn) : null;
        $endsOn = $endsOn !== null && trim($endsOn) !== '' ? $endsOn : null;
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


    /**
     * Creates a draft period by copying the group and active membership snapshot
     * from an existing period. Historical rows are not copied.
     */
    public function clonePeriod(int $sourcePeriodId, string $key, string $title, ?string $startsOn, ?string $endsOn, string $actor): array {
        $source = $this->getPeriod($sourcePeriodId);
        $key = strtolower(trim($key));
        $title = trim($title);
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $key)) {
            throw new \InvalidArgumentException('Der Periodenschlüssel darf nur Kleinbuchstaben, Zahlen, Bindestriche und Unterstriche enthalten.');
        }
        if ($title === '' || mb_strlen($title) > 128) {
            throw new \InvalidArgumentException('Bitte gib einen gültigen Namen für die Organisationsperiode an.');
        }
        $startsOn = $startsOn !== null && trim($startsOn) !== '' ? trim($startsOn) : null;
        $endsOn = $endsOn !== null && trim($endsOn) !== '' ? trim($endsOn) : null;
        $this->validateDateRange($startsOn, $endsOn);
        $sourceGroups = $this->listGroups($sourcePeriodId);
        $now = time();

        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('jugend_periods')
                ->setValue('period_key', $qb->createNamedParameter($key))
                ->setValue('title', $qb->createNamedParameter($title))
                ->setValue('status', $qb->createNamedParameter('draft'))
                ->setValue('starts_on', $qb->createNamedParameter($startsOn))
                ->setValue('ends_on', $qb->createNamedParameter($endsOn))
                ->setValue('created_by', $qb->createNamedParameter($actor))
                ->setValue('created_at', $qb->createNamedParameter($now))
                ->setValue('updated_at', $qb->createNamedParameter($now));
            $qb->executeStatement();
            $newPeriodId = (int)$this->db->lastInsertId('*PREFIX*jugend_periods');

            foreach ($sourceGroups as $sourceGroup) {
                $qb = $this->db->getQueryBuilder();
                $qb->insert('jugend_groups')
                    ->setValue('period_id', $qb->createNamedParameter($newPeriodId))
                    ->setValue('slug', $qb->createNamedParameter($sourceGroup['slug']))
                    ->setValue('name', $qb->createNamedParameter($sourceGroup['name']))
                    ->setValue('description', $qb->createNamedParameter($sourceGroup['description']))
                    ->setValue('sort_order', $qb->createNamedParameter((int)$sourceGroup['sort_order']))
                    ->setValue('created_at', $qb->createNamedParameter($now))
                    ->setValue('updated_at', $qb->createNamedParameter($now));
                $qb->executeStatement();
                $newGroupId = (int)$this->db->lastInsertId('*PREFIX*jugend_groups');

                foreach ($sourceGroup['members'] as $member) {
                    $qb = $this->db->getQueryBuilder();
                    $qb->insert('jugend_members')
                        ->setValue('group_id', $qb->createNamedParameter($newGroupId))
                        ->setValue('user_id', $qb->createNamedParameter($member['user_id']))
                        ->setValue('is_leader', $qb->createNamedParameter((bool)$member['is_leader']))
                        ->setValue('joined_at', $qb->createNamedParameter($now))
                        ->setValue('left_at', $qb->createNamedParameter(null));
                    $qb->executeStatement();
                }
            }

            $this->audit($actor, 'period.cloned', 'period', (string)$newPeriodId, [
                'sourcePeriodId' => $sourcePeriodId,
                'sourcePeriodKey' => $source['period_key'],
                'groupCount' => count($sourceGroups),
                'memberCount' => count(array_unique(array_merge(...array_map(
                    static fn(array $group): array => array_column($group['members'], 'user_id'),
                    $sourceGroups ?: [[]]
                )))),
            ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->getPeriod($newPeriodId);
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

    /**
     * Returns only active work groups for which the given user is a current leader.
     */
    /** Returns all groups in the current active period, including their period label. */
    public function listActiveGroups(): array {
        $groups = [];
        foreach ($this->listPeriods() as $period) {
            if ($period['status'] !== 'active') {
                continue;
            }
            foreach ($this->listGroups((int)$period['id']) as $group) {
                $group['period_title'] = $period['title'];
                $groups[] = $group;
            }
        }
        return $groups;
    }

    public function listGroupsForLeader(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('g.id', 'p.title AS period_title')
            ->from('jugend_groups', 'g')
            ->innerJoin('g', 'jugend_periods', 'p', $qb->expr()->eq('g.period_id', 'p.id'))
            ->innerJoin('g', 'jugend_members', 'm', $qb->expr()->eq('m.group_id', 'g.id'))
            ->where($qb->expr()->eq('p.status', $qb->createNamedParameter('active')))
            ->andWhere($qb->expr()->eq('m.user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('m.is_leader', $qb->createNamedParameter(true)))
            ->andWhere($qb->expr()->isNull('m.left_at'))
            ->orderBy('g.sort_order', 'ASC')
            ->addOrderBy('g.name', 'ASC');

        $groups = [];
        foreach ($qb->executeQuery()->fetchAll() as $row) {
            $group = $this->getGroup((int)$row['id']);
            $group['period_title'] = $row['period_title'];
            $groups[] = $group;
        }
        return $groups;
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
        if (!in_array($period['status'], ['draft', 'active'], true)) {
            throw new \InvalidArgumentException('Mitgliedschaften können nur in geplanten oder aktiven Organisationsperioden geändert werden.');
        }
        $members = array_values(array_unique(array_filter(array_map('trim', $memberUids), static fn(string $uid): bool => $uid !== '')));
        $leaders = array_values(array_unique(array_filter(array_map('trim', $leaderUids), static fn(string $uid): bool => $uid !== '')));
        if (count($leaders) !== 2) {
            throw new \InvalidArgumentException('Jede Arbeitsgruppe benötigt genau zwei Gruppenleiter.');
        }
        if (array_diff($leaders, $members) !== []) {
            throw new \InvalidArgumentException('Beide Gruppenleiter müssen auch Mitglieder der Arbeitsgruppe sein.');
        }
        $allowedUsers = [];
        foreach ($this->authentik->getRecipients() as $recipient) {
            $allowedUid = trim((string)($recipient['username'] ?? ''));
            if ($allowedUid !== '') {
                $allowedUsers[$allowedUid] = true;
            }
        }
        foreach ($members as $uid) {
            if (!isset($allowedUsers[$uid]) || $this->users->get($uid) === null) {
                throw new \InvalidArgumentException('Der Benutzer „' . $uid . '“ ist nicht mehr Mitglied der aktuellen App-Zugriffsgruppe oder existiert nicht in Nextcloud.');
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


    /**
     * Returns a non-mutating activation preview, including validation and membership deltas.
     */
    public function previewPeriod(int $periodId): array {
        $period = $this->getPeriod($periodId);
        if ($period['status'] !== 'draft') {
            throw new \InvalidArgumentException('Nur geplante Perioden können aktiviert werden.');
        }

        $groups = $this->listGroups($periodId);
        $errors = [];
        if ($groups === []) {
            $errors[] = 'Die Periode benötigt mindestens eine Arbeitsgruppe.';
        }

        $allowedUsers = [];
        foreach ($this->authentik->getRecipients() as $recipient) {
            $uid = trim((string)($recipient['username'] ?? ''));
            if ($uid !== '') { $allowedUsers[$uid] = true; }
        }

        foreach ($groups as $group) {
            $leaders = array_values(array_filter($group['members'], static fn(array $member): bool =>
                $member['is_leader'] === true || $member['is_leader'] === 1 || $member['is_leader'] === '1'
            ));
            if (count($leaders) !== 2) {
                $errors[] = $group['name'] . ': Es müssen genau zwei aktive Gruppenleitungen festgelegt sein.';
            }
            foreach ($group['members'] as $member) {
                $uid = (string)$member['user_id'];
                if (!isset($allowedUsers[$uid]) || $this->users->get($uid) === null) {
                    $errors[] = $group['name'] . ': Mitglied „' . $uid . '“ ist nicht mehr als App-Benutzer verfügbar.';
                }
            }
        }

        $active = null;
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('jugend_periods')
            ->where($qb->expr()->eq('status', $qb->createNamedParameter('active')))
            ->setMaxResults(1);
        $active = $qb->executeQuery()->fetch();
        $activeGroups = $active ? $this->listGroups((int)$active['id']) : [];
        $oldBySlug = [];
        foreach ($activeGroups as $group) { $oldBySlug[$group['slug']] = $group; }
        $newBySlug = [];
        foreach ($groups as $group) { $newBySlug[$group['slug']] = $group; }

        $addedGroups = [];
        $removedGroups = [];
        $membershipChanges = [];
        $renamedGroups = [];
        foreach ($newBySlug as $slug => $group) {
            if (!isset($oldBySlug[$slug])) {
                $addedGroups[] = ['slug' => $slug, 'name' => $group['name']];
                continue;
            }
            if ($oldBySlug[$slug]['name'] !== $group['name']) {
                $renamedGroups[] = ['from' => $oldBySlug[$slug]['name'], 'to' => $group['name']];
            }
            $oldMembers = [];
            foreach ($oldBySlug[$slug]['members'] as $member) {
                $oldMembers[$member['user_id']] = (bool)$member['is_leader'];
            }
            $newMembers = [];
            foreach ($group['members'] as $member) {
                $newMembers[$member['user_id']] = (bool)$member['is_leader'];
            }
            $added = array_values(array_diff(array_keys($newMembers), array_keys($oldMembers)));
            $removed = array_values(array_diff(array_keys($oldMembers), array_keys($newMembers)));
            $leadershipChanged = [];
            foreach (array_intersect(array_keys($oldMembers), array_keys($newMembers)) as $uid) {
                if ($oldMembers[$uid] !== $newMembers[$uid]) { $leadershipChanged[] = $uid; }
            }
            if ($added !== [] || $removed !== [] || $leadershipChanged !== []) {
                $membershipChanges[] = [
                    'group' => $group['name'],
                    'added' => $added,
                    'removed' => $removed,
                    'leadershipChanged' => $leadershipChanged,
                ];
            }
        }
        foreach ($oldBySlug as $slug => $group) {
            if (!isset($newBySlug[$slug])) {
                $removedGroups[] = ['slug' => $slug, 'name' => $group['name']];
            }
        }

        return [
            'period' => $period,
            'previousActivePeriod' => $active ?: null,
            'valid' => $errors === [],
            'errors' => $errors,
            'summary' => [
                'groupCount' => count($groups),
                'memberCount' => count(array_unique(array_merge(...array_map(
                    static fn(array $group): array => array_column($group['members'], 'user_id'),
                    $groups ?: [[]]
                )))),
                'addedGroups' => $addedGroups,
                'removedGroups' => $removedGroups,
                'renamedGroups' => $renamedGroups,
                'membershipChanges' => $membershipChanges,
            ],
        ];
    }

    /**
     * Atomically switches the active period after a fresh server-side validation.
     */
    public function activatePeriod(int $periodId, string $actor): array {
        $preview = $this->previewPeriod($periodId);
        if (!$preview['valid']) {
            throw new \InvalidArgumentException('Die Periode kann nicht aktiviert werden: ' . implode(' ', $preview['errors']));
        }

        $now = time();
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->update('jugend_periods')
                ->set('status', $qb->createNamedParameter('archived'))
                ->set('archived_at', $qb->createNamedParameter($now))
                ->set('updated_at', $qb->createNamedParameter($now))
                ->where($qb->expr()->eq('status', $qb->createNamedParameter('active')));
            $qb->executeStatement();

            $qb = $this->db->getQueryBuilder();
            $qb->update('jugend_periods')
                ->set('status', $qb->createNamedParameter('active'))
                ->set('activated_at', $qb->createNamedParameter($now))
                ->set('archived_at', $qb->createNamedParameter(null))
                ->set('updated_at', $qb->createNamedParameter($now))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($periodId)))
                ->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('draft')));
            if ($qb->executeStatement() !== 1) {
                throw new \RuntimeException('Der Periodenstatus hat sich während der Aktivierung geändert. Bitte lade die Vorschau erneut.');
            }

            $this->audit($actor, 'period.activated', 'period', (string)$periodId, [
                'previousPeriodId' => $preview['previousActivePeriod']['id'] ?? null,
                'groupCount' => $preview['summary']['groupCount'],
                'memberCount' => $preview['summary']['memberCount'],
            ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->getPeriod($periodId);
    }

    public function listNextcloudUsers(string $search = ''): array {
        $allowed = [];
        foreach ($this->authentik->getRecipients() as $recipient) {
            $uid = trim((string)($recipient['username'] ?? ''));
            if ($uid !== '') { $allowed[$uid] = true; }
        }
        $result = [];
        foreach ($this->users->search(trim($search), 200) as $user) {
            $uid = $user->getUID();
            if (!isset($allowed[$uid])) { continue; }
            $result[] = ['uid' => $uid, 'displayName' => $user->getDisplayName()];
        }
        return $result;
    }

    private function validateDateRange(?string $startsOn, ?string $endsOn): void {
        foreach (['Beginn' => $startsOn, 'Ende' => $endsOn] as $label => $date) {
            if ($date === null || $date === '') {
                continue;
            }
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $dateErrors = \DateTimeImmutable::getLastErrors();
            if ($parsed === false
                || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                || $parsed->format('Y-m-d') !== $date) {
                throw new \InvalidArgumentException('Ungültiges Datum für ' . $label . '. Bitte ein gültiges Kalenderdatum angeben.');
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
