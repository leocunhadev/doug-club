# -*- coding: utf-8 -*-
"""
Gerador do relatório de auditoria de segurança (PDF) do projeto DO.ing Club.

Uso:
    venv/Scripts/python.exe gerar_relatorio.py

Regenera relatorio-auditoria-seguranca.pdf a partir dos achados
codificados em `FINDINGS` abaixo. Para atualizar o relatório depois de
uma nova rodada de auditoria, edite `FINDINGS`, `STRENGTHS` e
`RECOMMENDATIONS` e rode o script novamente.
"""
import io
import os

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import cm
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_LEFT, TA_CENTER
from reportlab.platypus import (
    BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table,
    TableStyle, Image, PageBreak, KeepTogether, HRFlowable
)
from reportlab.pdfgen import canvas as pdfcanvas
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

HERE = os.path.dirname(os.path.abspath(__file__))
OUT_PDF = os.path.join(HERE, "relatorio-auditoria-seguranca.pdf")

# Fontes base-14 do reportlab (Helvetica/Courier) tem um bug de posicionamento
# do cedilha (Ç) em negrito/tamanho grande. Usamos as TTF DejaVu Sans que
# já vem junto com o matplotlib (dependência deste script) para Unicode
# pt-BR correto em qualquer tamanho.
_FONT_DIR = os.path.join(os.path.dirname(matplotlib.__file__), "mpl-data", "fonts", "ttf")
pdfmetrics.registerFont(TTFont("DejaVuSans", os.path.join(_FONT_DIR, "DejaVuSans.ttf")))
pdfmetrics.registerFont(TTFont("DejaVuSans-Bold", os.path.join(_FONT_DIR, "DejaVuSans-Bold.ttf")))
pdfmetrics.registerFont(TTFont("DejaVuSansMono", os.path.join(_FONT_DIR, "DejaVuSansMono.ttf")))
pdfmetrics.registerFontFamily("DejaVuSans", normal="DejaVuSans", bold="DejaVuSans-Bold")

# ---------------------------------------------------------------------------
# Paleta
# ---------------------------------------------------------------------------
COLOR_CRITICA = "#B91C1C"
COLOR_ALTA = "#EA580C"
COLOR_MEDIA = "#D97706"
COLOR_BAIXA = "#2563EB"
COLOR_FORTE = "#059669"
COLOR_INK = "#111827"
COLOR_MUTED = "#6B7280"
COLOR_BORDER = "#E5E7EB"
COLOR_BG_SOFT = "#F9FAFB"

SEV_COLORS = {
    "Critica": COLOR_CRITICA,
    "Alta": COLOR_ALTA,
    "Media": COLOR_MEDIA,
    "Baixa": COLOR_BAIXA,
    "Informativa": COLOR_MUTED,
}
SEV_ORDER = ["Critica", "Alta", "Media", "Baixa", "Informativa"]

# ---------------------------------------------------------------------------
# Dados da auditoria
# ---------------------------------------------------------------------------
PROJECT_NAME = "DO.ing Club (doug-club)"
AUDIT_DATE = "31 de agosto de 2026"
STACK_SUMMARY = (
    "Laravel 13 (PHP 8.3) + Livewire 3 / Volt + Filament 4 (painel admin) + SQLite/MySQL via Eloquent. "
    "Sem Docker/CI/Helm/Terraform no repositório (deploy manual via Supervisor, ver docs/deploy)."
)

METHOD_NOTES = [
    ("Isolamento de dono/tenant", "Não ha multi-tenant nem RLS (Postgres/Supabase). O isolamento é feito por "
     "coluna 'tier' do usuário (start/club/mentor) + coluna de posse (member_id/user_id/mentor_id) checada "
     "manualmente em cada Livewire component/controller, mais dois middlewares custom: EnsureAccessIsActive "
     "(assinatura ativa) e EnsureTier (tier mínimo por rota)."),
    ("Permissão definida no navegador", "Gates de UI (botões/links condicionais em Blade) foram cruzados com a "
     "checagem correspondente no componente Livewire ou controller server-side."),
    ("IDOR", "Percorridos TODOS os controllers HTTP (app/Http/Controllers/**) e TODOS os componentes Livewire "
     "(app/Livewire/**) que recebem ID via route-model-binding, wire:click ou query string."),
    ("Chaves expostas", "Buscado em config/**, .env.example, seeders, docs/deploy, routes e histórico de rotas "
     "por segredos hardcoded, defaults inseguros (env('X','secret')) e credenciais de seed."),
    ("XSS / entrada sem tratamento", "Buscado por {!! !!} em Blade, innerHTML/dangerouslySetInnerHTML/v-html, "
     "eval/new Function, e renderização de Markdown/HTML de conteúdo de usuário em views e notificações."),
]

