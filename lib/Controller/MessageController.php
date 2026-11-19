<?php
declare(strict_types=1);

namespace OCA\Jugend\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class MessageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Accepts a message send request.
     *
     * The actual dispatch pipeline is added in the next implementation step.
     * Returning an explicit unavailable response prevents the UI from reporting
     * a successful send before a channel adapter is configured.
     */
    #[NoAdminRequired]
    public function send(string $message = ''): JSONResponse {
        $message = trim($message);

        if ($message === '') {
            return new JSONResponse([
                'error' => 'empty_message',
                'message' => 'Bitte gib eine Nachricht ein.',
            ], 400);
        }

        return new JSONResponse([
            'error' => 'sending_not_configured',
            'message' => 'Der Nachrichtenversand ist noch nicht eingerichtet.',
        ], 503);
    }
}
