<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Radar do dia
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                O que precisa da sua atenção hoje. Esta é a sua secretária.
            </p>
        </div>

        <div class="kpis">
            <div class="kpi rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
                <b>{{ $this->todaySessions->count() }}</b>
                <small>
                    @if ($this->todaySessions->isEmpty())
                        Nenhuma sessão hoje.
                    @else
                        sessões 1:1 hoje: {{ $this->todaySessions->map(fn ($session) => $session->member->name.' às '.$session->scheduled_at->format('H\h'))->join(', ') }}
                    @endif
                </small>
            </div>

            <div class="kpi rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
                <b>{{ $this->averageNpsScore !== null ? number_format($this->averageNpsScore, 1, ',', '.') : '—' }}</b>
                <small>NPS médio das últimas sessões e aulas (últimos 30 dias).</small>
            </div>

            <div class="kpi alerta rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
                <b>{{ $this->overdueMembers->count() }}</b>
                <small>
                    @if ($this->overdueMembers->isEmpty())
                        Nenhum mentorado atrasado.
                    @elseif ($this->overdueMembers->count() === 1)
                        alerta: {{ $this->overdueMembers->first()['member']->name }} está há {{ $this->overdueMembers->first()['days_since'] }} dias sem sessão.
                    @else
                        alerta: {{ $this->overdueMembers->count() }} mentorados sem sessão há mais de 30 dias.
                    @endif
                </small>
            </div>
        </div>

        <h3 class="text-[17px] font-semibold mb-3">Antes das sessões de hoje</h3>
        @forelse ($this->todaySessions as $session)
            <div class="rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)] briefing-card border-l-4 border-brand p-[22px] mb-3">
                <p class="eyebrow laranja">{{ $session->member->name }} · {{ $session->scheduled_at->format('H\hi') }}</p>
                <p class="mt-2 text-[15px]">
                    @if ($lastNote = $this->lastNoteFor($session->member_id))
                        Última sessão: {{ $lastNote->body }}
                    @else
                        Nenhuma nota registrada ainda.
                    @endif
                    @if (($commitment = $this->activeCommitmentFor($session->member_id)) && $commitment->text)
                        Compromisso: {{ $commitment->text }}.
                    @endif
                </p>
            </div>
        @empty
            <p class="text-stone">Nenhuma sessão hoje.</p>
        @endforelse
    </div>

    <x-membros.footer />
</div>
