@extends('layouts.prototype')

@section('content')
<div class="sec-head">
  <h2>Meu cofre</h2>
  <p>Tudo que construímos juntos, sessão a sessão: insights, planos e materiais que o Douglas preparou para você. Só você e ele veem isso.</p>
</div>
<div class="cofre-note">Documentos com seu nome gravado em cada página. Este espaço é individual e intransferível.</div>
<div class="card">
  @foreach ($documentos as $d)
    <div class="doc-row">
      <div class="doc-ic">{{ $d['ic'] }}</div>
      <div class="d"><b>{{ $d['t'] }}</b><small>{{ $d['m'] }}</small></div>
      @if ($d['novo'])
        <span class="novo-pill">Novo</span>
      @endif
      <button class="btn ghost mini" onclick="toast('Abrindo <b>{{ addslashes($d['t']) }}</b> com marca d&#39;água do seu nome.')">Abrir</button>
    </div>
  @endforeach
</div>
@endsection
