<?php

namespace App\Http\Controllers\Prototype;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrototypeController extends Controller
{
    protected const PERSONAS = ['start', 'club', 'mentor'];

    protected const USER = [
        'name' => 'Ricardo',
        'email' => 'ricardo@mendeslog.com.br',
    ];

    public function login(): View
    {
        return view('prototype.login');
    }

    public function home(Request $request): View
    {
        $persona = $this->persona($request, 'club');

        return view('prototype.home', [
            'persona' => $persona,
            'active' => 'home',
            'user' => self::USER,
        ]);
    }

    public function aulas(Request $request): View
    {
        $persona = $this->persona($request, 'club');
        $cat = $request->query('cat', 'Tudo');
        $categorias = ['Tudo', 'Encontros', 'Convidados', 'Frameworks'];
        if (! in_array($cat, $categorias, true)) {
            $cat = 'Tudo';
        }

        $aulas = collect(config('prototype.aulas'))
            ->when($persona === 'start', fn ($c) => $c->where('tier', 'start'))
            ->when($cat !== 'Tudo', fn ($c) => $c->where('cat', $cat))
            ->values();

        $total = collect(config('prototype.aulas'))
            ->when($persona === 'start', fn ($c) => $c->where('tier', 'start'))
            ->count();

        return view('prototype.aulas', [
            'persona' => $persona,
            'active' => 'aulas',
            'user' => self::USER,
            'aulas' => $aulas,
            'categorias' => $categorias,
            'catAtual' => $cat,
            'total' => $total,
        ]);
    }

    public function frameworks(Request $request): View
    {
        $persona = $this->persona($request, 'club');

        return view('prototype.frameworks', [
            'persona' => $persona,
            'active' => 'frameworks',
            'user' => self::USER,
            'frameworks' => config('prototype.frameworks'),
        ]);
    }

    public function upgrade(Request $request): View
    {
        $persona = $this->persona($request, 'start');

        return view('prototype.upgrade', [
            'persona' => $persona,
            'active' => 'upgrade',
            'user' => self::USER,
        ]);
    }

    public function cofre(Request $request): View
    {
        $persona = $this->persona($request, 'club');

        return view('prototype.cofre', [
            'persona' => $persona,
            'active' => 'cofre',
            'user' => self::USER,
            'documentos' => config('prototype.cofre_docs'),
        ]);
    }

    public function agenda(Request $request): View
    {
        $persona = $this->persona($request, 'club');
        $dias = config('prototype.dias');
        $horarios = config('prototype.horarios');

        $diaSel = $request->query('dia');
        $diasAbertos = collect($dias)->where('aberto', true)->pluck('n')->all();
        if (! in_array($diaSel, $diasAbertos, true)) {
            $diaSel = null;
        }

        $slots = $diaSel ? ($horarios[$diaSel] ?? []) : [];
        $slotSel = $request->query('slot');
        if (! in_array($slotSel, $slots, true)) {
            $slotSel = null;
        }

        return view('prototype.agenda', [
            'persona' => $persona,
            'active' => 'agenda',
            'user' => self::USER,
            'dias' => $dias,
            'diaSel' => $diaSel,
            'slots' => $slots,
            'slotSel' => $slotSel,
        ]);
    }

    public function pessoas(Request $request): View
    {
        $persona = $this->persona($request, 'club');

        return view('prototype.pessoas', [
            'persona' => $persona,
            'active' => 'pessoas',
            'user' => self::USER,
            'membros' => config('prototype.membros'),
        ]);
    }

    public function encontros(Request $request): View
    {
        $persona = $this->persona($request, 'club');

        return view('prototype.encontros', [
            'persona' => $persona,
            'active' => 'encontros',
            'user' => self::USER,
            'encontros' => config('prototype.encontros'),
        ]);
    }

    public function radar(Request $request): View
    {
        $persona = $this->persona($request, 'mentor');

        return view('prototype.radar', [
            'persona' => $persona,
            'active' => 'radar',
            'user' => self::USER,
        ]);
    }

    public function dossies(Request $request): View
    {
        $persona = $this->persona($request, 'mentor');
        $dossies = config('prototype.dossies');

        $dossieSel = $request->query('dossie', 'RM');
        if (! array_key_exists($dossieSel, $dossies)) {
            $dossieSel = 'RM';
        }

        return view('prototype.dossies', [
            'persona' => $persona,
            'active' => 'dossies',
            'user' => self::USER,
            'dossies' => $dossies,
            'dossieSel' => $dossieSel,
        ]);
    }

    public function conteudo(Request $request): View
    {
        $persona = $this->persona($request, 'mentor');

        return view('prototype.conteudo', [
            'persona' => $persona,
            'active' => 'conteudo',
            'user' => self::USER,
        ]);
    }

    public function disp(Request $request): View
    {
        $persona = $this->persona($request, 'mentor');

        return view('prototype.disp', [
            'persona' => $persona,
            'active' => 'disp',
            'user' => self::USER,
            'blocos' => config('prototype.blocos'),
        ]);
    }

    protected function persona(Request $request, string $default): string
    {
        $persona = $request->query('persona', $default);

        return in_array($persona, self::PERSONAS, true) ? $persona : $default;
    }
}
