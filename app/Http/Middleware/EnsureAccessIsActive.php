<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccessIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->access_revoked_at !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'Sua assinatura está inativa. Entre em contato com o suporte.');
        }

        return $next($request);
    }
}
