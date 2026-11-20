<?php
declare(strict_types=1);
/** @var array $_ */
/** @var \OCP\IL10N\IL10N $l */
$values = $_['values'];
$configured = $_['configured'];
$talkWebhookUrl = $_['talkWebhookUrl'];
?>
<div id="jugend-vue-root"
     data-values="<?php p(json_encode($values, JSON_UNESCAPED_SLASHES)); ?>"
     data-configured="<?php p(json_encode($configured)); ?>"
     data-talk-webhook-url="<?php p($talkWebhookUrl); ?>"></div>
