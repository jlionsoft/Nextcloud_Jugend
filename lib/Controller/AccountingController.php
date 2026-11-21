<?php
declare(strict_types=1);

namespace OCA\Jugend\Controller;

use OCA\Jugend\Service\AccountingService;
use OCA\Jugend\Service\AuthorizationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class AccountingController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private AccountingService $accounting,
        private AuthorizationService $authorization,
        private IUserSession $session,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function overview(): JSONResponse {
        $uid = $this->session->getUser()?->getUID();
        if ($uid === null || !$this->authorization->isAppUser($uid)) {
            return new JSONResponse(['error' => 'forbidden'], 403);
        }
        if (!$this->authorization->canManageAccounting($uid)) {
            return new JSONResponse(['error' => 'forbidden'], 403);
        }
        $data = $this->accounting->listEntries();
        return new JSONResponse([
            'users' => $this->accounting->listUsers(),
            'entries' => $data['entries'],
            'balances' => $data['balanceCents'],
        ]);
    }

    #[NoAdminRequired]
    public function addEntry(string $userId = '', int $amountCents = 0, string $description = '', int $bookedAt = 0): JSONResponse {
        $uid = $this->session->getUser()?->getUID();
        if ($uid === null || !$this->authorization->isAppUser($uid) || !$this->authorization->canManageAccounting($uid)) {
            return new JSONResponse(['error' => 'forbidden'], 403);
        }
        try {
            $entry = $this->accounting->addEntry(trim($userId), $amountCents, $description, $bookedAt, $uid);
            $data = $this->accounting->listEntries();
            return new JSONResponse([
                'entry' => $entry,
                'entries' => $data['entries'],
                'balances' => $data['balanceCents'],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'save_failed', 'message' => 'Die Buchung konnte nicht gespeichert werden.'], 500);
        }
    }

    #[NoAdminRequired]
    public function personal(): JSONResponse {
        $uid = $this->session->getUser()?->getUID();
        if ($uid === null || !$this->authorization->isAppUser($uid)) {
            return new JSONResponse(['error' => 'forbidden'], 403);
        }
        $data = $this->accounting->listEntries($uid);
        return new JSONResponse([
            'entries' => $data['entries'],
            'balanceCents' => $data['balanceCents'],
        ]);
    }
}
