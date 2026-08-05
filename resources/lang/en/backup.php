<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => 'Download',
                    'delete' => 'Delete',
                    'restore' => 'Restore',
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
                    'retained' => 'Still retained',
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
                'restore_backup' => 'Restore Backup',
            ],

            'heading' => 'Backups',

            'messages' => [
                'backup_success' => 'Creating a new backup in background.',
                'backup_delete_success' => 'Deleting this backup in background.',
                'backup_running' => 'A backup is already running. Please wait until it has finished.',
                'backup_running_tooltip' => 'Backup in progress …',
                'restore_success' => 'Restoring the backup in background.',
                'restore_blocked' => 'A backup or restore is already running. Please wait until it has finished.',
                'restore_running_tooltip' => 'Restore in progress …',
            ],

            'restore_modal' => [
                'label' => 'Restore a backup',
                'description' => 'The archive replaces everything in the target database. Data written since the backup was taken is lost and cannot be recovered. The site goes into maintenance mode for the duration and comes back on its own.',

                'buttons' => [
                    'restore' => 'Restore and overwrite',
                ],

                'confirmation_phrase' => 'overwrite everything',

                'fields' => [
                    'archive' => 'Backup archive (.zip)',
                    'connections' => 'Databases to restore',
                    'password' => 'Archive password',
                    'password_helper' => 'Only needed when the archive predates a password change.',
                    'password_placeholder' => 'Configured password',
                    'reset' => 'Drop existing tables first',
                    'reset_helper' => 'Leaves the database exactly as the backup had it. Switch off to import on top of the current tables.',
                    'media' => 'Also restore the media files',
                    'media_helper' => 'Empties the media directory and refills it from the archive. Files uploaded since the backup are lost. Only the media — the application itself is never replaced.',
                    'confirmation' => 'Type “:phrase” to continue',
                    'confirmation_mismatch' => 'Type “:phrase” exactly to confirm the restore.',
                ],
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
