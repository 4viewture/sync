<?php

/**
 * Definitions for modules provided by EXT:examples
 */
return [
    '4viewture_sync' => [
        'parent' => 'site',
        'position' => ['after' => 'site_configuration'],
        'access' => 'user,group',
        'workspaces' => 'live',
        'path' => '/module/4viewture-sync',
        'labels' => 'LLL:EXT:sync/Resources/Private/Language/locallang_sync.xlf',
        'extensionName' => 'sync',
        'icon' => 'EXT:sync/Resources/Public/Icons/module_syncconfiguration.svg',
        'controllerActions' => [
            \FourViewture\Sync\Controller\SyncConfigurationController::class => [
                'list','show','refreshData', 'providerList',
            ],
        ],
    ]
];
