<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a tenant-owned action is attempted without an active business.
 *
 * This is a 403, not a 500: the user IS authenticated, but the request
 * reached a tenant-scoped route without a resolved tenant (a stale session,
 * a removed membership, a hand-crafted link). Returning 500 here would both
 * leak a stack trace in production and tell the user nothing.
 */
class NoActiveBusinessException extends Exception
{
    protected $message = 'You do not have an active business. Please sign in again or select a business.';

    public function __construct(string $message = 'You do not have an active business. Please sign in again or select a business.', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