# Each finding: id, title, severity, category, files (list of (path, lines)), description, why, impact, fix, ac (list)
FINDINGS = [
    dict(
        id=1,
        title="Download de PDF de framework ignora o tier da aula vinculada (IDOR / controle de acesso quebrado)",
        severity="Alta",
        category="IDOR / Banco sem tranca",
        files=[
            ("app/Http/Controllers/Membros/FrameworkPdfDownloadController.php", "13-32"),
            ("routes/web.php", "41-43"),
            ("resources/views/components/framework-card.blade.php", "8-22"),
        ],
        snippet=(
            "public function __invoke(Framework $framework): StreamedResponse\n"
            "{\n"
            "    abort_unless(\n"
            "        $framework->hasUploadedFile() && Storage::disk('public')->exists($framework->pdf_path),\n"
            "        404,\n"
            "    );\n"
            "    // não ha checagem de $framework->lesson?->isAvailableFor($user)\n"
            "    ...\n"
            "    return Storage::disk('public')->download($framework->pdf_path, ...);\n"
            "}"
        ),
        why=(
            "Frameworks podem estar vinculados a uma Lesson com tier 'club' (Lesson::isAvailableFor() só libera "
            "para quem tem hasClubAccess()). O controller irmão, LessonMaterialDownloadController, faz "
            "abort_unless($material->lesson->isAvailableFor($user)) antes de servir o arquivo (linha 14) — mas "
            "FrameworkPdfDownloadController nunca faz essa checagem. A rota só exige "
            "['auth','verified','active'], sem 'tier'. No Blade (framework-card.blade.php), o botão 'Ver aula' "
            "é ocultado quando a lição não está disponível para o tier do usuário (linha 25), mas o botão "
            "'Baixar PDF' é renderizado incondicionalmente (linhas 8-12) sempre que há arquivo. Um usuário tier "
            "'start' autenticado pode acessar diretamente /membros/frameworks/{id}/download para qualquer "
            "framework, inclusive os vinculados a aulas exclusivas do CLUB, apenas descobrindo/iterando o ID "
            "(sequencial, incremental via chave primária)."
        ),
        impact=(
            "Bypass do paywall de tier: conteúdo pago (PDF de framework exclusivo CLUB) é obtido por assinantes "
            "do plano Start sem upgrade. O próprio DemoDataSeeder cria esse cenário real: o framework '4S' e "
            "vinculado a uma Lesson tier=club E tem pdf_path real, exercitando o bug com dados de exemplo."
        ),
        fix=(
            "Adicionar, no início do __invoke, "
            "`abort_unless(!$framework->lesson_id || $framework->lesson->isAvailableFor(request()->user()), 404);` "
            "— mesmo padrão ja usado em LessonMaterialDownloadController. Cobrir com teste de feature "
            "verificando 403/404 para usuário 'start' baixando framework de lição 'club'."
        ),
        ac=[
            "Usuário tier=start recebe 404 ao acessar membros/frameworks/{id}/download quando {id} pertence a uma lesson tier=club",
            "Usuário tier=club/mentor continua baixando normalmente",
            "Framework sem lesson_id (livre) continua acessível a qualquer tier autenticado",
            "Teste de feature cobrindo os três casos acima adicionado em tests/Feature",
        ],
    ),
    dict(
        id=2,
        title="Ações de progresso de aula (watchLesson/updateProgress/markCompleted) não validam o tier da lição",
        severity="Media",
        category="IDOR / Permissão no navegador",
        files=[
            ("app/Livewire/Concerns/TracksLessonProgress.php", "25-51"),
            ("app/Actions/MarkLessonAsWatching.php", "9-18"),
            ("app/Actions/MarkLessonAsCompleted.php", "9-15"),
            ("app/Actions/UpdateLessonWatchedSeconds.php", "9-21"),
        ],
        snippet=(
            "public function watchLesson(int $lessonId, MarkLessonAsWatching $action): void\n"
            "{\n"
            "    $action->handle(Auth::id(), $lessonId); // só verifica Lesson::findOrFail\n"
            "    $this->featuredLessonId = $lessonId;\n"
            "}\n"
            "\n"
            "// mas no MESMO trait, submitNpsScore FAZ a checagem correta:\n"
            "public function submitNpsScore(int $lessonId, int $score, ...): void\n"
            "{\n"
            "    $lesson = Lesson::query()->find($lessonId);\n"
            "    if (! $lesson || ! $lesson->isAvailableFor(Auth::user())) { return; }\n"
            "    ...\n"
            "}"
        ),
        why=(
            "Métodos públicos de componentes Livewire são invocaveis via requisição AJAX direta ao endpoint "
            "Livewire, independente do que a UI renderiza (wire:click e só açúcar sintatico). watchLesson, "
            "updateProgress e markCompleted aceitam lessonId vindo do cliente e chamam Actions que gravam em "
            "LessonProgress sem checar Lesson::isAvailableFor($user) — diferente de submitNpsScore, no mesmo "
            "trait, que faz exatamente essa checagem antes de agir. MarkLessonAsWatching só confirma que a "
            "Lesson existe (findOrFail); MarkLessonAsCompleted e UpdateLessonWatchedSeconds não checam nada."
        ),
        impact=(
            "Um membro tier=start pode forjar uma chamada Livewire com o lessonId de uma aula exclusiva CLUB e "
            "criar/alterar seu próprio LessonProgress (status completed, watched_seconds) para essa aula, mesmo "
            "sem ter acesso a ela. Isso corrompe sinais usados em Radar::engagedStartMembers (contagem de "
            "lições 'start' completadas) e no dashboard. Mitigado parcialmente: o player de vídeo "
            "(lesson-player.blade.php:3) e o título em destaque (aulas.blade.php:19) re-checam isAvailableFor "
            "antes de exibir o embed_url, então o vídeo em si não vaza por esse caminho — o problema e a "
            "integridade dos dados de progresso, não exposicao direta de mídia."
        ),
        fix=(
            "Replicar em watchLesson/updateProgress/markCompleted a mesma guarda usada em submitNpsScore: "
            "buscar a Lesson e checar isAvailableFor($user) antes de chamar a Action, retornando cedo (sem "
            "efeito) quando não disponível."
        ),
        ac=[
            "watchLesson/updateProgress/markCompleted não gravam LessonProgress quando a lesson não está disponível para o tier do usuário autenticado",
            "Comportamento existente preservado para lições disponíveis (start sempre, club/mentor para hasClubAccess())",
            "Teste de feature chamando os tres métodos via Livewire::test() com usuário start e lesson club, garantindo que LessonProgress não é criado",
        ],
    ),
    dict(
        id=3,
        title="Rotas /prototype/* publicadas sem qualquer autenticação ou guarda de ambiente",
        severity="Media",
        category="Banco sem tranca (controle de acesso ausente)",
        files=[
            ("routes/web.php", "100"),
            ("routes/prototype.php", "1-22"),
            ("app/Http/Controllers/Prototype/PrototypeController.php", "1-210"),
        ],
        snippet=(
            "// routes/web.php\n"
            "require __DIR__.'/auth.php';\n"
            "require __DIR__.'/prototype.php';   // nenhum middleware, nenhum guard de ambiente\n"
            "\n"
            "// routes/prototype.php\n"
            "Route::prefix('prototype')->name('prototype.')->group(function () {\n"
            "    Route::get('home', [PrototypeController::class, 'home'])->name('home');\n"
            "    ... // 13 rotas, todas públicas\n"
            "});"
        ),
        why=(
            "Nenhuma das 13 rotas de /prototype/* passa por middleware de autenticação, e o grupo não esta "
            "protegido por `app()->environment('local')` nem por variavel de config. Qualquer visitante não "
            "autenticado, em qualquer ambiente (inclusive produção, se este código for implantado como está), "
            "acessa /prototype/home, /prototype/dossies, /prototype/radar etc."
        ),
        impact=(
            "Os dados são mockados (config/prototype.php), não registros reais de banco, então não ha "
            "vazamento de PII de membros reais. Ainda assim, expoe publicamente o design de produto ainda não "
            "lancado, a metodologia de mentoria (estrutura de 'dossies', 'pontes', precificacao dos planos "
            "Start/Club) e a navegacao completa do app para qualquer pessoa na internet — informação de "
            "negócio sensível para um produto em construção, além de superfície de ataque "
            "desnecessaria exposta sem necessidade."
        ),
        fix=(
            "Envolver o require de routes/prototype.php (ou o grupo de rotas dentro dele) em "
            "`if (app()->environment('local')) { ... }`, ou aplicar um middleware dedicado (ex: 'auth' + "
            "'is_admin') caso o prototipo precise ficar acessível em staging para o time."
        ),
        ac=[
            "GET /prototype/* retorna 404 (rota inexistente) quando APP_ENV != local, ou passa a exigir autenticação+is_admin",
            "Nenhuma rota prototype.* aparece em `php artisan route:list` quando executado com APP_ENV=production",
            "Time confirma se staging precisa de acesso; se sim, adicionar middleware equivalente em vez de deixar público",
        ],
    ),
    dict(
        id=4,
        title="Credenciais de administrador hardcoded em seeders, sem guarda de ambiente",
        severity="Media",
        category="Chaves expostas / credenciais padrão",
        files=[
            ("database/seeders/DatabaseSeeder.php", "21-33"),
            ("database/seeders/DemoDataSeeder.php", "45-87"),
        ],
        snippet=(
            "User::factory()->create([\n"
            "    'name' => 'Admin User',\n"
            "    'email' => 'admin@example.com',\n"
            "    'password' => Hash::make('123456789'),\n"
            "    'is_admin' => true,\n"
            "    'tier' => 'mentor',\n"
            "]);"
        ),
        why=(
            "DatabaseSeeder::run() cria incondicionalmente um usuário admin@example.com com is_admin=true e "
            "senha fixa '123456789' (mesma senha reaproveitada em mais 5 usuários no DemoDataSeeder). Nenhum "
            "dos dois seeders verifica `app()->environment('local')` ou `app()->isProduction()` antes de "
            "rodar. Se `php artisan db:seed` (ou um passo de deploy que chame `migrate --seed`) for executado "
            "contra produção — por engano ou por um pipeline de deploy mal configurado — cria-se uma conta "
            "admin com credencial pública e conhecida (está no código-fonte)."
        ),
        impact=(
            "Caso executado em produção: acesso administrativo total ao painel Filament "
            "(User::canAccessPanel() retorna true para is_admin) com credencial trivial e pública no "
            "repositório, permitindo leitura/escrita de todos os recursos administrativos (membros, sessoes, "
            "documentos do cofre, aplicações ao CLUB)."
        ),
        fix=(
            "Adicionar guarda no topo de DatabaseSeeder::run() e DemoDataSeeder::run(): "
            "`abort_if(app()->isProduction(), 403, 'Seeders de demo não podem rodar em produção.');` "
            "ou envolver as chamadas com `if (! app()->environment('production')) { ... }`. "
            "Alternativamente, gerar senha aleatoria via `Str::password()` e nunca reusar a mesma senha em "
            "vários usuários, mesmo em seeds de demonstração."
        ),
        ac=[
            "DatabaseSeeder::run() e DemoDataSeeder::run() abortam (ou viram no-op) quando app()->environment('production')",
            "Documentado em docs/deploy que `db:seed` nunca deve rodar contra produção",
            "(Opcional) senha de demo deixa de ser fixa/reaproveitada entre usuários",
        ],
    ),
    dict(
        id=5,
        title="Segredo do webhook AbacatePay trafega via query string",
        severity="Baixa",
        category="Chaves expostas (manuseio de segredo)",
        files=[
            ("app/Http/Controllers/Webhooks/AbacatePayWebhookController.php", "37-41"),
        ],
        snippet=(
            "$expectedSecret = config('services.abacatepay.webhook_secret');\n"
            "if (! $expectedSecret || ! hash_equals($expectedSecret, (string) $request->query('webhookSecret'))) {\n"
            "    abort(403);\n"
            "}"
        ),
        why=(
            "A comparação em si é correta (hash_equals, com o valor conhecido como primeiro argumento, e o "
            "endpoint falha fechado quando o secret não está configurado). O ponto fraco é o transporte: o "
            "segredo compartilhado vai na query string (?webhookSecret=...) em vez de um header assinado. "
            "Query strings tendem a ser gravadas em logs de acesso do servidor/proxy, em ferramentas de APM/"
            "monitoramento de erro que capturam a URL completa, e potencialmente em caches de CDN/proxy."
        ),
        impact=(
            "Não é explorável diretamente por um atacante externo sem acesso a esses logs/ferramentas — a "
            "checagem em si bloqueia requisicoes sem o segredo correto. O risco é o segredo vazar por um canal "
            "secundario (log agregado, dashboard de erros) e depois ser reaproveitado para forjar eventos de "
            "pagamento (ex: liberar acesso CLUB via evento 'checkout.completed' falso)."
        ),
        fix=(
            "Se o provedor (AbacatePay) suportar, migrar para verificação via header assinado (HMAC do corpo "
            "da requisição) em vez de query string. Caso o provedor só ofereça query string, garantir que o "
            "log de acesso do servidor/proxy não persista query strings para essa rota e que ferramentas de "
            "APM redijam parametros sensíveis antes de enviar telemetria."
        ),
        ac=[
            "Confirmar com a documentação do AbacatePay se ha alternativa de assinatura via header",
            "Se disponível, migrar a validação para o header assinado, mantendo query string apenas como fallback documentado",
            "Configurar redaction de query string para essa rota nas ferramentas de log/APM em uso",
        ],
    ),
]

