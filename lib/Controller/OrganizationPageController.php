<?php
declare(strict_types=1);

namespace OCA\Jugend\Controller;

use OCA\Jugend\AppInfo\Application;
use OCA\Jugend\Service\AuthorizationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

class OrganizationPageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private AuthorizationService $authorization,
        private IGroupManager $groupManager,
        private IUserSession $userSession,
        private IInitialState $initialState,
        private IURLGenerator $urlGenerator,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(): TemplateResponse {
        $uid = $this->userSession->getUser()?->getUID();
        if ($uid === null || !$this->authorization->isAppAdmin($uid)) {
            return new TemplateResponse(Application::APP_ID, 'forbidden', [], 'error');
        }

        Util::addScript(Application::APP_ID, 'organization');
        Util::addStyle(Application::APP_ID, 'main');
        $this->initialState->provideInitialState('organizationUrls', [
            'periods' => $this->urlGenerator->linkToRoute('jugend.organization.periods'),
            'createPeriod' => $this->urlGenerator->linkToRoute('jugend.organization.createPeriod'),
            'groups' => $this->urlGenerator->linkToRoute('jugend.organization.groups'),
            'createGroup' => $this->urlGenerator->linkToRoute('jugend.organization.createGroup'),
            'users' => $this->urlGenerator->linkToRoute('jugend.organization.users'),
            'setMembers' => $this->urlGenerator->linkToRoute('jugend.organization.setMembers'),
            'appAdmins' => $this->urlGenerator->linkToRoute('jugend.organization.appAdmins'),
            'grantAppAdmin' => $this->urlGenerator->linkToRoute('jugend.organization.grantAppAdmin'),
            'revokeAppAdmin' => $this->urlGenerator->linkToRoute('jugend.organization.revokeAppAdmin'),
            'manageAppAdmins' => $this->groupManager->isAdmin($uid),
            'home' => $this->urlGenerator->linkToRoute('jugend.page.index'),
        ]);
        return new TemplateResponse(Application::APP_ID, 'organization');
    }
}
