# Encontros ao vivo (issue #18) — página real /membros/encontros

Recria, no app real, a página `/prototype/encontros` (referência: `resources/views/prototype/encontros.blade.php`
+ `config/prototype.php`'s chave `encontros`), como um calendário de eventos ao vivo de verdade,
exclusivo do plano CLUB (+ mentor). Fecha a issue #18. A gravação de cada encontro, depois de subir,
vira uma `Lesson` normal (category `Encontros`) e cai na Biblioteca de aulas — não cria nenhum
mecanismo novo pra isso, só um link de volta.

## 1. Contexto

Confirmado com o usuário:

- **Rota inteira exclusiva CLUB.** No nav real (`App\Support\PersonaNavigation`), "Encontros" só existe
  na lista do tier `club` — nem aparece (nem trancado) na lista do `start`. Diferente de Aulas/Frameworks,
  que são rotas abertas com gate por conteúdo, `/membros/encontros` é bloqueada na própria rota com o
  middleware `tier:club` (já existe, cobre `hasClubAccess()` — club + mentor).
- **Link de acesso real (`access_url`) + botão "Entrar".** O protótipo só mostra um selo "Próximo"/"Ao
  vivo" no encontro mais próximo, sem nenhum jeito de efetivamente entrar na chamada — lacuna real do
  protótipo, não uma omissão intencional. O app real ganha um campo de link (Zoom/Meet) e um botão de
  verdade.
- **Gravação linkada por `lesson_id`, igual ao Framework "Ver aula".** Mesmo padrão de vínculo opcional
  a uma `Lesson` real usado no Framework — sem string livre, sem duplicar o vídeo.

## 2. Modelo de dados

Migration `create_encontros_table`:

```
encontros
  id
  tema                 string    -- "Precificação sem medo"
  quem                 string    -- "Com Douglas" ou "Convidada: Marina Prado"
  scheduled_at          datetime -- data/hora real do encontro
  access_url           string nullable   -- link Zoom/Meet/etc
  recording_lesson_id  FK -> lessons, nullable, nullOnDelete
  timestamps
```

Na migration, a FK precisa nomear a tabela explicitamente —
`$table->foreignId('recording_lesson_id')->nullable()->constrained('lessons')->nullOnDelete()` —
porque `constrained()` sem argumento inferiria a tabela `recording_lessons` a partir do nome da coluna.

`Encontro` (model): `$fillable` = todos os campos acima exceto `id`/timestamps.
`$casts`: `scheduled_at => 'datetime'`.
`isPast(): bool` — `$this->scheduled_at->isPast()`.
`lesson(): BelongsTo` — `$this->belongsTo(Lesson::class, 'recording_lesson_id')` — a FK custom precisa
ser explícita, porque pelo nome do método o Eloquent inferiria `lesson_id`, que não existe nesta tabela.

Se a `Lesson` vinculada for apagada, `recording_lesson_id` vira `null` (`nullOnDelete`) em vez de apagar
o encontro — o registro do evento continua existindo mesmo sem gravação linkada.

Sem campo de "status" armazenado (diferente do protótipo, que tinha `next`/`fut`/`past` fixos no seed):
passado/futuro é sempre calculado a partir de `scheduled_at` comparado a `now()`, pra nunca desincronizar
por falta de alguém atualizar manualmente um campo.

## 3. Rota

```php
Route::get('membros/encontros', Encontros::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.encontros');
```

`tier:club` (via `EnsureTier`) redireciona pro dashboard com uma mensagem se o usuário não tiver
`hasClubAccess()` — mesmo comportamento já usado em `mentor.placeholder` com `tier:mentor`. Nenhuma
outra rota nova: `access_url` é um link externo direto (`target="_blank" rel="noopener"`), sem
controller/streaming no meio — diferente do PDF de Framework, aqui não há arquivo pra proteger.

## 4. Página `/membros/encontros`

### `App\Livewire\Membros\Encontros`

```php
class Encontros extends Component
{
    use ComputesUserInitials;

    #[Computed]
    public function encontros()
    {
        $upcoming = Encontro::query()->with('lesson')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->get();

        $past = Encontro::query()->with('lesson')
            ->where('scheduled_at', '<', now())
            ->orderByDesc('scheduled_at')
            ->get();

        return $upcoming->concat($past);
    }

    public function render()
    {
        return view('livewire.membros.encontros');
    }
}
```

Duas queries simples (sem `orderByRaw`) concatenadas: futuros em ordem crescente (o mais próximo
primeiro), depois passados em ordem decrescente (o mais recente primeiro) — mesma lógica de "linha do
tempo" do protótipo, só que calculada de verdade em vez de depender de um campo `status` fixo no seed.

### View (`resources/views/livewire/membros/encontros.blade.php`)

1. `x-membros.header`
2. Cabeçalho: "Encontros do grupo" + subtítulo "Aulas ao vivo com o Douglas e convidados. As gravações
   vão direto para a biblioteca." (copiado do protótipo, ainda verdadeiro no app real)
3. Lista (`<x-encontro-card>` por `Encontro`), com estado vazio ("Nenhum encontro agendado ainda.")
   seguindo o mesmo padrão `@forelse` já usado em Aulas/Frameworks.

### `<x-encontro-card :encontro="..." :is-next="...">`

O componente recebe também se este é o item futuro mais próximo (calculado uma vez na view, comparando
com o primeiro elemento não-passado da lista, não recalculado card a card).

Estrutura/CSS: mesmo padrão de card já estabelecido (`bg-card border-sand rounded shadow`, classes
Tailwind, sem CSS custom novo) — não precisa portar `.enc`/`.datebox` do protótipo linha a linha, só o
essencial visual (caixa de data com dia/mês, título, quem/hora).

- Caixa de data: dia + mês abreviado em pt-BR, extraídos de `scheduled_at` (`->format('d')` /
  `->translatedFormat('M')`).
- `tema` (título) + `quem` · hora (`scheduled_at->format('H\hi')`).
- Se **passado**:
  - `recording_lesson_id` setado e a `Lesson` ainda existe → "Ver na biblioteca", deep-link real pra
    `route('membros.aulas', ['lesson' => $encontro->recording_lesson_id])` — mesmo padrão do "Ver aula"
    do Framework.
  - senão → span desabilitado "Gravação em breve" (mesmo padrão do "PDF em breve"/"Exclusivo CLUB" já
    usado em Frameworks).
- Se **futuro**:
  - `access_url` setado → botão "Entrar", `<a href="{{ $encontro->access_url }}" target="_blank"
    rel="noopener">` — link direto, sem controller.
  - senão → span desabilitado "Link em breve".
  - Se for o mais próximo (`isNext`), ganha destaque visual (badge "Próximo") além do botão — os demais
    futuros só mostram o botão "Entrar" (ou "Link em breve"), sem o badge.

Nenhum botão "Avaliar"/NPS nesta versão — fica pra quando a issue #19 (NPS pós-encontro, bloqueada por
esta) for implementada.

