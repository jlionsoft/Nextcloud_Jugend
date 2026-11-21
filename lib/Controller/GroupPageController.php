<?php
declare(strict_types=1);

namespace OCA\Jugend\Controller;

use OCA\Jugend\AppInfo\Application;
use OCA\Jugend\Service\AuthorizationService;
use OCA\Jugend\Service\OrganizationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

class GroupPageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private OrganizationService $organization,
        private AuthorizationService $authorization,
        private IUserSession $session,
        private IInitialState $initialState,
        private IURLGenerator $urlGenerator,
        private IGroupManager $groupManager,
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

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        $uid = $this->session->getUser()?->getUID();
        if ($uid === null || !$this->hasManagedGroups($uid)) {
            return new TemplateResponse(Application::APP_ID, 'forbidden', [], 'error');
        }

        Util::addScript(Application::APP_ID, 'groups');
        Util::addStyle(Application::APP_ID, 'main');
        $this->initialState->provideInitialState('groupUrls', [
            'groups' => $this->urlGenerator->linkToRoute('jugend.group.groups'),
            'users' => $this->urlGenerator->linkToRoute('jugend.group.users'),
            'setMembers' => $this->urlGenerator->linkToRoute('jugend.group.setMembers'),
            'home' => $this->urlGenerator->linkToRoute('jugend.page.index'),
        ]);
        return new TemplateResponse(Application::APP_ID, 'groups', [
            'navigation' => [
                'groups' => $this->urlGenerator->linkToRoute('jugend.groupPage.index'),
                'organization' => $this->authorization->isAppAdmin($uid)
                    ? $this->urlGenerator->linkToRoute('jugend.organizationPage.index')
                    : null,
                'settings' => $this->groupManager->isAdmin($uid)
                    ? $this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => 'jugend'])
                    : null,
            ],
        ]);
    }
}
