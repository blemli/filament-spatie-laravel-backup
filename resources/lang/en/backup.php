<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => 'Download',
                    'delete' => 'Delete',
                ],

                'fields' => [
                    'path' => 'Path',
                    'disk' => 'Disk',
                    'date' => 'Date',
                    'size' => 'Size',
                    'type' => 'Type',
                    'cleanup_in' => 'Cleanup',
                ],

                'types' => [
                    'db' => 'DB',
                    'files' => 'Files',
                    'all' => 'DB & Files',
                ],

                'cleanup' => [
                    'in_rotation' => 'In rotation',
                ],

                'filters' => [
                    'disk' => 'Disk',
                ],
            ],
        ],

        'backup_destination_status_list' => [
            'table' => [
                'fields' => [
                    'name' => 'Name',
                    'disk' => 'Disk',
                    'healthy' => 'Healthy',
                    'amount' => 'Amount',
                    'newest' => 'Newest',
                    'used_storage' => 'Used Storage',
                    'no_backups_present' => 'No backups present',
                ],
            ],
        ],
    ],

    'pages' => [
        'backups' => [
            'actions' => [
                'create_backup' => 'Create Backup',
            ],

            'heading' => 'Backups',

            'messages' => [
                'backup_success' => 'Creating a new backup in background.',
                'backup_delete_success' => 'Deleting this backup in background.',
                'backup_running' => 'A backup is already running. Please wait until it has finished.',
                'backup_running_tooltip' => 'Backup in progress …',
            ],

            'modal' => [
                'buttons' => [
                    'only_db' => 'Only DB',
                    'only_files' => 'Only Files',
                    'db_and_files' => 'DB & Files',
                ],

                'label' => 'Please choose an option',
            ],

            'navigation' => [
                'group' => 'Settings',
                'label' => 'Backups',
            ],
        ],
    ],

];
