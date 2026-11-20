<?php
declare(strict_types=1);
namespace OCA\Jugend\Service;

use Psr\Log\LoggerInterface;

class MessageService {
    public function __construct(
        private AuthentikService $authentik,
        private MatrixService $matrix,
        private SmsService $sms,
        private NextcloudTalkService $talk,
        private LoggerInterface $logger,
    ) {}

    public function send(string $message): array {
        $message = trim($message);
        if ($message === '') {
            throw new \InvalidArgumentException('Bitte gib eine Nachricht ein.');
        }

        $result = [
            'talk' => ['ok' => false, 'error' => null],
            'matrix' => ['sent' => 0, 'total' => 0, 'failed' => [], 'error' => null],
            'sms' => ['sent' => 0, 'total' => 0, 'failed' => [], 'error' => null],
        ];

        try {
            $this->talk->send($message);
            $result['talk']['ok'] = true;
        } catch (\Throwable $e) {
            $this->logger->error('Jugend Talk message delivery failed.', ['exception' => $e]);
            $result['talk']['error'] = 'Die Nachricht konnte nicht über Nextcloud Talk versendet werden.';
        }

        try {
            $recipients = $this->authentik->getRecipients();
            $matrix = $this->matrix->sendToUsers($recipients, $message);
            $result['matrix'] = $matrix;

            if ($result['matrix']['error'] !== null) {
                $this->logger->error('Jugend Matrix message delivery failed.', [
                    'error' => $result['matrix']['error'],
                ]);
                $result['matrix']['error'] = 'Der Matrix-Versand konnte nicht vollständig durchgeführt werden.';
            }
            foreach ($result['matrix']['failed'] as $index => &$failure) {
                $this->logger->warning('Jugend Matrix recipient delivery failed.', [
                    'recipientIndex' => $index,
                    'error' => (string)($failure['error'] ?? 'Unbekannter Fehler'),
                ]);
                $failure['error'] = 'Die Zustellung an diesen Matrix-Empfänger ist fehlgeschlagen.';
            }
            unset($failure);

            $smsRecipients = array_values(array_filter(
                $recipients,
                static fn(array $recipient): bool => ($recipient['sms_enabled'] ?? false) && trim((string)($recipient['phone'] ?? '')) !== ''
            ));
            $result['sms']['total'] = count($smsRecipients);
            foreach ($smsRecipients as $index => $recipient) {
                $label = $recipient['username'] !== '' ? $recipient['username'] : $recipient['phone'];
                try {
                    $this->sms->send($recipient['phone'], $message);
                    $result['sms']['sent']++;
                } catch (\Throwable $e) {
                    $this->logger->warning('Jugend SMS recipient delivery failed.', [
                        'recipientIndex' => $index,
                        'exception' => $e,
                    ]);
                    $result['sms']['failed'][] = [
                        'recipient' => $label,
                        'error' => 'Die SMS-Zustellung an diesen Empfänger ist fehlgeschlagen.',
                    ];
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Jugend recipient lookup or message delivery failed.', ['exception' => $e]);
            $result['matrix']['error'] = 'Die Matrix-Empfänger konnten nicht verarbeitet werden.';
            $result['sms']['error'] = 'Die SMS-Empfänger konnten nicht verarbeitet werden.';
        }

        $result['success'] = $result['talk']['ok']
            && $result['matrix']['error'] === null
            && $result['matrix']['sent'] === $result['matrix']['total']
            && $result['sms']['error'] === null
            && $result['sms']['sent'] === $result['sms']['total'];
        return $result;
    }
}
