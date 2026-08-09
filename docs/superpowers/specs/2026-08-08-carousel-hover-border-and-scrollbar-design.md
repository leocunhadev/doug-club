# Spec — Carrossel de aulas: borda de hover cortada e scrollbar visível

## Contexto

O carrossel horizontal de aulas na área de membros (`resources/views/livewire/membros/dashboard.blade.php`)
tem dois defeitos visuais reportados após a última tentativa de correção da borda de hover
dos cards (commit "Restructure lesson cards so the hover border isn't clipped at the
corners"): essa correção resolveu o recorte causado pelo `overflow-hidden` do próprio
card, mas **não resolveu o problema real**, que está um nível acima, no container do
carrossel.

## Diagnóstico (root cause)

O track do carrossel:

```html
<div x-ref="track" class="mt-4 flex gap-4 overflow-x-auto pb-2 scroll-smooth snap-x">
```

Tem `overflow-x: auto` e não define `overflow-y`. Pela [CSS Overflow spec](https://www.w3.org/TR/css-overflow-3/#overflow-properties):
se um eixo é `auto`/`scroll`/`hidden` e o outro fica no valor inicial `visible`, o
`visible` é computado como `auto`. Ou seja, o navegador força `overflow-y: auto` no
track, mesmo que isso nunca tenha sido declarado explicitamente — o track passa a
recortar verticalmente qualquer conteúdo que ultrapasse sua caixa.

Cada card tem `hover:scale-[1.02]`, que cresce a caixa visual do card ~1% em todas as
direções a partir do centro no hover. O track só tem `pb-2` (padding embaixo, 8px) e
nenhum padding em cima. Resultado: ao herdar hover, o topo do card (incluindo o
`ring-1 ring-inset hover:ring-brand` que faz a borda) ultrapassa a borda superior da
caixa do track e é cortado pelo `overflow-y: auto` implícito.

**Reproduzido e confirmado:** isolando o track em um harness HTML mínimo com o CSS
compilado real do projeto — com `pb-2` (sem padding no topo) a borda do card em estado
de hover corta em linha reta no topo; trocando para `py-2` (padding simétrico
top+bottom), a borda passa a contornar o card por completo, sem cortes.

Separadamente, o track também expõe a **scrollbar nativa do navegador**, já que
`overflow-x-auto` por padrão desenha a scrollbar do SO. O carrossel já tem botões de
seta (`canScrollLeft`/`canScrollRight`, Alpine) como mecanismo de navegação, então essa
scrollbar nativa é ruído visual — deve continuar funcionando o scroll (arraste,
scroll do mouse/trackpad, os botões), só a barra visível deve sumir.

## Objetivo

1. A borda de hover (`ring-brand`) deve contornar o card inteiro, sem cortes, em
   qualquer posição do carrossel (incluindo o primeiro e o último card da fileira).
2. A scrollbar nativa do track não deve aparecer visualmente, mas o scroll (arraste,
   roda do mouse, trackpad, botões de seta) continua funcionando normalmente.

## Decisão de design

### 1. Borda cortada — remover o `hover:scale-[1.02]`

Duas opções foram consideradas:

- **(A) Aumentar o padding do track** (`pb-2` → `py-2` ou mais) para abrir espaço
  suficiente para o crescimento do `scale`.
- **(B) Remover o `hover:scale-[1.02]`**, mantendo só `hover:ring-brand` +
  `hover:brightness-110` como feedback de hover.

Escolha: **(B)**. Razões:
- (A) resolve o sintoma mas deixa uma dependência frágil: qualquer ajuste futuro no
  valor do `scale`, no tamanho dos cards, ou em zoom/fontes do navegador do usuário
  pode reabrir o mesmo corte — o padding é uma calibragem manual em cima de um
  comportamento de layout que não é controlado por quem escreve o CSS do card.
- (B) remove a causa raiz do estouro de caixa: sem crescimento de tamanho no hover,
  não há mais nada para o `overflow-y: auto` implícito do track cortar. A borda
  (`ring`) e o brilho (`brightness-110`) já são feedback de hover suficiente e visível;
  o efeito de escala era um detalhe estético, não essencial.
- Elimina de vez a classe de bug "conteúdo que cresce dentro de um container com
  scroll" para este componente, em vez de administrar o respiro necessário.

Aplica-se a `lesson-card.blade.php` e `lesson-card-simple.blade.php` (mesma classe
`hover:scale-[1.02]` nos dois).

### 2. Scrollbar visível — ocultar via utilitário CSS

O projeto já usa Tailwind CSS v3 (`tailwind.config.js`, plugin `@tailwindcss/forms`).
Não há plugin de scrollbar instalado. Em vez de adicionar uma dependência nova para
uma única classe, a solução é uma classe utilitária pequena definida diretamente no
`tailwind.config.js` via `plugins: [plugin(({ addUtilities }) => ...)]`, ou
equivalentemente uma regra CSS simples em `resources/css/app.css`:

```css
.scrollbar-none {
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge legado */
}
.scrollbar-none::-webkit-scrollbar {
    display: none; /* Chrome/Safari/Edge Chromium */
}
```

Decisão: adicionar essa regra em `resources/css/app.css` (o projeto já centraliza CSS
customizado ali, não há necessidade de um plugin Tailwind para 5 linhas de CSS puro) e
aplicar a classe `scrollbar-none` no `x-ref="track"`. Scroll continua funcional
(`overflow-x-auto` inalterado) — só a barra deixa de ser desenhada.

## Arquivos afetados

- `resources/views/components/lesson-card.blade.php` — remove `hover:scale-[1.02]` da
  classe do `<button>`.
- `resources/views/components/lesson-card-simple.blade.php` — idem.
- `resources/views/livewire/membros/dashboard.blade.php` — adiciona `scrollbar-none` à
  classe do `x-ref="track"`.
- `resources/css/app.css` — adiciona a regra `.scrollbar-none`.

## Testes

- Teste de feature existente (`DashboardTest`) não cobre CSS/hover (Livewire test não
  executa `:hover` real), então não há assertion nova de PHPUnit possível para isso.
  A verificação é visual: screenshot antes/depois do estado de hover simulado
  (classe aplicada diretamente, sem `:hover`, como já feito durante o diagnóstico)
  confirmando que a borda contorna o card por completo em todas as posições da
  fileira (primeiro card, card do meio, último card visível).
- Confirmar visualmente que a scrollbar nativa não aparece mais no Chrome (`::-webkit-scrollbar`)
  nem — se houver acesso pra testar — no Firefox (`scrollbar-width: none`).

## Fora do escopo

- Não mexe nos botões de seta (`canScrollLeft`/`canScrollRight`) nem na lógica Alpine
  de scroll — só a aparência da scrollbar nativa do SO.
- Não introduz nenhum plugin novo de dependência (scrollbar-hide, etc.) — a regra CSS
  é pequena o bastante para viver em `app.css`.
