# Relação mentor↔mentorado + Dossiês (issue #32)

Fecha a issue #32 (fatia A da divisão do antigo #21). Recria `/prototype/dossies` (referência:
`resources/views/prototype/dossies.blade.php` + seed `dossies` em `config/prototype.php`) como a
ferramenta real do mentor pra acompanhar cada mentorado: um "fio da mentoria" (histórico de notas) e
um "compromisso ativo" por pessoa. Desbloqueia a issue #33 (Radar do dia), que usa esses dados pro
briefing pré-sessão.

## 1. Contexto

Confirmado com o usuário:

- **Sem tabela de vínculo mentor↔mentorado.** Mesma simplificação já usada em Agenda (#20), Cofre
  (#22) e Pessoas (#23): todo `tier=club` já é mentorado implícito do único mentor. A página lista
  todos os membros CLUB direto — não existe um passo de "matricular" alguém.
- **"Compromisso ativo" é um model separado**, não uma coluna em `users` — mesmo sendo um valor único
  por mentorado (sem histórico), fica isolado do resto do perfil do usuário porque é o mentor quem
  escreve, não o membro (diferente de `company`/`bio`/tags do Pessoas, que o próprio membro edita).
- **Nota do fio tem título curto + texto, dois campos.** O protótipo mostra notas ricas
  (data · sessão — título — parágrafo) mas o formulário interativo dele só tem um campo de texto
  livre; a versão real pede os dois campos (título + texto) porque o valor do fio como ferramenta de
  memória depende de conseguir escanear os títulos rapidamente, não só ler tudo.
- **Sem recurso Filament.** O mentor mexe direto pela própria página Livewire (`/membros/mentor/dossies`),
  mesmo padrão de Disponibilidade/Conteudo — diferente do Cofre, que precisou do Filament pelo upload
  de arquivo.

## 2. Modelo de dados

### 2.1 `mentor_notes` (o fio da mentoria)

Migration `create_mentor_notes_table`:

```
mentor_notes
  id
  member_id     FK -> users, cascadeOnDelete   -- de quem é a nota
  mentor_id     FK -> users, cascadeOnDelete   -- quem escreveu (sempre o mentor logado)
  title         string
  body          text
  timestamps
```

`MentorNote` (model): `$fillable` = `member_id`, `mentor_id`, `title`, `body`. `member(): BelongsTo`,
`mentor(): BelongsTo` (`User::class`, FK explícita nos dois — nem `member_id` nem `mentor_id` batem
com a inferência padrão do Eloquent, mesmo padrão de `VaultDocument`/`MentorSession`).

### 2.2 `mentor_commitments` (compromisso ativo)

Migration `create_mentor_commitments_table`:

```
mentor_commitments
  id
  member_id     FK -> users, cascadeOnDelete, UNIQUE   -- no máximo 1 linha por mentorado
  text          text nullable
  timestamps
```

`MentorCommitment` (model): `$fillable` = `member_id`, `text`. `member(): BelongsTo` (`User::class`,
FK explícita). A unicidade em `member_id` garante que salvar o compromisso de alguém é sempre um
upsert (`updateOrCreate`), nunca acumula histórico — é "o que está valendo agora", igual ao protótipo.

## 3. Página `/membros/mentor/dossies`

```php
Route::get('membros/mentor/dossies', Dossies::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.dossies');
```

### `App\Livewire\Membros\Dossies`

```php
class Dossies extends Component
{
    use ComputesUserInitials;

    public ?int $selectedMemberId = null;

    public string $noteTitle = '';

    public string $noteBody = '';

    public string $commitmentInput = '';

    public function mount(): void
    {
        $this->selectedMemberId = $this->members->first()?->id;
        $this->loadCommitmentInput();
    }

    #[Computed]
    public function members()
    {
        return User::query()
            ->where('tier', 'club')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedMember(): ?User
    {
        return $this->members->firstWhere('id', $this->selectedMemberId);
    }

    #[Computed]
    public function notes()
    {
        return MentorNote::query()
            ->where('member_id', $this->selectedMemberId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function selectMember(int $memberId): void
    {
        if (! $this->members->contains('id', $memberId)) {
            return;
        }

        $this->selectedMemberId = $memberId;
        $this->reset('noteTitle', 'noteBody');
        $this->loadCommitmentInput();
    }

    public function addNote(): void
    {
        $this->validate([
            'noteTitle' => ['required', 'string', 'max:255'],
            'noteBody' => ['required', 'string', 'max:2000'],
        ]);

        MentorNote::create([
            'member_id' => $this->selectedMemberId,
            'mentor_id' => Auth::id(),
            'title' => $this->noteTitle,
            'body' => $this->noteBody,
        ]);

        $this->reset('noteTitle', 'noteBody');
    }

    public function saveCommitment(): void
    {
        $this->validate([
            'commitmentInput' => ['nullable', 'string', 'max:500'],
        ]);

        MentorCommitment::updateOrCreate(
            ['member_id' => $this->selectedMemberId],
            ['text' => trim($this->commitmentInput) ?: null],
        );
    }

    private function loadCommitmentInput(): void
    {
        $this->commitmentInput = MentorCommitment::query()
            ->where('member_id', $this->selectedMemberId)
            ->value('text') ?? '';
    }

    public function render()
    {
        return view('livewire.membros.dossies');
    }
}
```

Notas de implementação:

- `mentor_id` em `MentorNote::create()` é sempre `Auth::id()` diretamente — diferente de
  `VaultDocument`/`ClubApplication`, que resolvem o mentor via `User::where('tier', 'mentor')->first()`
  porque quem está agindo ali é o *membro*. Aqui é o próprio mentor logado agindo na própria página
  (`tier:mentor`), mesmo padrão já usado em `Disponibilidade::addBlock()` (`'mentor_id' => Auth::id()`).
- `selectMember()` valida que o `memberId` recebido é de fato um membro `tier=club` antes de trocar a
  seleção — o parâmetro chega pelo payload do Livewire, que o cliente controla, mesmo raciocínio
  defensivo já aplicado em `Pessoas::requestBridge()`.
- **Sem `unset()` de nenhum `#[Computed]` depois das mutações.** Diferente do bug já encontrado (e
  corrigido com `unset()`) em `Pessoas::requestBridge()` e `Upgrade::apply()` — lá, o método lia o
  `#[Computed]` *antes* de mutar os dados por trás dele (checagem de duplicata), o que esquentava o
  cache com o valor antigo antes do `render()` reler o mesmo computed já stale. Aqui, nenhum método
  (`addNote`, `saveCommitment`, `selectMember`) lê `notes`/`selectedMember` antes de mudar o que eles
  dependem — o primeiro acesso a cada computed em cada request já acontece depois da mutação, então o
  cache nunca esquenta com dado velho. A regra geral, generalizada dos dois casos anteriores: só é
  preciso invalidar manualmente um `#[Computed]` quando o próprio método que muta os dados também já
  leu esse computed antes de mutar — não é um `unset()` obrigatório depois de toda mutação.

### View

1. `x-membros.header`
2. Cabeçalho: "Dossiês" + subtítulo (copiado do protótipo): "A memória viva de cada mentorado. O fio
   laranja é a história de vocês dois."
3. Grid de duas colunas (`.dossie-wrap`):
   - **Coluna esquerda** (`.dlist`, dentro do card Tailwind já padrão): um link por
     `$this->members`, `wire:click="selectMember({{ $member->id }})"`, classe `on` quando
     `$member->id === $this->selectedMemberId`. Avatar 38px (`.avatar`, `.avatar.o` quando
     selecionado — primeiro uso real dessa variante, que ficou de fora do Pessoas por falta de
     critério; aqui o critério é "é o selecionado") + nome + empresa.
   - **Coluna direita** (`.dossie`, dentro do card Tailwind já padrão): se `$this->members` estiver
     vazio, `"Nenhum mentorado ainda."`; senão:
     - Cabeçalho (`.head`): avatar 54px `.avatar.o` + nome + empresa de `$this->selectedMember`.
     - Caixa "Compromisso ativo" (`.compromisso`): diferente do protótipo (que só exibe o texto
       estático), a versão real é editável — rótulo `<b>Compromisso ativo:</b>` (reaproveita
       `.compromisso b`, que no protótipo estilizava o valor; aqui estiliza o rótulo, já que o valor
       vira campo editável) seguido do campo (`wire:model="commitmentInput"`, classe `.inp`,
       placeholder `"Sem compromisso ativo."`) e botão "Salvar" (`wire:click="saveCommitment"`,
       Tailwind `rounded-full bg-brand text-white`, **não** `.btn`/`.btn.solid`) logo abaixo. O
       próprio campo já mostra o valor atual (carregado por `loadCommitmentInput()`) — não existe uma
       exibição estática separada do valor salvo.
     - "O fio da mentoria" (`.eyebrow.laranja` — self-contido, ver seção 5): timeline
       `.fio` de `$this->notes`, cada nota (`.no`) mostra a data (`$note->created_at->format('d/m/Y')`
       — numérico, mesmo formato já usado em toda tabela Filament deste projeto; **não** tenta imitar
       o "18 jun" por extenso do protótipo, porque isso exigiria nomes de mês em português e
       `APP_LOCALE` está em `en` — nenhuma outra tela real deste projeto formata data por extenso),
       título (`b`) e texto (`p`).
     - Formulário de nova nota (`.nota-add`, dois campos empilhados: `wire:model="noteTitle"` e
       `wire:model="noteBody"`, ambos `.inp`) + botão "Guardar" (`wire:click="addNote"`, Tailwind
       `rounded-full bg-black text-white`).

## 4. Navegação

`PersonaNavigation::tabs()`: `Dossiês` (`route: 'mentor.dossies'`, na lista `mentor`) vira
`available: true`.

## 5. CSS

Porta as classes do protótipo pro padrão já estabelecido no projeto:

```css
.dossie-wrap { display: grid; grid-template-columns: 280px 1fr; gap: 18px; }
@media (max-width: 860px) { .dossie-wrap { grid-template-columns: 1fr; } }
.dlist { padding: 10px; display: flex; flex-direction: column; gap: 4px; align-self: start; }
.dlist a { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 12px;
  text-align: left; width: 100%; }
.dlist a:hover { background: theme('colors.paper'); }
.dlist a.on { background: theme('colors.brand-soft'); }
.dlist .d b { display: block; font-size: 14px; }
.dlist .d small { color: theme('colors.stone'); font-size: 12px; }
.dossie { padding: 26px; }
.dossie .head { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; margin-bottom: 6px; }
.dossie .head h3 { font-size: 22px; font-family: 'Syne', sans-serif; }
.dossie .head small { color: theme('colors.stone'); display: block; }
.dossie .compromisso { margin: 18px 0; padding: 16px 20px; background: theme('colors.black');
  color: #fff; border-radius: 14px; font-size: 14px; }
.dossie .compromisso b { color: theme('colors.brand'); }
.fio { position: relative; margin-top: 24px; padding-left: 28px; }
.fio::before { content: ""; position: absolute; left: 8px; top: 6px; bottom: 6px; width: 3px;
  background: theme('colors.brand'); border-radius: 2px; }
.no { position: relative; padding-bottom: 24px; }
.no::before { content: ""; position: absolute; left: -26px; top: 5px; width: 13px; height: 13px;
  border-radius: 50%; background: theme('colors.brand'); box-shadow: 0 0 0 4px theme('colors.card'); }
.no small { font-size: 12px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
  color: theme('colors.stone'); }
.no b { display: block; font-family: 'Syne', sans-serif; font-size: 15px; margin: 3px 0 4px; }
.no p { font-size: 14px; color: #4b4740; }
.nota-add { margin-top: 8px; display: flex; flex-direction: column; gap: 10px; }
.inp { padding: 13px 16px; border-radius: 12px; border: 1px solid theme('colors.sand'); font-size: 14px;
  background: theme('colors.paper'); width: 100%; }
.inp:focus { outline: 2px solid theme('colors.brand'); border-color: transparent; }
.avatar.o { background: theme('colors.brand'); }
.eyebrow.laranja { font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
  color: theme('colors.brand'); }
```

`.eyebrow.laranja` é self-contido (não depende de uma classe base `.eyebrow` genérica, que não existe
em nenhuma tela real ainda — mesmo raciocínio já aplicado no `.upg .eyebrow` do Upgrade #24, só que
aqui sem o `.upg` por perto pra herdar o contexto de card preto).

`.nota-add` vira `flex-direction: column` (o protótipo usa `flex` horizontal porque lá é um campo só;
aqui são dois campos empilhados + botão, então a versão real ajusta o layout, mantendo o resto da
classe). `.avatar.o` finalmente é portado (a spec do Pessoas #23 explicitamente não portou essa
variante por falta de critério real de uso — aqui o critério existe: mentorado selecionado).

## 6. Testes

- `Tests\Unit\MentorNoteTest`: `member()`/`mentor()` resolvem via FK explícita; nota apagada quando o
  membro é apagado (`cascadeOnDelete`); nota apagada quando o mentor é apagado (`cascadeOnDelete`,
  teste separado).
- `Tests\Unit\MentorCommitmentTest`: `member()` resolve; registro apagado quando o membro é apagado
  (`cascadeOnDelete`); `member_id` é único (segunda tentativa de `create` pro mesmo membro lança
  violação de unicidade — testa a constraint do banco, não só a lógica do model).
- `Tests\Feature\Membros\DossiesTest`: guest redireciona; membro `club` é negado; página mostra todos
  os membros CLUB na lista; seleciona o primeiro mentorado por padrão quando existe algum; sem
  mentorados mostra "Nenhum mentorado ainda."; `selectMember` troca o mentorado exibido;
  `selectMember` com um id que não é `tier=club` não muda a seleção; `addNote` cria uma `MentorNote`
  com `mentor_id` igual ao mentor logado; `addNote` com título ou texto vazio falha a validação;
  `saveCommitment` cria o registro na primeira vez; `saveCommitment` chamado de novo atualiza (não
  duplica) o registro existente; `saveCommitment` com texto vazio salva `null` (compromisso limpo).
- `Tests\Unit\Support\PersonaNavigationTest` / `Tests\Feature\Membros\PersonaNavigationTest`: mesma
  atualização de `available:false→true`, agora pra `Dossiês`.

## 7. Fora de escopo

- Botão "Enviar ao cofre" do protótipo (envia um documento pro Cofre direto do dossiê) — território
  da #22 (Cofre), que já tem seu próprio fluxo de upload via Filament. Se quiser um atalho aqui depois,
  é uma issue separada.
- Recurso Filament pro mentor gerenciar notas/compromissos — o mentor já tem a própria página Livewire
  pra isso.
- "Sessão N" ou qualquer numeração automática de sessão associada a cada nota — o protótipo mostra
  isso no mock (`"18 jun · Sessão 4"`) mas é só decoração da seed; a versão real usa a data crua da
  nota, sem tentar correlacionar com `MentorSession`.
- Radar do dia (alerta de "sem sessão há muito tempo", briefing pré-sessão puxando a última entrada do
  fio) — issue #33, que depende deste #32 mas é escopo dela, não daqui.
