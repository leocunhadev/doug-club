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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-3xl">
            <form wire:submit="publishLesson" class="rounded-[18px] border border-sand bg-card p-6 flex flex-col gap-3">
                <p class="text-xs font-bold uppercase tracking-widest text-stone">Nova aula na biblioteca</p>

                <div>
                    <input type="text" wire:model="lessonTitle" placeholder="Título da aula"
                           class="w-full rounded-lg border border-sand bg-paper px-3 py-2 text-sm">
                    @error('lessonTitle') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <input type="text" wire:model="lessonVideoUrl" placeholder="Link do vídeo (YouTube ou Vimeo)"
                           class="w-full rounded-lg border border-sand bg-paper px-3 py-2 text-sm">
                    @error('lessonVideoUrl') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <select wire:model="lessonTier" class="w-full rounded-lg border border-sand bg-paper px-3 py-2 text-sm">
                        <option value="start">Start + CLUB veem</option>
                        <option value="club">Só o CLUB vê</option>
                    </select>
                    @error('lessonTier') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="mt-1 self-start px-4 py-2 rounded-full text-sm font-semibold bg-black text-white hover:brightness-110">
                    Publicar na biblioteca
                </button>
            </form>

            <form wire:submit="publishEncontro" class="rounded-[18px] border border-sand bg-card p-6 flex flex-col gap-3">
                <p class="text-xs font-bold uppercase tracking-widest text-stone">Novo encontro ao vivo</p>

                <div>
                    <input type="text" wire:model="encontroTema" placeholder="Tema do encontro"
                           class="w-full rounded-lg border border-sand bg-paper px-3 py-2 text-sm">
                    @error('encontroTema') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <input type="text" wire:model="encontroQuem" placeholder="Quem conduz (você ou convidado)"
                           class="w-full rounded-lg border border-sand bg-paper px-3 py-2 text-sm">
                    @error('encontroQuem') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <input type="datetime-local" wire:model="encontroScheduledAt"
                           class="w-full rounded-lg border border-sand bg-paper px-3 py-2 text-sm">
                    @error('encontroScheduledAt') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="mt-1 self-start px-4 py-2 rounded-full text-sm font-semibold bg-black text-white hover:brightness-110">
                    Publicar encontro
                </button>
            </form>
        </div>
    </div>

    <x-membros.footer />
</div>
