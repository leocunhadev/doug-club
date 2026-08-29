<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTier
{
    public function handle(Request $request, Closure $next, string $minTier): Response
    {
        $user = $request->user();

        $allowed = match ($minTier) {
            'club' => $user?->hasClubAccess() ?? false,
            'mentor' => $user?->isMentor() ?? false,
            default => false,
        };

        if (! $allowed) {
            return redirect()->route('dashboard')
                ->with('status', "Esse conteúdo está disponível no {$minTier}.");
        }

        return $next($request);
    }
}
