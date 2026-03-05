<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backup S3 Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control where database backups are stored in S3.
    | They are read from the database settings table at runtime, but
    | can also be overridden via environment variables as fallback.
    |
    */
    's3_bucket' => env('BACKUP_S3_BUCKET', env('AWS_BUCKET', '')),
    's3_prefix' => env('BACKUP_S3_PREFIX', 'backups/'),

    /*
    |--------------------------------------------------------------------------
    | Backup Schedule
    |--------------------------------------------------------------------------
    |
    | Daily time for automatic backup (24h format, "HH:MM").
    | Controlled by the admin panel via database settings.
    |
    */
    'schedule_time' => env('BACKUP_SCHEDULE_TIME', '00:00'),
    'schedule_enabled' => env('BACKUP_SCHEDULE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How many backup records to keep in the database history.
    | Old S3 objects should be managed by an S3 Lifecycle Policy.
    |
    */
    'history_limit' => env('BACKUP_HISTORY_LIMIT', 30),
];
