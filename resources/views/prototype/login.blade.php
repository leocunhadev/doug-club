<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DO.ing CLUB · Entrar</title>
@include('prototype.partials._styles')
</head>
<body>

<div class="login-ov">
  <div class="login-box">
    <div class="lw">DO.ing <span>CLUB</span></div>
    <p class="frase">Decisão Orientada. Tudo é gente.</p>
    <div class="login-card">
      <form method="GET" action="{{ route('prototype.home') }}">
        <label for="loginEmail">Seu e-mail de membro</label>
        <input class="inp" id="loginEmail" name="email" type="email" placeholder="voce@suaempresa.com.br">
        <label for="loginSenha" style="margin-top:4px">Sua senha</label>
        <input class="inp" id="loginSenha" name="senha" type="password" placeholder="••••••••">
        <button class="btn laranja" type="submit" style="width:100%">Entrar na plataforma</button>
      </form>
      <button class="btn" style="width:100%;margin-top:12px;color:#8f8a82;font-size:13px" onclick="toast('Link mágico enviado. No protótipo, use o botão &quot;Entrar na plataforma&quot;.')">Esqueci a senha · entrar com link mágico</button>
    </div>
    <p class="login-hint">Acesso individual e intransferível, com sessão única por conta.<br>Entrar em um novo aparelho <b>desconecta o anterior</b>.</p>
  </div>
</div>

<div class="toast" id="toast"></div>
<script>
let tt;
function toast(msg){const t=document.getElementById('toast');t.innerHTML=msg;t.classList.add('show');clearTimeout(tt);tt=setTimeout(()=>t.classList.remove('show'),3400)}
</script>
</body>
</html>
