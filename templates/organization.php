<?php
declare(strict_types=1);
/** @var \OCP\IL10N\IL10N $l */
?>
<?php
$navigation = [
    ['label' => $l->t('Organisation'), 'url' => $_['navigation']['organization'], 'icon' => 'icon-category-monitoring', 'active' => true],
];
if (!empty($_['navigation']['groups'])) {
    $navigation[] = ['label' => $l->t('Arbeitsgruppen'), 'url' => $_['navigation']['groups'], 'icon' => 'icon-group', 'active' => false];
}
if (!empty($_['navigation']['settings'])) {
    $navigation[] = ['label' => $l->t('Einstellungen'), 'url' => $_['navigation']['settings'], 'icon' => 'icon-settings', 'active' => false];
}
?>
<div id="jugend-vue-root" data-navigation="<?php p(json_encode($navigation, JSON_UNESCAPED_SLASHES)); ?>"></div>