STRENGTHS = [
    ("app/Http/Controllers/Membros/VaultDocumentOpenController.php:15",
     "Verifica `$document->member_id === request()->user()->id` antes de servir qualquer documento do cofre — bloqueia IDOR."),
    ("app/Http/Controllers/Membros/LessonMaterialDownloadController.php:14",
     "Verifica `$material->lesson->isAvailableFor($user)` antes do download — padrão correto que faltou no controller de frameworks (achado #1)."),
    ("app/Http/Middleware/EnsureTier.php + app/Models/User.php:72-79",
     "Preview de persona do admin (`viewingTier()`) e explicitamente separado do `tier` real usado no gate de rota — o middleware nunca confia no valor de preview."),
    ("app/Http/Controllers/Membros/PreviewPersonaController.php:16",
     "Exige `is_admin` no servidor antes de permitir a troca de persona de visualizacao — não é apenas escondido na UI."),
    ("routes/web.php (grupo membros/*)",
     "Todas as rotas autenticadas aplicam consistentemente ['auth','verified','active'], com 'tier:X' adicional onde o conteúdo e restrito por plano."),
    ("Toda a base de código (app/**)",
     "Nenhum uso de DB::raw/whereRaw/selectRaw com dados de entrada — 100% Eloquent com parameter binding, sem superfície de SQL injection encontrada."),
    ("resources/views/**/*.blade.php",
     "Nenhuma ocorrência de `{!! !!}`, v-html, dangerouslySetInnerHTML ou renderização de Markdown sobre conteúdo de usuário (notas de mentor, bio, tags) — tudo passa por `{{ }}` escapado."),
    ("resources/js/*.js",
     "Nenhum uso de innerHTML/insertAdjacentHTML/document.write com dado dinâmico nos componentes Alpine (vimeo-progress, lesson-watermark)."),
    ("app/Http/Controllers/Webhooks/AbacatePayWebhookController.php:39",
     "Comparação de segredo do webhook usa hash_equals() (timing-safe) e falha fechado (abort 403) quando o segredo não está configurado, em vez de aceitar por omissão."),
    (".gitignore + git history",
     "`.env` nunca foi commitado (confirmado via `git ls-files` e `git log -p -- .env`); nenhuma dependência AGPL no composer.json."),
    ("Todos os Models (app/Models/**)",
     "Mass assignment protegido: `$fillable` explicito em cada model, nenhum uso de `$guarded = []` ou `Model::unguard()`."),
    ("app/Livewire/Membros/Disponibilidade.php:66-71 e Agenda.php:78-93",
     "Mutacoes (remover bloco de agenda, cancelar sessao) sempre filtram por Auth::id() do dono, mesmo padrão replicado nos demais componentes revisados."),
]

