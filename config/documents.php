<?php

return [
    // Trap 5: read through config(), never env(), from a console command.
    'disk' => env('DOCUMENTS_DISK', 'documents'),

    // Decision 3.1 applies this to draft and sent documents only.
    'retention_days' => 3,
];
