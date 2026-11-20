<?php
declare(strict_types=1);

namespace OCA\Jugend\Controller;

use OCA\Jugend\Service\OrganizationService;
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
        private IGroupManager $groups,
        private IUserSession $session,
    ) {
        parent::__construct($appName, $request);
    }

    private function requireAdmin(): ?JSONResponse {
        $uid = $this->session->getUser()?->getUID();
        if ($uid === null || !$this->groups->isAdmin($uid)) {
            return new JSONResponse(['error' => 'forbidden', 'message' => 'Keine Berechtigung für die Organisationsverwaltung.'], 403);
        }
        return null;
    }

    #[NoAdminRequired]
    public function periods(): JSONResponse {
        if ($denied = $this->requireAdmin()) { return $denied; }
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
}
