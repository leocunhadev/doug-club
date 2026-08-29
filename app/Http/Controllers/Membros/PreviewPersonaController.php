<?php

namespace App\Http\Controllers\Membros;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreviewPersonaController extends Controller
{
    private const TIERS = ['start', 'club', 'mentor'];

    public function __invoke(Request $request, string $tier): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);
        abort_unless(in_array($tier, self::TIERS, true), 404);

        if ($tier === $request->user()->tier) {
            $request->session()->forget('admin_persona_preview');
        } else {
            $request->session()->put('admin_persona_preview', $tier);
        }

        return back();
    }
}
