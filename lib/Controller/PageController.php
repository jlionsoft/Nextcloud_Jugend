<?php
declare(strict_types=1);
namespace OCA\Jugend\Controller;

use OCA\Jugend\Settings\AdminSection;
use OCA\Jugend\Service\AuthorizationService;
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
        private IUserSession $userSession,
        private IInitialState $initialState,
        private IL10N $l,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
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

        $userId = $this->userSession->getUser()?->getUID();
        $isAdmin = $userId !== null && $this->groupManager->isAdmin($userId);
        $settingsUrl = $isAdmin ? $this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => AdminSection::SECTION_ID]) : null;
        $organizationUrl = ($userId !== null && $this->authorization->isAppAdmin($userId)) ? $this->urlGenerator->linkToRoute('jugend.organizationpage.index') : null;

        return new TemplateResponse($this->appName, 'main', [
            'settingsUrl' => $settingsUrl,
            'organizationUrl' => $organizationUrl,
        ]);
    }
}
