<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Signs out users whose account has been deactivated by an administrator.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->withErrors(['email' => 'আপনার অ্যাকাউন্ট বন্ধ করা হয়েছে। অ্যাডমিনের সাথে যোগাযোগ করুন।']);
        }

        return $next($request);
    }
}
