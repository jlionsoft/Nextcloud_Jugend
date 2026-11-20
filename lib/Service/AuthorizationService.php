<?php
declare(strict_types=1);

namespace OCA\Jugend\Service;

use OCP\IDBConnection;
use OCP\IGroupManager;

class AuthorizationService {
    private const ROLE_KEY = 'app_admin';
    private const PERMISSIONS = [
        'organization.manage' => ['Organisationsverwaltung', 'Perioden und Arbeitsgruppen verwalten'],
        'roles.manage' => ['Rollen verwalten', 'App-Administratoren zuweisen und entziehen'],
        'group.members.manage' => ['Gruppenmitgliedschaften verwalten', 'Mitglieder und Leitung der eigenen Arbeitsgruppe verwalten'],
    ];

    public function __construct(private IDBConnection $db, private IGroupManager $groups) {}

    private function ensureDefinitions(): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')->from('jugend_roles')->where($qb->expr()->eq('role_key', $qb->createNamedParameter(self::ROLE_KEY)));
        $roleId = $qb->executeQuery()->fetchOne();
        if ($roleId === false) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('jugend_roles')
                ->setValue('role_key', $qb->createNamedParameter(self::ROLE_KEY))
                ->setValue('name', $qb->createNamedParameter('App-Administration'))
                ->setValue('description', $qb->createNamedParameter('Vollständige Verwaltung der Jugend-App'))
                ->setValue('is_system', $qb->createNamedParameter(true))
                ->setValue('created_at', $qb->createNamedParameter(time()));
            $qb->executeStatement();
            $roleId = $this->db->lastInsertId('*PREFIX*jugend_roles');
        }
        $roleId = (int)$roleId;

        foreach (self::PERMISSIONS as $key => [$name, $description]) {
            $qb = $this->db->getQueryBuilder();
            $qb->select('id')->from('jugend_perms')->where($qb->expr()->eq('permission_key', $qb->createNamedParameter($key)));
            $permissionId = $qb->executeQuery()->fetchOne();
            if ($permissionId === false) {
                $qb = $this->db->getQueryBuilder();
                $qb->insert('jugend_perms')
                    ->setValue('permission_key', $qb->createNamedParameter($key))
                    ->setValue('name', $qb->createNamedParameter($name))
                    ->setValue('description', $qb->createNamedParameter($description));
                $qb->executeStatement();
                $permissionId = $this->db->lastInsertId('*PREFIX*jugend_perms');
            }
            $qb = $this->db->getQueryBuilder();
            $qb->select('id')->from('jugend_role_perm')
                ->where($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId)))
                ->andWhere($qb->expr()->eq('permission_id', $qb->createNamedParameter((int)$permissionId)));
            if ($qb->executeQuery()->fetchOne() === false) {
                $qb = $this->db->getQueryBuilder();
                $qb->insert('jugend_role_perm')
                    ->setValue('role_id', $qb->createNamedParameter($roleId))
                    ->setValue('permission_id', $qb->createNamedParameter((int)$permissionId));
                $qb->executeStatement();
            }
        }
        return $roleId;
    }

    public function can(string $uid, string $permission, ?string $scopeType = null, ?int $scopeId = null): bool {
        if ($uid === '' || !isset(self::PERMISSIONS[$permission])) {
            return false;
        }
        // Global Nextcloud administrators are the trusted bootstrap principals.
        if ($this->groups->isAdmin($uid)) {
            return true;
        }
        $this->ensureDefinitions();

        // Period/group role assignments are effective only for active scopes.
        // Global grants remain effective for draft planning. A current group
        // leader also inherits membership management for the matching draft group.
        $scopeIsActive = true;
        $targetPeriodId = null;
        $targetGroupSlug = null;
        $targetGroupStatus = null;
        if ($scopeType === 'period' && $scopeId !== null) {
            $periodQuery = $this->db->getQueryBuilder();
            $periodQuery->select('id')->from('jugend_periods')
                ->where($periodQuery->expr()->eq('id', $periodQuery->createNamedParameter($scopeId)))
                ->andWhere($periodQuery->expr()->eq('status', $periodQuery->createNamedParameter('active')));
            $scopeIsActive = $periodQuery->executeQuery()->fetchOne() !== false;
            $targetPeriodId = $scopeIsActive ? $scopeId : null;
        } elseif ($scopeType === 'group' && $scopeId !== null) {
            $groupQuery = $this->db->getQueryBuilder();
            $groupQuery->select('g.period_id', 'g.slug', 'p.status')->from('jugend_groups', 'g')
                ->innerJoin('g', 'jugend_periods', 'p', $groupQuery->expr()->eq('g.period_id', 'p.id'))
                ->where($groupQuery->expr()->eq('g.id', $groupQuery->createNamedParameter($scopeId)));
            $groupRow = $groupQuery->executeQuery()->fetch();
            $targetGroupStatus = $groupRow === false ? null : (string)$groupRow['status'];
            $targetGroupSlug = $groupRow === false ? null : (string)$groupRow['slug'];
            $targetPeriodId = $groupRow !== false ? (int)$groupRow['period_id'] : null;
            $scopeIsActive = $targetGroupStatus === 'active';
        }

        // The leader flag is a convenient built-in scoped grant. It is evaluated
        // against the current membership row and only for active groups.
        if ($permission === 'group.members.manage' && $scopeType === 'group' && $scopeId !== null) {
            if ($scopeIsActive) {
                $leaderQuery = $this->db->getQueryBuilder();
                $leaderQuery->select('id')->from('jugend_members')
                    ->where($leaderQuery->expr()->eq('group_id', $leaderQuery->createNamedParameter($scopeId)))
                    ->andWhere($leaderQuery->expr()->eq('user_id', $leaderQuery->createNamedParameter($uid)))
                    ->andWhere($leaderQuery->expr()->eq('is_leader', $leaderQuery->createNamedParameter(true)))
                    ->andWhere($leaderQuery->expr()->isNull('left_at'));
                if ($leaderQuery->executeQuery()->fetchOne() !== false) {
                    return true;
                }
            } elseif ($targetGroupStatus === 'draft' && $targetGroupSlug !== null) {
                // A current leader may plan the matching group in a draft period.
                // Stable group slugs carry the leadership scope across periods.
                $plannedLeaderQuery = $this->db->getQueryBuilder();
                $plannedLeaderQuery->select('m.id')
                    ->from('jugend_members', 'm')
                    ->innerJoin('m', 'jugend_groups', 'g', $plannedLeaderQuery->expr()->eq('m.group_id', 'g.id'))
                    ->innerJoin('g', 'jugend_periods', 'p', $plannedLeaderQuery->expr()->eq('g.period_id', 'p.id'))
                    ->where($plannedLeaderQuery->expr()->eq('g.slug', $plannedLeaderQuery->createNamedParameter($targetGroupSlug)))
                    ->andWhere($plannedLeaderQuery->expr()->eq('p.status', $plannedLeaderQuery->createNamedParameter('active')))
                    ->andWhere($plannedLeaderQuery->expr()->eq('m.user_id', $plannedLeaderQuery->createNamedParameter($uid)))
                    ->andWhere($plannedLeaderQuery->expr()->eq('m.is_leader', $plannedLeaderQuery->createNamedParameter(true)))
                    ->andWhere($plannedLeaderQuery->expr()->isNull('m.left_at'));
                if ($plannedLeaderQuery->executeQuery()->fetchOne() !== false) {
                    return true;
                }
            }
        }

        // Generic RBAC evaluation: global grants apply in every period state.
        // Exact period/group assignments are considered only for active scopes.
        $qb = $this->db->getQueryBuilder();
        $qb->select('ur.id')
            ->from('jugend_user_roles', 'ur')
            ->innerJoin('ur', 'jugend_role_perm', 'rp', $qb->expr()->eq('ur.role_id', 'rp.role_id'))
            ->innerJoin('rp', 'jugend_perms', 'p', $qb->expr()->eq('rp.permission_id', 'p.id'))
            ->where($qb->expr()->eq('ur.user_id', $qb->createNamedParameter($uid)))
            ->andWhere($qb->expr()->eq('p.permission_key', $qb->createNamedParameter($permission)));
        $scopeConditions = [$qb->expr()->andX(
            $qb->expr()->eq('ur.scope_type', $qb->createNamedParameter('global')),
            $qb->expr()->isNull('ur.scope_id')
        )];
        if ($scopeIsActive && $scopeType !== null && $scopeId !== null) {
            $scopeConditions[] = $qb->expr()->andX(
                $qb->expr()->eq('ur.scope_type', $qb->createNamedParameter($scopeType)),
                $qb->expr()->eq('ur.scope_id', $qb->createNamedParameter($scopeId))
            );
            // A period-scoped grant covers every group in that period.
            if ($scopeType === 'group' && $targetPeriodId !== null) {
                $scopeConditions[] = $qb->expr()->andX(
                    $qb->expr()->eq('ur.scope_type', $qb->createNamedParameter('period')),
                    $qb->expr()->eq('ur.scope_id', $qb->createNamedParameter($targetPeriodId))
                );
            }
        }
        $qb->andWhere($qb->expr()->orX(...$scopeConditions));
        return $qb->executeQuery()->fetchOne() !== false;
    }

    public function isAppAdmin(string $uid): bool {
        return $this->can($uid, 'organization.manage');
    }

    public function grantAppAdmin(string $uid, string $actor): void {
        $roleId = $this->ensureDefinitions();
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')->from('jugend_user_roles')
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
            ->andWhere($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId)))
            ->andWhere($qb->expr()->eq('scope_type', $qb->createNamedParameter('global')))
            ->andWhere($qb->expr()->isNull('scope_id'));
        if ($qb->executeQuery()->fetchOne() !== false) {
            return;
        }
        $qb = $this->db->getQueryBuilder();
        $qb->insert('jugend_user_roles')
            ->setValue('user_id', $qb->createNamedParameter($uid))
            ->setValue('role_id', $qb->createNamedParameter($roleId))
            ->setValue('scope_type', $qb->createNamedParameter('global'))
            ->setValue('scope_id', $qb->createNamedParameter(null))
            ->setValue('granted_by', $qb->createNamedParameter($actor))
            ->setValue('granted_at', $qb->createNamedParameter(time()));
        $qb->executeStatement();
        $this->audit($actor, 'role.granted', $uid, ['role' => self::ROLE_KEY]);
    }

    public function revokeAppAdmin(string $uid, string $actor): void {
        $roleId = $this->ensureDefinitions();
        $qb = $this->db->getQueryBuilder();
        $qb->delete('jugend_user_roles')
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
            ->andWhere($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId)))
            ->andWhere($qb->expr()->eq('scope_type', $qb->createNamedParameter('global')))
            ->andWhere($qb->expr()->isNull('scope_id'));
        $qb->executeStatement();
        $this->audit($actor, 'role.revoked', $uid, ['role' => self::ROLE_KEY]);
    }


    public function listPermissions(): array {
        $this->ensureDefinitions();
        $qb = $this->db->getQueryBuilder();
        $qb->select('permission_key', 'name', 'description')->from('jugend_perms')->orderBy('permission_key', 'ASC');
        return $qb->executeQuery()->fetchAll();
    }

    public function listRoles(): array {
        $this->ensureDefinitions();
        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'role_key', 'name', 'description', 'is_system', 'created_at')
            ->from('jugend_roles')->orderBy('is_system', 'DESC')->addOrderBy('name', 'ASC');
        $roles = $qb->executeQuery()->fetchAll();
        foreach ($roles as &$role) {
            $permissions = $this->db->getQueryBuilder();
            $permissions->select('p.permission_key')->from('jugend_role_perm', 'rp')
                ->innerJoin('rp', 'jugend_perms', 'p', $permissions->expr()->eq('rp.permission_id', 'p.id'))
                ->where($permissions->expr()->eq('rp.role_id', $permissions->createNamedParameter((int)$role['id'])))
                ->orderBy('p.permission_key', 'ASC');
            $role['permissions'] = array_column($permissions->executeQuery()->fetchAll(), 'permission_key');
            $role['is_system'] = (bool)$role['is_system'];
        }
        unset($role);
        return $roles;
    }

    public function saveRole(?int $roleId, string $roleKey, string $name, string $description, array $permissionKeys, string $actor, bool $allowPrivilegePermissions): array {
        $this->ensureDefinitions();
        $roleKey = strtolower(trim($roleKey));
        $name = trim($name);
        $description = trim($description);
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $roleKey) || $roleKey === self::ROLE_KEY) {
            throw new \InvalidArgumentException('Der Rollenschlüssel ist ungültig oder reserviert.');
        }
        if ($name === '' || mb_strlen($name) > 128 || mb_strlen($description) > 2000) {
            throw new \InvalidArgumentException('Bitte gib einen gültigen Rollennamen und eine gültige Beschreibung an.');
        }
        $permissionKeys = array_values(array_unique(array_map('strval', $permissionKeys)));
        $known = array_column($this->listPermissions(), 'permission_key');
        if ($permissionKeys === [] || array_diff($permissionKeys, $known) !== []) {
            throw new \InvalidArgumentException('Die Rolle muss mindestens eine gültige Berechtigung enthalten.');
        }
        if (!$allowPrivilegePermissions && in_array('roles.manage', $permissionKeys, true)) {
            throw new \InvalidArgumentException('Die Berechtigung zur Rollenverwaltung darf nur durch globale Nextcloud-Administratoren vergeben werden.');
        }

        if ($roleId !== null) {
            $qb = $this->db->getQueryBuilder();
            $qb->select('id', 'role_key', 'is_system')->from('jugend_roles')
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($roleId)));
            $existing = $qb->executeQuery()->fetch();
            if (!$existing || (bool)$existing['is_system']) {
                throw new \InvalidArgumentException('Diese Systemrolle kann nicht bearbeitet werden.');
            }
            if ($existing['role_key'] !== $roleKey) {
                throw new \InvalidArgumentException('Der Rollenschlüssel kann nach dem Anlegen nicht geändert werden.');
            }
        } else {
            $qb = $this->db->getQueryBuilder();
            $qb->select('id')->from('jugend_roles')
                ->where($qb->expr()->eq('role_key', $qb->createNamedParameter($roleKey)));
            if ($qb->executeQuery()->fetchOne() !== false) {
                throw new \InvalidArgumentException('Dieser Rollenschlüssel ist bereits vergeben.');
            }
        }

        $this->db->beginTransaction();
        try {
            if ($roleId === null) {
                $qb = $this->db->getQueryBuilder();
                $qb->insert('jugend_roles')
                    ->setValue('role_key', $qb->createNamedParameter($roleKey))
                    ->setValue('name', $qb->createNamedParameter($name))
                    ->setValue('description', $qb->createNamedParameter($description ?: null))
                    ->setValue('is_system', $qb->createNamedParameter(false))
                    ->setValue('created_at', $qb->createNamedParameter(time()));
                $qb->executeStatement();
                $roleId = (int)$this->db->lastInsertId('*PREFIX*jugend_roles');
                $action = 'role.created';
            } else {
                $qb = $this->db->getQueryBuilder();
                $qb->update('jugend_roles')
                    ->set('name', $qb->createNamedParameter($name))
                    ->set('description', $qb->createNamedParameter($description ?: null))
                    ->where($qb->expr()->eq('id', $qb->createNamedParameter($roleId)));
                $qb->executeStatement();
                $action = 'role.updated';
            }

            $qb = $this->db->getQueryBuilder();
            $qb->delete('jugend_role_perm')->where($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId)));
            $qb->executeStatement();
            foreach ($permissionKeys as $permissionKey) {
                $qb = $this->db->getQueryBuilder();
                $qb->select('id')->from('jugend_perms')
                    ->where($qb->expr()->eq('permission_key', $qb->createNamedParameter($permissionKey)));
                $permissionId = (int)$qb->executeQuery()->fetchOne();
                $qb = $this->db->getQueryBuilder();
                $qb->insert('jugend_role_perm')
                    ->setValue('role_id', $qb->createNamedParameter($roleId))
                    ->setValue('permission_id', $qb->createNamedParameter($permissionId));
                $qb->executeStatement();
            }
            $this->audit($actor, $action, (string)$roleId, ['roleKey' => $roleKey, 'permissions' => $permissionKeys]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $this->getRole($roleId);
    }

    public function getRole(int $roleId): array {
        foreach ($this->listRoles() as $role) {
            if ((int)$role['id'] === $roleId) {
                return $role;
            }
        }
        throw new \InvalidArgumentException('Die Rolle wurde nicht gefunden.');
    }

    public function deleteRole(int $roleId, string $actor): void {
        $role = $this->getRole($roleId);
        if ($role['is_system']) {
            throw new \InvalidArgumentException('Systemrollen können nicht gelöscht werden.');
        }
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')->from('jugend_user_roles')
            ->where($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId)));
        if ($qb->executeQuery()->fetchOne() !== false) {
            throw new \InvalidArgumentException('Die Rolle ist noch zugewiesen. Entferne zuerst alle Zuweisungen.');
        }
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->delete('jugend_role_perm')->where($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId)));
            $qb->executeStatement();
            $qb = $this->db->getQueryBuilder();
            $qb->delete('jugend_roles')->where($qb->expr()->eq('id', $qb->createNamedParameter($roleId)));
            $qb->executeStatement();
            $this->audit($actor, 'role.deleted', (string)$roleId, ['roleKey' => $role['role_key']]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function validateScope(string $scopeType, ?int $scopeId): void {
        if ($scopeType === 'global') {
            if ($scopeId !== null) {
                throw new \InvalidArgumentException('Globale Rollen dürfen keine Scope-ID besitzen.');
            }
            return;
        }
        if (!in_array($scopeType, ['period', 'group'], true) || $scopeId === null || $scopeId < 1) {
            throw new \InvalidArgumentException('Der Geltungsbereich ist ungültig.');
        }
        $table = $scopeType === 'period' ? 'jugend_periods' : 'jugend_groups';
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')->from($table)->where($qb->expr()->eq('id', $qb->createNamedParameter($scopeId)));
        if ($qb->executeQuery()->fetchOne() === false) {
            throw new \InvalidArgumentException('Der ausgewählte Geltungsbereich existiert nicht.');
        }
    }

    public function assignRole(string $uid, int $roleId, string $scopeType, ?int $scopeId, string $actor, bool $allowPrivilegePermissions): void {
        $role = $this->getRole($roleId);
        $this->validateScope($scopeType, $scopeId);
        if (!$allowPrivilegePermissions && in_array('roles.manage', $role['permissions'], true)) {
            throw new \InvalidArgumentException('Rollen mit Verwaltungsrechten dürfen nur globale Nextcloud-Administratoren zuweisen.');
        }
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')->from('jugend_user_roles')
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
            ->andWhere($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId)))
            ->andWhere($qb->expr()->eq('scope_type', $qb->createNamedParameter($scopeType)));
        if ($scopeId === null) {
            $qb->andWhere($qb->expr()->isNull('scope_id'));
        } else {
            $qb->andWhere($qb->expr()->eq('scope_id', $qb->createNamedParameter($scopeId)));
        }
        if ($qb->executeQuery()->fetchOne() !== false) {
            return;
        }
        $qb = $this->db->getQueryBuilder();
        $qb->insert('jugend_user_roles')
            ->setValue('user_id', $qb->createNamedParameter($uid))
            ->setValue('role_id', $qb->createNamedParameter($roleId))
            ->setValue('scope_type', $qb->createNamedParameter($scopeType))
            ->setValue('scope_id', $qb->createNamedParameter($scopeId))
            ->setValue('granted_by', $qb->createNamedParameter($actor))
            ->setValue('granted_at', $qb->createNamedParameter(time()));
        $qb->executeStatement();
        $this->audit($actor, 'role.assigned', $uid, ['roleId' => $roleId, 'roleKey' => $role['role_key'], 'scopeType' => $scopeType, 'scopeId' => $scopeId]);
    }

    public function revokeRoleAssignment(int $assignmentId, string $actor): void {
        $qb = $this->db->getQueryBuilder();
        $qb->select('ur.id', 'ur.user_id', 'ur.role_id', 'ur.scope_type', 'ur.scope_id', 'r.role_key', 'r.is_system')
            ->from('jugend_user_roles', 'ur')
            ->innerJoin('ur', 'jugend_roles', 'r', $qb->expr()->eq('ur.role_id', 'r.id'))
            ->where($qb->expr()->eq('ur.id', $qb->createNamedParameter($assignmentId)));
        $assignment = $qb->executeQuery()->fetch();
        if (!$assignment) {
            throw new \InvalidArgumentException('Die Rollenzuweisung wurde nicht gefunden.');
        }
        if ((bool)$assignment['is_system']) {
            throw new \InvalidArgumentException('Systemrollen müssen über die dafür vorgesehene Administration verwaltet werden.');
        }
        $qb = $this->db->getQueryBuilder();
        $qb->delete('jugend_user_roles')->where($qb->expr()->eq('id', $qb->createNamedParameter($assignmentId)));
        $qb->executeStatement();
        $this->audit($actor, 'role.unassigned', (string)$assignment['user_id'], ['roleId' => (int)$assignment['role_id'], 'roleKey' => $assignment['role_key'], 'scopeType' => $assignment['scope_type'], 'scopeId' => $assignment['scope_id']]);
    }

    public function listRoleAssignments(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('ur.id', 'ur.user_id', 'ur.role_id', 'ur.scope_type', 'ur.scope_id', 'ur.granted_by', 'ur.granted_at', 'r.role_key', 'r.name')
            ->from('jugend_user_roles', 'ur')
            ->innerJoin('ur', 'jugend_roles', 'r', $qb->expr()->eq('ur.role_id', 'r.id'))
            ->orderBy('ur.user_id', 'ASC')->addOrderBy('r.name', 'ASC');
        return $qb->executeQuery()->fetchAll();
    }


    private function audit(string $actor, string $action, string $subjectUid, array $details): void {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('jugend_audit')
            ->setValue('actor_uid', $qb->createNamedParameter($actor))
            ->setValue('action', $qb->createNamedParameter($action))
            ->setValue('subject_type', $qb->createNamedParameter('user'))
            ->setValue('subject_id', $qb->createNamedParameter($subjectUid))
            ->setValue('details', $qb->createNamedParameter(json_encode($details, JSON_THROW_ON_ERROR)))
            ->setValue('created_at', $qb->createNamedParameter(time()));
        $qb->executeStatement();
    }

    public function listAppAdmins(): array {
        $roleId = $this->ensureDefinitions();
        $qb = $this->db->getQueryBuilder();
        $qb->select('user_id', 'granted_by', 'granted_at')->from('jugend_user_roles')
            ->where($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId)))
            ->andWhere($qb->expr()->eq('scope_type', $qb->createNamedParameter('global')))
            ->andWhere($qb->expr()->isNull('scope_id'))
            ->orderBy('user_id', 'ASC');
        return $qb->executeQuery()->fetchAll();
    }
}
