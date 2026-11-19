<?php
declare(strict_types=1);
namespace OCA\Jugend\AppInfo;

use OCA\Jugend\Service\AuthentikService;
use OCA\Jugend\Service\MessageService;
use OCA\Jugend\Service\NextcloudTalkService;
use OCA\Jugend\Service\SmsService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap {
    public const APP_ID = 'jugend';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerService(AuthentikService::class, static function (ContainerInterface $c): AuthentikService {
            return new AuthentikService($c->get(IClientService::class), $c->get(IAppConfig::class));
        });
        $context->registerService(SmsService::class, static function (ContainerInterface $c): SmsService {
            return new SmsService($c->get(IClientService::class), $c->get(IAppConfig::class));
        });
        $context->registerService(NextcloudTalkService::class, static function (ContainerInterface $c): NextcloudTalkService {
            return new NextcloudTalkService($c->get(IClientService::class), $c->get(IAppConfig::class));
        });
        $context->registerService(MessageService::class, static function (ContainerInterface $c): MessageService {
            return new MessageService(
                $c->get(AuthentikService::class),
                $c->get(SmsService::class),
                $c->get(NextcloudTalkService::class),
            );
        });
    }

    public function boot(\OCP\AppFramework\Bootstrap\IBootContext $context): void {
    }
}
