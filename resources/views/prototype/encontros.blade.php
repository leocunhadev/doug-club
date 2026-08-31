@extends('layouts.prototype')

@section('content')
<div class="sec-head">
  <h2>Encontros do grupo</h2>
  <p>Aulas ao vivo com o Douglas e convidados. As gravações vão direto para a biblioteca.</p>
</div>
<div class="enc">
  @foreach ($encontros as $e)
    <div class="enc-item {{ $e['status'] === 'next' ? 'next' : '' }}">
      <div class="card">
        <div class="datebox"><b>{{ $e['d'] }}</b><small>{{ $e['m'] }}</small></div>
        <div class="d"><b>{{ $e['tema'] }}</b><small>{{ $e['quem'] }} · {{ $e['hora'] }}</small></div>
        @if ($e['status'] === 'past')
          <a class="pill cinza" href="{{ route('prototype.aulas', ['persona' => $persona]) }}">Ver na biblioteca</a>
          <button class="pill cinza" onclick="openNps('este encontro','Avalie: {{ addslashes($e['tema']) }}')">Avaliar</button>
        @else
          <span class="pill laranja">{{ $e['status'] === 'next' ? 'Próximo' : 'Ao vivo' }}</span>
        @endif
      </div>
    </div>
  @endforeach
</div>
@endsection
