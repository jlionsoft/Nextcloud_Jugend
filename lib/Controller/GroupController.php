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

    private function currentUid(): ?string {
        return $this->session->getUser()?->getUID();
    }

    #[NoAdminRequired]
    public function groups(): JSONResponse {
        $uid = $this->currentUid();
        if ($uid === null) {
            return new JSONResponse(['error' => 'unauthenticated'], 401);
        }

        if ($this->authorization->isAppAdmin($uid)) {
            $groups = [];
            foreach ($this->organization->listPeriods() as $period) {
                if ($period['status'] !== 'active') {
                    continue;
                }
                foreach ($this->organization->listGroups((int)$period['id']) as $group) {
                    $group['period_title'] = $period['title'];
                    $groups[] = $group;
                }
            }
        } else {
            $groups = $this->organization->listGroupsForLeader($uid);
        }

        foreach ($groups as &$group) {
            $group['resources'] = $this->resources->getGroupResourceLinks((int)$group['id']);
        }
        unset($group);
        return new JSONResponse(['groups' => $groups]);
    }

    #[NoAdminRequired]
    public function users(): JSONResponse {
        $uid = $this->currentUid();
        if ($uid === null) {
            return new JSONResponse(['error' => 'unauthenticated'], 401);
        }
        if (!$this->authorization->isAppAdmin($uid) && $this->organization->listGroupsForLeader($uid) === []) {
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
            return new JSONResponse(['error' => 'forbidden', 'message' => 'Du darfst nur die Mitgliedschaften deiner eigenen Arbeitsgruppen verwalten.'], 403);
        }

        try {
            return new JSONResponse([
                'group' => $this->organization->setMembers($groupId, $members, $leaders, $uid),
                'message' => 'Die Mitgliedschaften wurden gespeichert. Externe Gruppen- und Ressourcenrechte werden innerhalb des nächsten Synchronisationslaufs aktualisiert.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'update_failed', 'message' => 'Die Gruppenmitgliedschaften konnten nicht gespeichert werden.'], 500);
        }
    }
}
