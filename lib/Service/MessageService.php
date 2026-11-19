<?php
declare(strict_types=1);
namespace OCA\Jugend\Service;

class MessageService {
    public function __construct(
        private AuthentikService $authentik,
        private SmsService $sms,
        private NextcloudTalkService $talk,
    ) {}

    public function send(string $message): array {
        $message = trim($message);
        if ($message === '') { throw new \InvalidArgumentException('Bitte gib eine Nachricht ein.'); }
        $result = ['talk' => ['ok' => false, 'error' => null], 'sms' => ['sent' => 0, 'total' => 0, 'failed' => []]];
        try { $this->talk->send($message); $result['talk']['ok'] = true; }
        catch (\Throwable $e) { $result['talk']['error'] = $e->getMessage(); }

        try {
            $recipients = $this->authentik->getRecipients();
            $result['sms']['total'] = count($recipients);
            foreach ($recipients as $recipient) {
                try { $this->sms->send($recipient['phone'], $message); $result['sms']['sent']++; }
                catch (\Throwable $e) { $result['sms']['failed'][] = ['recipient' => $recipient['username'] ?: $recipient['phone'], 'error' => $e->getMessage()]; }
            }
        } catch (\Throwable $e) {
            $result['sms']['error'] = $e->getMessage();
        }
        $result['success'] = $result['talk']['ok'] && !isset($result['sms']['error']) && $result['sms']['sent'] === $result['sms']['total'];
        return $result;
    }
}
