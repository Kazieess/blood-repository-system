<?php

return [
    'allow_clinical_self_review' => false,
    'backup_at' => env('REPOSITORY_BACKUP_AT') ?: null,
    'backup_max_bytes' => 16 * 1024 * 1024,
];
