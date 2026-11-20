<?php
declare(strict_types=1);

namespace OCA\Jugend\Controller;

use OCA\Jugend\Service\NextcloudTalkService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\ICacheFactory;
use OCP\IAppConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TalkWebhookController extends Controller {
    /** @var \OCP\ICache */
    private $replayCache;

    public function __construct(
        string $appName,
        IRequest $request,
        private IAppConfig $config,
        ICacheFactory $cacheFactory,
        private NextcloudTalkService $talk,
        private LoggerInterface $logger,
    ) {
        parent::__construct($appName, $request);
        $this->replayCache = $cacheFactory->createDistributed('jugend_talk_webhook');
    }

    /**
     * Receives and validates Nextcloud Talk bot webhook events.
     *
     * @return JSONResponse Webhook processing result
     */
    #[PublicPage]
    #[NoCSRFRequired]
    public function webhook(): JSONResponse {
        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            return new JSONResponse(['error' => 'empty_body'], 400);
        }
        $random = $this->request->getHeader('X-Nextcloud-Talk-Random');
        $signature = strtolower($this->request->getHeader('X-Nextcloud-Talk-Signature'));
        $secret = $this->config->getValueString($this->appName, 'talk_bot_secret');
        if ($random === '' || $signature === '' || $secret === '') {
            return new JSONResponse(['error' => 'unauthorized'], 401);
        }
        $expected = hash_hmac('sha256', $random . $body, $secret);
        if (!hash_equals($expected, $signature)) {
            $this->logger->warning('Jugend Talk webhook rejected: invalid signature.');
            return new JSONResponse(['error' => 'unauthorized'], 401);
        }
        $cacheKey = 'event_' . hash('sha256', $random);
        if ($this->replayCache->get($cacheKey) !== null) {
            return new JSONResponse(['error' => 'replayed_event'], 409);
        }
        $this->replayCache->set($cacheKey, true, 300);

        try {
            $event = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JSONResponse(['error' => 'invalid_json'], 400);
        }
        if (!is_array($event) || !isset($event['type'])) {
            return new JSONResponse(['error' => 'invalid_event'], 400);
        }

        $type = (string) $event['type'];
        if (in_array($type, ['Join', 'Leave', 'Like', 'Undo'], true)) {
            $this->logger->info('Jugend Talk webhook event: ' . $type);
        } elseif ($type === 'Create' && ($event['object']['name'] ?? '') === 'message') {
            $actorId = (string)($event['actor']['id'] ?? '');
            if (!str_starts_with(strtolower($actorId), 'bots/')) {
                $room = (string)($event['target']['id'] ?? '');
                $content = (string)($event['object']['content'] ?? '');
                $decoded = json_decode($content, true);
                $message = is_array($decoded) && isset($decoded['message']) ? (string)$decoded['message'] : $content;
                if ($room !== '' && trim($message) === '!ping') {
                    try {
                        $this->talk->sendToRoom($room, '🏓 pong');
                    } catch (\Throwable $e) {
                        $this->logger->error('Jugend Talk webhook could not send pong: ' . $e->getMessage());
                        return new JSONResponse(['error' => 'reply_failed'], 502);
                    }
                }
            }
        }
        return new JSONResponse(['ok' => true]);
    }
}