RECOMMENDATIONS = [
    ("P1", "Corrigir o IDOR de download de framework (achado #1) — bypass de paywall já demonstrável com os dados do próprio seeder de demonstração."),
    ("P1", "Adicionar guarda de ambiente aos seeders (achado #4) antes do próximo deploy, para eliminar o risco de credencial admin pública em produção."),
    ("P2", "Fechar a lacuna de controle de acesso nas ações de progresso de aula (achado #2), para preservar a integridade dos sinais usados no Radar."),
    ("P2", "Restringir /prototype/* a ambiente local ou a admin autenticado (achado #3) antes de qualquer deploy público."),
    ("P3", "Avaliar migrar a autenticação do webhook AbacatePay para header assinado, ou ao menos configurar redaction de query string nos logs (achado #5)."),
    ("P3", "Adicionar suíte de testes de regressão de autorização (feature tests) cobrindo cada combinação tier x rota protegida, para travar os achados #1 e #2 permanentemente."),
]

# ---------------------------------------------------------------------------
# Gráficos
# ---------------------------------------------------------------------------

def sev_of(f):
    return f["severity"].replace("í", "i").replace("Í", "I") if False else f["severity"]


def make_donut_chart():
    counts = {s: 0 for s in SEV_ORDER}
    for f in FINDINGS:
        counts[f["severity"]] += 1
    counts = {k: v for k, v in counts.items() if v > 0}

    labels = list(counts.keys())
    sizes = list(counts.values())
    colors_ = [SEV_COLORS[l] for l in labels]

    # Figura mais larga que alta: a caixa dos eixos (0.03-0.60 = 57% da largura)
    # ocupa quase toda a altura (0.05-0.95 = 90%) e fica QUADRADA em polegadas
    # (0.57*7.4 ~= 0.90*4.2), então set_aspect('equal') não precisa encolhe-la —
    # numa figura quadrada com a legenda tomando espaco só na largura, a mesma
    # fracao de altura sobra bem maior em polegadas e a rosca fica minuscula.
    fig, ax = plt.subplots(figsize=(7.4, 4.2), dpi=200)
    wedges, _ = ax.pie(
        sizes, colors=colors_, startangle=90, counterclock=False,
        wedgeprops=dict(width=0.42, edgecolor="white", linewidth=2),
    )
    ax.text(0, 0.08, str(sum(sizes)), ha="center", va="center", fontsize=26, fontweight="bold", color=COLOR_INK)
    ax.text(0, -0.18, "achados", ha="center", va="center", fontsize=11, color=COLOR_MUTED)
    ax.set_aspect("equal")

    legend_labels = [f"{l} ({c})" for l, c in zip(labels, sizes)]
    ax.legend(wedges, legend_labels, loc="center left", bbox_to_anchor=(1.03, 0.5),
              frameon=False, fontsize=11, handlelength=1.2, handleheight=1.2)
    fig.subplots_adjust(left=0.03, right=0.60, top=0.95, bottom=0.05)

    buf = io.BytesIO()
    fig.savefig(buf, format="png", transparent=True)
    plt.close(fig)
    buf.seek(0)
    return buf


