<?php
declare(strict_types=1);
namespace OCA\Jugend\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;

class PageController extends Controller {
    public function __construct(string $appName, IRequest $request, private IURLGenerator $urlGenerator) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        Util::addScript($this->appName, 'main');
        Util::addStyle($this->appName, 'main');
        return new TemplateResponse($this->appName, 'main', [
            'settingsUrl' => $this->urlGenerator->linkToRoute('jugend.settings.index'),
        ]);
    }
}
