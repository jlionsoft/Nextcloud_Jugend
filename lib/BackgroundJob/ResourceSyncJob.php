<?php
declare(strict_types=1);

namespace OCA\Jugend\BackgroundJob;

use OCA\Jugend\Service\ResourceProvisioningService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class ResourceSyncJob extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private ResourceProvisioningService $resources,
        private LoggerInterface $logger,
    ) {
        parent::__construct($time);
        $this->setInterval(900);
        $this->setAllowParallelRuns(false);
    }

    protected function run($arguments): void {
        try {
            $this->resources->reconcileAllResources();
        } catch (\Throwable $e) {
            $this->logger->error('Jugend-Ressourcensynchronisierung fehlgeschlagen: ' . $e->getMessage(), [
                'app' => 'jugend',
                'exception' => $e,
            ]);
        }
    }
}
