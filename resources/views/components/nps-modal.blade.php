<div
    x-data="{ open: false, action: null, subjectId: null, subtitle: '', score: null }"
    x-on:open-nps-modal.window="
        open = true;
        action = $event.detail.action;
        subjectId = $event.detail.subjectId;
        subtitle = $event.detail.subtitle;
        score = null;
    "
    x-show="open" x-cloak
    class="fixed inset-0 z-[150] bg-black/55 flex items-end sm:items-center justify-center p-[18px]"
>
    <div @click.outside="open = false" class="bg-card rounded-t-[22px] sm:rounded-[22px] p-[26px] max-w-[470px] w-full shadow-[0_24px_60px_rgba(0,0,0,.35)]">
        <h3 class="font-display text-lg">Como foi para você?</h3>
        <p class="mt-1 mb-4 text-sm text-stone" x-text="subtitle"></p>

        <div class="flex flex-wrap gap-1.5 mb-4">
            <template x-for="i in 11" :key="i">
                <button
                    type="button"
                    @click="score = i - 1"
                    :class="score === i - 1 ? 'bg-brand border-brand text-white' : 'bg-card border-sand text-ink'"
                    class="w-9 h-[38px] rounded-[10px] border font-bold text-sm"
                ><span x-text="i - 1"></span></button>
            </template>
        </div>

        <div class="flex items-center gap-2.5">
            <button type="button" @click="open = false" class="text-sm text-stone hover:text-ink">Agora não</button>
            <button
                type="button"
                @click="if (score !== null) { $wire[action](subjectId, score); open = false }"
                :disabled="score === null"
                class="ms-auto px-4 py-2 rounded-full bg-black text-white text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
            >Enviar</button>
        </div>
    </div>
</div>
