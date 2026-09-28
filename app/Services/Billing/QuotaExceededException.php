<?php

namespace App\Services\Billing;

use RuntimeException;

/**
 * A business tried to create more of a plan-limited resource than it has
 * room for.
 *
 * Rendered to the user as a friendly message on a 422 response rather than a
 * 500: it is a normal business outcome, not a fault. The accompanying
 * message names the plan, the limit and the current usage so the UI can offer
 * a one-click upgrade path.
 */
class QuotaExceededException extends RuntimeException
{
    public function __construct(string $message = 'This plan limit has been reached.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
