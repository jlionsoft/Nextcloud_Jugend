<?php
declare(strict_types=1);

namespace OCA\Jugend\BackgroundJob;

use OCA\Jugend\Service\ResourceProvisioningService;
use OCA\Jugend\AppInfo\Application;
use OCP\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class ResourceSyncJob extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private ResourceProvisioningService $resources,
        private IAppConfig $appConfig,
        private LoggerInterface $logger,
    ) {
        parent::__construct($time);
        $this->setInterval(900);
        $this->setAllowParallelRuns(false);
    }

    protected function run($arguments): void {
        $appId = Application::APP_ID;
        $this->appConfig->setValueString($appId, 'resource_sync_status', 'running');
        $this->appConfig->setValueString($appId, 'resource_sync_started_at', (string)time());

        try {
            if (!$this->resources->isConfigured()) {
                $this->appConfig->setValueString($appId, 'resource_sync_status', 'not_configured');
                $this->appConfig->setValueString($appId, 'resource_sync_finished_at', (string)time());
                $this->appConfig->deleteKey($appId, 'resource_sync_last_error');
                return;
            }

            $this->resources->reconcileAllResources();
            $finishedAt = (string)time();
            $this->appConfig->setValueString($appId, 'resource_sync_status', 'success');
            $this->appConfig->setValueString($appId, 'resource_sync_finished_at', $finishedAt);
            $this->appConfig->setValueString($appId, 'resource_sync_last_success_at', $finishedAt);
            $this->appConfig->deleteKey($appId, 'resource_sync_last_error');
        } catch (\Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 1500);
            $this->appConfig->setValueString($appId, 'resource_sync_status', 'failed');
            $this->appConfig->setValueString($appId, 'resource_sync_finished_at', (string)time());
            $this->appConfig->setValueString($appId, 'resource_sync_last_error', $message);
            $this->logger->error('Jugend-Ressourcensynchronisierung fehlgeschlagen: ' . $e->getMessage(), [
                'app' => $appId,
                'exception' => $e,
            ]);
        }
    }
}
