<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-16 sm:space-y-20">
        <section>
            <p class="text-xs font-bold uppercase tracking-widest text-brand mb-2.5">
                {{ $this->viewingHasClubAccess ? 'DO.ing Club · Mentoria' : 'DO.ing Club start · Sua base' }}
            </p>
            <h1 class="text-[clamp(34px,6vw,58px)] leading-[1.02] font-display font-extrabold tracking-[-0.015em] text-black">
                Olá, {{ auth()->user()->name }}.<br>
                @if ($this->viewingHasClubAccess)
                    Vamos <span class="text-brand">continuar de onde paramos?</span>
                @else
                    Sua próxima <span class="text-brand">decisão</span> começa aqui.
                @endif
            </h1>
            @if ($note = $this->latestMentorNote)
                <div class="mt-4 flex gap-3 items-start max-w-2xl">
                    <div class="w-1 rounded-full bg-brand self-stretch shrink-0"></div>
                    <div>
                        <p class="text-[15px] text-ink/80">"{{ $note->body }}"</p>
                        <small class="block text-stone mt-1 text-xs">Onde paramos · nota de {{ $note->mentor->name }} · {{ $note->created_at->format('d/m') }}</small>
                    </div>
                </div>
            @endif
            @if ($this->viewingHasClubAccess)
                <p class="mt-3 max-w-2xl text-stone">
                    Acompanhe as transmissões ao vivo e os conteúdos gravados de Douglas Oliveira. Tudo em um lugar só,
                    exclusivo para quem decidiu agir.
                </p>
            @else
                <p class="mt-3 max-w-2xl text-stone">
                    Os conteúdos gravados de Douglas Oliveira, organizados pra você assistir no seu ritmo.
                </p>
            @endif

            <div class="mt-8 grid grid-cols-1 min-[860px]:grid-cols-[1.5fr_0.8fr] gap-[18px]">
            <div>
            <p class="text-xs font-bold uppercase tracking-widest text-stone mb-2.5">Continuar assistindo</p>
            <x-lesson-player :lesson="$this->featuredLesson" :progress="$this->featuredProgress" :has-feedback="$this->featuredHasFeedback" />
            <x-nps-modal />
            </div>

            <div class="flex flex-col gap-4">
                <div class="rounded-2xl bg-black text-white p-6 flex flex-col gap-2.5">
                    @if (! $this->viewingHasClubAccess)
                        @if ($newest = $this->newestLesson)
                            <p class="text-xs font-bold uppercase tracking-widest text-white/50">Novidade na biblioteca</p>
                            <p class="font-display text-xl leading-tight">{{ $newest->title }}</p>
                            <p class="text-sm text-white/70">Aula nova na sua biblioteca, já disponível para você.</p>
                            <button type="button" wire:click="watchLesson({{ $newest->id }})"
                                    class="mt-1 self-start rounded-full bg-brand px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                                Assistir agora
                            </button>
                        @endif
                    @elseif ($card = $this->nextSessionCard)
                        <p class="text-xs font-bold uppercase tracking-widest text-white/50">Sua próxima sessão 1:1</p>
                        <p class="font-display text-xl leading-tight">{{ $card['title'] }}</p>
                        <p class="text-sm text-white/70">{{ $card['subtitle'] }}</p>
                        <a href="{{ route($card['ctaRoute']) }}" wire:navigate
                           class="mt-1 self-start rounded-full bg-brand px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                            {{ $card['ctaLabel'] }}
                        </a>
                    @endif
                </div>

                <div class="rounded-2xl bg-card border border-sand p-5">
                    <h3 class="font-display text-sm mb-2.5">Atalhos</h3>
                    @foreach ($this->quickLinks as $link)
                        @if ($link['available'])
                            <a href="{{ route($link['route']) }}" wire:navigate
                               class="flex items-center gap-2.5 w-full text-left py-2.5 border-t border-sand first:border-t-0 text-sm font-medium text-ink">
                                {{ $link['label'] }}
                                <span class="ml-auto text-brand font-bold">→</span>
                            </a>
                        @else
                            <span class="flex items-center gap-2.5 w-full text-left py-2.5 border-t border-sand first:border-t-0 text-sm font-medium text-stone/60 cursor-not-allowed">
                                {{ $link['label'] }}
                                <span class="ml-auto text-xs">🔒</span>
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
            </div>
        </section>
    </div>

    <x-membros.footer />
</div>
