<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => 'Download',
                    'delete' => 'Löschen',
                    'restore' => 'Einspielen',
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
                    'retained' => 'Noch aufbewahrt',
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
                'restore_backup' => 'Sicherung einspielen',
            ],

            'heading' => 'Sicherungen',

            'messages' => [
                'backup_success' => 'Erstelle eine neue Sicherung im Hintergrund.',
                'backup_delete_success' => 'Lösche die Sicherung im Hintergrund.',
                'backup_running' => 'Es läuft bereits eine Sicherung. Bitte warten, bis sie abgeschlossen ist.',
                'backup_running_tooltip' => 'Sicherung läuft …',
                'restore_success' => 'Spiele die Sicherung im Hintergrund ein.',
                'restore_blocked' => 'Es läuft bereits eine Sicherung oder Wiederherstellung. Bitte warten, bis sie abgeschlossen ist.',
                'restore_running_tooltip' => 'Wiederherstellung läuft …',
            ],

            'restore_modal' => [
                'label' => 'Sicherung einspielen',
                'description' => 'Die Sicherung ersetzt den gesamten Inhalt der Zieldatenbank. Alles, was seit der Sicherung erfasst wurde, ist danach unwiederbringlich weg. Die Seite geht dafür in den Wartungsmodus und kommt von selbst zurück.',

                'buttons' => [
                    'restore' => 'Einspielen und überschreiben',
                ],

                'confirmation_phrase' => 'alles überschreiben',

                'fields' => [
                    'archive' => 'Sicherungsdatei (.zip)',
                    'connections' => 'Einzuspielende Datenbanken',
                    'password' => 'Passwort der Sicherung',
                    'password_helper' => 'Nur nötig, wenn die Sicherung älter ist als der letzte Passwortwechsel.',
                    'password_placeholder' => 'Hinterlegtes Passwort',
                    'reset' => 'Bestehende Tabellen zuerst löschen',
                    'reset_helper' => 'Stellt den Stand der Sicherung exakt wieder her. Ausgeschaltet wird über die bestehenden Tabellen eingespielt.',
                    'media' => 'Auch die Mediendateien einspielen',
                    'media_helper' => 'Leert den Medienordner und füllt ihn aus der Sicherung. Seit der Sicherung hochgeladene Dateien gehen verloren. Nur die Medien — die Anwendung selbst wird nie ersetzt.',
                    'confirmation' => '«:phrase» eintippen, um fortzufahren',
                    'confirmation_mismatch' => 'Bitte genau «:phrase» eintippen, um die Wiederherstellung zu bestätigen.',
                ],
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
