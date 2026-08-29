@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-sand text-ink focus:border-brand focus:ring-brand rounded-md shadow-sm']) }}>
