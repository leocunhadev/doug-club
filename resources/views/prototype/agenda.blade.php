@extends('layouts.prototype')

@section('content')
<div class="sec-head">
  <h2>Escolha sua sessão</h2>
  <p>Estes são os horários que o Douglas abriu. Você escolhe, o sistema confirma, o lembrete chega sozinho.</p>
</div>
<div class="days">
  @foreach ($dias as $d)
    <a class="day {{ $d['aberto'] ? '' : 'off' }} {{ $diaSel === $d['n'] ? 'on' : '' }}"
       href="{{ $d['aberto'] ? route('prototype.agenda', ['persona' => $persona, 'dia' => $d['n']]) : '#' }}">
      <small>{{ $d['w'] }}</small><b>{{ $d['n'] }}</b><div class="dot"></div>
    </a>
  @endforeach
</div>
<div class="slots">
  @foreach ($slots as $h)
    <a class="slot {{ $slotSel === $h ? 'on' : '' }}" href="{{ route('prototype.agenda', ['persona' => $persona, 'dia' => $diaSel, 'slot' => $h]) }}">{{ $h }}</a>
  @endforeach
</div>
@if ($slotSel)
  <div class="card confirm">
    <div class="t"><b>Dia {{ $diaSel }} de julho às {{ $slotSel }}</b><small>Sessão 1:1 · 90 minutos · ao vivo com Douglas</small></div>
    <button class="btn laranja" onclick="toast('Sessão confirmada para <b>{{ $diaSel }}/jul às {{ $slotSel }}</b>. Convite e lembrete no seu e-mail.')">Confirmar sessão</button>
  </div>
@endif
@endsection
