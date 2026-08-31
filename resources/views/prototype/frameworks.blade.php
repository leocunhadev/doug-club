@extends('layouts.prototype')

@section('content')
<div class="sec-head">
  <h2>Frameworks DO</h2>
  <p>As ferramentas proprietárias do método Decisão Orientada. Cada uma tem o material para baixar e a aula que ensina a aplicar.</p>
</div>
<div class="fw-grid">
  @foreach ($frameworks as $f)
    <div class="card fw">
      <div class="num">{{ $f['n'] }}</div>
      <h3>{{ $f['t'] }}</h3>
      <p>{{ $f['p'] }}</p>
      <div class="foot">
        <button class="btn solid mini" onclick="toast('Download do material <b>{{ addslashes($f['t']) }}</b> iniciado (PDF).')">Baixar PDF</button>
        <a class="btn ghost mini" href="{{ route('prototype.aulas', ['persona' => $persona]) }}">Ver aula</a>
      </div>
    </div>
  @endforeach
</div>
@endsection
