<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanAccessAdmin
{
    /**
     * Only users holding at least one administration permission may open
     * the Administration Portal. Members are sent to their own dashboard.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canAccessAdmin()) {
            return redirect()->route('member.dashboard');
        }

        return $next($request);
    }
}
