<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // The Laravel 12 skeleton leaves authorization to the caller. PayKaro has a
    // tenant and three roles, so every controller gets `$this->authorize()` and
    // policy checks stay one line instead of a Gate:: facade call each time.
    use AuthorizesRequests;
}
