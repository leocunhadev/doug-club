# Spec — Rebranding da LMS para DO.ing Club / Douglas Oliveira

## Contexto

A área de membros foi construída a partir de um template/clone cujo copy ainda referencia
o produto original: "Flávio Augusto", "Estabilidade Não Existe" e "Geração de Valor LTDA"
(rodapé do dashboard, header, `APP_NAME`). O cliente real é Douglas Oliveira, do
DO.ing Club (https://www.doingclub.com/).

O site doingclub.com **não tem um arquivo de logo**: a marca é um wordmark de texto
"DO.ing Club" (o ponto em laranja) acompanhado de um ícone SVG de círculos concêntricos
que o próprio código-fonte do site marca como placeholder
(`<!-- PLACEHOLDER: trocar pelo SVG oficial -->`). As cores extraídas do CSS do site:

```
--orange:  #FF5100   (cor de destaque / marca)
--base:    #100B09   (fundo principal)
--panel:   #1A120E   (fundo de cards/paineis)
--panel-2: #241813   (fundo de paineis secundários)
--cream:   #F4EDE4   (texto principal)
--cream-dim: #A99B8C (texto secundário)
```

A bio pública do Douglas (extraída da landing page): 22 anos de mercado, 16 anos em
varejo/shopping centers, 500+ empreendedores atendidos, 10.000+ horas entre palestras e
aulas, frase de efeito "A visão do dono do negócio é o que determina se a empresa avança
ou estagna".

## Objetivo

1. Substituir o logo padrão do Jetstream por um wordmark/ícone no estilo DO.ing Club.
2. Alinhar a paleta de cores da LMS aos valores exatos do site.
3. Trocar todo o copy que referencia "Flávio Augusto" / "Estabilidade Não Existe" /
   "Geração de Valor LTDA" por Douglas Oliveira / DO.ing Club.
4. Adicionar uma página "Sobre" com a bio do Douglas, linkada no rodapé.

Fora do escopo: títulos de aulas/cursos em `database/seeders/LmsSeeder.php` — são dados
de demonstração local, não copy do produto.

## Design

### 1. Componente de logo

Novo `resources/views/components/brand-logo.blade.php`:
- Ícone SVG (`viewBox="0 0 40 40"`) com 4 círculos concêntricos em `currentColor`/laranja,
  reproduzindo o ícone-placeholder do site.
- Wordmark: `DO` + `.` (em laranja) + `ing` + ` Club` (opacidade reduzida), fonte padrão
  do projeto (Figtree — não estamos trocando tipografia, só cor).
- Prop `:icon-only="true"` para renderizar somente o ícone (usado na marca d'água do
  player, onde não há espaço pro texto).
- Substitui `<x-application-logo>` em:
  - `resources/views/components/membros/header.blade.php`
  - `resources/views/layouts/guest.blade.php` (login/registro)
  - `resources/views/livewire/membros/dashboard.blade.php` (marca d'água do vídeo, `icon-only`)
- `application-logo.blade.php` (artwork padrão do Jetstream) permanece no repo sem uso —
  não será deletado neste escopo, só desreferenciado.

### 2. Cores

Em `tailwind.config.js`, dentro de `theme.extend.colors`:
- `canvas`: `'#0a0a0b'` → `'#100B09'`
- `surface`: `'#12141a'` → `'#1A120E'`
- novo `'surface-2': '#241813'`
- novo `brand: '#FF5100'`

Trocar classes que representam acento de marca (não erro/perigo) de `orange-500` /
`from-orange-500 to-red-600` para `brand` / `from-brand to-brand` nos arquivos:
- `resources/views/components/membros/header.blade.php`
- `resources/views/livewire/membros/dashboard.blade.php`
- `resources/views/components/lesson-card.blade.php`
- `resources/views/components/lesson-card-simple.blade.php`

`resources/views/components/danger-button.blade.php` e
`resources/views/components/input-error.blade.php` usam vermelho para estado de erro —
não são tocados.

### 3. Textos

- `.env`: `APP_NAME=Laravel` → `APP_NAME="DO.ing Club"`
- `resources/views/components/membros/header.blade.php`: remove o bloco de texto
  "Estabilidade" / "Não existe" ao lado do logo (substituído pelo wordmark do novo
  componente).
- `resources/views/livewire/membros/dashboard.blade.php`:
  - "Acompanhe as transmissões ao vivo e os conteúdos gravados de Flávio Augusto." →
    "...de Douglas Oliveira."
  - Rodapé: "Estabilidade Não Existe · Uma iniciativa de Flávio Augusto, operada pela
    Geração de Valor LTDA · © {ano} Todos os direitos reservados." →
    "© DO.ing Club · {ano} Todos os direitos reservados."
  - Link "Sobre" (hoje `href="#"`) passa a apontar para `route('membros.sobre')`.
- `app/Providers/Filament/AdminPanelProvider.php`: adiciona `->brandName('DO.ing Club')`
  e troca `Color::Amber` pela cor de marca (`Color::hex('#FF5100')` ou equivalente já
  aceito pela API do Filament).

### 4. Página "Sobre"

- Nova rota em `routes/web.php`, seguindo o padrão da rota `profile` (view estática):
  `Route::view('membros/sobre', 'membros.sobre')->middleware(['auth','verified'])->name('membros.sobre');`
- Nova view `resources/views/membros/sobre.blade.php`: reusa `<x-membros.header>`,
  título "Sobre Douglas Oliveira", parágrafo de bio (texto confirmado acima) e citação em
  destaque.
- Extrai o rodapé (hoje inline em `dashboard.blade.php`) para
  `resources/views/components/membros/footer.blade.php`, reutilizado por `dashboard` e
  `sobre` — evita duplicar o markup agora que duas páginas o usam.

## Testes

- Ajustar/checar `tests/Feature/Livewire/Membros/DashboardTest.php` e
  `tests/Feature/Auth/AuthenticationTest.php` caso façam asserção sobre o texto antigo
  ("Flávio Augusto", "Estabilidade", etc.) — grep encontrou essas strings nesses arquivos
  como parte de fixtures/seed, então precisam ser revisados.
- Teste simples de que a rota `membros.sobre` responde 200 para usuário autenticado e
  redireciona para login se não autenticado (mesmo padrão de `dashboard`).

## Fora do escopo (confirmado com o usuário)

- Não trocar a razão social no rodapé por outra empresa — ficará só "© DO.ing Club".
- Não reescrever títulos de aula do seeder.
- Não trocar a fonte (Figtree permanece).
