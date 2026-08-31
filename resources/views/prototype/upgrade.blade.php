@extends('layouts.prototype')

@section('content')
<div class="card upg">
  <p class="eyebrow">Isso vive no CLUB</p>
  <h2>O Start te dá o conteúdo.<br>O CLUB te dá o Douglas.</h2>
  <p>Sessões individuais, dossiê vivo da sua empresa e pontes curadas com outros empresários. É mentoria de verdade, com poucas cadeiras por ano.</p>
  <ul>
    <li>Sessão 1:1 mensal de 90 minutos com o Douglas, com agenda direta na plataforma</li>
    <li>O fio da mentoria: cada decisão e compromisso registrado, sessão após sessão</li>
    <li>Seu cofre: insights, planos e gravações privadas de cada sessão, organizados para você</li>
    <li>Pontes curadas: o Douglas apresenta você a quem pode destravar seu negócio</li>
    <li>Encontros ao vivo com participação, não só a gravação</li>
  </ul>
  <button class="btn laranja" onclick="toast('Aplicação enviada. O Douglas analisa pessoalmente e responde em até 48h.')">Aplicar para o CLUB</button>
</div>
@endsection