def make_category_bar_chart():
    cat_counts = {}
    cat_severity = {}
    for f in FINDINGS:
        cat = f["category"]
        cat_counts[cat] = cat_counts.get(cat, 0) + 1
        # cor pela pior severidade da categoria
        order = {"Critica": 0, "Alta": 1, "Media": 2, "Baixa": 3, "Informativa": 4}
        if cat not in cat_severity or order[f["severity"]] < order[cat_severity[cat]]:
            cat_severity[cat] = f["severity"]

    cats = list(cat_counts.keys())
    values = [cat_counts[c] for c in cats]
    bar_colors = [SEV_COLORS[cat_severity[c]] for c in cats]

    fig, ax = plt.subplots(figsize=(7.6, 3.6), dpi=200)
    y_pos = range(len(cats))
    bars = ax.barh(y_pos, values, color=bar_colors, height=0.55, zorder=3)
    ax.set_yticks(list(y_pos))
    wrapped = ["\n".join(_wrap(c, 28)) for c in cats]
    ax.set_yticklabels(wrapped, fontsize=9.5, color=COLOR_INK)
    ax.invert_yaxis()
    ax.set_xlabel("Número de achados", fontsize=9.5, color=COLOR_MUTED)
    max_v = max(values) if values else 1
    ax.set_xlim(0, max_v + 1)
    ax.set_xticks(range(0, max_v + 2))
    for spine in ["top", "right", "left"]:
        ax.spines[spine].set_visible(False)
    ax.spines["bottom"].set_color(COLOR_BORDER)
    ax.tick_params(axis="x", colors=COLOR_MUTED, labelsize=9)
    ax.grid(axis="x", color=COLOR_BORDER, linewidth=0.8, zorder=0)
    for bar, v in zip(bars, values):
        ax.text(bar.get_width() + 0.05, bar.get_y() + bar.get_height() / 2, str(v),
                 va="center", ha="left", fontsize=9.5, color=COLOR_INK, fontweight="bold")
    fig.tight_layout()

    buf = io.BytesIO()
    fig.savefig(buf, format="png", transparent=True)
    plt.close(fig)
    buf.seek(0)
    return buf


def _wrap(text, width):
    import textwrap
    return textwrap.wrap(text, width) or [text]


# ---------------------------------------------------------------------------
# PDF layout helpers
# ---------------------------------------------------------------------------
PAGE_W, PAGE_H = A4
MARGIN = 2 * cm

styles = getSampleStyleSheet()
S_TITLE = ParagraphStyle("STitle", parent=styles["Title"], fontName="DejaVuSans-Bold", fontSize=26, leading=30, textColor=colors.HexColor(COLOR_INK), spaceAfter=6)
S_SUBTITLE = ParagraphStyle("SSubtitle", parent=styles["Normal"], fontName="DejaVuSans", fontSize=13, leading=18, textColor=colors.HexColor(COLOR_MUTED))
S_H1 = ParagraphStyle("SH1", parent=styles["Heading1"], fontName="DejaVuSans-Bold", fontSize=16, leading=20, textColor=colors.HexColor(COLOR_INK), spaceBefore=4, spaceAfter=10)
S_H2 = ParagraphStyle("SH2", parent=styles["Heading2"], fontName="DejaVuSans-Bold", fontSize=12.5, leading=16, textColor=colors.HexColor(COLOR_INK), spaceBefore=10, spaceAfter=6)
S_BODY = ParagraphStyle("SBody", parent=styles["Normal"], fontName="DejaVuSans", fontSize=9.7, leading=14, textColor=colors.HexColor(COLOR_INK))
S_BODY_MUTED = ParagraphStyle("SBodyMuted", parent=S_BODY, textColor=colors.HexColor(COLOR_MUTED))
S_LABEL = ParagraphStyle("SLabel", parent=S_BODY, fontName="DejaVuSans-Bold", fontSize=9.5)
S_LABEL_WHITE = ParagraphStyle("SLabelWhite", parent=S_LABEL, textColor=colors.white)
S_CODE = ParagraphStyle("SCode", parent=styles["Normal"], fontName="DejaVuSansMono", fontSize=8, leading=11.5, textColor=colors.HexColor("#1F2937"))
S_SMALL = ParagraphStyle("SSmall", parent=S_BODY, fontSize=8.4, leading=12, textColor=colors.HexColor(COLOR_MUTED))
S_COVER_METHOD_TITLE = ParagraphStyle("SCoverMethodTitle", parent=S_BODY, fontName="DejaVuSans-Bold", fontSize=9.5, textColor=colors.HexColor(COLOR_INK))


