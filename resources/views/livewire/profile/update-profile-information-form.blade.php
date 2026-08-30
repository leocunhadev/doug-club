<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;

new class extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public ?UploadedFile $photo = null;
    public string $company = '';
    public string $bio = '';
    public string $teachTagsInput = '';
    public string $learnTagsInput = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->company = Auth::user()->company ?? '';
        $this->bio = Auth::user()->bio ?? '';
        $this->teachTagsInput = implode(', ', Auth::user()->teach_tags ?? []);
        $this->learnTagsInput = implode(', ', Auth::user()->learn_tags ?? []);
    }

    /**
     * Save the new photo as soon as it finishes uploading, no separate button needed.
     */
    public function updatedPhoto(): void
    {
        $this->updatePhoto();
    }

    /**
     * Upload and set a new profile photo, replacing any existing one.
     */
    public function updatePhoto(): void
    {
        try {
            $this->validate([
                'photo' => ['required', 'image', 'max:2048'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->photo = null;

            throw $e;
        }

        $user = Auth::user();
        $previousPath = $user->photo_path;

        $user->photo_path = $this->photo->store('avatars', 'public');
        $user->save();

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        $this->photo = null;

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Remove the current profile photo.
     */
    public function removePhoto(): void
    {
        $user = Auth::user();

        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
            $user->photo_path = null;
            $user->save();
        }

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Update the network-profile fields (company, bio, teach/learn tags).
     */
    public function updateNetworkProfile(): void
    {
        $validated = $this->validate([
            'company' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:500'],
            'teachTagsInput' => ['nullable', 'string', 'max:255'],
            'learnTagsInput' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();
        $user->company = $validated['company'] ?: null;
        $user->bio = $validated['bio'] ?: null;
        $user->teach_tags = $this->parseTags($validated['teachTagsInput']);
        $user->learn_tags = $this->parseTags($validated['learnTagsInput']);
        $user->save();

        $this->dispatch('network-profile-updated');
    }

    /**
     * Split a comma-separated tag string into a trimmed, non-empty array.
     */
    private function parseTags(?string $input): array
    {
        return collect(explode(',', $input ?? ''))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<div>
<section>
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-stone">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <div class="mt-6 flex items-center gap-4">
        @if ($photo)
            <img src="{{ $photo->temporaryUrl() }}" alt="" class="h-16 w-16 rounded-full object-cover">
        @elseif (auth()->user()->photo_url)
            <img src="{{ auth()->user()->photo_url }}" alt="" class="h-16 w-16 rounded-full object-cover">
        @else
            <div class="h-16 w-16 rounded-full bg-brand text-white flex items-center justify-center font-semibold">
                {{ auth()->user()->initials }}
            </div>
        @endif

        <div>
            <label class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold border border-sand text-ink hover:border-black cursor-pointer">
                Trocar foto
                <input type="file" wire:model="photo" accept="image/*" class="hidden">
            </label>

            @if (auth()->user()->photo_url)
                <button type="button" wire:click="removePhoto" class="ms-2 text-xs font-semibold text-stone hover:text-ink">
                    Remover foto
                </button>
            @endif

            <x-input-error class="mt-2" :messages="$errors->get('photo')" />
        </div>
    </div>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-ink">
                        {{ __('Your email address is unverified.') }}

                        <button wire:click.prevent="sendVerification" class="underline text-sm text-stone hover:text-ink rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
@if (auth()->user()->tier === 'club')
    <section class="mt-10 pt-10 border-t border-sand">
        <header>
            <h2 class="text-lg font-medium text-ink">
                Perfil na rede CLUB
            </h2>

            <p class="mt-1 text-sm text-stone">
                Apareça pros outros membros do CLUB em "Pessoas" — conte o que você faz, no que pode ajudar e o que quer aprender.
            </p>
        </header>

        <form wire:submit="updateNetworkProfile" class="mt-6 space-y-6">
            <div>
                <x-input-label for="company" value="Empresa" />
                <x-text-input wire:model="company" id="company" name="company" type="text" class="mt-1 block w-full" />
                <x-input-error class="mt-2" :messages="$errors->get('company')" />
            </div>

            <div>
                <x-input-label for="bio" value="Bio" />
                <textarea wire:model="bio" id="bio" name="bio" rows="3"
                    class="mt-1 block w-full border-sand text-ink focus:border-brand focus:ring-brand rounded-md shadow-sm"></textarea>
                <x-input-error class="mt-2" :messages="$errors->get('bio')" />
            </div>

            <div>
                <x-input-label for="teachTagsInput" value="Pode ensinar" />
                <x-text-input wire:model="teachTagsInput" id="teachTagsInput" name="teachTagsInput" type="text"
                    class="mt-1 block w-full" placeholder="Vendas, Copywriting, Gestão" />
                <x-input-error class="mt-2" :messages="$errors->get('teachTagsInput')" />
            </div>

            <div>
                <x-input-label for="learnTagsInput" value="Quer aprender" />
                <x-text-input wire:model="learnTagsInput" id="learnTagsInput" name="learnTagsInput" type="text"
                    class="mt-1 block w-full" placeholder="Vendas, Copywriting, Gestão" />
                <x-input-error class="mt-2" :messages="$errors->get('learnTagsInput')" />
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>Salvar</x-primary-button>

                <x-action-message class="me-3" on="network-profile-updated">
                    Salvo.
                </x-action-message>
            </div>
        </form>
    </section>
@endif
</div>
