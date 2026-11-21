<?php
declare(strict_types=1);

namespace OCA\Jugend\Service;

use OCP\IDBConnection;
use OCP\IAppConfig;
use OCP\IUserManager;

class AccountingService {
    public function __construct(
        private IDBConnection $db,
        private IUserManager $users,
        private AuthentikService $authentik,
        private IAppConfig $appConfig,
    ) {}

    public function getMonthlyFeeCents(): int {
        return max(0, (int)$this->appConfig->getValueString('jugend', 'accounting_monthly_fee_cents'));
    }

    public function listUsers(): array {
        $users = [];
        foreach ($this->authentik->getRecipients() as $recipient) {
            $uid = trim((string)($recipient['username'] ?? ''));
            if ($uid === '') {
                continue;
            }
            $user = $this->users->get($uid);
            $users[$uid] = [
                'uid' => $uid,
                'displayName' => $user?->getDisplayName() ?: $uid,
            ];
        }
        $result = array_values($users);
        usort($result, static fn(array $a, array $b): int => strcasecmp($a['displayName'], $b['displayName']));
        return $result;
    }

    public function listStarts(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('user_id', 'start_month')->from('jugend_accounting_starts');
        $starts = [];
        $result = $qb->executeQuery();
        while ($row = $result->fetch()) {
            $starts[(string)$row['user_id']] = (string)$row['start_month'];
        }
        $result->closeCursor();
        return $starts;
    }

    public function setStartMonth(string $userId, string $startMonth, string $actor): void {
        if (!preg_match('/^\d{4}-\d{2}$/', $startMonth)) {
            throw new \InvalidArgumentException('Bitte gib einen gültigen Monat an.');
        }
        $known = false;
        foreach ($this->listUsers() as $user) {
            if ($user['uid'] === $userId) {
                $known = true;
                break;
            }
        }
        if (!$known) {
            throw new \InvalidArgumentException('Die Person ist kein Mitglied der Jugendgruppe.');
        }
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')->from('jugend_accounting_starts')
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        $id = $qb->executeQuery()->fetchOne();
        $now = time();
        if ($id !== false) {
            $update = $this->db->getQueryBuilder();
            $update->update('jugend_accounting_starts')
                ->set('start_month', $update->createNamedParameter($startMonth))
                ->set('updated_by', $update->createNamedParameter($actor))
                ->set('updated_at', $update->createNamedParameter($now))
                ->where($update->expr()->eq('user_id', $update->createNamedParameter($userId)))
                ->executeStatement();
            return;
        }
        $insert = $this->db->getQueryBuilder();
        $insert->insert('jugend_accounting_starts')
            ->setValue('user_id', $insert->createNamedParameter($userId))
            ->setValue('start_month', $insert->createNamedParameter($startMonth))
            ->setValue('updated_by', $insert->createNamedParameter($actor))
            ->setValue('updated_at', $insert->createNamedParameter($now))
            ->executeStatement();
    }

    public function listEntries(?string $userId = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'user_id', 'amount_cents', 'description', 'booked_at', 'created_by', 'created_at', 'entry_type', 'event_name')
            ->from('jugend_accounting')
            ->orderBy('booked_at', 'DESC')
            ->addOrderBy('id', 'DESC');
        if ($userId !== null) {
            $qb->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        }
        $entries = [];
        $balance = [];
        $result = $qb->executeQuery();
        while ($row = $result->fetch()) {
            $uid = (string)$row['user_id'];
            $amount = (int)$row['amount_cents'];
            $entries[] = [
                'id' => (int)$row['id'],
                'userId' => $uid,
                'amountCents' => $amount,
                'description' => (string)($row['description'] ?? ''),
                'entryType' => (string)($row['entry_type'] ?? 'payment'),
                'eventName' => (string)($row['event_name'] ?? ''),
                'bookedAt' => (int)$row['booked_at'],
                'createdBy' => (string)$row['created_by'],
                'createdAt' => (int)$row['created_at'],
            ];
        }
        $result->closeCursor();

