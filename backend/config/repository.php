<?php

return [
    'allow_clinical_self_review' => false,
    'backup_at' => env('REPOSITORY_BACKUP_AT'),
    'backup_max_bytes' => 100 * 1024 * 1024,
];
