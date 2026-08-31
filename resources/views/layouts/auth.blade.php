<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="login-ov">
            <div class="login-box">
                <a href="/" wire:navigate class="lw" id="wmark">
                    DO.ing <span>CLUB</span>
                </a>
                <p class="frase">Decisão Orientada. Tudo é gente.</p>

                <div class="login-card">
                    {{ $slot }}
                </div>

                @if ($showHint ?? false)
                    <p class="login-hint">
                        Acesso individual e intransferível, com sessão única por conta.<br>
                        Entrar em um novo aparelho <b>desconecta o anterior</b>.
                    </p>
                @endif
            </div>
        </div>
    </body>
</html>
