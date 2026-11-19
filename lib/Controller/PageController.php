<?php
declare(strict_types=1);
namespace OCA\Jugend\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
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
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        Util::addScript($this->appName, 'main');
        Util::addStyle($this->appName, 'main');

        $userId = $this->userSession->getUser()?->getUID();
        $settingsUrl = $userId !== null && $this->groupManager->isAdmin($userId)
            ? $this->urlGenerator->linkToRoute('jugend.settings.index')
            : null;

        return new TemplateResponse($this->appName, 'main', [
            'settingsUrl' => $settingsUrl,
        ]);
    }
}
