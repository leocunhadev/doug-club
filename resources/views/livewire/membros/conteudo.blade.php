<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Publicar conteúdo
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                Cole o link do vídeo, dê um título, escolha quem vê. Em segundos está na biblioteca de todo mundo.
            </p>
        </div>

        @if (session('conteudo-status'))
            <p class="mb-6 text-sm font-medium text-brand">{{ session('conteudo-status') }}</p>
        @endif

        <div class="max-w-xl">
            <form wire:submit="publishLesson" class="rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)] p-[22px] mb-[22px]">
                <p class="text-[11px] font-bold uppercase tracking-[.14em] text-stone">Nova aula na biblioteca</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                    <div class="sm:col-span-2">
                        <input type="text" wire:model="lessonTitle" placeholder="Título da aula"
                               class="w-full rounded-xl border border-sand bg-paper px-4 py-[13px] text-sm focus:outline-none focus:border-black">
                        @error('lessonTitle') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <input type="text" wire:model="lessonVideoUrl" placeholder="Link do vídeo (YouTube ou Vimeo)"
                               class="w-full rounded-xl border border-sand bg-paper px-4 py-[13px] text-sm focus:outline-none focus:border-black">
                        @error('lessonVideoUrl') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <select wire:model="lessonTier" class="w-full rounded-xl border border-sand bg-paper px-4 py-[13px] text-sm focus:outline-none focus:border-black">
                            <option value="start">Start + CLUB veem</option>
                            <option value="club">Só o CLUB vê</option>
                        </select>
                        @error('lessonTier') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <button type="submit" class="mt-[14px] px-[15px] py-2 rounded-full text-[13px] font-bold bg-brand text-white hover:brightness-110">
                    Publicar na biblioteca
                </button>
            </form>

            <form wire:submit="publishEncontro" class="rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)] p-[22px]">
                <p class="text-[11px] font-bold uppercase tracking-[.14em] text-stone">Novo encontro ao vivo</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                    <div class="sm:col-span-2">
                        <input type="text" wire:model="encontroTema" placeholder="Tema do encontro"
                               class="w-full rounded-xl border border-sand bg-paper px-4 py-[13px] text-sm focus:outline-none focus:border-black">
                        @error('encontroTema') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <input type="text" wire:model="encontroQuem" placeholder="Quem conduz (você ou convidado)"
                               class="w-full rounded-xl border border-sand bg-paper px-4 py-[13px] text-sm focus:outline-none focus:border-black">
                        @error('encontroQuem') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <input type="datetime-local" wire:model="encontroScheduledAt"
                               class="w-full rounded-xl border border-sand bg-paper px-4 py-[13px] text-sm focus:outline-none focus:border-black">
                        @error('encontroScheduledAt') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <button type="submit" class="mt-[14px] px-[15px] py-2 rounded-full text-[13px] font-bold bg-brand text-white hover:brightness-110">
                    Publicar encontro
                </button>
            </form>
        </div>
    </div>

    <x-membros.footer />
</div>
