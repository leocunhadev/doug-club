@extends('layouts.prototype')

@section('content')
<div class="sec-head">
  <h2>Gente do CLUB</h2>
  <p>Cada pessoa aqui foi escolhida. Veja o que cada uma ensina e quer aprender, e peça a ponte. O Douglas apresenta com contexto.</p>
</div>
<div class="people">
  @foreach ($membros as $m)
    <div class="card person">
      <div class="top">
        <div class="avatar {{ in_array($m['ini'], ['MP', 'AR']) ? 'o' : '' }}">{{ $m['ini'] }}</div>
        <div><b>{{ $m['nome'] }}</b><small>{{ $m['emp'] }}</small></div>
      </div>
      <p class="bio">{{ $m['bio'] }}</p>
      <div>
        <div class="lbl">Pode ensinar</div>
        @foreach ($m['ensina'] as $t)
          <span class="tag ensina">{{ $t }}</span>
        @endforeach
      </div>
      <div>
        <div class="lbl">Quer aprender</div>
        @foreach ($m['aprende'] as $t)
          <span class="tag">{{ $t }}</span>
        @endforeach
      </div>
      <div class="foot">
        <button class="btn solid mini" style="flex:1" onclick="toast('O Douglas recebeu seu pedido e vai apresentar vocês: <b>você + {{ explode(' ', $m['nome'])[0] }}</b>.')">Pedir a ponte</button>
      </div>
    </div>
  @endforeach
</div>
@endsection
