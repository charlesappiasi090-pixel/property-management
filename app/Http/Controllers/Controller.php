<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Base controller.
 *
 * Both traits are added explicitly. Laravel 11+ ships this class EMPTY, so
 * without them `$this->authorize()` and `$this->validate()` are undefined
 * method calls — and because controllers are only exercised at request time,
 * that surfaces as a fatal on the first request that hits the line rather than
 * as a build failure.
 */
abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;
}
