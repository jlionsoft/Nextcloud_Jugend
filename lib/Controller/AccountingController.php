<?php
declare(strict_types=1);

namespace OCA\Jugend\Controller;

use OCA\Jugend\Service\AccountingService;
use OCA\Jugend\Service\AuthorizationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Dompdf\Dompdf;

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
            'starts' => $this->accounting->listStarts(),
            'monthlyFeeCents' => $this->accounting->getMonthlyFeeCents(),
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
    public function setStart(string $userId = '', string $startMonth = ''): JSONResponse {
        $uid = $this->session->getUser()?->getUID();
        if ($uid === null || !$this->authorization->isAppUser($uid) || !$this->authorization->canManageAccounting($uid)) {
            return new JSONResponse(['error' => 'forbidden'], 403);
        }
        try {
            $this->accounting->setStartMonth(trim($userId), trim($startMonth), $uid);
            return new JSONResponse(['ok' => true, 'starts' => $this->accounting->listStarts()]);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => $e->getMessage()], 400);
        }
    }

    #[NoAdminRequired]
    public function addEvent(string $eventName = '', string $description = '', int $bookedAt = 0, string $allocations = '[]'): JSONResponse {
        $uid = $this->session->getUser()?->getUID();
        if ($uid === null || !$this->authorization->isAppUser($uid) || !$this->authorization->canManageAccounting($uid)) {
            return new JSONResponse(['error' => 'forbidden'], 403);
        }
        $allocationRows = json_decode($allocations, true);
        if (!is_array($allocationRows)) {
            $allocationRows = [];
        }
        if (trim($eventName) === '' || count($allocationRows) === 0) {
            return new JSONResponse(['error' => 'invalid_input', 'message' => 'Ereignisname und mindestens eine Person sind erforderlich.'], 400);
        }
        $created = [];
        try {
            foreach ($allocationRows as $allocation) {
                $userId = trim((string)($allocation['userId'] ?? ''));
                $amountCents = (int)($allocation['amountCents'] ?? 0);
                if ($userId === '' || $amountCents === 0) {
                    continue;
                }
                $created[] = $this->accounting->addEntry($userId, $amountCents, $description, $bookedAt, $uid, 'event', trim($eventName));
            }
            if (count($created) === 0) {
                return new JSONResponse(['error' => 'invalid_input', 'message' => 'Keine gültigen Ereignisbuchungen angegeben.'], 400);
            }
            $data = $this->accounting->listEntries();
            return new JSONResponse(['entries' => $data['entries'], 'balances' => $data['balanceCents']], 201);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => 'save_failed', 'message' => 'Das Ereignis konnte nicht gespeichert werden.'], 500);
        }
    }

    #[NoAdminRequired]
    public function report(string $format = 'excel'): \OCP\AppFramework\Http\Response {
        $uid = $this->session->getUser()?->getUID();
        if ($uid === null || !$this->authorization->isAppUser($uid) || !$this->authorization->canManageAccounting($uid)) {
            return new JSONResponse(['error' => 'forbidden'], 403);
        }
        $users = $this->accounting->listUsers();
        $starts = $this->accounting->listStarts();
        $data = $this->accounting->listEntries();
        $nameMap = [];
        foreach ($users as $user) {
            $nameMap[$user['uid']] = $user['displayName'];
        }
        $rows = [['Name', 'Benutzer-ID', 'Zahlungspflicht ab', 'Kontostand']];
        foreach ($users as $user) {
            $uidValue = $user['uid'];
            $rows[] = [
                $user['displayName'],
                $uidValue,
                $starts[$uidValue] ?? '',
                number_format(((int)($data['balanceCents'][$uidValue] ?? 0)) / 100, 2, ',', '.') . ' EUR',
            ];
        }
        if (strtolower($format) === 'pdf') {
            $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:sans-serif;font-size:11pt}table{width:100%;border-collapse:collapse}th,td{border:1px solid #999;padding:6px}th{background:#eee}</style></head><body><h1>Buchhaltungsbericht</h1><table>';
            foreach ($rows as $index => $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $tag = $index === 0 ? 'th' : 'td';
                    $html .= '<' . $tag . '>' . htmlspecialchars((string)$cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</' . $tag . '>';
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
            $html .= '<h2>Buchungen</h2><table><tr><th>Datum</th><th>Person</th><th>Art</th><th>Bezeichnung</th><th>Betrag</th></tr>';
            foreach ($data['entries'] as $entry) {
                $entryUid = (string)$entry['userId'];
                $entryDate = date('d.m.Y', (int)$entry['bookedAt']);
                $entryType = (string)$entry['entryType'];
                $entryName = $entryType === 'event'
                    ? (string)$entry['eventName']
                    : ($entryType === 'monthly' ? 'Monatlich' : 'Sonstige');
                $amount = number_format(((int)$entry['amountCents']) / 100, 2, ',', '.') . ' EUR';
                $entryRow = [$entryDate, $nameMap[$entryUid] ?? $entryUid, $entryName, (string)$entry['description'], $amount];
                $html .= '<tr>';
                foreach ($entryRow as $cell) {
                    $html .= '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table></body></html>';
            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            return new DataDownloadResponse($dompdf->output(), 'buchhaltung.pdf', 'application/pdf');
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Worksheet ss:Name="Kontostände"><Table>';
        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $cell) {
                $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$cell, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
            }
            $xml .= '</Row>';
        }
        $xml .= '</Table></Worksheet>';
        $xml .= '<Worksheet ss:Name="Buchungen"><Table>';
        foreach ([['Datum', 'Person', 'Art', 'Bezeichnung', 'Betrag']] as $row) {
            $xml .= '<Row>';
            foreach ($row as $cell) {
                $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$cell, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
            }
            $xml .= '</Row>';
        }
        foreach ($data['entries'] as $entry) {
            $entryUid = (string)$entry['userId'];
            $entryType = (string)$entry['entryType'];
            $entryName = $entryType === 'event' ? (string)$entry['eventName'] : ($entryType === 'monthly' ? 'Monatlich' : 'Sonstige');
            $entryRow = [
                date('d.m.Y', (int)$entry['bookedAt']),
                $nameMap[$entryUid] ?? $entryUid,
                $entryName,
                (string)$entry['description'],
                number_format(((int)$entry['amountCents']) / 100, 2, ',', '.') . ' EUR',
            ];
            $xml .= '<Row>';
            foreach ($entryRow as $cell) {
                $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$cell, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
            }
            $xml .= '</Row>';
        }
        $xml .= '</Table></Worksheet></Workbook>';
        return new DataDownloadResponse($xml, 'buchhaltung.xls', 'application/vnd.ms-excel');
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
