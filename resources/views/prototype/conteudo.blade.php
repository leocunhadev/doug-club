@extends('layouts.prototype')

@section('content')
<div class="sec-head">
  <h2>Publicar conteúdo</h2>
  <p>Cole o link do vídeo, dê um título, escolha quem vê. Em 30 segundos está na biblioteca de todo mundo.</p>
</div>
<div class="card pub-form">
  <p class="eyebrow">Nova aula na biblioteca</p>
  <div class="row">
    <input class="inp" id="pa-titulo" placeholder="Título da aula" style="grid-column:1/-1">
    <input class="inp" id="pa-link" placeholder="Link do embed (YouTube, Vimeo, Panda)">
    <select class="inp" id="pa-tier"><option value="start">Start + CLUB veem</option><option value="club">Só o CLUB vê</option></select>
  </div>
  <button class="btn laranja mini" style="margin-top:14px" onclick="pubAula()">Publicar na biblioteca</button>
</div>
<div class="card pub-form">
  <p class="eyebrow">Novo encontro ao vivo</p>
  <div class="row">
    <input class="inp" id="ne-tema" placeholder="Tema do encontro" style="grid-column:1/-1">
    <input class="inp" id="ne-quem" placeholder="Quem conduz (você ou convidado)">
    <input class="inp" id="ne-data" placeholder="Data e hora (ex: 12 ago · 19h)">
  </div>
  <button class="btn laranja mini" style="margin-top:14px" onclick="addEncontro()">Publicar encontro</button>
</div>
@endsection

@section('scripts')
<script>
function pubAula(){
  const t=document.getElementById('pa-titulo').value.trim();
  if(!t){toast('Dê um título à aula antes de publicar.');return}
  const tier=document.getElementById('pa-tier').value;
  document.getElementById('pa-titulo').value='';document.getElementById('pa-link').value='';
  toast(`<b>${t}</b> publicada na biblioteca ${tier==='club'?'(só CLUB)':'(Start + CLUB)'}.`);
}
function addEncontro(){
  const tema=document.getElementById('ne-tema').value.trim();
  if(!tema){toast('Dê um tema ao encontro antes de publicar.');return}
  document.getElementById('ne-tema').value='';
  toast(`<b>${tema}</b> publicado. Todos os membros já veem na agenda.`);
}
</script>
@endsection
