<?php
declare(strict_types=1);

namespace OCA\Jugend\Controller;

use OCA\Jugend\Service\AuthorizationService;
use OCA\Jugend\Service\OrganizationService;
use OCA\Jugend\Service\ResourceProvisioningService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class GroupController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private OrganizationService $organization,
        private ResourceProvisioningService $resources,
        private AuthorizationService $authorization,
        private IUserSession $session,
    ) {
        parent::__construct($appName, $request);
    }

    private function hasManagedGroups(string $uid): bool {
        foreach ($this->organization->listManageableGroups() as $group) {
            if ($this->authorization->can($uid, 'group.members.manage', 'group', (int)$group['id'])) {
                return true;
            }
        }
        return false;
    }

    private function currentUid(): ?string {
        return $this->session->getUser()?->getUID();
    }

    #[NoAdminRequired]
    public function groups(): JSONResponse {
        $uid = $this->currentUid();
        if ($uid === null) {
            return new JSONResponse(['error' => 'unauthenticated'], 401);
        }

        $groups = [];
        foreach ($this->organization->listActiveGroups() as $group) {
            if ($this->authorization->can($uid, 'group.members.manage', 'group', (int)$group['id'])) {
                $group['resources'] = $this->resources->getGroupResourceLinks((int)$group['id']);
                $groups[] = $group;
            }
        }
        return new JSONResponse(['groups' => $groups]);
    }

    #[NoAdminRequired]
    public function users(): JSONResponse {
        $uid = $this->currentUid();
        if ($uid === null) {
            return new JSONResponse(['error' => 'unauthenticated'], 401);
        }
        if (!$this->hasManagedGroups($uid)) {
            return new JSONResponse(['error' => 'forbidden'], 403);
        }
        return new JSONResponse(['users' => $this->organization->listNextcloudUsers()]);
    }

    #[NoAdminRequired]
    public function setMembers(int $groupId = 0, array $members = [], array $leaders = []): JSONResponse {
        $uid = $this->currentUid();
        if ($uid === null) {
            return new JSONResponse(['error' => 'unauthenticated'], 401);
        }
        if (!$this->authorization->can($uid, 'group.members.manage', 'group', $groupId)) {
            return new JSONResponse(['error' => 'forbidden', 'message' => 'Du darfst nur die Mitgliedschaften von Arbeitsgruppen verwalten, für die du berechtigt bist.'], 403);
        }

        try {
            $group = $this->organization->setMembers($groupId, $members, $leaders, $uid);
            $period = $this->organization->getPeriod((int)$group['period_id']);
            $message = $period['status'] === 'draft'
                ? 'Die Planung wurde gespeichert. Ressourcen werden erst im Rahmen der Vorab-Bereitstellung und nach der Aktivierung freigegeben.'
                : 'Die Mitgliedschaften wurden gespeichert. Externe Gruppen- und Ressourcenrechte werden innerhalb des nächsten Synchronisationslaufs aktualisiert.';
            return new JSONResponse(['group' => $group, 'message' => $message]);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'update_failed', 'message' => 'Die Gruppenmitgliedschaften konnten nicht gespeichert werden.'], 500);
        }
    }
}
