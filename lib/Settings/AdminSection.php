<?php
declare(strict_types=1);
namespace OCA\Jugend\Settings;

use OCA\Jugend\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/**
 * Own entry "Jugend" in the sidebar of Nextcloud's administration settings.
 */
class AdminSection implements IIconSection {
    public const SECTION_ID = 'jugend';

    public function __construct(
        private IL10N $l,
        private IURLGenerator $urlGenerator,
    ) {
    }

    public function getIcon(): string {
        return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
    }

    public function getID(): string {
        return self::SECTION_ID;
    }

    public function getName(): string {
        return $this->l->t('Jugend');
    }

    public function getPriority(): int {
        return 80;
    }
}