def severity_chip(sev):
    color = SEV_COLORS.get(sev, COLOR_MUTED)
    t = Table([[sev]], colWidths=[2.6 * cm], rowHeights=[0.55 * cm])
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor(color)),
        ("TEXTCOLOR", (0, 0), (-1, -1), colors.white),
        ("FONTNAME", (0, 0), (-1, -1), "DejaVuSans-Bold"),
        ("FONTSIZE", (0, 0), (-1, -1), 8),
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("ROUNDEDCORNERS", [6, 6, 6, 6]),
    ]))
    return t


class NumberedCanvas(pdfcanvas.Canvas):
    def __init__(self, *args, **kwargs):
        pdfcanvas.Canvas.__init__(self, *args, **kwargs)
        self._saved_states = []

    def showPage(self):
        self._saved_states.append(dict(self.__dict__))
        pdfcanvas.Canvas.showPage(self)

    def save(self):
        pass  # not used; we draw footer inline via onPage


def draw_header_footer(canvas_obj, doc):
    canvas_obj.saveState()
    canvas_obj.setStrokeColor(colors.HexColor(COLOR_BORDER))
    canvas_obj.setLineWidth(0.6)
    canvas_obj.line(MARGIN, PAGE_H - 1.3 * cm, PAGE_W - MARGIN, PAGE_H - 1.3 * cm)
    canvas_obj.setFont("DejaVuSans", 8)
    canvas_obj.setFillColor(colors.HexColor(COLOR_MUTED))
    canvas_obj.drawString(MARGIN, PAGE_H - 1.15 * cm, f"Relatório de Auditoria de Segurança — {PROJECT_NAME}")
    canvas_obj.drawRightString(PAGE_W - MARGIN, PAGE_H - 1.15 * cm, AUDIT_DATE)

    canvas_obj.line(MARGIN, 1.3 * cm, PAGE_W - MARGIN, 1.3 * cm)
    canvas_obj.drawString(MARGIN, 1.0 * cm, "Confidencial — uso interno")
    canvas_obj.drawRightString(PAGE_W - MARGIN, 1.0 * cm, f"Página {doc.page}")
    canvas_obj.restoreState()


def draw_cover(canvas_obj, doc):
    canvas_obj.saveState()
    # top brand bar
    canvas_obj.setFillColor(colors.HexColor(COLOR_INK))
    canvas_obj.rect(0, PAGE_H - 0.35 * cm, PAGE_W, 0.35 * cm, fill=1, stroke=0)
    canvas_obj.restoreState()


story = []
frame_normal = None


def build_doc():
    doc = BaseDocTemplate(OUT_PDF, pagesize=A4,
                           leftMargin=MARGIN, rightMargin=MARGIN,
                           topMargin=2.0 * cm, bottomMargin=1.8 * cm,
                           title=f"Relatório de Auditoria de Segurança — {PROJECT_NAME}",
                           author="Auditoria de Segurança")

    frame_cover = Frame(MARGIN, MARGIN, PAGE_W - 2 * MARGIN, PAGE_H - 2 * MARGIN, id="cover")
    frame_body = Frame(MARGIN, 1.6 * cm, PAGE_W - 2 * MARGIN, PAGE_H - 3.6 * cm, id="body")

    doc.addPageTemplates([
        PageTemplate(id="Cover", frames=[frame_cover], onPage=draw_cover),
        PageTemplate(id="Body", frames=[frame_body], onPage=draw_header_footer),
    ])
    return doc


