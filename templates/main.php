<?php
declare(strict_types=1);
/** @var array $_ */
/** @var \OCP\IL10N\IL10N $l */
$navigation = [
    ['label' => $l->t('Nachrichten'), 'url' => $_['homeUrl'], 'icon' => 'icon-comment', 'active' => true],
];
if (!empty($_['organizationUrl'])) {
    $navigation[] = ['label' => $l->t('Organisation'), 'url' => $_['organizationUrl'], 'icon' => 'icon-category-monitoring', 'active' => false];
}
if (!empty($_['groupsUrl'])) {
    $navigation[] = ['label' => $l->t('Arbeitsgruppen'), 'url' => $_['groupsUrl'], 'icon' => 'icon-group', 'active' => false];
}
if (!empty($_['settingsUrl'])) {
    $navigation[] = ['label' => $l->t('Einstellungen'), 'url' => $_['settingsUrl'], 'icon' => 'icon-settings', 'active' => false];
}
?>
<div id="jugend-vue-root" data-navigation="<?php p(json_encode($navigation, JSON_UNESCAPED_SLASHES)); ?>"></div>
