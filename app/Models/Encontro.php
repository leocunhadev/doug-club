<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Encontro extends Model
{
    use HasFactory;

    protected $fillable = [
        'tema',
        'quem',
        'scheduled_at',
        'access_url',
        'recording_lesson_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'recording_lesson_id');
    }

    public function isPast(): bool
    {
        return $this->scheduled_at->isPast();
    }

    /**
     * pt-BR month abbreviation, independent of the app locale (which is 'en').
     */
    protected function scheduledMonthLabel(): Attribute
    {
        return Attribute::get(fn () => [
            'jan', 'fev', 'mar', 'abr', 'mai', 'jun',
            'jul', 'ago', 'set', 'out', 'nov', 'dez',
        ][$this->scheduled_at->month - 1]);
    }
}
