<?php
declare(strict_types=1);
namespace OCA\Jugend\Service;

class MessageService {
    public function __construct(
        private AuthentikService $authentik,
        private MatrixService $matrix,
        private SmsService $sms,
        private NextcloudTalkService $talk,
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
            $result['talk']['error'] = $e->getMessage();
        }

        try {
            $recipients = $this->authentik->getRecipients();
            $matrix = $this->matrix->sendToUsers($recipients, $message);
            $result['matrix'] = $matrix;

            $smsRecipients = array_values(array_filter(
                $recipients,
                static fn(array $recipient): bool => ($recipient['sms_enabled'] ?? false) && trim((string)($recipient['phone'] ?? '')) !== ''
            ));
            $result['sms']['total'] = count($smsRecipients);
            foreach ($smsRecipients as $recipient) {
                $label = $recipient['username'] !== '' ? $recipient['username'] : $recipient['phone'];
                try {
                    $this->sms->send($recipient['phone'], $message);
                    $result['sms']['sent']++;
                } catch (\Throwable $e) {
                    $result['sms']['failed'][] = ['recipient' => $label, 'error' => $e->getMessage()];
                }
            }
        } catch (\Throwable $e) {
            $result['matrix']['error'] = $e->getMessage();
            $result['sms']['error'] = $e->getMessage();
        }

        $result['success'] = $result['talk']['ok']
            && $result['matrix']['error'] === null
            && $result['matrix']['sent'] === $result['matrix']['total']
            && $result['sms']['error'] === null
            && $result['sms']['sent'] === $result['sms']['total'];
        return $result;
    }
}
