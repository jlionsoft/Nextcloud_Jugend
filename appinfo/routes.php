<?php
declare(strict_types=1);
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'settings#index', 'url' => '/settings', 'verb' => 'GET'],
        ['name' => 'settings#save', 'url' => '/api/v1/settings', 'verb' => 'POST'],
        ['name' => 'settings#test', 'url' => '/api/v1/settings/test', 'verb' => 'POST'],
        ['name' => 'message#send', 'url' => '/api/v1/messages', 'verb' => 'POST'],
        ['name' => 'talkwebhook#webhook', 'url' => '/api/v1/talk/webhook', 'verb' => 'POST'],
    ],
];
