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

class OrganizationController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private OrganizationService $organization,
        private AuthorizationService $authorization,
        private IGroupManager $groups,
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
        if ($denied = $this->requireAdmin()) { return $denied; }
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
        if ($denied = $this->requireAdmin()) { return $denied; }
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
        if ($denied = $this->requireAdmin()) { return $denied; }
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
        if ($denied = $this->requireAdmin()) { return $denied; }
        return new JSONResponse(['users' => $this->organization->listNextcloudUsers($search)]);
    }

    #[NoAdminRequired]
    public function setMembers(int $groupId = 0, array $members = [], array $leaders = []): JSONResponse {
        if ($denied = $this->requireAdmin()) { return $denied; }
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
        $this->authorization->revokeAppAdmin($userId);
        return new JSONResponse(['ok' => true, 'admins' => $this->authorization->listAppAdmins()]);
    }

}
