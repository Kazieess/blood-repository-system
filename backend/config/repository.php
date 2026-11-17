<?php

return [
    'password_reset_email' => (bool) env('REPOSITORY_PASSWORD_RESET_EMAIL', false),
    'allow_clinical_self_review' => false,
    'backup_at' => env('REPOSITORY_BACKUP_AT') ?: null,
    'backup_max_bytes' => 16 * 1024 * 1024,
];
