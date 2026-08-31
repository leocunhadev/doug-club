<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DO.ing CLUB · Plataforma</title>
@include('prototype.partials._styles')
</head>
<body>

<div class="shell">
  <div class="topbar">
    <div class="wordmark">
      DO.ing <span>CLUB</span>
      @if ($persona === 'start')
        <em class="start-tag">start</em>
      @endif
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <div class="planswitch" role="tablist" aria-label="Trocar plano de visualização">
        @foreach (['start' => 'Start', 'club' => 'CLUB', 'mentor' => 'Mentor'] as $p => $label)
          @php
            $targetNav = collect(config('prototype.navs.'.$p));
            $targetView = $targetNav->firstWhere('view', $active) ? $active : $targetNav->first()['view'];
          @endphp
          <a class="{{ $persona === $p ? 'on' : '' }}" href="{{ route('prototype.'.$targetView, ['persona' => $p]) }}">{{ $label }}</a>
        @endforeach
      </div>
      <a class="avatar topav" href="{{ route('prototype.'.($persona === 'mentor' ? 'radar' : 'home'), ['persona' => $persona]) }}#" onclick="event.preventDefault();openProfile()" aria-label="Meu perfil">{{ strtoupper(substr($user['name'], 0, 1)) }}</a>
    </div>
  </div>

  <div class="marquee" aria-hidden="true">
    <div class="track">
      @foreach (config('prototype.mq_words') as $word)
        <span>{{ $word }} ·</span>
      @endforeach
    </div>
  </div>

  <nav class="nav">
    @foreach (config('prototype.navs.'.$persona) as $item)
      <a class="{{ $active === $item['view'] ? 'on' : '' }}" href="{{ route('prototype.'.$item['view'], ['persona' => $persona]) }}">
        {{ $item['label'] }}
        @if (! empty($item['lock']))
          <span class="lock">🔒</span>
        @endif
      </a>
    @endforeach
  </nav>

  <section class="view">
    @yield('content')
  </section>

  <div class="rodape">
    <div><b>DO.ing CLUB</b> · Decisão Orientada</div>
    <div>Tudo é gente. Até o software.</div>
  </div>
</div>

<div class="toast" id="toast"></div>

<div class="nps-ov" id="npsOv">
  <div class="nps-card">
    <h3 id="npsTitle">Como foi para você?</h3>
    <p id="npsSub">De 0 a 10, o quanto isso te ajudou a decidir melhor?</p>
    <div class="nps-nums" id="npsNums"></div>
    <div class="nps-foot">
      <input class="inp" id="npsComment" placeholder="Quer contar o porquê? (opcional)">
      <button class="btn laranja mini" onclick="sendNps()">Enviar</button>
    </div>
    <button style="margin-top:12px;color:var(--stone);font-size:13px" onclick="closeNps()">Agora não</button>
  </div>
</div>

<div class="modal-ov" id="profOv">
  <div class="modal-card">
    <h3>Seu perfil</h3>
    <p>É assim que o grupo e o Douglas veem você. Toque na foto para trocar.</p>
    <div class="foto-pick">
      <button class="foto-prev" id="fotoPrev" onclick="document.getElementById('fotoInput').click()">{{ strtoupper(substr($user['name'], 0, 1)) }}</button>
      <div>
        <button class="btn ghost mini" onclick="document.getElementById('fotoInput').click()">Enviar foto</button>
        <input type="file" id="fotoInput" accept="image/*" style="display:none" onchange="pickFoto(event)">
      </div>
    </div>
    <label for="profNome">Seu nome</label>
    <input class="inp" id="profNome" value="{{ $user['name'] }}" placeholder="Como você quer ser chamado" style="margin-bottom:16px">
    <button class="btn laranja" style="width:100%" onclick="saveProfile()">Salvar perfil</button>
  </div>
</div>

<script>
/* ================= TOAST ================= */
let tt;
function toast(msg){const t=document.getElementById('toast');t.innerHTML=msg;t.classList.add('show');clearTimeout(tt);tt=setTimeout(()=>t.classList.remove('show'),3400)}

/* ================= NPS ================= */
let npsScore=null;
function openNps(ctx,title){
  npsScore=null;
  document.getElementById('npsTitle').textContent=title||'Como foi para você?';
  document.getElementById('npsSub').textContent=`De 0 a 10, o quanto ${ctx} te ajudou a decidir melhor?`;
  document.getElementById('npsNums').innerHTML=Array.from({length:11},(_,i)=>
    `<button onclick="npsScore=${i};document.querySelectorAll('.nps-nums button').forEach(b=>b.classList.remove('on'));this.classList.add('on')">${i}</button>`).join('');
  document.getElementById('npsComment').value='';
  document.getElementById('npsOv').classList.add('on');
}
function closeNps(){document.getElementById('npsOv').classList.remove('on')}
function sendNps(){
  if(npsScore===null){toast('Escolha uma nota de 0 a 10.');return}
  closeNps();
  toast(`Nota <b>${npsScore}</b> registrada. ${npsScore<=6?'O Douglas é avisado na hora quando algo não foi bem.':'Obrigado! Isso vai direto para o Radar do Douglas.'}`);
}

/* ================= PERFIL ================= */
let profPhoto=null;
function openProfile(){document.getElementById('profOv').classList.add('on')}
function closeProfile(){document.getElementById('profOv').classList.remove('on')}
function pickFoto(ev){
  const f=ev.target.files[0];if(!f)return;
  const r=new FileReader();
  r.onload=()=>{profPhoto=r.result;const el=document.getElementById('fotoPrev');el.style.backgroundImage=`url(${profPhoto})`;el.textContent=''};
  r.readAsDataURL(f);
}
function saveProfile(){
  const n=document.getElementById('profNome').value.trim();
  closeProfile();
  toast(`Perfil salvo${n?`. Olá, <b>${n}</b>!`:''}`);
}

/* ================= PLAYER (demo) ================= */
let playTimer=null;
function fakePlay(pid,fid,start){
  const p=document.getElementById(pid);
  if(!p||p.classList.contains('playing'))return;
  p.classList.add('playing');
  let w=start;clearInterval(playTimer);
  playTimer=setInterval(()=>{w=Math.min(w+2.2,100);const fillEl=document.getElementById(fid);if(fillEl)fillEl.style.width=w+'%';
    if(w>=100){clearInterval(playTimer);openNps('esta aula','A aula terminou. Como foi?')}},400);
}
</script>

@yield('scripts')

</body>
</html>
