<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Single source of truth for anything user-facing that names the product.
    | Hard-coding "PropertyHub" across Blade templates makes a re-brand a
    | find-and-replace across the whole view layer, so read it from here.
    |
    */

    'name' => env('APP_NAME', 'PropertyHub'),

    'tagline' => env('PROPERTYHUB_TAGLINE', 'Property management, made clear.'),

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    |
    | Money is NEVER stored as a float. Amount columns are DECIMAL(15,2) and
    | all arithmetic runs through BCMath (see App\Support\Money\Money). The
    | helpers below only control how a stored integer/decimal is *rendered*.
    |
    */

    'currency' => env('PROPERTYHUB_CURRENCY', 'USD'),

    'currency_symbol' => env('PROPERTYHUB_CURRENCY_SYMBOL', '$'),

    /*
    |--------------------------------------------------------------------------
    | Locale / timezone
    |--------------------------------------------------------------------------
    |
    | A business may override these per-record via `businesses.timezone`.
    | The value below is only the fallback used by the money formatter and by
    | date-range defaults on reports.
    |
    */

    'timezone' => env('PROPERTYHUB_TIMEZONE', 'UTC'),

    'locale' => env('APP_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Lease & rent business rules
    |--------------------------------------------------------------------------
    |
    | lease_expiry_warning_days
    |     How far ahead a lease is flagged as "expiring soon". Drives the
    |     dashboard warning list, the notification that is queued, and the
    |     `leases.expiring_soon` scope.
    |
    | rent_grace_days
    |     Number of days after the due date before an unpaid rent charge is
    |     reclassified from `due` to `overdue`.
    |
    | rent_due_day_of_month
    |     Default day of month rent is charged when a lease does not specify
    |     its own `rent_due_day`. Clamped to 1-28 by the form request so that
    |     February and 30-day months never produce a rolled-over due date.
    |
    */

    'lease_expiry_warning_days' => (int) env('PROPERTYHUB_LEASE_EXPIRY_WARNING_DAYS', 60),

    'rent_grace_days' => (int) env('PROPERTYHUB_RENT_GRACE_DAYS', 5),

    'rent_due_day_of_month' => (int) env('PROPERTYHUB_RENT_DUE_DAY', 1),

    /*
    |--------------------------------------------------------------------------
    | Maintenance
    |--------------------------------------------------------------------------
    |
    | maximum_attachments
    |     Cap on photo attachments per maintenance request. Prevents a single
    |     request from exhausting the private disk or the session upload
    |     limits.
    |
    */

    'maintenance' => [
        'max_attachments' => (int) env('PROPERTYHUB_MAINTENANCE_MAX_ATTACHMENTS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Secure document storage
    |--------------------------------------------------------------------------
    |
    | documents.disk
    |     Which filesystem disk backs Document / MaintenanceAttachment models.
    |     "local" resolves to storage/app/private, which is OUTSIDE the
    |     document root. Files are therefore never web-reachable and can only
    |     be retrieved through a signed, policy-checked controller route.
    |
    | documents.max_kb
    |     Server-side size ceiling enforced by the upload form request, in
    |     kilobytes. Keep in sync with php.ini `upload_max_filesize`.
    |
    | documents.allowed_mime
    |     Allow-list of MIME types. Uploads are validated against this AND
    |     against the sniffed/guessed extension, so a renamed `.php` is
    |     rejected regardless of what the browser claims.
    |
    | documents.retention_days
    |     Soft-delete retention window before the scheduled cleanup job
    |     purges orphaned blobs from disk.
    |
    */

    'documents' => [
        'disk' => env('PROPERTYHUB_DOCUMENTS_DISK', 'local'),
        'max_kb' => (int) env('PROPERTYHUB_DOCUMENTS_MAX_KB', 10240),
        'allowed_mime' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'PROPERTYHUB_DOCUMENTS_ALLOWED_MIME',
                'application/pdf,image/jpeg,image/png,image/webp'
            ))
        ))),
        'retention_days' => (int) env('PROPERTYHUB_DOCUMENTS_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription gating
    |--------------------------------------------------------------------------
    |
    | When a business' trial/subscription has lapsed we still let the user in
    | (so they can see why and renew) but read-only. Flipping
    | PROPERTYHUB_GRACE_ON_SUBSCRIPTION=true keeps the app fully writable,
    | which is what you want in local/staging.
    |
    */

    'grace_on_subscription' => filter_var(
        env('PROPERTYHUB_GRACE_ON_SUBSCRIPTION', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'pagination' => [
        'per_page' => (int) env('PROPERTYHUB_PER_PAGE', 15),
        'max_per_page' => 100,
    ],

];
