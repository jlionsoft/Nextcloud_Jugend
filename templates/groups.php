<?php
declare(strict_types=1);
/** @var \OCP\IL10N\IL10N $l */
    ?>
    <?php
    $navigation = [
        ['label' => $l->t('Nachrichten'), 'url' => $_['navigation']['home'], 'icon' => 'icon-comment', 'active' => false],
    ];
    if (!empty($_['navigation']['organization'])) {
        $navigation[] = ['label' => $l->t('Organisation'), 'url' => $_['navigation']['organization'], 'icon' => 'icon-category-monitoring', 'active' => false];
    }
    $navigation[] = ['label' => $l->t('Arbeitsgruppen'), 'url' => $_['navigation']['groups'], 'icon' => 'icon-group', 'active' => true];
    if (!empty($_['navigation']['settings'])) {
        $navigation[] = ['label' => $l->t('Einstellungen'), 'url' => $_['navigation']['settings'], 'icon' => 'icon-settings', 'active' => false];
    }
    if (!empty($_['navigation']['accountingManage'])) {
        $navigation[] = ['label' => $l->t('Buchhaltung verwalten'), 'url' => $_['navigation']['accountingManage'], 'icon' => 'icon-category-workflow', 'active' => false];
    }
    $navigation[] = ['label' => $l->t('Mein Kontostand'), 'url' => $_['navigation']['accountingPersonal'], 'icon' => 'icon-paypal', 'active' => false];
    ?>
    <div id="jugend-vue-root" data-navigation="<?php p(json_encode($navigation, JSON_UNESCAPED_SLASHES)); ?>"></div>
