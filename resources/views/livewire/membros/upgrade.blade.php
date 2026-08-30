<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="upg rounded-[18px] shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)] max-w-3xl">
            <p class="eyebrow">Isso vive no CLUB</p>
            <h2>O Start te dá o conteúdo.<br>O CLUB te dá o Douglas.</h2>
            <p>Sessões individuais, dossiê vivo da sua empresa e pontes curadas com outros empresários. É mentoria de verdade, com poucas cadeiras por ano.</p>
            <ul>
                <li>Sessão 1:1 mensal de 90 minutos com o Douglas, com agenda direta na plataforma</li>
                <li>O fio da mentoria: cada decisão e compromisso registrado, sessão após sessão</li>
                <li>Seu cofre: insights, planos e gravações privadas de cada sessão, organizados para você</li>
                <li>Pontes curadas: o Douglas apresenta você a quem pode destravar seu negócio</li>
                <li>Encontros ao vivo com participação, não só a gravação</li>
            </ul>

            @if ($this->hasApplied)
                <button type="button" disabled
                    class="rounded-full bg-brand text-white text-sm font-semibold px-5 py-2.5 disabled:opacity-40 disabled:cursor-not-allowed">
                    Aplicação enviada — o Douglas responde em até 48h.
                </button>
            @else
                <button type="button" wire:click="apply"
                    class="rounded-full bg-brand text-white text-sm font-semibold px-5 py-2.5 hover:brightness-110">
                    Aplicar para o CLUB
                </button>
            @endif
        </div>
    </div>

    <x-membros.footer />
</div>
