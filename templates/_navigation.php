<?php
declare(strict_types=1);
/** @var array $_ */
/** @var \OCP\IL10N\IL10N $l */
$navigation = $_['navigation'] ?? [];
?>
<div id="app-navigation" aria-label="<?php p($l->t('Jugend-Bereiche')); ?>">
    <ul>
        <?php foreach ($navigation as $entry) { ?>
            <li<?php if (!empty($entry['active'])) { ?> class="active"<?php } ?>>
                <a class="<?php p($entry['icon']); ?>" href="<?php p($entry['url']); ?>"<?php if (!empty($entry['active'])) { ?> aria-current="page"<?php } ?>>
                    <?php p($entry['label']); ?>
                </a>
            </li>
        <?php } ?>
    </ul>
</div>
