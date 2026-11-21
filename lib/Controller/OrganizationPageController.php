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
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

class OrganizationPageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private AuthorizationService $authorization,
        private OrganizationService $organization,
        private IGroupManager $groupManager,
        private IUserSession $userSession,
        private IInitialState $initialState,
        private IURLGenerator $urlGenerator,
        private IL10N $l,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
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
            'clonePeriod' => $this->urlGenerator->linkToRoute('jugend.organization.clonePeriod'),
            'previewPeriod' => $this->urlGenerator->linkToRoute('jugend.organization.previewPeriod'),
            'activatePeriod' => $this->urlGenerator->linkToRoute('jugend.organization.activatePeriod'),
            'preparePeriodResources' => $this->urlGenerator->linkToRoute('jugend.organization.preparePeriodResources'),
            'provisionTalkRooms' => $this->urlGenerator->linkToRoute('jugend.organization.provisionTalkRooms'),
            'provisionGroupFolders' => $this->urlGenerator->linkToRoute('jugend.organization.provisionGroupFolders'),
            'provisionCalendars' => $this->urlGenerator->linkToRoute('jugend.organization.provisionCalendars'),
            'talkProvisioningStatus' => $this->urlGenerator->linkToRoute('jugend.organization.talkProvisioningStatus'),
            'resourceProvisioningStatus' => $this->urlGenerator->linkToRoute('jugend.organization.resourceProvisioningStatus'),
            'resourceSyncStatus' => $this->urlGenerator->linkToRoute('jugend.organization.resourceSyncStatus'),
            'groups' => $this->urlGenerator->linkToRoute('jugend.organization.groups'),
            'createGroup' => $this->urlGenerator->linkToRoute('jugend.organization.createGroup'),
            'users' => $this->urlGenerator->linkToRoute('jugend.organization.users'),
            'setMembers' => $this->urlGenerator->linkToRoute('jugend.organization.setMembers'),
            'appAdmins' => $this->urlGenerator->linkToRoute('jugend.organization.appAdmins'),
            'grantAppAdmin' => $this->urlGenerator->linkToRoute('jugend.organization.grantAppAdmin'),
            'revokeAppAdmin' => $this->urlGenerator->linkToRoute('jugend.organization.revokeAppAdmin'),
            'roles' => $this->urlGenerator->linkToRoute('jugend.organization.roles'),
            'saveRole' => $this->urlGenerator->linkToRoute('jugend.organization.saveRole'),
            'deleteRole' => $this->urlGenerator->linkToRoute('jugend.organization.deleteRole'),
            'assignRole' => $this->urlGenerator->linkToRoute('jugend.organization.assignRole'),
            'revokeRole' => $this->urlGenerator->linkToRoute('jugend.organization.revokeRole'),
            'manageRoles' => $this->authorization->can($uid, 'roles.manage'),
            'manageAppAdmins' => $this->groupManager->isAdmin($uid),
        ]);
        $groupsUrl = null;
        foreach ($this->organization->listManageableGroups() as $group) {
            if ($this->authorization->can($uid, 'group.members.manage', 'group', (int)$group['id'])) {
                $groupsUrl = $this->urlGenerator->linkToRoute('jugend.groupPage.index');
                break;
            }
        }
        $navigation = [
            'organization' => $this->urlGenerator->linkToRoute('jugend.organizationPage.index'),
            'groups' => $groupsUrl,
            'settings' => $this->groupManager->isAdmin($uid)
                ? $this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => 'jugend'])
                : null,
        ];
        return new TemplateResponse(Application::APP_ID, 'organization', ['navigation' => $navigation]);
    }
}
