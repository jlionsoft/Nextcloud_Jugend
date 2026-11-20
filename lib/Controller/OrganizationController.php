<?php
declare(strict_types=1);

namespace OCA\Jugend\Controller;

use OCA\Jugend\Service\OrganizationService;
use OCA\Jugend\Service\AuthorizationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IUserManager;

class OrganizationController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private OrganizationService $organization,
        private AuthorizationService $authorization,
        private IGroupManager $groups,
        private IUserManager $users,
        private IUserSession $session,
    ) {
        parent::__construct($appName, $request);
    }

    private function requirePermission(string $permission, ?string $scopeType = null, ?int $scopeId = null): ?JSONResponse {
        $uid = $this->session->getUser()?->getUID();
        if ($uid === null || !$this->authorization->can($uid, $permission, $scopeType, $scopeId)) {
            return new JSONResponse(['error' => 'forbidden', 'message' => 'Keine Berechtigung für die Organisationsverwaltung.'], 403);
        }
        return null;
    }

    #[NoAdminRequired]
    public function periods(): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        return new JSONResponse(['periods' => $this->organization->listPeriods()]);
    }

    #[NoAdminRequired]
    public function createPeriod(
        string $key = '',
        string $title = '',
        ?string $startsOn = null,
        ?string $endsOn = null,
    ): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        try {
            $actor = $this->session->getUser()?->getUID();
            if ($actor === null) { return new JSONResponse(['error' => 'unauthenticated'], 401); }
            return new JSONResponse(['period' => $this->organization->createPeriod($key, $title, $startsOn, $endsOn, $actor)], 201);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'create_failed', 'message' => 'Die Organisationsperiode konnte nicht angelegt werden. Der Schlüssel ist möglicherweise bereits vergeben.'], 500);
        }
    }

    #[NoAdminRequired]
    public function groups(int $periodId): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        try {
            return new JSONResponse(['groups' => $this->organization->listGroups($periodId)]);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'not_found', 'message' => $e->getMessage()], 404);
        }
    }

    #[NoAdminRequired]
    public function createGroup(
        int $periodId = 0,
        string $slug = '',
        string $name = '',
        string $description = '',
    ): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        try {
            $actor = $this->session->getUser()?->getUID();
            if ($actor === null) { return new JSONResponse(['error' => 'unauthenticated'], 401); }
            return new JSONResponse(['group' => $this->organization->createGroup($periodId, $slug, $name, $description, $actor)], 201);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'create_failed', 'message' => 'Die Arbeitsgruppe konnte nicht angelegt werden. Der Schlüssel ist möglicherweise bereits vergeben.'], 500);
        }
    }

    #[NoAdminRequired]
    public function users(string $search = ''): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        return new JSONResponse(['users' => $this->organization->listNextcloudUsers($search)]);
    }

    #[NoAdminRequired]
    public function setMembers(int $groupId = 0, array $members = [], array $leaders = []): JSONResponse {
        if ($denied = $this->requirePermission('group.members.manage', 'group', $groupId)) { return $denied; }
        try {
            $actor = $this->session->getUser()?->getUID();
            if ($actor === null) { return new JSONResponse(['error' => 'unauthenticated'], 401); }
            return new JSONResponse(['group' => $this->organization->setMembers($groupId, $members, $leaders, $actor)]);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'update_failed', 'message' => 'Die Gruppenmitgliedschaften konnten nicht gespeichert werden.'], 500);
        }
    }

    #[NoAdminRequired]
    public function appAdmins(): JSONResponse {
        $uid = $this->session->getUser()?->getUID();
        if ($uid === null || !$this->groups->isAdmin($uid)) {
            return new JSONResponse(['error' => 'forbidden'], 403);
        }
        return new JSONResponse(['admins' => $this->authorization->listAppAdmins()]);
    }

    #[NoAdminRequired]
    public function grantAppAdmin(string $userId = ''): JSONResponse {
        $actor = $this->session->getUser()?->getUID();
        if ($actor === null || !$this->groups->isAdmin($actor)) {
            return new JSONResponse(['error' => 'forbidden'], 403);
        }
        $userId = trim($userId);
        if ($userId === '' || $this->users->get($userId) === null) {
            return new JSONResponse(['error' => 'invalid_user', 'message' => 'Der Nextcloud-Benutzer wurde nicht gefunden.'], 400);
        }
        $this->authorization->grantAppAdmin($userId, $actor);
        return new JSONResponse(['ok' => true, 'admins' => $this->authorization->listAppAdmins()]);
    }

    #[NoAdminRequired]
    public function revokeAppAdmin(string $userId = ''): JSONResponse {
        $actor = $this->session->getUser()?->getUID();
        if ($actor === null || !$this->groups->isAdmin($actor)) {
            return new JSONResponse(['error' => 'forbidden'], 403);
        }
        $userId = trim($userId);
        if ($userId === '') {
            return new JSONResponse(['error' => 'invalid_user'], 400);
        }
        $this->authorization->revokeAppAdmin($userId, $actor);
        return new JSONResponse(['ok' => true, 'admins' => $this->authorization->listAppAdmins()]);
    }


    #[NoAdminRequired]
    public function roles(): JSONResponse {
        if ($denied = $this->requirePermission('roles.manage')) { return $denied; }
        return new JSONResponse([
            'roles' => $this->authorization->listRoles(),
            'permissions' => $this->authorization->listPermissions(),
            'assignments' => $this->authorization->listRoleAssignments(),
        ]);
    }

    #[NoAdminRequired]
    public function saveRole(int $roleId = 0, string $roleKey = '', string $name = '', string $description = '', array $permissionKeys = []): JSONResponse {
        if ($denied = $this->requirePermission('roles.manage')) { return $denied; }
        $actor = $this->session->getUser()?->getUID();
        if ($actor === null) { return new JSONResponse(['error' => 'unauthenticated'], 401); }
        try {
            $role = $this->authorization->saveRole($roleId > 0 ? $roleId : null, $roleKey, $name, $description, $permissionKeys, $actor, $this->groups->isAdmin($actor));
            return new JSONResponse(['role' => $role], $roleId > 0 ? 200 : 201);
        } catch (\\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        } catch (\\Throwable $e) {
            return new JSONResponse(['error' => 'save_failed', 'message' => 'Die Rolle konnte nicht gespeichert werden.'], 500);
        }
    }

    #[NoAdminRequired]
    public function deleteRole(int $roleId = 0): JSONResponse {
        if ($denied = $this->requirePermission('roles.manage')) { return $denied; }
        $actor = $this->session->getUser()?->getUID();
        if ($actor === null) { return new JSONResponse(['error' => 'unauthenticated'], 401); }
        try {
            $this->authorization->deleteRole($roleId, $actor);
            return new JSONResponse(['ok' => true]);
        } catch (\\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        }
    }

    #[NoAdminRequired]
    public function assignRole(string $userId = '', int $roleId = 0, string $scopeType = 'global', ?int $scopeId = null): JSONResponse {
        if ($denied = $this->requirePermission('roles.manage')) { return $denied; }
        $actor = $this->session->getUser()?->getUID();
        if ($actor === null) { return new JSONResponse(['error' => 'unauthenticated'], 401); }
        $userId = trim($userId);
        if ($userId === '' || $this->users->get($userId) === null) {
            return new JSONResponse(['error' => 'invalid_user', 'message' => 'Der Nextcloud-Benutzer wurde nicht gefunden.'], 400);
        }
        try {
            $this->authorization->assignRole($userId, $roleId, $scopeType, $scopeId, $actor, $this->groups->isAdmin($actor));
            return new JSONResponse(['ok' => true, 'assignments' => $this->authorization->listRoleAssignments()]);
        } catch (\\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        } catch (\\Throwable $e) {
            return new JSONResponse(['error' => 'assign_failed', 'message' => 'Die Rolle konnte nicht zugewiesen werden.'], 500);
        }
    }

    #[NoAdminRequired]
    public function revokeRole(int $assignmentId = 0): JSONResponse {
        if ($denied = $this->requirePermission('roles.manage')) { return $denied; }
        $actor = $this->session->getUser()?->getUID();
        if ($actor === null) { return new JSONResponse(['error' => 'unauthenticated'], 401); }
        try {
            $this->authorization->revokeRoleAssignment($assignmentId, $actor);
            return new JSONResponse(['ok' => true, 'assignments' => $this->authorization->listRoleAssignments()]);
        } catch (\\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        }
    }


}
