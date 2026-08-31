<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectMentorFromDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isMentor()) {
            return redirect()->route('mentor.radar');
        }

        return $next($request);
    }
}
