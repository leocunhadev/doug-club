@extends('layouts.prototype')

@section('content')
<div class="sec-head">
  <h2>Radar do dia</h2>
  <p>O que precisa da sua atenção hoje. Esta é a sua secretária.</p>
</div>
<div class="kpis">
  <div class="card kpi"><b>2</b><small>sessões 1:1 hoje: Ricardo às 10h, Alessandra às 15h</small></div>
  <div class="card kpi"><b>9,2</b><small>NPS médio das últimas sessões e aulas. Notas até 6 disparam alerta imediato para você.</small></div>
  <div class="card kpi alerta"><b>1</b><small>alerta: Caio está há 34 dias sem sessão. Toque nele.</small></div>
</div>
<h3 style="font-size:17px;margin-bottom:12px">Pontes sugeridas</h3>
<div class="card match">
  <div class="duo"><div class="avatar">RM</div><div class="avatar o">MP</div></div>
  <div class="d"><b>Ricardo</b> quer destravar <em>precificação</em> e <b>Marina</b> declarou que pode ensinar exatamente isso.</div>
  <button class="btn solid mini" onclick="toast('Apresentação enviada para <b>Ricardo e Marina</b>, com contexto dos dois perfis.')">Fazer a ponte</button>
</div>
<div class="card match">
  <div class="duo"><div class="avatar">ST</div><div class="avatar o">RM</div></div>
  <div class="d"><b>3 membros Start</b> assistiram todas as aulas e baixaram 2+ frameworks. <em>Prontos para o convite ao CLUB.</em></div>
  <button class="btn solid mini" onclick="toast('Lista dos 3 membros Start mais engajados aberta. Convite pessoal recomendado.')">Ver quem são</button>
</div>
<h3 style="font-size:17px;margin:24px 0 12px">Antes das sessões de hoje</h3>
<div class="card" style="padding:22px;margin-bottom:12px;border-left:4px solid var(--orange)">
  <p class="eyebrow">Ricardo Mendes · 10h00</p>
  <p style="margin-top:8px;font-size:15px">Última sessão: decidiu assumir o comercial. Compromisso: gravar 3 conversas de venda. Abra perguntando pelas gravações.</p>
</div>
<div class="card" style="padding:22px;border-left:4px solid var(--orange)">
  <p class="eyebrow">Alessandra Ribeiro · 15h00</p>
  <p style="margin-top:8px;font-size:15px">Última sessão: definiu o ICP da clínica. Compromisso: validar a oferta com 5 clientes. Pergunte o que ouviu deles.</p>
</div>
@endsection
