# Personas/planos (Start/CLUB/Mentor) — tier no User, navegação e gating base

Item #1 de `docs/lista-spec.md` ("base de tudo"). Introduz o conceito de tier no usuário e a casca
de navegação por persona que as próximas specs (`#2` biblioteca de aulas, `#6`-`#10` agenda/pessoas/
cofre/painel do mentor, `#11` upgrade) vão preencher. Referência funcional: `doingclub.html` (protótipo
estático fornecido pelo usuário) — usado como inspiração de estrutura/copy, não como implementação.

## 1. Contexto

O app hoje (`docs/lms-spec.md`) é um LMS de tier único: qualquer usuário autenticado e ativo
(`access_revoked_at IS NULL`) vê o mesmo dashboard (`/membros`), sem noção de plano. O protótipo
`doingclub.html` descreve três personas com navegação e conteúdo distintos:

- **Start** — acesso à biblioteca de aulas e frameworks; sem mentoria 1:1.
- **CLUB** — tudo do Start + sessão 1:1, cofre, agenda, pessoas do CLUB, encontros ao vivo.
- **Mentor** — painel de gestão (radar do dia, dossiês de mentorados, publicar conteúdo,
  disponibilidade). Papel administrativo, não um produto comprado por membros.

Confirmado com o usuário: CLUB é hierárquico sobre Start (`CLUB ⊇ Start`); Mentor é um papel
atribuído manualmente (seed/tinker/admin), fora do funil de compra. A automação do webhook de
pagamento (AbacatePay) para definir tier automaticamente **fica fora desta spec** — hoje o payload
não carrega produto/plano, e mapear isso é decisão de negócio separada. Só existem 2 usuários no
banco hoje (nenhum ativo), então não há risco de re-tierizar membros reais.

## 2. Modelo de dados

Nova coluna em `users`:

```
tier   enum('start','club','mentor')   default 'start'   not null
```

Migration `add_tier_to_users_table`. `User::casts()` não precisa de cast especial (enum de string
já é comparável diretamente); `tier` entra no `#[Fillable]` do model.

`User` ganha um helper de nível de acesso, para não espalhar comparações de string pelo código:

```php
public function hasClubAccess(): bool
{
    return in_array($this->tier, ['club', 'mentor'], true);
}

public function isMentor(): bool
{
    return $this->tier === 'mentor';
}
```

`hasClubAccess()` é a pergunta que a spec `#2` (gating de aula por tier) e as demais vão reusar.
`is_admin` (acesso ao Filament) continua um conceito totalmente separado de `tier` — um mentor não é
automaticamente admin do Filament, e vice-versa.

## 3. Gating por rota

Middleware novo `App\Http\Middleware\EnsureTier`, registrado com alias `tier` em `bootstrap/app.php`
(mesmo padrão do alias `active` já existente):

```php
Route::get('membros/mentor', MentorPlaceholder::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.placeholder');
```

`EnsureTier::handle($request, $next, string $minTier)` resolve `$minTier` via a mesma lógica de
`hasClubAccess()`/`isMentor()` (não uma ordem numérica genérica — só existem essas duas checagens
reais hoje) e, se o usuário não atender, redireciona para `/membros` com
`session('status', 'Esse conteúdo está disponível no <tier>.')`, renderizado como toast (reaproveita
o padrão de flash message que `EnsureAccessIsActive` já usa).

Nesta spec, a única rota que efetivamente usa o middleware é `tier:mentor` (`/membros/mentor`) —
`tier:club` não protege rota nenhuma ainda, já que `/membros` é compartilhada entre Start e CLUB. O
middleware fica pronto para as specs `#6`-`#10` usarem `tier:club` nas rotas de cofre/agenda/pessoas/
encontros assim que essas rotas existirem.

`/membros` (dashboard atual) continua exigindo só `auth`, `verified`, `active` — Start e CLUB
acessam a mesma rota hoje (gating de *conteúdo* dentro dela é a spec `#2`, não esta).

## 4. Navegação por persona

### Config

