<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncontroFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'encontro_id',
        'score',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function encontro(): BelongsTo
    {
        return $this->belongsTo(Encontro::class);
    }
}
