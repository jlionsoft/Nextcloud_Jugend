<?php
declare(strict_types=1);
namespace OCA\Jugend\Controller;

use OCA\Jugend\Settings\AdminSection;
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

class PageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private IURLGenerator $urlGenerator,
        private IGroupManager $groupManager,
        private AuthorizationService $authorization,
        private OrganizationService $organization,
        private IUserSession $userSession,
        private IInitialState $initialState,
        private IL10N $l,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        $userId = $this->userSession->getUser()?->getUID();
        if ($userId === null || !$this->authorization->isAppUser($userId)) {
            return new TemplateResponse($this->appName, 'forbidden', [], 'error');
        }

        Util::addScript($this->appName, 'main');
        Util::addStyle($this->appName, 'main');

        $this->initialState->provideInitialState('urls', [
            'send' => $this->urlGenerator->linkToRoute('jugend.message.send'),
        ]);
        $this->initialState->provideInitialState('strings', [
            'confirmSend' => $this->l->t('Nachricht jetzt an alle Matrix-Mitglieder, den Talk-Raum und SMS-Opt-in-Empfänger senden?'),
            'sending' => $this->l->t('Nachricht wird versendet …'),
            'sendFailedHttp' => $this->l->t('Versand fehlgeschlagen (HTTP {status}).'),
            'unknownError' => $this->l->t('Unbekannter Fehler beim Versand.'),
            'unknown' => $this->l->t('unbekannt'),
            'smsEstimate' => $this->l->t('SMS: ca. {count} Segmente'),
            'talkOk' => $this->l->t('Talk: erfolgreich'),
            'talkFailed' => $this->l->t('Talk: fehlgeschlagen ({error})'),
            'matrixSummary' => $this->l->t('Matrix: {sent}/{total} erfolgreich'),
            'smsSummary' => $this->l->t('SMS: {sent}/{total} erfolgreich'),
        ]);

        $isAdmin = $userId !== null && $this->groupManager->isAdmin($userId);
        $settingsUrl = $isAdmin ? $this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => AdminSection::SECTION_ID]) : null;
        $isAppAdmin = $userId !== null && $this->authorization->isAppAdmin($userId);
        $organizationUrl = $isAppAdmin ? $this->urlGenerator->linkToRoute('jugend.organizationPage.index') : null;
        $canManageAnyGroup = false;
        if ($userId !== null) {
            foreach ($this->organization->listManageableGroups() as $group) {
                if ($this->authorization->can($userId, 'group.members.manage', 'group', (int)$group['id'])) {
                    $canManageAnyGroup = true;
                    break;
                }
            }
        }
        $groupsUrl = $canManageAnyGroup
            ? $this->urlGenerator->linkToRoute('jugend.groupPage.index')
            : null;

        $navigation = [
            [
                'label' => $this->l->t('Nachricht senden'),
                'url' => $this->urlGenerator->linkToRoute('jugend.page.index'),
                'icon' => 'icon-comment',
                'active' => true,
            ],
        ];
        if ($groupsUrl !== null) {
            $navigation[] = [
                'label' => $this->l->t('Meine Arbeitsgruppen'),
                'url' => $groupsUrl,
                'icon' => 'icon-group',
                'active' => false,
            ];
        }
        if ($organizationUrl !== null) {
            $navigation[] = [
                'label' => $this->l->t('Organisation'),
                'url' => $organizationUrl,
                'icon' => 'icon-category-organization',
                'active' => false,
            ];
        }
        if ($settingsUrl !== null) {
            $navigation[] = [
                'label' => $this->l->t('Einstellungen'),
                'url' => $settingsUrl,
                'icon' => 'icon-settings',
                'active' => false,
            ];
        }
        if ($this->authorization->canManageAccounting($userId)) {
            $navigation[] = [
                'label' => $this->l->t('Buchhaltung verwalten'),
                'url' => $this->urlGenerator->linkToRoute('jugend.accountingPage.manage'),
                'icon' => 'icon-category-workflow',
                'active' => false,
            ];
        }
        $navigation[] = [
            'label' => $this->l->t('Mein Kontostand'),
            'url' => $this->urlGenerator->linkToRoute('jugend.accountingPage.personal'),
            'icon' => 'icon-paypal',
            'active' => false,
        ];

        return new TemplateResponse($this->appName, 'main', [
            'homeUrl' => $this->urlGenerator->linkToRoute('jugend.page.index'),
            'settingsUrl' => $settingsUrl,
            'organizationUrl' => $organizationUrl,
            'groupsUrl' => $groupsUrl,
            'navigation' => $navigation,
        ]);
    }
}
