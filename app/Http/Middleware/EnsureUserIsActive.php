<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * Logs out and blocks any authenticated user whose account is
     * suspended, even if the suspension happened after they already
     * logged in (mid-session).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->isDeleted()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email_address' => 'This account has been deleted.',
            ]);
        }

        if (Auth::check() && Auth::user()->isSuspended()) {
            $reason = Auth::user()->userStatus?->suspension_reason;

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email_address' => $reason
                    ? "Your account has been suspended. Reason: {$reason} Please visit the terminal office to have your account reviewed."
                    : 'Your account has been suspended. Please visit the terminal office to have your account reviewed.',
            ]);
        }

        return $next($request);
    }
}
