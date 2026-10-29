<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isInternal(), 403, 'Hanya untuk Admin dan Staff.');

        return $next($request);
    }
}
