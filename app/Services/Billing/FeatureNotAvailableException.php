<?php

namespace App\Services\Billing;

use RuntimeException;

/**
 * A plan-gated capability was used on a plan that does not include it.
 *
 * Distinct from QuotaExceededException on purpose: "you have used all 5
 * properties" and "your plan has no financial reporting" are different
 * problems with different fixes, and the UI routes the user to a different
 * place for each.
 */
class FeatureNotAvailableException extends RuntimeException
{
    public function __construct(string $message = 'This feature is not available on your current plan.', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
