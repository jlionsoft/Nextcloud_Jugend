<?php
declare(strict_types=1);

namespace OCA\Jugend\Controller;

use OCA\Jugend\AppInfo\Application;
use OCA\Jugend\Service\OrganizationService;
use OCA\Jugend\Service\ResourceProvisioningService;
use OCA\Jugend\Service\AuthorizationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IUserManager;

class OrganizationController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private OrganizationService $organization,
        private ResourceProvisioningService $resources,
        private AuthorizationService $authorization,
        private IGroupManager $groups,
        private IUserManager $users,
        private IUserSession $session,
        private IAppConfig $appConfig,
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
    public function resourceSyncStatus(): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        $appId = Application::APP_ID;
        $status = $this->appConfig->getValueString($appId, 'resource_sync_status');
        return new JSONResponse([
            'status' => $status !== '' ? $status : 'never',
            'startedAt' => (int)$this->appConfig->getValueString($appId, 'resource_sync_started_at'),
            'finishedAt' => (int)$this->appConfig->getValueString($appId, 'resource_sync_finished_at'),
            'lastSuccessAt' => (int)$this->appConfig->getValueString($appId, 'resource_sync_last_success_at'),
            'lastError' => $this->appConfig->getValueString($appId, 'resource_sync_last_error'),
        ]);
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
    public function clonePeriod(int $sourcePeriodId = 0, string $key = '', string $title = '', ?string $startsOn = null, ?string $endsOn = null): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        $actor = $this->session->getUser()?->getUID();
        if ($actor === null) { return new JSONResponse(['error' => 'unauthenticated'], 401); }
        try {
            return new JSONResponse(['period' => $this->organization->clonePeriod($sourcePeriodId, $key, $title, $startsOn, $endsOn, $actor)], 201);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_template', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'clone_failed', 'message' => 'Die Organisationsperiode konnte nicht aus der Vorlage erstellt werden. Der Schlüssel ist möglicherweise bereits vergeben.'], 500);
        }
    }

    #[NoAdminRequired]
    public function previewPeriod(int $periodId = 0): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        try {
            $preview = $this->organization->previewPeriod($periodId);
            $preview['resourcePreparation'] = $this->resources->getPeriodPreparationStatus($periodId);
            $preview['activationReady'] = $preview['valid'] && $preview['resourcePreparation']['ready'];
            return new JSONResponse($preview);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_period', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'preview_failed', 'message' => 'Die Aktivierungsvorschau konnte nicht erstellt werden.'], 500);
        }
    }

    #[NoAdminRequired]
    public function preparePeriodResources(int $periodId = 0): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        $actor = $this->session->getUser()?->getUID();
        if ($actor === null) { return new JSONResponse(['error' => 'unauthenticated'], 401); }
        try {
            return new JSONResponse([
                'result' => $this->resources->preparePeriodResources($periodId, $actor),
                'status' => $this->resources->getPeriodPreparationStatus($periodId),
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_period', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'preparation_failed', 'message' => $e->getMessage()], 500);
        }
    }

    #[NoAdminRequired]
    public function activatePeriod(int $periodId = 0): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        $actor = $this->session->getUser()?->getUID();
        if ($actor === null) { return new JSONResponse(['error' => 'unauthenticated'], 401); }
        try {
            $this->resources->assertPeriodPrepared($periodId);
            return new JSONResponse(['period' => $this->organization->activatePeriod($periodId, $actor)]);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_period', 'message' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return new JSONResponse(['error' => 'activation_conflict', 'message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'activation_failed', 'message' => 'Die Periode konnte nicht aktiviert werden. Es wurden keine Änderungen übernommen.'], 500);
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
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
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
        } catch (\InvalidArgumentException $e) {
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
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
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
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        }
    }



    #[NoAdminRequired]
    public function resourceProvisioningStatus(int $periodId = 0): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        try {
            return new JSONResponse($this->resources->getResourceProvisioningStatus($periodId));
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_period', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'status_failed', 'message' => 'Der Ressourcenstatus konnte nicht geladen werden.'], 500);
        }
    }

    #[NoAdminRequired]
    public function talkProvisioningStatus(int $periodId = 0): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        try {
            return new JSONResponse($this->resources->getTalkProvisioningStatus($periodId));
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_period', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'status_failed', 'message' => 'Der Provisionierungsstatus konnte nicht geladen werden.'], 500);
        }
    }

    #[NoAdminRequired]
    public function provisionGroupFolders(int $periodId = 0): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        $actor = $this->session->getUser()?->getUID();
        if ($actor === null) { return new JSONResponse(['error' => 'unauthenticated'], 401); }
        try {
            return new JSONResponse($this->resources->provisionGroupFolders($periodId, $actor));
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_period', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'groupfolder_provisioning_failed', 'message' => 'Die Dateiordner konnten nicht vollständig bereitgestellt werden.'], 500);
        }
    }

    #[NoAdminRequired]
    public function provisionCalendars(int $periodId = 0): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        $actor = $this->session->getUser()?->getUID();
        if ($actor === null) { return new JSONResponse(['error' => 'unauthenticated'], 401); }
        try {
            return new JSONResponse($this->resources->provisionCalendars($periodId, $actor));
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_period', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'calendar_provisioning_failed', 'message' => 'Die Kalender konnten nicht vollständig bereitgestellt werden.'], 500);
        }
    }

    #[NoAdminRequired]
    public function provisionTalkRooms(int $periodId = 0): JSONResponse {
        if ($denied = $this->requirePermission('organization.manage')) { return $denied; }
        $actor = $this->session->getUser()?->getUID();
        if ($actor === null) { return new JSONResponse(['error' => 'unauthenticated'], 401); }
        try {
            return new JSONResponse($this->resources->provisionTalkRooms($periodId, $actor));
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_period', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'provisioning_failed', 'message' => 'Die Talk-Räume konnten nicht vollständig bereitgestellt werden.'], 500);
        }
    }


}