        $starts = $this->listStarts();
        $monthlyFee = $this->getMonthlyFeeCents();
        if ($monthlyFee > 0) {
            $targetUsers = $userId !== null ? [$userId] : array_keys($starts);
            $now = new \DateTimeImmutable('first day of this month');
            foreach ($targetUsers as $uid) {
                $start = $starts[$uid] ?? null;
                if ($start === null) {
                    continue;
                }
                if (!preg_match('/^(\d{4})-(\d{2})$/', $start, $match)) {
                    continue;
                }
                $startDate = new \DateTimeImmutable($match[1] . '-' . $match[2] . '-01');
                for ($month = $startDate; $month <= $now; $month = $month->modify('+1 month')) {
                    $entries[] = [
                        'id' => 'monthly-' . $uid . '-' . $month->format('Y-m'),
                        'userId' => $uid,
                        'amountCents' => -$monthlyFee,
                        'description' => 'Monatlicher Beitrag ' . $month->format('m/Y'),
                        'entryType' => 'monthly',
                        'eventName' => '',
                        'bookedAt' => $month->getTimestamp(),
                        'createdBy' => '',
                        'createdAt' => $month->getTimestamp(),
                    ];
                }
            }
        }
        usort($entries, static fn(array $a, array $b): int => ($b['bookedAt'] <=> $a['bookedAt']) ?: strcmp((string)$b['id'], (string)$a['id']));
        foreach ($entries as $entry) {
            $uid = (string)$entry['userId'];
            $balance[$uid] = ($balance[$uid] ?? 0) + (int)$entry['amountCents'];
        }
        return [
            'entries' => $entries,
            'balanceCents' => $userId !== null ? ($balance[$userId] ?? 0) : $balance,
        ];
    }

    public function addEntry(string $userId, int $amountCents, string $description, int $bookedAt, string $actor, string $entryType = 'payment', string $eventName = ''): array {
        if ($userId === '' || $amountCents === 0) {
            throw new \InvalidArgumentException('Person und ein Betrag ungleich 0 sind erforderlich.');
        }
        if (!in_array($entryType, ['payment', 'event'], true)) {
            throw new \InvalidArgumentException('Ungültige Buchungsart.');
        }
        if ($entryType === 'event' && trim($eventName) === '') {
            throw new \InvalidArgumentException('Bitte gib einen Ereignisnamen an.');
        }
        if (abs($amountCents) > 100000000) {
            throw new \InvalidArgumentException('Der Betrag ist zu groß.');
        }
        if (mb_strlen($description) > 255 || mb_strlen($eventName) > 255) {
            throw new \InvalidArgumentException('Textangaben dürfen höchstens 255 Zeichen lang sein.');
        }
        $known = false;
        foreach ($this->listUsers() as $user) {
            if ($user['uid'] === $userId) {
                $known = true;
                break;
            }
        }
        if (!$known) {
            throw new \InvalidArgumentException('Die Person ist kein Mitglied der Jugendgruppe.');
        }
        $now = time();
        $qb = $this->db->getQueryBuilder();
        $qb->insert('jugend_accounting')
            ->setValue('user_id', $qb->createNamedParameter($userId))
            ->setValue('amount_cents', $qb->createNamedParameter($amountCents))
            ->setValue('description', $qb->createNamedParameter(trim($description)))
            ->setValue('booked_at', $qb->createNamedParameter($bookedAt > 0 ? $bookedAt : $now))
            ->setValue('created_by', $qb->createNamedParameter($actor))
            ->setValue('created_at', $qb->createNamedParameter($now))
            ->setValue('entry_type', $qb->createNamedParameter($entryType))
            ->setValue('event_name', $qb->createNamedParameter(trim($eventName)))
            ->executeStatement();
        return $this->getEntry((int)$this->db->lastInsertId('*PREFIX*jugend_accounting'));
    }

    private function getEntry(int $id): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'user_id', 'amount_cents', 'description', 'booked_at', 'created_by', 'created_at', 'entry_type', 'event_name')
            ->from('jugend_accounting')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        $row = $qb->executeQuery()->fetch();
        if ($row === false) {
            throw new \RuntimeException('Die Buchung wurde nicht gespeichert.');
        }
        return [
            'id' => (int)$row['id'],
            'userId' => (string)$row['user_id'],
            'amountCents' => (int)$row['amount_cents'],
            'description' => (string)($row['description'] ?? ''),
            'entryType' => (string)($row['entry_type'] ?? 'payment'),
            'eventName' => (string)($row['event_name'] ?? ''),
            'bookedAt' => (int)$row['booked_at'],
            'createdBy' => (string)$row['created_by'],
            'createdAt' => (int)$row['created_at'],
        ];
    }
}
