<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => 'Download',
                    'delete' => 'Löschen',
                ],

                'fields' => [
                    'path' => 'Pfad',
                    'disk' => 'Speicher',
                    'date' => 'Datum',
                    'size' => 'Größe',
                    'type' => 'Typ',
                    'cleanup_in' => 'Bereinigung',
                ],

                'types' => [
                    'db' => 'DB',
                    'files' => 'Dateien',
                    'all' => 'DB & Dateien',
                ],

                'cleanup' => [
                    'in_rotation' => 'In Rotation',
                ],

                'filters' => [
                    'disk' => 'Speicher',
                ],
            ],
        ],

        'backup_destination_status_list' => [
            'table' => [
                'fields' => [
                    'name' => 'Name',
                    'disk' => 'Speicher',
                    'healthy' => 'Gesund',
                    'amount' => 'Anzahl',
                    'newest' => 'Neuestes',
                    'used_storage' => 'Verwendeter Speicher',
                    'no_backups_present' => 'Keine Sicherungen vorhanden',
                ],
            ],
        ],
    ],

    'pages' => [
        'backups' => [
            'actions' => [
                'create_backup' => 'Sicherung erstellen',
                'create_backup_db' => 'Nur DB',
                'create_backup_files' => 'Nur Dateien',
                'create_backup_options' => 'Weitere Sicherungsoptionen',
            ],

            'heading' => 'Sicherungen',

            'messages' => [
                'backup_success' => 'Erstelle eine neue Sicherung im Hintergrund.',
                'backup_delete_success' => 'Lösche die Sicherung im Hintergrund.',
                'backup_running' => 'Es läuft bereits eine Sicherung. Bitte warten, bis sie abgeschlossen ist.',
                'backup_running_tooltip' => 'Sicherung läuft …',
            ],

            'modal' => [
                'buttons' => [
                    'only_db' => 'Nur DB',
                    'only_files' => 'Nur Dateien',
                    'db_and_files' => 'DB & Dateien',
                ],

                'label' => 'Bitte eine Option auswählen',
            ],

            'navigation' => [
                'group' => 'Einstellungen',
                'label' => 'Sicherungen',
            ],
        ],
    ],

];
