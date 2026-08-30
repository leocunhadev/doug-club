<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'mentor_id',
        'member_id',
        'scheduled_at',
        'cancelled_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function isCancelled(): bool
    {
        return filled($this->cancelled_at);
    }

    public function isUpcoming(): bool
    {
        return ! $this->isCancelled() && $this->scheduled_at->isFuture();
    }
}
