<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'is_admin', 'access_revoked_at', 'email_verified_at', 'tier', 'photo_path', 'company', 'bio', 'teach_tags', 'learn_tags'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'access_revoked_at' => 'datetime',
            'teach_tags' => 'array',
            'learn_tags' => 'array',
        ];
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    public function hasClubAccess(): bool
    {
        return in_array($this->tier, ['club', 'mentor'], true);
    }

    public function isMentor(): bool
    {
        return $this->tier === 'mentor';
    }

    public function isStart(): bool
    {
        return $this->tier === 'start';
    }

    /**
     * The tier to render the UI for. Admins can preview another persona via
     * the plan switcher — this never changes `tier` itself, and route gating
     * (EnsureTier) always checks the real `tier`, not this preview.
     */
    public function viewingTier(): string
    {
        if ($this->is_admin && session()->has('admin_persona_preview')) {
            return session('admin_persona_preview');
        }

        return $this->tier;
    }

    protected function initials(): Attribute
    {
        return Attribute::get(function () {
            $initials = collect(explode(' ', $this->name))
                ->filter()
                ->map(fn (string $part) => mb_substr($part, 0, 1))
                ->take(2)
                ->implode('');

            return mb_strtoupper($initials);
        });
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => filled($this->photo_path)
            ? Storage::disk('public')->url($this->photo_path)
            : null);
    }
}
