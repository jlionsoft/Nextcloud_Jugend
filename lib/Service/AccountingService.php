<?php
declare(strict_types=1);

namespace OCA\Jugend\Service;

use OCP\IDBConnection;
use OCP\IUserManager;

class AccountingService {
    public function __construct(
        private IDBConnection $db,
        private IUserManager $users,
        private AuthentikService $authentik,
    ) {}

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

    public function listEntries(?string $userId = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'user_id', 'amount_cents', 'description', 'booked_at', 'created_by', 'created_at')
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
            $balance[$uid] = ($balance[$uid] ?? 0) + $amount;
            $entries[] = [
                'id' => (int)$row['id'],
                'userId' => $uid,
                'amountCents' => $amount,
                'description' => (string)($row['description'] ?? ''),
                'bookedAt' => (int)$row['booked_at'],
                'createdBy' => (string)$row['created_by'],
                'createdAt' => (int)$row['created_at'],
            ];
        }
        $result->closeCursor();
        return [
            'entries' => $entries,
            'balanceCents' => $userId !== null ? ($balance[$userId] ?? 0) : $balance,
        ];
    }

    public function addEntry(string $userId, int $amountCents, string $description, int $bookedAt, string $actor): array {
        if ($userId === '' || $amountCents === 0) {
            throw new \InvalidArgumentException('Person und ein Betrag ungleich 0 sind erforderlich.');
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
            ->executeStatement();
        return $this->getEntry((int)$this->db->lastInsertId('*PREFIX*jugend_accounting'));
    }

    private function getEntry(int $id): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'user_id', 'amount_cents', 'description', 'booked_at', 'created_by', 'created_at')
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
            'bookedAt' => (int)$row['booked_at'],
            'createdBy' => (string)$row['created_by'],
            'createdAt' => (int)$row['created_at'],
        ];
    }
}