`App\Support\PersonaNavigation` (classe simples, sem estado) com um método
`tabs(string $tier): array`, retornando a lista de abas por persona. Cada aba: rótulo, rota nomeada
(ou `null` se ainda não existe) e se está disponível:

```php
'start' => [
    ['label' => 'Início',      'route' => 'dashboard',       'available' => true],
    ['label' => 'Aulas',       'route' => 'membros.aulas',   'available' => false],
    ['label' => 'Frameworks',  'route' => 'membros.frameworks', 'available' => false],
    ['label' => 'Sessão 1:1',  'route' => 'membros.upgrade', 'available' => false],
],
'club' => [
    ['label' => 'Início',      'route' => 'dashboard',        'available' => true],
    ['label' => 'Aulas',       'route' => 'membros.aulas',    'available' => false],
    ['label' => 'Meu cofre',   'route' => 'membros.cofre',    'available' => false],
    ['label' => 'Minha sessão','route' => 'membros.agenda',   'available' => false],
    ['label' => 'Pessoas',     'route' => 'membros.pessoas',  'available' => false],
    ['label' => 'Encontros',   'route' => 'membros.encontros','available' => false],
    ['label' => 'Frameworks',  'route' => 'membros.frameworks','available' => false],
],
'mentor' => [
    ['label' => 'Radar',            'route' => 'mentor.radar',   'available' => false],
    ['label' => 'Dossiês',          'route' => 'mentor.dossies', 'available' => false],
    ['label' => 'Publicar',         'route' => 'mentor.conteudo','available' => false],
    ['label' => 'Disponibilidade',  'route' => 'mentor.disp',    'available' => false],
],
```

