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
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

class AccountingPageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private AuthorizationService $authorization,
        private OrganizationService $organization,
        private IGroupManager $groupManager,
        private IUserSession $session,
        private IInitialState $initialState,
        private IURLGenerator $urlGenerator,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function manage(): TemplateResponse {
        $uid = $this->session->getUser()?->getUID();
        if ($uid === null || !$this->authorization->isAppUser($uid) || !$this->authorization->canManageAccounting($uid)) {
            return new TemplateResponse(Application::APP_ID, 'forbidden', [], 'error');
        }
        return $this->render('manage', $uid);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function personal(): TemplateResponse {
        $uid = $this->session->getUser()?->getUID();
        if ($uid === null || !$this->authorization->isAppUser($uid)) {
            return new TemplateResponse(Application::APP_ID, 'forbidden', [], 'error');
        }
        return $this->render('personal', $uid);
    }

    private function render(string $mode, string $uid): TemplateResponse {
        Util::addScript(Application::APP_ID, 'accounting');
        Util::addStyle(Application::APP_ID, 'main');
        $this->initialState->provideInitialState('accountingUrls', [
            'overview' => $this->urlGenerator->linkToRoute('jugend.accounting.overview'),
            'addEntry' => $this->urlGenerator->linkToRoute('jugend.accounting.addEntry'),
            'personal' => $this->urlGenerator->linkToRoute('jugend.accounting.personal'),
            'setStart' => $this->urlGenerator->linkToRoute('jugend.accounting.setStart'),
            'addEvent' => $this->urlGenerator->linkToRoute('jugend.accounting.addEvent'),
            'report' => $this->urlGenerator->linkToRoute('jugend.accounting.report'),
        ]);
        $manageUrl = $this->authorization->canManageAccounting($uid)
            ? $this->urlGenerator->linkToRoute('jugend.accountingPage.manage')
            : null;
        $personalUrl = $this->urlGenerator->linkToRoute('jugend.accountingPage.personal');

        $navigation = [];
        $groupsUrl = null;
        foreach ($this->organization->listManageableGroups() as $group) {
            if ($this->authorization->can($uid, 'group.members.manage', 'group', (int)$group['id'])) {
                $groupsUrl = $this->urlGenerator->linkToRoute('jugend.groupPage.index');
                break;
            }
        }
        if ($manageUrl !== null) {
            $navigation[] = ['label' => 'Buchhaltung verwalten', 'url' => $manageUrl, 'active' => $mode === 'manage', 'icon' => 'icon-category-workflow'];
        }
        $navigation[] = ['label' => 'Mein Kontostand', 'url' => $personalUrl, 'active' => $mode === 'personal', 'icon' => 'icon-paypal'];
        if ($this->authorization->isAppAdmin($uid)) {
            $navigation[] = ['label' => 'Organisation', 'url' => $this->urlGenerator->linkToRoute('jugend.organizationPage.index'), 'active' => false, 'icon' => 'icon-category-monitoring'];
        }
        if ($groupsUrl !== null) {
            $navigation[] = ['label' => 'Arbeitsgruppen', 'url' => $groupsUrl, 'active' => false, 'icon' => 'icon-group'];
        }
        if ($this->groupManager->isAdmin($uid)) {
            $navigation[] = ['label' => 'Einstellungen', 'url' => $this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => 'jugend']), 'active' => false, 'icon' => 'icon-settings'];
        }
        return new TemplateResponse(Application::APP_ID, 'accounting', [
            'mode' => $mode,
            'navigation' => $navigation,
        ]);
    }
}
