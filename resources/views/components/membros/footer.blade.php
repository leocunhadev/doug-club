<footer class="border-t border-sand mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col items-center justify-center gap-2 text-center text-sm text-stone">
        <div class="flex gap-4">
            <a href="#" class="hover:text-ink">Política de Privacidade</a>
            <a href="{{ route('membros.sobre') }}" wire:navigate class="hover:text-ink">Sobre</a>
        </div>
        <p>&copy; DO.ing Club &middot; {{ now()->year }} Todos os direitos reservados.</p>
    </div>
</footer>

@if ($whatsappNumber = config('services.whatsapp.number'))
    <a href="https://wa.me/{{ $whatsappNumber }}" target="_blank" rel="noopener"
       class="fixed bottom-4 right-4 h-14 w-14 rounded-full bg-[#25D366] flex items-center justify-center shadow-lg hover:brightness-110">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-7 w-7 fill-white">
            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.79.47 3.47 1.29 4.93L2 22l5.28-1.38a9.9 9.9 0 0 0 4.76 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2Zm5.8 14.08c-.24.68-1.4 1.3-1.93 1.38-.5.08-1.11.11-1.79-.11-.41-.13-.94-.3-1.62-.6-2.85-1.23-4.7-4.1-4.84-4.29-.14-.19-1.16-1.54-1.16-2.94s.73-2.09 1-2.38c.24-.26.53-.32.71-.32h.5c.16 0 .38-.03.58.44.24.57.81 1.98.88 2.12.07.15.11.31.02.5-.09.19-.14.31-.28.47-.14.16-.29.36-.42.48-.14.13-.28.28-.12.55.16.27.71 1.17 1.53 1.9 1.05.93 1.94 1.22 2.21 1.36.27.13.43.11.59-.07.16-.19.68-.79.86-1.06.18-.27.36-.22.6-.13.24.09 1.55.73 1.82.86.27.14.44.2.51.32.07.13.07.72-.17 1.4Z"/>
        </svg>
    </a>
@endif
