<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Framework extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'description',
        'pdf_url',
        'pdf_path',
        'lesson_id',
        'position',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function hasUploadedFile(): bool
    {
        return filled($this->pdf_path);
    }
}
