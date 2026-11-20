<?php
declare(strict_types=1);
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'organizationpage#index', 'url' => '/organization', 'verb' => 'GET'],
        ['name' => 'organization#periods', 'url' => '/api/v1/organization/periods', 'verb' => 'GET'],
        ['name' => 'organization#createPeriod', 'url' => '/api/v1/organization/periods', 'verb' => 'POST'],
        ['name' => 'organization#groups', 'url' => '/api/v1/organization/groups', 'verb' => 'GET'],
        ['name' => 'organization#createGroup', 'url' => '/api/v1/organization/groups', 'verb' => 'POST'],
        ['name' => 'organization#users', 'url' => '/api/v1/organization/users', 'verb' => 'GET'],
        ['name' => 'organization#setMembers', 'url' => '/api/v1/organization/groups/members', 'verb' => 'PUT'],
        ['name' => 'settings#save', 'url' => '/api/v1/settings', 'verb' => 'POST'],
        ['name' => 'settings#test', 'url' => '/api/v1/settings/test', 'verb' => 'POST'],
        ['name' => 'message#send', 'url' => '/api/v1/messages', 'verb' => 'POST'],
        ['name' => 'talkwebhook#webhook', 'url' => '/api/v1/talk/webhook', 'verb' => 'POST'],
    ],
];
