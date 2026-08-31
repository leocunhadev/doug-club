@extends('layouts.prototype')

@section('content')
<div class="sec-head">
  <h2>Dossiês</h2>
  <p>A memória viva de cada mentorado. O fio laranja é a história de vocês dois.</p>
</div>
<div class="dossie-wrap">
  <div class="card dlist">
    @foreach ($dossies as $k => $d)
      <a class="{{ $dossieSel === $k ? 'on' : '' }}" href="{{ route('prototype.dossies', ['persona' => $persona, 'dossie' => $k]) }}">
        <div class="avatar {{ $dossieSel === $k ? 'o' : '' }}" style="width:38px;height:38px;font-size:13px">{{ $k }}</div>
        <div class="d"><b>{{ $d['nome'] }}</b><small>{{ $d['emp'] }}</small></div>
      </a>
    @endforeach
  </div>
  <div class="card dossie">
    @php($d = $dossies[$dossieSel])
    <div class="head">
      <div class="avatar o" style="width:54px;height:54px;font-size:18px">{{ $dossieSel }}</div>
      <div><h3>{{ $d['nome'] }}</h3><small>{{ $d['emp'] }} · {{ $d['desde'] }}</small></div>
    </div>
    <div class="compromisso">Compromisso ativo: <b>{{ $d['comp'] }}</b></div>
    <p class="eyebrow laranja">O fio da mentoria</p>
    <div class="fio">
      @foreach ($d['fio'] as $n)
        <div class="no"><small>{{ $n['q'] }}</small><b>{{ $n['t'] }}</b><p>{{ $n['p'] }}</p></div>
      @endforeach
    </div>
    <div class="nota-add">
      <input class="inp" id="novaNota" placeholder="Anotar algo sobre {{ explode(' ', $d['nome'])[0] }}...">
      <button class="btn laranja mini" onclick="addNota()">Guardar</button>
    </div>
    <div class="nota-add">
      <input class="inp" id="novoDoc" placeholder="Enviar insight ou documento para o cofre de {{ explode(' ', $d['nome'])[0] }}...">
      <button class="btn solid mini" onclick="sendDoc()">Enviar ao cofre</button>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
function addNota(){
  const inp=document.getElementById('novaNota');
  if(!inp.value.trim()){toast('Escreva a nota antes de guardar.');return}
  toast('Nota guardada no fio. Só você vê.');
  inp.value='';
}
function sendDoc(){
  const inp=document.getElementById('novoDoc');
  if(!inp.value.trim()){toast('Descreva o documento antes de enviar.');return}
  toast('Enviado para o cofre. O mentorado é avisado por e-mail e vê o selo Novo.');
  inp.value='';
}
</script>
@endsection
