@extends('layouts.prototype')

@section('content')
<div class="sec-head">
  <h2>Sua disponibilidade</h2>
  <p>Ligue e desligue blocos. O que estiver aberto aparece na hora para os mentorados.</p>
</div>
<div class="disp-grid">
  @foreach ($blocos as $b)
    <button class="card disp {{ $b['aberto'] ? 'aberto' : '' }}" onclick="togDisp(this,'{{ addslashes($b['dia']) }}','{{ addslashes($b['h']) }}')">
      <b>{{ $b['dia'] }}</b><small>{{ $b['h'] }}</small><div class="sw"></div>
    </button>
  @endforeach
</div>
@endsection

@section('scripts')
<script>
function togDisp(btn,dia,h){
  btn.classList.toggle('aberto');
  const aberto=btn.classList.contains('aberto');
  toast(aberto?`<b>${dia}, ${h}</b> aberto para os mentorados.`:`<b>${dia}, ${h}</b> fechado.`);
}
</script>
@endsection