`available => false` quando a rota ainda não existe nesta spec — cada spec futura (#2, #6-#10) muda
o respectivo item para `true` e aponta pra rota real, sem tocar nas outras entradas.

### Header/nav (Blade)

`x-membros.header` ganha uma segunda linha, abaixo do logo+avatar, renderizando `PersonaNavigation`
para `auth()->user()->tier`:

- Item `available === true`: `<a>` normal, estado ativo (`aria-current`) quando a rota bate com a
  atual.
- Item `available === false`: `<span>` com opacidade reduzida, ícone de cadeado, `title="Em breve"`,
  sem `href` (não navegável, não é link morto/quebrado).

### Rota placeholder do Mentor

`/membros/mentor` (`App\Livewire\Membros\MentorPlaceholder`, componente novo e mínimo — sem
sub-lógica): header + nav (as 4 abas, todas trancadas) + um card central "Seu painel de mentor está
sendo construído. Volta em breve." Middleware `tier:mentor`. É o destino de login/redirect para
usuários `tier = mentor` enquanto os itens #6-#10 não existem — sem essa rota, um mentor logado não
teria nenhuma página válida pra cair (o dashboard `/membros` de aulas não faz sentido pra essa
persona).

### Redirect pós-login

`routes/web.php`, a rota `/` (e o redirect pós-login do Breeze/Livewire) passam a considerar tier:
`tier === 'mentor'` → `route('mentor.placeholder')`; `start`/`club` → `route('dashboard')` (mantém o
comportamento atual).

## 5. Home por persona

Dentro de `/membros` (dashboard), a única variação é o texto de abertura, condicionado a
`auth()->user()->tier` (sem novo componente Livewire, é lógica de view igual ao resto de
`dashboard.blade.php`):

- **Start**: `"Sua central de conteúdos"` / texto sem menção a acompanhamento com o Douglas.
- **CLUB**: mantém o texto atual ("Acompanhe as transmissões ao vivo e os conteúdos gravados de
  Douglas Oliveira...").

O protótipo (`doingclub.html`) também mostra, na home do CLUB, um bloco com a última anotação da
sessão de mentoria ("onde paramos"). Esse bloco depende de dado que não existe no banco ainda
(resumo de sessão 1:1 — nasce só nos itens `#7`/`#8`, agenda e painel do mentor), então **não entra
nesta spec** — não há dado real pra exibir, e mockar um texto fixo seria inventar conteúdo falso na
tela. Fica registrado no backlog (seção 8).

Sem mudança de dados/consulta — é condicional de exibição de texto estático sobre `$user->tier`.

## 6. Migração visual (design tokens)

Escopo confirmado: re-skin completo das telas existentes, não só das novas.

### `tailwind.config.js`

```js
colors: {
    paper: '#F6F3EE',
    ink: '#1A1A1C',
    black: '#0B0B0C',
    card: '#FFFFFF',
    sand: '#E6E0D6',
    stone: '#8B857A',
    brand: '#FF5100',        // mantém o nome (já usado em vários lugares), troca o uso visual
    'brand-soft': '#FFEDE4',
},
fontFamily: {
    display: ['Syne', ...defaultTheme.fontFamily.sans],   // títulos
    sans: ['DM Sans', ...defaultTheme.fontFamily.sans],   // corpo (substitui Figtree)
},
```

`canvas`/`surface`/`surface-2` (tokens dark da spec de 2026-08-07) são removidos e todas as
ocorrências trocadas pelos novos tokens claros. Import da fonte no `<head>` (`layouts/membros.blade.php`
e `layouts/guest.blade.php`) troca de Bunny/Figtree para Google Fonts Syne+DM Sans (mesmo `<link>`
usado no protótipo).

### Telas afetadas

`layouts/membros.blade.php`, `layouts/guest.blade.php`, `components/membros/header.blade.php` (+ nav
nova), `components/membros/footer.blade.php`, `livewire/membros/dashboard.blade.php`,
`components/lesson-card.blade.php`, `components/lesson-card-simple.blade.php`,
`livewire/membros/sobre.blade.php`, `livewire/pages/auth/login.blade.php`, `resources/views/profile.blade.php`
(+ os 3 componentes de formulário de profile). Ajuste ponto a ponto de classe (`bg-canvas` →
`bg-paper`, `text-white` → `text-ink`, `bg-surface` → `bg-card` + `border-sand`, etc.), sem redesenhar
layout/estrutura de cada tela — é troca de paleta/tipografia sobre a estrutura que já existe, não uma
reconstrução visual peça a peça do protótipo (cards com marca d'água, player fake, avatares
coloridos etc. pertencem às specs de conteúdo, não a esta).

Filament (`/admin`) **não muda** — é uma superfície separada, sem tokens de marca compartilhados
hoje.

## 7. Testes

- `Tests\Feature\Membros\PersonaNavigationTest` (novo): para cada tier, a home renderiza as abas
  esperadas com `available` correto; abas indisponíveis não têm `href`.
- `Tests\Feature\Membros\TierGatingTest` (novo): `tier:mentor` bloqueia `start`/`club` em
  `/membros/mentor` com redirect; `tier:mentor` libera `mentor`.
- Ajustar `Tests\Feature\Livewire\Membros\DashboardTest` existente se alguma asserção depender de
  classe/copy que o re-skin ou a home por persona alterarem.
- `npm run build` compila sem erro (mesma validação usada na spec de tokens de 2026-08-07).
- Sem teste automatizado para os re-skins ponto a ponto de login/profile/sobre — validação visual
  manual, como já é prática no projeto pra CSS.

## 8. Fora de escopo (backlog)

- Definir tier automaticamente a partir do webhook AbacatePay (`ActivateUserFromPayment`) — precisa
  antes decidir como o payload identifica o produto comprado.
- Conteúdo real de qualquer aba trancada (aulas por tier, frameworks, cofre, agenda, pessoas,
  encontros, radar/dossiês/publicar/disponibilidade do mentor, tela de upgrade Start→CLUB) — itens
  #2 e #6-#11 do `lista-spec.md`, cada um com sua própria spec.
- Bloco "onde paramos" (resumo da última sessão de mentoria) na home do CLUB — depende de dado que
  só existe depois dos itens `#7`/`#8`.
- Troca de persona livre por um seletor (como o protótipo faz pra demonstração) — no app real o tier
  é fixo por usuário, não um toggle.
- Marca d'água dinâmica no player, NPS, upload de foto de perfil — itens #3-#5 do `lista-spec.md`.
