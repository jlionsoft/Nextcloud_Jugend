<?php
declare(strict_types=1);
namespace OCA\Jugend\Controller;

use OCA\Jugend\Service\MessageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\ILogger;

class MessageController extends Controller {
    public function __construct(string $appName, IRequest $request, private MessageService $messages, private ILogger $logger) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function send(string $message = ''): JSONResponse {
        $message = trim($message);
        if ($message === '') {
            return new JSONResponse(['error' => 'empty_message', 'message' => 'Bitte gib eine Nachricht ein.'], 400);
        }
        if (mb_strlen($message, 'UTF-8') > 5000) {
            return new JSONResponse(['error' => 'message_too_long', 'message' => 'Die Nachricht darf höchstens 5000 Zeichen enthalten.'], 400);
        }
        try {
            return new JSONResponse($this->messages->send($message));
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_message', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $errorId = bin2hex(random_bytes(6));
            $this->logger->logException($e, ['app' => 'jugend', 'errorId' => $errorId, 'message' => 'Message delivery failed']);
            return new JSONResponse(['error' => 'send_failed', 'errorId' => $errorId, 'message' => 'Der Versand ist serverseitig fehlgeschlagen. Fehler-ID: ' . $errorId . '. Details stehen im Nextcloud-Log.'], 500);
        }
    }
}
