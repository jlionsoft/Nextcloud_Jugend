<?php
declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'settings#index', 'url' => '/settings', 'verb' => 'GET'],
        ['name' => 'settings#save', 'url' => '/api/v1/settings', 'verb' => 'POST'],
        ['name' => 'message#send', 'url' => '/api/v1/messages', 'verb' => 'POST'],
    ],
];
