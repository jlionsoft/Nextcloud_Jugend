<?php
declare(strict_types=1);
namespace OCA\Jugend\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;
use OCA\Jugend\BackgroundJob\ResourceSyncJob;

class Application extends App implements IBootstrap {
    public const APP_ID = 'jugend';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Services are resolved by Nextcloud's dependency injection (autowiring).
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(static function (IJobList $jobs): void {
            $jobs->add(ResourceSyncJob::class);
        });
    }
}
