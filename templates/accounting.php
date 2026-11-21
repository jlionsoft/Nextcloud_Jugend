<?php
declare(strict_types=1);
/** @var array $_ */
?>
<div id="jugend-vue-root"
	data-navigation="<?php p(json_encode(array_map(static fn(array $item): array => [
		'label' => $item['label'],
		'url' => $item['url'],
		'icon' => $item['icon'],
		'active' => $item['active'],
	], $_['navigation']), JSON_UNESCAPED_SLASHES)); ?>"
	data-mode="<?php p($_['mode']); ?>"></div>