## 5. Navegação

`PersonaNavigation::tabs()`: o item `Encontros` (`route: 'membros.encontros'`, hoje só na lista `club`)
vira `available: true`. Nenhuma mudança na lista `start` (o item não existe lá) nem na `mentor`.

## 6. Admin (Filament)

`App\Filament\Resources\Encontros\EncontroResource`, espelhando 1:1 a estrutura de
`App\Filament\Resources\Frameworks\FrameworkResource` (mesmos 3 arquivos de Pages, um Schema, uma
Table):

- **Form**: `TextInput` tema/quem, `DateTimePicker` scheduled_at, `TextInput` access_url (`->url()`,
  nullable), `Select` recording_lesson_id (`relationship('lesson', 'title')`, nullable/searchable).
- **Table**: colunas tema/quem/scheduled_at/lesson.title (placeholder '—'), `defaultSort('scheduled_at',
  'desc')`, `EditAction`/`DeleteAction`, sem filtro dedicado (poucos registros esperados, igual
  Frameworks).

## 7. Testes

- `Tests\Unit\EncontroTest`: `isPast()` true/false, `lesson()` resolve o relacionamento,
  `recording_lesson_id` vira `null` quando a `Lesson` vinculada é apagada.
- `Tests\Feature\Livewire\Membros\EncontrosTest`: guest redireciona pro login; membro `start` é
  redirecionado pro dashboard (tier:club bloqueia); membro `club` e `mentor` acessam normalmente;
  ordenação (futuros ascendente, passados descendente, futuros antes de passados); "Ver na biblioteca"
  só aparece com gravação linkada e existente, senão "Gravação em breve"; "Entrar" só aparece com
  `access_url` setado, senão "Link em breve"; badge "Próximo" só no primeiro futuro, não nos demais;
  estado vazio sem encontros cadastrados.
- `Tests\Feature\Admin\EncontroResourceTest`: espelha `FrameworkResourceTest` (non-admin 403; listar;
  criar; criar com gravação vinculada; editar; deletar).
- `Tests\Unit\Support\PersonaNavigationTest` / `Tests\Feature\Membros\PersonaNavigationTest`: mesma
  atualização de `available:false→true` já feita pra Aulas/Frameworks, agora pra Encontros (só na lista
  `club`).

## 8. Fora de escopo

- NPS pós-encontro — issue #19, depende desta.
- Detecção de "ao vivo agora" em tempo real (ex.: badge que muda sozinho quando o horário chega/passa
  durante a sessão do navegador) — só passado/futuro calculados no load da página.
- Encontros recorrentes/série — cada encontro é um registro isolado, sem repetição automática.
- RSVP/lista de presença — não há confirmação de presença nem lotação máxima.
- Fuso horário por usuário — `scheduled_at` é exibido no fuso da aplicação, sem conversão por membro.
