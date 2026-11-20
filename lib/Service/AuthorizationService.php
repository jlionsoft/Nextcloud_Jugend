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

        // The leader flag is a convenient built-in scoped grant. It is evaluated
        // against the current membership row, so removing leadership revokes access.
        if ($permission === 'group.members.manage' && $scopeType === 'group' && $scopeId !== null) {
            $leaderQuery = $this->db->getQueryBuilder();
            $leaderQuery->select('id')->from('jugend_members')
                ->where($leaderQuery->expr()->eq('group_id', $leaderQuery->createNamedParameter($scopeId)))
                ->andWhere($leaderQuery->expr()->eq('user_id', $leaderQuery->createNamedParameter($uid)))
                ->andWhere($leaderQuery->expr()->eq('is_leader', $leaderQuery->createNamedParameter(true)))
                ->andWhere($leaderQuery->expr()->isNull('left_at'));
            if ($leaderQuery->executeQuery()->fetchOne() !== false) {
                return true;
            }
        }

        // Generic RBAC evaluation: a user role must carry the requested
        // permission and match either global scope or the exact requested scope.
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
        if ($scopeType !== null && $scopeId !== null) {
            $scopeConditions[] = $qb->expr()->andX(
                $qb->expr()->eq('ur.scope_type', $qb->createNamedParameter($scopeType)),
                $qb->expr()->eq('ur.scope_id', $qb->createNamedParameter($scopeId))
            );
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
    }

    public function revokeAppAdmin(string $uid): void {
        $roleId = $this->ensureDefinitions();
        $qb = $this->db->getQueryBuilder();
        $qb->delete('jugend_user_roles')
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
            ->andWhere($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId)))
            ->andWhere($qb->expr()->eq('scope_type', $qb->createNamedParameter('global')))
            ->andWhere($qb->expr()->isNull('scope_id'));
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
