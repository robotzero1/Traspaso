<?php

/*
| Running the live service (SPEC §11, milestone 16): health checks,
| backups and data retention.
*/

return [

    // Behind a load balancer or Cloudflare: TRUSTED_PROXIES=* (or their IPs,
    // comma-separated), so HTTPS and visitors' IPs are seen correctly.
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'health' => [
        // A queued job waiting longer than this means no worker is running.
        'queue_stale_minutes' => 30,
        // Failed jobs within this window make /up report a problem.
        'failed_jobs_hours' => 24,
    ],

    // Optional: a URL pinged after each nightly run (healthchecks.io,
    // Better Stack, cron-job.org...), so a missed night raises an alert.
    'nightly_ping_url' => env('NIGHTLY_PING_URL'),

    // On hosting without a process manager (cPanel and other shared
    // hosting), the scheduler works the queue each minute instead of a
    // long-running queue:work. See docs/deploy-cpanel.md.
    'queue_from_scheduler' => (bool) env('QUEUE_FROM_SCHEDULER', false),

    'backup' => [
        'at' => '04:00',
        // null: the default connection.
        'connection' => null,
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
    ],

    // Unpaid viability reports are deleted after this many days (GDPR:
    // keep data no longer than needed). Paid reports stay.
    'keep_unpaid_reports_days' => 90,
    // Abandoned checkouts (bank payments can take up to two weeks to clear).
    'keep_pending_purchases_days' => 30,

];
