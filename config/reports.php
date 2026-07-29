<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queued-export threshold
    |--------------------------------------------------------------------------
    |
    | Row count above which a report export stops streaming inline and is handed
    | to the queue instead: the request returns immediately, GenerateReportExport
    | builds the file on the private disk, and a "report ready" notification
    | carries the Policy-gated download link.
    |
    | Tune it per environment via REPORTS_QUEUE_THRESHOLD. Lowering it (e.g. to
    | 50) is also how the queued path is demonstrated on a small dataset without
    | manufacturing thousands of throwaway enrolments — remember a queue worker
    | must be running (`php artisan queue:work`) unless QUEUE_CONNECTION=sync.
    |
    */

    'queue_threshold' => (int) env('REPORTS_QUEUE_THRESHOLD', 2000),

];
