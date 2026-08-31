<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&display=swap" rel="stylesheet">
<style>
:root{
  --black:#0B0B0C;
  --ink:#1A1A1C;
  --paper:#F6F3EE;
  --card:#FFFFFF;
  --sand:#E6E0D6;
  --stone:#8B857A;
  --orange:#FF5100;
  --orange-soft:#FFEDE4;
  --radius:18px;
  --shadow:0 1px 2px rgba(11,11,12,.05), 0 10px 28px rgba(11,11,12,.07);
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:var(--paper);color:var(--ink);font-size:15px;line-height:1.55;-webkit-font-smoothing:antialiased}
h1,h2,h3,.syne{font-family:'Syne',sans-serif;font-weight:800;letter-spacing:-.015em}
button{font-family:'DM Sans',sans-serif;cursor:pointer;border:none;background:none;font-size:14px}
input{font-family:'DM Sans',sans-serif}
button:focus-visible,input:focus-visible{outline:2px solid var(--orange);outline-offset:2px;border-radius:6px}
a{text-decoration:none;color:inherit}

/* ===== SHELL ===== */
.shell{max-width:1120px;margin:0 auto;padding:0 20px 90px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 0 12px;position:sticky;top:0;z-index:60;background:linear-gradient(var(--paper) 80%,rgba(246,243,238,0))}
.wordmark{font-family:'Syne',sans-serif;font-weight:800;font-size:17px;color:var(--black);white-space:nowrap}
.wordmark span{color:var(--orange)}
.planswitch{display:flex;background:var(--black);border-radius:99px;padding:3px}
.planswitch button,.planswitch a{color:#B9B4AB;padding:7px 14px;border-radius:99px;font-weight:500;transition:.2s;white-space:nowrap;display:inline-block}
.planswitch a.on,.planswitch button.on{background:var(--orange);color:#fff;font-weight:700}
@media(max-width:560px){.planswitch button,.planswitch a{padding:7px 10px;font-size:13px}}

/* ===== MARQUEE (Floka-inspired) ===== */
.marquee{overflow:hidden;background:var(--black);border-radius:14px;margin:6px 0 18px;padding:11px 0}
.marquee .track{display:flex;gap:0;width:max-content;animation:mq 26s linear infinite}
.marquee span{font-family:'Syne',sans-serif;font-weight:800;font-size:13px;letter-spacing:.14em;text-transform:uppercase;color:#fff;padding:0 22px;white-space:nowrap}
.marquee span:nth-child(even){color:var(--orange)}
@keyframes mq{from{transform:translateX(0)}to{transform:translateX(-50%)}}
@media(prefers-reduced-motion:reduce){.marquee .track{animation:none}}

/* ===== NAV ===== */
.nav{display:flex;gap:6px;margin:0 0 26px;flex-wrap:wrap}
.nav a{padding:9px 16px;border-radius:99px;font-weight:500;color:var(--stone);border:1px solid transparent;transition:.15s;display:flex;align-items:center;gap:6px}
.nav a:hover{color:var(--ink)}
.nav a.on{background:var(--card);color:var(--black);border-color:var(--sand);font-weight:700;box-shadow:var(--shadow)}
.nav a .lock{font-size:12px;opacity:.7}

/* ===== COMMON ===== */
.view{animation:rise .35s ease both}
@keyframes rise{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
@media(prefers-reduced-motion:reduce){.view{animation:none}}
.card{background:var(--card);border:1px solid var(--sand);border-radius:var(--radius);box-shadow:var(--shadow)}
.eyebrow{font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--stone)}
.eyebrow.laranja{color:var(--orange)}
.sec-head{margin:4px 0 22px}
.sec-head h2{font-size:clamp(26px,4vw,38px);color:var(--black);line-height:1.05}
.sec-head p{color:var(--stone);margin-top:8px;max-width:560px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 22px;border-radius:99px;font-weight:700;font-size:14px;transition:.15s}
.btn.solid{background:var(--black);color:#fff}
.btn.solid:hover{background:var(--orange)}
.btn.laranja{background:var(--orange);color:#fff}
.btn.laranja:hover{background:#D94500}
.btn.ghost{border:1px solid var(--sand);background:var(--card);color:var(--ink)}
.btn.ghost:hover{border-color:var(--black)}
.btn.mini{padding:8px 15px;font-size:13px}
.avatar{width:44px;height:44px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:15px;color:#fff;background:var(--black)}
.avatar.o{background:var(--orange)}
.tag{display:inline-block;font-size:12px;font-weight:500;padding:4px 10px;border-radius:99px;background:var(--paper);border:1px solid var(--sand);margin:0 4px 6px 0}
.tag.ensina{background:var(--orange-soft);border-color:#FFD2BC;color:#B23800}
.pill{font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:5px 11px;border-radius:99px}
.pill.laranja{background:var(--orange);color:#fff}
.pill.cinza{background:var(--paper);color:var(--stone);border:1px solid var(--sand)}
.toast{position:fixed;left:50%;bottom:26px;transform:translateX(-50%) translateY(80px);background:var(--black);color:#fff;padding:14px 22px;border-radius:99px;font-weight:500;font-size:14px;opacity:0;transition:.3s;z-index:99;box-shadow:0 12px 32px rgba(0,0,0,.25);max-width:calc(100vw - 40px);text-align:center}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.toast b{color:var(--orange)}

/* ===== HOME (limpa) ===== */
.hero{padding:14px 0 30px}
.hero .eyebrow{margin-bottom:10px}
.hero h1{font-size:clamp(34px,6vw,58px);line-height:1.02;color:var(--black)}
.hero h1 em{font-style:normal;color:var(--orange)}
.hero .fioline{margin-top:16px;display:flex;gap:12px;align-items:flex-start;max-width:640px}
.hero .fioline .bar{width:4px;border-radius:2px;background:var(--orange);align-self:stretch;flex-shrink:0}
.hero .fioline p{color:#4b4740;font-size:15px}
.hero .fioline small{display:block;color:var(--stone);margin-top:4px;font-size:12.5px}
.home-grid{display:grid;grid-template-columns:1.5fr .8fr;gap:18px}
@media(max-width:860px){.home-grid{grid-template-columns:1fr}}
.side-stack{display:flex;flex-direction:column;gap:16px}
.next-mini{padding:22px;background:var(--black);color:#fff;border:none;display:flex;flex-direction:column;gap:10px}
.next-mini .eyebrow{color:#8f8a82}
.next-mini b{font-family:'Syne',sans-serif;font-size:20px;line-height:1.15}
.next-mini b span{color:var(--orange)}
.next-mini p{color:#B9B4AB;font-size:13.5px}
.quick{padding:20px 22px}
.quick h3{font-size:15px;margin-bottom:10px}
.quick a,.quick button{display:flex;align-items:center;gap:10px;width:100%;text-align:left;padding:10px 0;border-top:1px solid var(--sand);color:var(--ink);font-size:14px;font-weight:500}
.quick a:first-of-type,.quick button:first-of-type{border-top:none}
.quick .seta{margin-left:auto;color:var(--orange);font-weight:700}

/* ===== PLAYER / AULAS ===== */
.player-wrap{border-radius:var(--radius);overflow:hidden;border:1px solid var(--sand);box-shadow:var(--shadow);background:var(--black)}
.player{position:relative;aspect-ratio:16/9;background:radial-gradient(120% 120% at 20% 0%, #232326 0%, #0B0B0C 60%);display:flex;align-items:center;justify-content:center;cursor:pointer}
.player .big-title{position:absolute;left:24px;top:20px;right:24px;color:#fff;font-family:'Syne',sans-serif;font-weight:800;font-size:clamp(17px,2.6vw,24px);line-height:1.15;text-shadow:0 2px 12px rgba(0,0,0,.4)}
.player .meta{position:absolute;left:24px;bottom:18px;color:#B9B4AB;font-size:13px}
.playbtn{width:74px;height:74px;border-radius:50%;background:var(--orange);display:flex;align-items:center;justify-content:center;transition:.2s;box-shadow:0 10px 30px rgba(255,81,0,.45)}
.playbtn::after{content:"";border-style:solid;border-width:13px 0 13px 22px;border-color:transparent transparent transparent #fff;margin-left:5px}
.player:hover .playbtn{transform:scale(1.08)}
.player.playing .playbtn,.player.playing .big-title{display:none}
.player .fake-video{display:none;position:absolute;inset:0;background:#000;color:#8f8a82;align-items:center;justify-content:center;flex-direction:column;gap:10px;font-size:13px;text-align:center;padding:20px}
.player.playing .fake-video{display:flex}
.player .fake-video b{color:#fff;font-family:'Syne',sans-serif;font-size:16px}
.progress{height:5px;background:#232326;position:relative}
.progress .fill{position:absolute;left:0;top:0;bottom:0;width:0%;background:var(--orange);transition:width .4s linear}
.player-bar{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:13px 20px;background:var(--black);color:#B9B4AB;font-size:13px;flex-wrap:wrap}
.player-bar b{color:#fff}
.aula-filters{display:flex;gap:8px;flex-wrap:wrap;margin:24px 0 16px}
.aula-filters a{padding:8px 14px;border-radius:99px;border:1px solid var(--sand);background:var(--card);font-size:13px;color:var(--stone);display:inline-block}
.aula-filters a.on{background:var(--black);color:#fff;border-color:var(--black);font-weight:700}
.aulas-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.aula{overflow:hidden;padding:0;display:flex;flex-direction:column;transition:.2s;text-align:left}
.aula:hover{transform:translateY(-3px);box-shadow:0 14px 34px rgba(11,11,12,.12)}
.aula .thumb{aspect-ratio:16/9;background:var(--black);position:relative;display:flex;align-items:flex-end;padding:14px}
.aula .thumb .n{font-family:'Syne',sans-serif;font-weight:800;font-size:44px;color:transparent;-webkit-text-stroke:1.5px var(--orange);position:absolute;top:8px;right:14px;opacity:.9}
.aula .thumb .p{width:34px;height:34px;border-radius:50%;background:var(--orange);display:flex;align-items:center;justify-content:center}
.aula .thumb .p::after{content:"";border-style:solid;border-width:6px 0 6px 10px;border-color:transparent transparent transparent #fff;margin-left:2px}
.aula .body{padding:14px 16px 16px}
.aula .body b{font-family:'Syne',sans-serif;font-size:14.5px;display:block;line-height:1.25}
.aula .body small{color:var(--stone);display:block;margin-top:5px;font-size:12.5px}

/* ===== FRAMEWORKS ===== */
.fw-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px}
.fw{padding:24px;display:flex;flex-direction:column;gap:10px;position:relative;overflow:hidden}
.fw .num{font-family:'Syne',sans-serif;font-weight:800;font-size:56px;line-height:1;color:transparent;-webkit-text-stroke:1.5px var(--orange)}
.fw h3{font-size:17px}
.fw p{font-size:13.5px;color:#4b4740;flex:1}
.fw .foot{display:flex;gap:8px;margin-top:6px}

/* ===== UPGRADE (Start -> CLUB) ===== */
.upg{background:var(--black);border:none;color:#fff;padding:clamp(28px,5vw,48px);position:relative;overflow:hidden}
.upg::after{content:"CLUB";position:absolute;right:-20px;bottom:-38px;font-family:'Syne',sans-serif;font-weight:800;font-size:160px;color:transparent;-webkit-text-stroke:1px rgba(255,81,0,.35);pointer-events:none}
.upg .eyebrow{color:var(--orange)}
.upg h2{font-size:clamp(26px,4.4vw,40px);line-height:1.05;margin:10px 0 14px;max-width:560px}
.upg p{color:#B9B4AB;max-width:520px}
.upg ul{list-style:none;margin:20px 0 26px;display:flex;flex-direction:column;gap:11px;max-width:520px}
.upg li{display:flex;gap:12px;font-size:14.5px}
.upg li::before{content:"";width:8px;height:8px;border-radius:50%;background:var(--orange);flex-shrink:0;margin-top:7px}
.start-banner{display:flex;align-items:center;gap:14px;padding:16px 20px;background:var(--orange-soft);border:1px solid #FFD2BC;border-radius:var(--radius);margin-bottom:20px;flex-wrap:wrap}
.start-banner p{flex:1;min-width:200px;font-size:14px;color:#7a3a14}
.start-banner b{color:#B23800}

/* ===== AGENDA ===== */
.days{display:flex;gap:10px;overflow-x:auto;padding-bottom:8px;scrollbar-width:none}
.days::-webkit-scrollbar{display:none}
.day{min-width:74px;padding:14px 10px;border-radius:14px;background:var(--card);border:1px solid var(--sand);text-align:center;transition:.15s;color:var(--ink);display:block}
.day small{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--stone)}
.day b{display:block;font-family:'Syne',sans-serif;font-size:20px;margin-top:2px}
.day .dot{width:6px;height:6px;border-radius:50%;background:var(--orange);margin:7px auto 0}
.day.off{opacity:.35;pointer-events:none}
.day.off .dot{background:transparent}
.day.on{background:var(--black);color:#fff;border-color:var(--black)}
.day.on small{color:#B9B4AB}
.slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(108px,1fr));gap:10px;margin-top:18px}
.slot{padding:13px 8px;border-radius:12px;border:1px solid var(--sand);background:var(--card);font-weight:700;font-size:14px;transition:.15s;color:var(--ink);display:block;text-align:center}
.slot:hover{border-color:var(--orange);color:var(--orange)}
.slot.on{background:var(--orange);border-color:var(--orange);color:#fff}
.confirm{margin-top:20px;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.confirm .t b{font-family:'Syne',sans-serif;font-size:17px}
.confirm .t small{display:block;color:var(--stone);margin-top:2px}

/* ===== PESSOAS ===== */
.people{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px}
.person{padding:22px;display:flex;flex-direction:column;gap:12px}
.person .top{display:flex;gap:14px;align-items:center}
.person .top b{font-family:'Syne',sans-serif;font-size:16px;display:block}
.person .top small{color:var(--stone);font-size:13px}
.person .bio{font-size:13.5px;color:#4b4740}
.person .lbl{font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--stone);margin-bottom:5px}
.person .foot{display:flex;gap:8px;margin-top:auto;padding-top:6px}

/* ===== ENCONTROS ===== */
.enc{display:flex;flex-direction:column;position:relative;padding-left:26px}
.enc::before{content:"";position:absolute;left:7px;top:8px;bottom:8px;width:2px;background:var(--sand)}
.enc-item{position:relative;padding:0 0 20px}
.enc-item::before{content:"";position:absolute;left:-25px;top:6px;width:12px;height:12px;border-radius:50%;background:var(--card);border:3px solid var(--sand)}
.enc-item.next::before{background:var(--orange);border-color:var(--orange)}
.enc-item .card{padding:18px 22px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}
.enc-item .d{flex:1;min-width:200px}
.enc-item .d b{font-family:'Syne',sans-serif;font-size:16px;display:block}
.enc-item .d small{color:var(--stone)}
.enc-item.next .card{border-color:var(--orange)}
.datebox{width:46px;height:50px;border-radius:12px;background:var(--paper);border:1px solid var(--sand);display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0}
.datebox b{font-family:'Syne',sans-serif;font-size:17px;line-height:1}
.datebox small{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--stone)}

/* ===== MENTOR ===== */
.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
@media(max-width:680px){.kpis{grid-template-columns:1fr}}
.kpi{padding:20px 22px}
.kpi b{font-family:'Syne',sans-serif;font-size:32px;display:block;line-height:1}
.kpi small{color:var(--stone)}
.kpi.alerta{border-color:#FFD2BC;background:var(--orange-soft)}
.kpi.alerta b{color:var(--orange)}
.match{padding:18px 22px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:12px}
.match .duo{display:flex;align-items:center}
.match .duo .avatar:last-child{margin-left:-12px;border:2px solid var(--card)}
.match .d{flex:1;min-width:220px;font-size:14px}
.match .d em{font-style:normal;color:var(--orange);font-weight:700}
.dossie-wrap{display:grid;grid-template-columns:280px 1fr;gap:18px}
@media(max-width:860px){.dossie-wrap{grid-template-columns:1fr}}
.dlist{padding:10px;display:flex;flex-direction:column;gap:4px;align-self:start}
.dlist a{display:flex;align-items:center;gap:12px;padding:12px;border-radius:12px;text-align:left;width:100%}
.dlist a:hover{background:var(--paper)}
.dlist a.on{background:var(--orange-soft)}
.dlist .d b{display:block;font-size:14px}
.dlist .d small{color:var(--stone);font-size:12px}
.dossie{padding:26px}
.dossie .head{display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin-bottom:6px}
.dossie .head h3{font-size:22px}
.dossie .head small{color:var(--stone);display:block}
.dossie .compromisso{margin:18px 0;padding:16px 20px;background:var(--black);color:#fff;border-radius:14px;font-size:14px}
.dossie .compromisso b{color:var(--orange)}
.fio{position:relative;margin-top:24px;padding-left:28px}
.fio::before{content:"";position:absolute;left:8px;top:6px;bottom:6px;width:3px;background:var(--orange);border-radius:2px}
.no{position:relative;padding-bottom:24px}
.no::before{content:"";position:absolute;left:-26px;top:5px;width:13px;height:13px;border-radius:50%;background:var(--orange);box-shadow:0 0 0 4px var(--card)}
.no small{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--stone)}
.no b{display:block;font-family:'Syne',sans-serif;font-size:15px;margin:3px 0 4px}
.no p{font-size:14px;color:#4b4740}
.nota-add{margin-top:8px;display:flex;gap:10px}
.inp{padding:13px 16px;border-radius:12px;border:1px solid var(--sand);font-size:14px;background:var(--paper);width:100%}
.inp:focus{outline:2px solid var(--orange);border-color:transparent}
.nota-add .inp{border-radius:99px;flex:1}
.disp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
.disp{padding:16px;text-align:left}
.disp b{font-family:'Syne',sans-serif;display:block;font-size:15px}
.disp small{color:var(--stone);font-size:12.5px}
.disp .sw{margin-top:12px;width:44px;height:24px;border-radius:99px;background:var(--sand);position:relative;transition:.2s}
.disp .sw::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:.2s}
.disp.aberto .sw{background:var(--orange)}
.disp.aberto .sw::after{left:23px}
.disp.aberto{border-color:var(--orange)}
.pub-form{padding:22px;margin-bottom:22px}
.pub-form .row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}
@media(max-width:640px){.pub-form .row{grid-template-columns:1fr}}

/* ===== LOGIN ===== */
.login-ov{position:fixed;inset:0;z-index:200;background:var(--black);display:flex;align-items:center;justify-content:center;padding:20px}
.login-box{width:100%;max-width:430px;text-align:center;color:#fff}
.login-box .lw{font-family:'Syne',sans-serif;font-weight:800;font-size:28px;margin-bottom:4px}
.login-box .lw span{color:var(--orange)}
.login-box .frase{color:#8f8a82;font-size:14px;margin-bottom:32px}
.login-card{background:#141416;border:1px solid #26262a;border-radius:20px;padding:30px;text-align:left}
.login-card label{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#8f8a82;display:block;margin-bottom:8px}
.login-card .inp{background:#0B0B0C;border:1px solid #2c2c30;color:#fff;margin-bottom:12px}
.login-card .inp::placeholder{color:#55555c}
.login-hint{margin-top:18px;font-size:12.5px;color:#8f8a82;text-align:center;line-height:1.6}
.login-hint b{color:var(--orange)}
/* ===== WATERMARK ===== */
.wm{position:absolute;z-index:5;font-size:11px;color:rgba(255,255,255,.45);pointer-events:none;display:none;letter-spacing:.06em;white-space:nowrap}
.player.playing .wm{display:block;animation:wmove 16s linear infinite alternate}
@keyframes wmove{0%{top:12%;left:8%}25%{top:70%;left:58%}50%{top:30%;left:72%}75%{top:78%;left:12%}100%{top:16%;left:44%}}
@media(prefers-reduced-motion:reduce){.player.playing .wm{animation:none;top:12%;left:8%}}
/* ===== NPS ===== */
.nps-ov{position:fixed;inset:0;z-index:150;background:rgba(11,11,12,.55);display:none;align-items:flex-end;justify-content:center;padding:18px}
.nps-ov.on{display:flex}
.nps-card{background:var(--card);border-radius:22px 22px 18px 18px;padding:26px;max-width:470px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.35)}
.nps-card h3{font-size:18px;margin-bottom:4px}
.nps-card>p{color:var(--stone);font-size:13.5px;margin-bottom:16px}
.nps-nums{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
.nps-nums button{width:35px;height:38px;border-radius:10px;border:1px solid var(--sand);font-weight:700;background:var(--card)}
.nps-nums button:hover,.nps-nums button.on{background:var(--orange);border-color:var(--orange);color:#fff}
.nps-foot{display:flex;gap:10px;align-items:center}
.nps-foot .inp{border-radius:99px;flex:1}
/* ===== COFRE ===== */
.doc-row{display:flex;align-items:center;gap:14px;padding:16px 20px;border-top:1px solid var(--sand)}
.doc-row:first-of-type{border-top:none}
.doc-ic{width:42px;height:46px;border-radius:10px;background:var(--paper);border:1px solid var(--sand);display:flex;align-items:center;justify-content:center;font-size:10.5px;font-weight:700;color:var(--orange);flex-shrink:0}
.doc-row .d{flex:1;min-width:0}
.doc-row .d b{display:block;font-size:14px}
.doc-row .d small{color:var(--stone);font-size:12.5px}
.novo-pill{background:var(--orange);color:#fff;font-size:10px;font-weight:700;letter-spacing:.08em;padding:4px 9px;border-radius:99px;text-transform:uppercase}
.cofre-note{display:flex;gap:12px;align-items:center;padding:14px 18px;background:var(--orange-soft);border:1px solid #FFD2BC;border-radius:14px;margin-bottom:18px;font-size:13.5px;color:#7a3a14}
/* ===== PERFIL ===== */
.topav{width:38px;height:38px;font-size:13px;border:2px solid var(--sand);background-size:cover;background-position:center;transition:.15s}
.topav:hover{border-color:var(--orange)}
.modal-ov{position:fixed;inset:0;z-index:160;background:rgba(11,11,12,.55);display:none;align-items:center;justify-content:center;padding:18px}
.modal-ov.on{display:flex}
.modal-card{background:var(--card);border-radius:22px;padding:28px;max-width:420px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.35)}
.modal-card h3{font-size:19px;margin-bottom:4px}
.modal-card>p{color:var(--stone);font-size:13.5px;margin-bottom:18px}
.foto-pick{display:flex;align-items:center;gap:16px;margin-bottom:16px}
.foto-prev{width:76px;height:76px;border-radius:50%;background:var(--black);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-weight:800;font-size:26px;background-size:cover;background-position:center;flex-shrink:0;border:3px solid var(--sand)}
.modal-card label{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--stone);display:block;margin-bottom:7px}
.start-tag{font-family:'Syne',sans-serif;font-style:normal;font-weight:800;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#fff;background:var(--orange);border-radius:99px;padding:3px 9px;vertical-align:middle;margin-left:6px}
.rodape{margin-top:56px;padding-top:20px;border-top:1px solid var(--sand);display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;color:var(--stone);font-size:13px}
.rodape b{color:var(--black);font-family:'Syne',sans-serif}
</style>
