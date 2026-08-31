@extends('layouts.prototype')

@section('content')
<div class="sec-head">
  <h2>Biblioteca de aulas</h2>
  <p>Todos os encontros gravados, aulas de convidados e frameworks em vídeo. Aperte o play e continue de onde parou.</p>
</div>
<div class="player-wrap">
  <div class="player" id="libPlayer" onclick="fakePlay('libPlayer','libFill',0)">
    <div class="big-title">Posicionamento que sustenta preço</div>
    <div class="playbtn"></div>
    <div class="meta">Convidado: Rodrigo Maciel · 64 min</div>
    <span class="wm">{{ $user['email'] }} · membro 0042</span>
    <div class="fake-video"><b>Player de exemplo</b>Aqui entra o embed protegido da aula selecionada. A marca d'água dinâmica carrega o e-mail de quem assiste.</div>
  </div>
  <div class="progress"><div class="fill" id="libFill"></div></div>
  <div class="player-bar"><span>Assistindo agora: <b>Posicionamento que sustenta preço</b></span><span>{{ $total }} aulas na sua biblioteca</span></div>
</div>
<div class="aula-filters">
  @foreach ($categorias as $c)
    <a class="{{ $c === $catAtual ? 'on' : '' }}" href="{{ route('prototype.aulas', ['persona' => $persona, 'cat' => $c]) }}">{{ $c }}</a>
  @endforeach
</div>
<div class="aulas-grid">
  @foreach ($aulas as $a)
    <button class="card aula" onclick="selAula(this,'{{ addslashes($a['t']) }}','{{ addslashes($a['m']) }}')">
      <div class="thumb"><span class="n">{{ $a['n'] }}</span><span class="p"></span></div>
      <div class="body"><b>{{ $a['t'] }}</b><small>{{ $a['m'] }}{{ $a['tier'] === 'club' ? ' · Exclusivo CLUB' : '' }}</small></div>
    </button>
  @endforeach
</div>
@endsection

@section('scripts')
<script>
function selAula(btn,t,m){
  const p=document.getElementById('libPlayer');
  p.classList.remove('playing');clearInterval(playTimer);
  document.getElementById('libFill').style.width='0%';
  p.querySelector('.big-title').textContent=t;
  p.querySelector('.meta').textContent=m;
  document.querySelector('.player-bar b').textContent=t;
  document.getElementById('libPlayer').scrollIntoView({behavior:'smooth',block:'center'});
}
</script>
@endsection
