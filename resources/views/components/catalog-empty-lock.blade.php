@props(['title', 'message'])

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
    <div class="text-4xl mb-4" aria-hidden="true">🔒</div>
    <h1 class="text-2xl font-bold font-display">{{ $title }}</h1>
    <p class="mt-3 text-stone">{{ $message }}</p>
</div>
