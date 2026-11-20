<?php
declare(strict_types=1);

namespace OCA\Jugend\Controller;

use OCA\Jugend\AppInfo\Application;
use OCA\Jugend\Service\MessageService;
use OCA\Jugend\Service\AuthorizationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class MessageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private MessageService $messages,
        private AuthorizationService $authorization,
        private IUserSession $session,
        private LoggerInterface $logger,
        private IL10N $l,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Sends a message through the configured communication channels.
     *
     * @param string $message Message text to send
     * @return JSONResponse Delivery result or a structured error response
     */
    #[NoAdminRequired]
    public function send(string $message = ''): JSONResponse {
        $uid = $this->session->getUser()?->getUID();
        if ($uid === null) {
            return new JSONResponse(['error' => 'unauthenticated'], 401);
        }
        if (!$this->authorization->isAppUser($uid)) {
            return new JSONResponse(['error' => 'forbidden', 'message' => 'Du bist nicht Mitglied der App-Zugriffsgruppe.'], 403);
        }

        $message = trim($message);
        if ($message === '') {
            return new JSONResponse(['error' => 'empty_message', 'message' => $this->l->t('Bitte gib eine Nachricht ein.')], 400);
        }
        if (mb_strlen($message, 'UTF-8') > 5000) {
            return new JSONResponse(['error' => 'message_too_long', 'message' => $this->l->t('Die Nachricht darf höchstens 5000 Zeichen enthalten.')], 400);
        }
        try {
            return new JSONResponse($this->messages->send($message));
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_message', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            $this->logger->error('Message delivery failed', ['app' => Application::APP_ID, 'exception' => $e, 'errorId' => $errorId]);
            return new JSONResponse(['error' => 'send_failed', 'errorId' => $errorId, 'message' => $this->l->t('Der Versand ist serverseitig fehlgeschlagen. Fehler-ID: %s. Details stehen im Nextcloud-Log.', [$errorId])], 500);
        }
    }
}