def build_story():
    flow = []

    # ---------------- CAPA ----------------
    flow.append(Spacer(1, 3.2 * cm))
    flow.append(Paragraph("RELATÓRIO DE AUDITORIA<br/>DE SEGURANÇA", ParagraphStyle(
        "CoverTitle", fontName="DejaVuSans-Bold", fontSize=30, leading=36,
        textColor=colors.HexColor(COLOR_INK))))
    flow.append(Spacer(1, 0.4 * cm))
    flow.append(Paragraph(PROJECT_NAME, ParagraphStyle(
        "CoverProject", fontName="DejaVuSans-Bold", fontSize=18, leading=22,
        textColor=colors.HexColor(COLOR_ALTA))))
    flow.append(Spacer(1, 0.3 * cm))
    flow.append(Paragraph(f"Data da auditoria: {AUDIT_DATE}", S_SUBTITLE))
    flow.append(Spacer(1, 1.0 * cm))
    flow.append(HRFlowable(width="100%", thickness=1, color=colors.HexColor(COLOR_BORDER)))
    flow.append(Spacer(1, 0.6 * cm))

    flow.append(Paragraph("Escopo auditado", S_H2))
    flow.append(Paragraph(
        "Código-fonte completo do repositório doug-club: rotas HTTP (routes/**), controllers "
        "(app/Http/Controllers/**), componentes Livewire (app/Livewire/**), models Eloquent "
        "(app/Models/**), recursos administrativos Filament (app/Filament/**), configuração "
        "(config/**, .env.example), seeders (database/seeders/**) e views Blade "
        "(resources/views/**). Não inclui pentest dinâmico nem dependências de terceiros "
        "(composer.lock/package-lock.json) linha a linha.", S_BODY))
    flow.append(Spacer(1, 0.4 * cm))

    flow.append(Paragraph("Stack detectada", S_H2))
    flow.append(Paragraph(STACK_SUMMARY, S_BODY))

    flow.append(NextPageTemplate("Body"))
    flow.append(PageBreak())

    # ---------------- NOTA METODOLÓGICA ----------------
    flow.append(Paragraph("Nota metodológica — mapeamento das 5 categorias para esta stack", S_H1))
    method_rows = [[Paragraph(f"<b>{t}</b>", S_COVER_METHOD_TITLE), Paragraph(d, S_SMALL)] for t, d in METHOD_NOTES]
    t = Table(method_rows, colWidths=[4.2 * cm, 12.3 * cm])
    t.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 10),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("LINEBELOW", (0, 0), (-1, -2), 0.5, colors.HexColor(COLOR_BORDER)),
    ]))
    flow.append(t)
    flow.append(PageBreak())

    # ---------------- RESUMO EXECUTIVO ----------------
    flow.append(Paragraph("Resumo executivo", S_H1))

    sev_counts = {s: 0 for s in SEV_ORDER}
    for f in FINDINGS:
        sev_counts[f["severity"]] += 1
    total = len(FINDINGS)
    strengths_count = len(STRENGTHS)

    summary_line = f"{total} achados no total"
    parts = [f"{sev_counts[s]} {s.lower()}" for s in SEV_ORDER if sev_counts[s] > 0]
    summary_line += " (" + ", ".join(parts) + ") — " + f"{strengths_count} pontos fortes verificados."
    flow.append(Paragraph(summary_line, S_BODY))
    flow.append(Spacer(1, 0.3 * cm))

    donut_buf = make_donut_chart()
    bar_buf = make_category_bar_chart()
    # As duas figuras tem proporcoes (largura:altura) diferentes no matplotlib
    # (7.4:4.2 e 7.6:3.6) — a altura é derivada da largura escolhida pra cada
    # coluna, em vez de forcar um quadrado, senao a imagem distorce ou sobra
    # margem interna enorme (como aconteceu ao forcar a rosca em 8.2x8.2cm).
    donut_w, donut_h = 7.8 * cm, 7.8 * cm * (4.2 / 7.4)
    bar_w, bar_h = 8.4 * cm, 8.4 * cm * (3.6 / 7.6)
    img_donut = Image(donut_buf, width=donut_w, height=donut_h)
    img_bar = Image(bar_buf, width=bar_w, height=bar_h)
    cap_style = ParagraphStyle("cap", parent=S_SMALL, alignment=TA_CENTER)

    combo = Table(
        [
            [img_donut, img_bar],
            [Paragraph("Achados por severidade", cap_style),
             Paragraph("Achados por categoria (cor = pior severidade na categoria)", cap_style)],
        ],
        colWidths=[8.1 * cm, 8.5 * cm],
    )
    combo.setStyle(TableStyle([
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ("VALIGN", (0, 0), (-1, 0), "MIDDLE"),
        ("VALIGN", (0, 1), (-1, 1), "TOP"),
        ("TOPPADDING", (0, 1), (-1, 1), 6),
    ]))
    flow.append(combo)
    flow.append(Spacer(1, 0.5 * cm))

    # ---------------- PONTOS FORTES / FRACOS ----------------
    flow.append(Paragraph("Pontos fortes (verificados no código)", S_H2))
    strength_rows = [[Paragraph("✓", ParagraphStyle("chk", parent=S_BODY, textColor=colors.HexColor(COLOR_FORTE), fontName="DejaVuSans-Bold")),
                       Paragraph(f"<font face='DejaVuSansMono' size='7.6' color='{COLOR_MUTED}'>{loc}</font><br/>{desc}", S_SMALL)]
                      for loc, desc in STRENGTHS]
    t_strengths = Table(strength_rows, colWidths=[0.6 * cm, 15.9 * cm])
    t_strengths.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
        ("LINEBELOW", (0, 0), (-1, -2), 0.4, colors.HexColor(COLOR_BORDER)),
    ]))
    flow.append(t_strengths)
    flow.append(Spacer(1, 0.4 * cm))

    flow.append(Paragraph("Pontos fracos (riscos centrais)", S_H2))
    weak_summary = (
        "O maior risco e o controle de acesso por nível de conteúdo (tier) ser aplicado de forma "
        "inconsistente entre controllers/actions quase identicos: dois dos cinco achados (#1 e #2) são "
        "bypasses de tier em fluxos que tem um irmão correto no mesmo arquivo/trait, o que sugere um padrão "
        "a reforçar com teste automatizado (ver Recomendações). Os demais achados são superfícies "
        "acessórias — rotas de prototipo públicas, credenciais de seed e transporte de segredo de webhook — "
        "de exploração mais indireta, mas fáceis de corrigir."
    )
    flow.append(Paragraph(weak_summary, S_BODY))

    flow.append(PageBreak())

    # ---------------- TABELA DE ACHADOS DETALHADOS ----------------
    flow.append(Paragraph("Achados detalhados", S_H1))
    header = [Paragraph("<b>Sev.</b>", S_LABEL_WHITE), Paragraph("<b>Arquivo:linha</b>", S_LABEL_WHITE), Paragraph("<b>Descrição</b>", S_LABEL_WHITE)]
    rows = [header]
    for f in FINDINGS:
        loc_lines = "<br/>".join(f"<font face='DejaVuSansMono' size='7.6'>{p}:{l}</font>" for p, l in f["files"])
        rows.append([
            severity_chip(f["severity"]),
            Paragraph(loc_lines, S_SMALL),
            Paragraph(f"<b>#{f['id']} {f['title']}</b><br/>{f['category']}", S_SMALL),
        ])
    t_findings = Table(rows, colWidths=[2.7 * cm, 5.4 * cm, 8.4 * cm], repeatRows=1)
    t_findings.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor(COLOR_INK)),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("LINEBELOW", (0, 0), (-1, -1), 0.5, colors.HexColor(COLOR_BORDER)),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor(COLOR_BG_SOFT)]),
    ]))
    flow.append(t_findings)
    flow.append(PageBreak())

    # ---------------- FICHAS DE CADA ACHADO ----------------
    for f in FINDINGS:
        block = []
        block.append(Table([[severity_chip(f["severity"]), Paragraph(f"<b>#{f['id']} — {f['title']}</b>", S_H2)]],
                            colWidths=[2.8 * cm, 13.7 * cm],
                            style=TableStyle([("VALIGN", (0, 0), (-1, -1), "MIDDLE")])))
        block.append(Paragraph(f"<b>Categoria:</b> {f['category']}", S_BODY_MUTED))
        block.append(Spacer(1, 0.15 * cm))

        loc_text = "<br/>".join(f"• <font face='DejaVuSansMono' size='8.2'>{p}:{l}</font>" for p, l in f["files"])
        block.append(Paragraph("<b>Local(is):</b>", S_LABEL))
        block.append(Paragraph(loc_text, S_BODY))
        block.append(Spacer(1, 0.2 * cm))

        code_table = Table([[Paragraph(f["snippet"].replace("\n", "<br/>").replace(" ", "&nbsp;"), S_CODE)]],
                            colWidths=[16.5 * cm])
        code_table.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#F3F4F6")),
            ("BOX", (0, 0), (-1, -1), 0.6, colors.HexColor(COLOR_BORDER)),
            ("LEFTPADDING", (0, 0), (-1, -1), 8),
            ("RIGHTPADDING", (0, 0), (-1, -1), 8),
            ("TOPPADDING", (0, 0), (-1, -1), 6),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
        ]))
        block.append(code_table)
        block.append(Spacer(1, 0.25 * cm))

        block.append(Paragraph("<b>Por que é explorável</b>", S_LABEL))
        block.append(Paragraph(f["why"], S_BODY))
        block.append(Spacer(1, 0.15 * cm))
        block.append(Paragraph("<b>Impacto</b>", S_LABEL))
        block.append(Paragraph(f["impact"], S_BODY))
        block.append(Spacer(1, 0.15 * cm))
        block.append(Paragraph("<b>Correção sugerida</b>", S_LABEL))
        block.append(Paragraph(f["fix"], S_BODY))

        flow.append(KeepTogether(block))
        flow.append(Spacer(1, 0.6 * cm))
        flow.append(HRFlowable(width="100%", thickness=0.6, color=colors.HexColor(COLOR_BORDER)))
        flow.append(Spacer(1, 0.4 * cm))

    flow.append(PageBreak())

    # ---------------- RECOMENDAÇÕES ----------------
    flow.append(Paragraph("Recomendações priorizadas", S_H1))
    rec_rows = [[Paragraph("<b>Prio.</b>", S_LABEL_WHITE), Paragraph("<b>Recomendação</b>", S_LABEL_WHITE)]]
    for p, text in RECOMMENDATIONS:
        color = {"P1": COLOR_CRITICA, "P2": COLOR_ALTA, "P3": COLOR_MEDIA}.get(p, COLOR_MUTED)
        chip = Table([[p]], colWidths=[1.3 * cm], rowHeights=[0.5 * cm])
        chip.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor(color)),
            ("TEXTCOLOR", (0, 0), (-1, -1), colors.white),
            ("FONTNAME", (0, 0), (-1, -1), "DejaVuSans-Bold"),
            ("FONTSIZE", (0, 0), (-1, -1), 8.5),
            ("ALIGN", (0, 0), (-1, -1), "CENTER"),
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ]))
        rec_rows.append([chip, Paragraph(text, S_BODY)])
    t_rec = Table(rec_rows, colWidths=[1.8 * cm, 14.7 * cm], repeatRows=1)
    t_rec.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor(COLOR_INK)),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("LINEBELOW", (0, 0), (-1, -1), 0.5, colors.HexColor(COLOR_BORDER)),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor(COLOR_BG_SOFT)]),
    ]))
    flow.append(t_rec)

    flow.append(PageBreak())

    # ---------------- ISSUES PARA O GITHUB ----------------
    flow.append(Paragraph("Issues para o GitHub", S_H1))
    flow.append(Paragraph(
        "Texto completo, pronto para copiar e colar, de uma issue por achado acionavel. Achados triviais "
        "relacionados não foi necessário agrupar nesta rodada (cada achado tem causa e correção "
        "distintas).", S_BODY))
    flow.append(Spacer(1, 0.3 * cm))

    for f in FINDINGS:
        issue_md = render_issue_markdown(f)
        flow.append(Paragraph(f"--- ISSUE {f['id']} ---", ParagraphStyle("issuemarker", parent=S_LABEL, textColor=colors.HexColor(COLOR_MUTED))))
        code_table = Table([[Paragraph(issue_md.replace("\n", "<br/>").replace(" ", "&nbsp;"), S_CODE)]], colWidths=[16.5 * cm])
        code_table.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#F3F4F6")),
            ("BOX", (0, 0), (-1, -1), 0.6, colors.HexColor(COLOR_BORDER)),
            ("LEFTPADDING", (0, 0), (-1, -1), 8),
            ("RIGHTPADDING", (0, 0), (-1, -1), 8),
            ("TOPPADDING", (0, 0), (-1, -1), 6),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
        ]))
        flow.append(code_table)
        flow.append(Paragraph(f"--- FIM ISSUE {f['id']} ---", ParagraphStyle("issuemarker2", parent=S_LABEL, textColor=colors.HexColor(COLOR_MUTED))))
        flow.append(Spacer(1, 0.5 * cm))

    return flow


def render_issue_markdown(f):
    sev_label = f["severity"]
    files_md = "\n".join(f"- `{p}:{l}`" for p, l in f["files"])
    ac_md = "\n".join(f"- [ ] {item}" for item in f["ac"])
    return f"""# [Segurança] {f['title']}

**Labels sugeridas:** `security`, `severidade:{sev_label.lower()}`

## Descrição do problema
{f['why']}

## Evidência
{files_md}

```php
{f['snippet']}
```

## Impacto
{f['impact']}

## Sugestão de correção
{f['fix']}

## Criterios de aceite
{ac_md}
"""


# reportlab precisa de NextPageTemplate — importar aqui pra não poluir topo
from reportlab.platypus import NextPageTemplate  # noqa: E402


def main():
    doc = build_doc()
    flow = build_story()
    doc.build(flow)
    print(f"PDF gerado em: {OUT_PDF}")


if __name__ == "__main__":
    main()
