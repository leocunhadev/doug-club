<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrameworkDownload extends Model
{
    protected $fillable = [
        'user_id',
        'framework_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(Framework::class);
    }
}
