@extends('layouts.prototype')

@section('content')
<div class="hero">
  <p class="eyebrow laranja">{{ $persona === 'start' ? 'DO.ing CLUB start · Sua base' : 'DO.ing CLUB · Mentoria' }}</p>
  @if ($persona === 'start')
    <h1>Olá, {{ $user['name'] }}.<br>Sua próxima <em>decisão</em> começa aqui.</h1>
  @else
    <h1>Olá, {{ $user['name'] }}.<br>Vamos <em>continuar de onde paramos?</em></h1>
    <div class="fioline">
      <div class="bar"></div>
      <div>
        <p>"Você decidiu assumir o discurso de venda. Combinamos: gravar 3 conversas até nossa próxima sessão."</p>
        <small>Onde paramos · nota do Douglas · 18 jun</small>
      </div>
    </div>
  @endif
</div>

<div class="home-grid">
  <div>
    <p class="eyebrow" style="margin-bottom:10px">Continuar assistindo</p>
    <div class="player-wrap">
      <div class="player" id="homePlayer" onclick="fakePlay('homePlayer','homeFill',40)">
        <div class="big-title">O comercial é gente</div>
        <div class="playbtn"></div>
        <div class="meta">Encontro gravado · Douglas · 58 min · você parou em 23:14</div>
        <span class="wm">{{ $user['email'] }} · membro 0042</span>
        <div class="fake-video"><b>Player de exemplo</b>No app real, aqui entra o embed protegido (Panda, Vimeo ou Mux), retomando de onde você parou. Repare na marca d'água com o e-mail do membro.</div>
      </div>
      <div class="progress"><div class="fill" id="homeFill" style="width:40%"></div></div>
    </div>
  </div>
  <div class="side-stack">
    <div class="card next-mini">
      @if ($persona === 'start')
        <p class="eyebrow">Novidade na biblioteca</p>
        <b>CAFÉ: prompts<br><span>que decidem</span></b>
        <p>Aula nova do framework CAFÉ, já disponível para você.</p>
        <a class="btn laranja mini" href="{{ route('prototype.aulas', ['persona' => $persona]) }}">Assistir agora</a>
      @else
        <p class="eyebrow">Sua próxima sessão 1:1</p>
        <b>Quinta, 09 de julho<br><span>10h00 · 90 min</span></b>
        <p>Com Douglas, ao vivo. O link chega por e-mail 1h antes.</p>
        <button class="btn laranja mini" onclick="toast('Adicionado ao seu calendário.')">Adicionar ao calendário</button>
      @endif
    </div>
    <div class="card quick">
      <h3>Atalhos</h3>
      <a href="{{ route('prototype.aulas', ['persona' => $persona]) }}">Biblioteca de aulas <span class="seta">→</span></a>
      <a href="{{ route('prototype.frameworks', ['persona' => $persona]) }}">Frameworks DO <span class="seta">→</span></a>
      @if ($persona === 'start')
        <a href="{{ route('prototype.upgrade', ['persona' => $persona]) }}">Conhecer o CLUB <span class="seta">→</span></a>
      @else
        <a href="{{ route('prototype.agenda', ['persona' => $persona]) }}">Marcar minha sessão <span class="seta">→</span></a>
      @endif
    </div>
  </div>
</div>
@endsection
