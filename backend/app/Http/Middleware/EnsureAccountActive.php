<?php

namespace App\Http\Middleware;

use App\Support\MemberRegistration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends access straight away when an account is suspended or deactivated
 * while signed in (REG-REQ06).
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            $message = MemberRegistration::blockedLoginMessage($user);

            if ($request->expectsJson()) {
                $user->currentAccessToken()?->delete();

                return response()->json(['success' => false, 'message' => $message], 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', $message);
        }

        return $next($request);
    }
}
